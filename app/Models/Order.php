<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['buyer_id', 'order_number', 'status', 'total_amount_minor', 'fulfillment_method', 'contact_name', 'contact_phone', 'delivery_address', 'idempotency_key'])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_CASH_ON_DELIVERY = 'cash_on_delivery';

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount_minor' => 'integer',
            'status' => 'string',
            'fulfillment_method' => 'string',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    #[Scope]
    protected function forSeller(Builder $query, User $seller): Builder
    {
        return $query->whereHas('items', fn (Builder $itemQuery): Builder => $itemQuery->forSeller($seller));
    }

    #[Scope]
    protected function withSellerItems(Builder $query, User $seller): Builder
    {
        return $query->with([
            'items' => fn (HasMany $itemQuery) => $itemQuery->forSeller($seller),
            'items.product',
        ]);
    }

    public function formattedCreatedAt(): string
    {
        return Product::formatDate($this->created_at);
    }

    public function formattedTotal(): string
    {
        return Product::formatMoney($this->total_amount_minor);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_CONFIRMED => __('Confirmed'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
            default => ucfirst((string) $this->status),
        };
    }

    public function fulfillmentLabel(): string
    {
        return match ($this->fulfillment_method) {
            self::FULFILLMENT_PICKUP => __('Pickup'),
            self::FULFILLMENT_CASH_ON_DELIVERY => __('Cash on delivery'),
            default => ucfirst(str_replace('_', ' ', (string) $this->fulfillment_method)),
        };
    }
}
