<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'product_title', 'price_minor', 'quantity', 'seller_status'])]
class OrderItem extends Model
{
    public const SELLER_STATUS_PENDING = 'pending';

    public const SELLER_STATUS_CONFIRMED = 'confirmed';

    public const SELLER_STATUS_COMPLETED = 'completed';

    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'seller_status' => self::SELLER_STATUS_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'quantity' => 'integer',
            'seller_status' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    #[Scope]
    protected function forSeller(Builder $query, User $seller): Builder
    {
        return $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery->where('seller_id', $seller->id));
    }

    public function canBeConfirmedBySeller(): bool
    {
        return $this->seller_status === self::SELLER_STATUS_PENDING;
    }

    public function canBeCompletedBySeller(): bool
    {
        return $this->seller_status === self::SELLER_STATUS_CONFIRMED;
    }

    public function sellerStatusLabel(): string
    {
        return match ($this->seller_status) {
            self::SELLER_STATUS_PENDING => __('Pending'),
            self::SELLER_STATUS_CONFIRMED => __('Confirmed'),
            self::SELLER_STATUS_COMPLETED => __('Completed'),
            default => ucfirst((string) $this->seller_status),
        };
    }

    public function formattedLineTotal(): string
    {
        return Product::formatMoney($this->price_minor * $this->quantity);
    }
}
