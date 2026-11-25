<?php

namespace App\Models;

use Database\Factories\PromotionCreditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'seller_id',
    'source_promotion_id',
    'source_order_item_id',
    'seconds_granted',
    'seconds_remaining',
])]
class PromotionCredit extends Model
{
    /** @use HasFactory<PromotionCreditFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seconds_granted' => 'integer',
            'seconds_remaining' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sourcePromotion(): BelongsTo
    {
        return $this->belongsTo(ProductPromotion::class, 'source_promotion_id');
    }

    public function sourceOrderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'source_order_item_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionCreditUsage::class);
    }

    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('seconds_remaining', '>', 0);
    }

    #[Scope]
    protected function exhausted(Builder $query): Builder
    {
        return $query->where('seconds_remaining', 0);
    }

    #[Scope]
    protected function forSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function hasAvailableSeconds(): bool
    {
        return $this->seconds_remaining > 0;
    }
}
