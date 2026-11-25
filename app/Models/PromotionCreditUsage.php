<?php

namespace App\Models;

use Database\Factories\PromotionCreditUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['credit_id', 'promotion_id', 'seconds_used'])]
class PromotionCreditUsage extends Model
{
    /** @use HasFactory<PromotionCreditUsageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seconds_used' => 'integer',
        ];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(PromotionCredit::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(ProductPromotion::class);
    }

    #[Scope]
    protected function forCredit(Builder $query, int $creditId): Builder
    {
        return $query->where('credit_id', $creditId);
    }

    #[Scope]
    protected function forPromotion(Builder $query, int $promotionId): Builder
    {
        return $query->where('promotion_id', $promotionId);
    }
}
