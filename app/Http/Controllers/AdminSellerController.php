<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveSellerRequest;
use App\Http\Requests\RestoreSellerListingsRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerArchiveEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminSellerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'all' => __('All'),
            'active' => __('Active'),
            'archived' => __('Archived'),
        ];
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && array_key_exists($requestedStatus, $filters)
            ? $requestedStatus
            : 'all';
        $requestedSearch = $request->query('q');
        $search = is_string($requestedSearch) ? trim(mb_substr($requestedSearch, 0, 100)) : '';

        $sellers = User::query()
            ->where('is_admin', false)
            ->withCount([
                'products as listings_count',
                'products as visible_listings_count' => fn (Builder $query): Builder => $query
                    ->whereNull('archived_at')
                    ->whereNull('seller_archived_at'),
                'products as hidden_listings_count' => fn (Builder $query): Builder => $query->whereNotNull('seller_archived_at'),
            ])
            ->when($status === 'active', fn (Builder $query): Builder => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn (Builder $query): Builder => $query->whereNotNull('archived_at'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.sellers.index', [
            'sellers' => $sellers,
            'filters' => $filters,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(User $seller): View
    {
        abort_if($seller->isAdmin(), 404);

        $seller->load([
            'sellerArchiveEvents' => fn ($query) => $query->with('actor')->latest()->orderByDesc('id'),
        ]);

        $products = $seller->products()
            ->with('category')
            ->withCount([
                'orderItems as sold_order_items_count' => fn (Builder $query): Builder => $query->whereHas(
                    'order',
                    fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED),
                ),
            ])
            ->latest()
            ->orderByDesc('id')
            ->get();

        $hiddenListings = $products->filter(fn (Product $product): bool => $product->isSellerArchived())->values();
        $restorableIds = Product::query()
            ->whereKey($hiddenListings->modelKeys())
            ->whereNull('archived_at')
            ->where('status', '!=', Product::STATUS_REJECTED)
            ->whereNull('reservation_token')
            ->whereDoesntHave('orderItems', fn (Builder $query): Builder => $query->whereHas(
                'order',
                fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED),
            ))
            ->pluck('id')
            ->all();

        return view('admin.sellers.show', [
            'seller' => $seller,
            'products' => $products,
            'restorableListings' => $hiddenListings->filter(fn (Product $product): bool => in_array($product->id, $restorableIds, true))->values(),
            'blockedListings' => $hiddenListings->filter(fn (Product $product): bool => ! in_array($product->id, $restorableIds, true))->values(),
        ]);
    }

    public function archive(ArchiveSellerRequest $request, User $seller): RedirectResponse
    {
        $actor = $request->user();

        if ($seller->is($actor)) {
            return back()->with('error', __('You cannot archive your own account.'));
        }

        if ($seller->isAdmin()) {
            if (User::activeAdmins()->count() <= 1) {
                return back()->with('error', __('The last active administrator cannot be archived.'));
            }

            return back()->with('error', __('Administrator accounts cannot be archived.'));
        }

        if ($seller->isArchived()) {
            return back()->with('error', __('This seller is already archived.'));
        }

        $event = DB::transaction(function () use ($actor, $seller, $request): SellerArchiveEvent {
            Gate::forUser($actor)->authorize('moderateProducts');

            $lockedSeller = User::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();

            abort_unless(! $lockedSeller->is($actor), 409, __('You cannot archive your own account.'));

            if ($lockedSeller->isAdmin()) {
                abort_unless(User::activeAdmins()->count() > 1, 409, __('The last active administrator cannot be archived.'));
                abort(409, __('Administrator accounts cannot be archived.'));
            }

            abort_unless(! $lockedSeller->isArchived(), 409, __('This seller is already archived.'));

            $lockedSeller->forceFill(['archived_at' => now()])->save();

            $listingsAffected = Product::query()
                ->where('seller_id', $lockedSeller->id)
                ->whereNull('archived_at')
                ->whereNull('seller_archived_at')
                ->update(['seller_archived_at' => now()]);

            return SellerArchiveEvent::create([
                'seller_id' => $lockedSeller->id,
                'actor_id' => $actor->id,
                'action' => SellerArchiveEvent::ACTION_ARCHIVE,
                'reason' => $request->string('reason')->toString() ?: null,
                'listings_affected' => $listingsAffected,
                'listings_restored' => 0,
                'metadata' => ['scope' => 'account'],
            ]);
        }, attempts: 3);

        return redirect()->route('admin.sellers.show', $seller)
            ->with('status', __('Seller archived. :count listings hidden.', ['count' => $event->listings_affected]))
            ->with('status_key', 'seller_archived');
    }

    public function restore(Request $request, User $seller): RedirectResponse
    {
        abort_if($seller->isAdmin(), 404);

        if (! $seller->isArchived()) {
            return back()->with('error', __('Only archived sellers can be restored.'));
        }

        DB::transaction(function () use ($request, $seller): void {
            Gate::forUser($request->user())->authorize('moderateProducts');

            $lockedSeller = User::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();

            abort_unless($lockedSeller->isArchived(), 409, __('Only archived sellers can be restored.'));

            $lockedSeller->forceFill(['archived_at' => null])->save();

            SellerArchiveEvent::create([
                'seller_id' => $lockedSeller->id,
                'actor_id' => $request->user()->id,
                'action' => SellerArchiveEvent::ACTION_RESTORE,
                'reason' => null,
                'listings_affected' => 0,
                'listings_restored' => 0,
                'metadata' => ['scope' => 'account'],
            ]);
        }, attempts: 3);

        return redirect()->route('admin.sellers.show', $seller)
            ->with('status', __('Seller restored. Listings remain hidden until selected for restoration.'))
            ->with('status_key', 'seller_restored');
    }

    public function restoreListings(RestoreSellerListingsRequest $request, User $seller): RedirectResponse
    {
        abort_if($seller->isAdmin(), 404);

        if ($seller->isArchived()) {
            return back()->with('error', __('Restore the seller account before restoring listings.'));
        }

        $productIds = collect($request->input('products'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $event = DB::transaction(function () use ($request, $seller, $productIds): SellerArchiveEvent {
            Gate::forUser($request->user())->authorize('moderateProducts');

            $lockedSeller = User::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();

            abort_unless(! $lockedSeller->isArchived(), 409, __('Restore the seller account before restoring listings.'));

            $products = Product::query()
                ->whereKey($productIds)
                ->where('seller_id', $lockedSeller->id)
                ->whereNotNull('seller_archived_at')
                ->lockForUpdate()
                ->get();

            abort_unless($products->count() === count($productIds), 409, __('Selected listings do not belong to this seller.'));

            foreach ($products as $product) {
                abort_unless($product->isEligibleForSellerRestore(), 409, __('One or more selected listings cannot be restored.'));
            }

            $listingsRestored = Product::query()
                ->whereKey($productIds)
                ->where('seller_id', $lockedSeller->id)
                ->whereNotNull('seller_archived_at')
                ->whereNull('archived_at')
                ->where('status', '!=', Product::STATUS_REJECTED)
                ->whereNull('reservation_token')
                ->whereDoesntHave('orderItems', fn (Builder $query): Builder => $query->whereHas(
                    'order',
                    fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED),
                ))
                ->update(['seller_archived_at' => null]);

            abort_unless($listingsRestored === count($productIds), 409, __('One or more selected listings changed while being restored.'));

            return SellerArchiveEvent::create([
                'seller_id' => $lockedSeller->id,
                'actor_id' => $request->user()->id,
                'action' => SellerArchiveEvent::ACTION_RESTORE,
                'reason' => $request->string('reason')->toString() ?: null,
                'listings_affected' => 0,
                'listings_restored' => $listingsRestored,
                'metadata' => ['scope' => 'listings', 'product_ids' => $productIds],
            ]);
        }, attempts: 3);

        return redirect()->route('admin.sellers.show', $seller)
            ->with('status', __(':count listings restored.', ['count' => $event->listings_restored]))
            ->with('status_key', 'seller_listings_restored');
    }
}
