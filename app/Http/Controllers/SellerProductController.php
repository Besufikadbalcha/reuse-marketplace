<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerProductRequest;
use App\Http\Requests\UpdateSellerProductRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\User;
use App\Notifications\ListingResubmittedForReviewNotification;
use App\Notifications\ListingSubmittedForReviewNotification;
use App\Notifications\MarketplaceNotification;
use App\Notifications\PromotionPausedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SellerProductController extends Controller
{
    public function index(Request $request): View
    {
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && array_key_exists($requestedStatus, $this->statuses())
            ? $requestedStatus
            : null;

        $products = $request->user()->products()
            ->with([
                'category',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount('orderItems')
            ->when($status, fn (Builder $query, string $selectedStatus) => $query->where('status', $selectedStatus))
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $promotionsByProductId = ProductPromotion::query()
            ->forSeller((int) $request->user()->id)
            ->whereIn('product_id', $products->pluck('id')->all())
            ->latest()
            ->orderByDesc('id')
            ->get(['id', 'seller_id', 'product_id', 'status', 'created_at'])
            ->groupBy('product_id')
            ->map(fn (Collection $promotions): ProductPromotion => $promotions->first(
                fn (ProductPromotion $promotion): bool => $promotion->isOpen()
            ) ?? $promotions->first())
            ->keyBy(fn (ProductPromotion $promotion): int => (int) $promotion->product_id);

        return view('seller.products.index', [
            'products' => $products,
            'statuses' => $this->statuses(),
            'status' => $status,
            'promotionsByProductId' => $promotionsByProductId,
        ]);
    }

    public function create(): View
    {
        return view('seller.products.create', [
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'conditions' => $this->conditions(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSellerProductRequest $request): RedirectResponse
    {
        $productData = [
            'seller_id' => $request->user()->id,
            'category_id' => $request->integer('category_id'),
            'location_id' => $request->integer('location_id'),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString(),
            'price_minor' => $this->convertPriceToMinor($request->input('price')),
            'condition' => $request->string('condition')->toString(),
            'slug' => $this->generateUniqueSlug($request->string('title')->toString()),
            'status' => Product::STATUS_PENDING,
        ];

        $storedPaths = [];

        try {
            $product = DB::transaction(function () use ($productData, $request, &$storedPaths): Product {
                $product = Product::create($productData);
                $this->storeUploadedImages($request, $product, $storedPaths);

                return $product;
            });
        } catch (Throwable $exception) {
            $this->deletePrivateFiles($storedPaths);

            throw $exception;
        }

        $this->notifyActiveAdminsOfListingSubmission($product, ListingSubmittedForReviewNotification::class);

        return redirect()->route('seller.products.index')
            ->with('status', __('Listing submitted for review.'))
            ->with('status_key', 'listing_submitted');
    }

    public function show(Request $request, Product $product): View
    {
        $this->authorizeOwnership($request, $product);

        $product->load([
            'category',
            'location',
            'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $promotion = $this->promotionForProduct($product);
        $promotionIsOpen = $promotion?->isOpen() ?? false;

        return view('seller.products.show', [
            'product' => $product,
            'promotion' => $promotion,
            'promotionIsOpen' => $promotionIsOpen,
            'canRequestPromotion' => $product->status === Product::STATUS_APPROVED
                && Product::query()->whereKey($product->id)->notArchived()->exists()
                && ! $promotionIsOpen,
        ]);
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeOwnership($request, $product);

        $product->load([
            'category',
            'location',
            'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return view('seller.products.edit', [
            'product' => $product,
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'conditions' => $this->conditions(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSellerProductRequest $request, Product $product): RedirectResponse
    {
        $product->load('images');

        $previousStatus = $product->status;
        $listingData = [
            'category_id' => $request->integer('category_id'),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString(),
            'price_minor' => $this->convertPriceToMinor($request->input('price')),
            'condition' => $request->string('condition')->toString(),
        ];

        if ($request->filled('location_id')) {
            $listingData['location_id'] = $request->integer('location_id');
        }

        $contentChanged = collect($listingData)->some(
            fn (mixed $value, string $field): bool => $product->{$field} != $value
        );

        $removedIds = array_map('intval', $request->input('remove_images', []));
        $orderIds = array_map('intval', $request->input('image_order', []));
        $newImages = $request->file('images', []);

        $remainingImages = $product->images->reject(
            fn (ProductImage $image): bool => in_array($image->id, $removedIds, true)
        );
        $remainingIds = $remainingImages->pluck('id')->map(fn (mixed $imageId): int => (int) $imageId)->all();
        $desiredIds = array_values(array_unique(array_merge($orderIds, $remainingIds)));
        $currentIds = $remainingImages->sortBy('sort_order')->sortBy('id')
            ->pluck('id')
            ->map(fn (mixed $imageId): int => (int) $imageId)
            ->values()
            ->all();

        $orderChanged = $desiredIds !== $currentIds;
        $imageChanged = $removedIds !== [] || $newImages !== [] || $orderChanged;

        if (! $contentChanged && ! $imageChanged) {
            return back()->with('status', __('No changes detected.'));
        }

        if (in_array($previousStatus, [Product::STATUS_APPROVED, Product::STATUS_REJECTED], true)) {
            $listingData['status'] = Product::STATUS_PENDING;
        }

        $listingData['rejection_reason'] = null;

        $stagedPaths = [];
        $removedPaths = [];
        $pausedPromotion = null;

        try {
            foreach ($newImages as $file) {
                $path = $file->store(ProductImage::PRIVATE_PATH_PREFIX.$request->user()->id, 'local');

                if ($path === false) {
                    throw new RuntimeException('Unable to store the uploaded image.');
                }

                $stagedPaths[] = $path;
            }

            DB::transaction(function () use (
                $listingData,
                $product,
                $previousStatus,
                $removedIds,
                $desiredIds,
                $orderChanged,
                $stagedPaths,
                &$removedPaths,
                &$pausedPromotion
            ): void {
                $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                abort_unless($lockedProduct->status === $previousStatus, 409);

                $lockedProduct->update($listingData);

                if ($removedIds !== []) {
                    $removedPaths = $lockedProduct->images()->whereIn('id', $removedIds)->pluck('path')->all();
                    $lockedProduct->images()->whereIn('id', $removedIds)->delete();
                }

                if ($orderChanged) {
                    foreach ($desiredIds as $position => $imageId) {
                        $lockedProduct->images()->where('id', $imageId)->update(['sort_order' => $position]);
                    }
                }

                foreach ($stagedPaths as $offset => $path) {
                    $lockedProduct->images()->create([
                        'path' => $path,
                        'sort_order' => count($desiredIds) + $offset,
                    ]);
                }

                if (in_array($previousStatus, [Product::STATUS_APPROVED, Product::STATUS_REJECTED], true)) {
                    $promotion = ProductPromotion::query()
                        ->forProduct($lockedProduct->id)
                        ->active()
                        ->lockForUpdate()
                        ->first();

                    if ($promotion !== null) {
                        $promotion->pauseAt(now());
                        $promotion->save();

                        $pausedPromotion = $promotion;
                    }
                }
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->deletePrivateFiles($stagedPaths);

            throw $exception;
        }

        foreach ($removedPaths as $path) {
            $this->deleteImageFile($path);
        }

        if ($pausedPromotion !== null) {
            $pausedPromotion->seller?->notify(new PromotionPausedNotification($pausedPromotion));
        }

        $resubmitted = in_array($previousStatus, [Product::STATUS_APPROVED, Product::STATUS_REJECTED], true);

        if ($resubmitted && $product->fresh()?->status === Product::STATUS_PENDING) {
            $this->notifyActiveAdminsOfListingSubmission($product, ListingResubmittedForReviewNotification::class);
        }

        return redirect()->route('seller.products.show', $product)
            ->with('status', $resubmitted ? __('Listing updated and submitted for review.') : __('Listing updated.'))
            ->with('status_key', $resubmitted ? 'listing_updated_submitted' : 'listing_updated');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        if ($product->isArchived()) {
            return back()->with('error', __('This listing is already archived.'));
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        if ($updated !== 1) {
            return back()->with('error', __('This listing is already archived.'));
        }

        return redirect()->route('seller.products.index')
            ->with('status', __('Listing archived.'))
            ->with('status_key', 'listing_archived');
    }

    private function authorizeOwnership(Request $request, Product $product): void
    {
        abort_unless($product->seller_id === $request->user()->id, 404);
    }

    private function promotionForProduct(Product $product): ?ProductPromotion
    {
        $promotions = ProductPromotion::query()
            ->forProduct((int) $product->id)
            ->latest()
            ->orderByDesc('id')
            ->get();

        return $promotions->first(fn (ProductPromotion $promotion): bool => $promotion->isOpen())
            ?? $promotions->first();
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deletePrivateFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    private function deleteImageFile(string $path): void
    {
        if ($path === '') {
            return;
        }

        $disk = str_starts_with($path, ProductImage::PRIVATE_PATH_PREFIX) ? 'local' : 'public';

        Storage::disk($disk)->delete($path);
    }

    private function convertPriceToMinor(mixed $price): int
    {
        return (int) round(((float) $price) * 100);
    }

    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'product';
        $slug = $baseSlug;
        $suffix = 1;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    private function storeUploadedImages(Request $request, Product $product, array &$storedPaths): void
    {
        foreach ($request->file('images', []) as $index => $file) {
            $path = $file->store(ProductImage::PRIVATE_PATH_PREFIX.$request->user()->id, 'local');

            if ($path === false) {
                throw new RuntimeException('Unable to store the uploaded image.');
            }

            $storedPaths[] = $path;
            $product->images()->create([
                'path' => $path,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function conditions(): array
    {
        return [
            Product::CONDITION_NEW => __('New'),
            Product::CONDITION_LIKE_NEW => __('Like New'),
            Product::CONDITION_GOOD => __('Good'),
            Product::CONDITION_FAIR => __('Fair'),
        ];
    }

    /**
     * @param  class-string<ListingSubmittedForReviewNotification|ListingResubmittedForReviewNotification>  $notificationClass
     */
    private function notifyActiveAdminsOfListingSubmission(Product $product, string $notificationClass): void
    {
        if (! MarketplaceNotification::databaseTableExists()) {
            return;
        }

        $admins = User::query()
            ->activeAdmins()
            ->whereKeyNot($product->seller_id)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $notificationTypes = [
            ListingSubmittedForReviewNotification::class,
            ListingResubmittedForReviewNotification::class,
        ];

        $recipients = $admins->filter(fn (User $admin): bool => ! $admin->unreadNotifications()
            ->whereIn('type', $notificationTypes)
            ->where('data->params->product_id', $product->id)
            ->exists());

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new $notificationClass($product));
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            Product::STATUS_PENDING => __('Pending'),
            Product::STATUS_APPROVED => __('Approved'),
            Product::STATUS_REJECTED => __('Rejected'),
        ];
    }
}
