<?php

namespace App\Notifications;

use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;

final class PromotionPaymentRejectedNotification extends MarketplaceNotification
{
    public function __construct(ProductPromotion $promotion, PromotionPaymentSubmission $submission)
    {
        parent::__construct(
            type: 'promotion_payment_rejected',
            translationKey: 'Promotion payment rejected for :title.',
            params: [
                'title' => $promotion->product?->title ?? __('Promotion request'),
                'reason' => $submission->rejection_reason,
                'attempt' => $submission->attempt,
            ],
            url: route('seller.promotions.show', $promotion),
        );
    }
}
