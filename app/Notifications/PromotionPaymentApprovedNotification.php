<?php

namespace App\Notifications;

use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;

final class PromotionPaymentApprovedNotification extends MarketplaceNotification
{
    public function __construct(ProductPromotion $promotion, PromotionPaymentSubmission $submission)
    {
        parent::__construct(
            type: 'promotion_payment_approved',
            translationKey: 'Promotion payment approved for :title.',
            params: [
                'title' => $promotion->product?->title ?? __('Promotion request'),
                'attempt' => $submission->attempt,
            ],
            url: route('seller.promotions.show', $promotion),
        );
    }
}
