<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectProductRequest;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use App\Notifications\PromotionResumedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->pending()
            ->with([
                'seller',
                'category',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        return view('admin.products.index', ['products' => $products]);
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
        if ($product->status !== Product::STATUS_PENDING) {
            return back()->with('error', __('Only pending listings can be approved.'));
        }

        $resumedPromotion = null;

        $approvedProduct = DB::transaction(function () use ($product, &$resumedPromotion): Product {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            abort_unless($lockedProduct->status === Product::STATUS_PENDING, 409);

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
        if ($product->status !== Product::STATUS_PENDING) {
            return back()->with('error', __('Only pending listings can be rejected.'));
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->where('status', Product::STATUS_PENDING)
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
}
