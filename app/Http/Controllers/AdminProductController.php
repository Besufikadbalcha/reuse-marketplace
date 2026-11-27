<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectProductRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use App\Notifications\PromotionResumedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters();
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && array_key_exists($requestedStatus, $filters)
            ? $requestedStatus
            : 'all';
        $requestedSearch = $request->query('q');
        $search = is_string($requestedSearch) ? trim(mb_substr($requestedSearch, 0, 100)) : '';

        $products = Product::query()
            ->with([
                'seller',
                'category',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount([
                'orderItems as sold_order_items_count' => fn ($query) => $query->whereHas(
                    'order',
                    fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED),
                ),
            ])
            ->when(
                $status !== 'all' && $status !== 'sold',
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when(
                $status === 'sold',
                fn (Builder $query): Builder => $query->whereHas(
                    'orderItems',
                    fn (Builder $itemQuery): Builder => $itemQuery->whereHas(
                        'order',
                        fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED),
                    ),
                ),
            )
            ->when($search !== '', fn (Builder $query): Builder => $this->applySearch($query, $search))
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(Product $product): View
    {
        $product->load([
            'seller',
            'category',
            'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return view('admin.products.show', ['product' => $product]);
    }

    public function approve(Product $product): RedirectResponse
    {
        if ($product->isArchived()) {
            return back()->with('error', __('Restore this listing before reviewing it.'));
        }

        if ($product->status !== Product::STATUS_PENDING) {
            return back()->with('error', __('Only pending listings can be approved.'));
        }

        $resumedPromotion = null;

        $approvedProduct = DB::transaction(function () use ($product, &$resumedPromotion): Product {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            abort_unless($lockedProduct->status === Product::STATUS_PENDING, 409);
            abort_unless(! $lockedProduct->isArchived(), 409);

            $lockedProduct->load([
                'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
            ]);

            foreach ($lockedProduct->images as $image) {
                abort_unless($image->isServableForSeller((int) $lockedProduct->seller_id), 409);
            }

            $lockedProduct->update([
                'status' => Product::STATUS_APPROVED,
                'rejection_reason' => null,
            ]);

            $promotion = ProductPromotion::query()
                ->forProduct($lockedProduct->id)
                ->paused()
                ->lockForUpdate()
                ->first();

            if ($promotion !== null) {
                $promotion->resumeAt(now());
                $promotion->save();

                $resumedPromotion = $promotion;
            }

            return $lockedProduct;
        }, attempts: 3);

        $approvedProduct->seller->notify(new ListingApprovedNotification($approvedProduct));

        if ($resumedPromotion !== null) {
            $approvedProduct->seller->notify(new PromotionResumedNotification($resumedPromotion));
        }

        return redirect()->route('admin.products.index')
            ->with('status', __('Listing :title approved and published.', ['title' => $approvedProduct->title]))
            ->with('status_key', 'listing_approved');
    }

    public function reject(RejectProductRequest $request, Product $product): RedirectResponse
    {
        if ($product->isArchived()) {
            return back()->with('error', __('Restore this listing before reviewing it.'));
        }

        if ($product->status !== Product::STATUS_PENDING) {
            return back()->with('error', __('Only pending listings can be rejected.'));
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->where('status', Product::STATUS_PENDING)
            ->whereNull('archived_at')
            ->update([
                'status' => Product::STATUS_REJECTED,
                'rejection_reason' => $request->string('rejection_reason')->toString(),
            ]);

        if ($updated !== 1) {
            return back()->with('error', __('Only pending listings can be rejected.'));
        }

        $product->refresh();

        $product->seller->notify(new ListingRejectedNotification($product));

        return redirect()->route('admin.products.index')
            ->with('status', __('Listing :title rejected.', ['title' => $product->title]))
            ->with('status_key', 'listing_rejected');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->isArchived()) {
            return back()->with('error', __('This listing is already archived.'));
        }

        if (! in_array($product->status, [Product::STATUS_PENDING, Product::STATUS_APPROVED], true)) {
            return back()->with('error', __('Only pending or approved listings can be deleted.'));
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->whereIn('status', [Product::STATUS_PENDING, Product::STATUS_APPROVED])
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        if ($updated !== 1) {
            return back()->with('error', __('Only pending or approved listings can be deleted.'));
        }

        return redirect()->route('admin.products.index')
            ->with('status', __('Listing :title deleted.', ['title' => $product->title]))
            ->with('status_key', 'listing_archived');
    }

    public function restore(Product $product): RedirectResponse
    {
        if (! $product->isArchived()) {
            return back()->with('error', __('Only archived listings can be restored.'));
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null]);

        if ($updated !== 1) {
            return back()->with('error', __('Only archived listings can be restored.'));
        }

        return redirect()->route('admin.products.index')
            ->with('status', __('Listing :title restored.', ['title' => $product->title]))
            ->with('status_key', 'listing_restored');
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return [
            'all' => __('All'),
            Product::STATUS_PENDING => __('Pending'),
            Product::STATUS_APPROVED => __('Approved'),
            Product::STATUS_REJECTED => __('Rejected'),
            'sold' => __('Sold'),
        ];
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $terms = Product::searchTermList($search);

        if ($terms === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($terms): void {
            foreach ($terms as $term) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw('lower(title) like lower(?) escape \'!\'', [$pattern])
                        ->orWhereHas('seller', fn (Builder $sellerQuery): Builder => $sellerQuery->whereRaw(
                            'lower(name) like lower(?) escape \'!\'',
                            [$pattern],
                        ))
                        ->orWhereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->whereRaw(
                            'lower(name) like lower(?) escape \'!\'',
                            [$pattern],
                        ));
                });
            }
        });
    }
}
