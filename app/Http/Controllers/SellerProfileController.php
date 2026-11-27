<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class SellerProfileController extends Controller
{
    public function show(User $seller): View
    {
        abort_if($seller->isAdmin() || $seller->isArchived(), 404);

        $listings = $seller->products()
            ->availableForPurchase()
            ->with([
                'category',
                'seller',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order'),
            ])
            ->latest()
            ->orderByDesc('id')
            ->get();

        return view('sellers.show', [
            'seller' => $seller,
            'listings' => $listings,
            'completedTradesCount' => $seller->completedTradesCount(),
            'activeApprovedListingsCount' => $seller->activeApprovedListingsCount(),
            'publicCityRegion' => $seller->publicCityRegion(),
            'profileImageUrl' => $seller->profileImageUrl(),
        ]);
    }
}
