<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'product_id',
    'product_title',
    'price_minor',
    'quantity',
    'seller_status',
    'fulfillment_status',
    'seller_cash_received_at',
    'seller_cash_received_by',
    'buyer_confirmed_received_at',
    'buyer_disputed_at',
    'buyer_dispute_reason',
    'dispute_resolved_at',
    'dispute_resolved_by',
    'dispute_resolution_note',
])]
class OrderItem extends Model
{
    public const SELLER_STATUS_PENDING = 'pending';

    public const SELLER_STATUS_CONFIRMED = 'confirmed';

    public const SELLER_STATUS_COMPLETED = 'completed';

    public const FULFILLMENT_PENDING = 'pending_confirmation';

    public const FULFILLMENT_CONFIRMED = 'confirmed';

    public const FULFILLMENT_READY_FOR_PICKUP = 'ready_for_pickup';

    public const FULFILLMENT_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const FULFILLMENT_AWAITING_BUYER_CONFIRMATION = 'awaiting_buyer_confirmation';

    public const FULFILLMENT_COMPLETED = 'completed';

    public const FULFILLMENT_DISPUTED = 'disputed';

    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'seller_status' => self::SELLER_STATUS_PENDING,
        'fulfillment_status' => self::FULFILLMENT_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'quantity' => 'integer',
            'seller_status' => 'string',
            'fulfillment_status' => 'string',
            'seller_cash_received_at' => 'datetime',
            'buyer_confirmed_received_at' => 'datetime',
            'buyer_disputed_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $fulfillment = $item->fulfillment_status ?: self::FULFILLMENT_PENDING;
            $seller = $item->seller_status ?: self::SELLER_STATUS_PENDING;
            $fulfillmentDirty = $item->isDirty('fulfillment_status');
            $sellerDirty = $item->isDirty('seller_status');

            if ($sellerDirty && ! $fulfillmentDirty) {
                $fulfillment = self::mapLegacySellerStatus($seller);
            } elseif (! $fulfillmentDirty && ! $sellerDirty) {
                $fulfillment = $fulfillment ?: self::mapLegacySellerStatus($seller);
            }

            if ($fulfillmentDirty && ! $sellerDirty) {
                $seller = self::mapFulfillmentToLegacySellerStatus($fulfillment);
            } else {
                $seller = self::mapFulfillmentToLegacySellerStatus($fulfillment);
            }

            $item->fulfillment_status = $fulfillment;
            $item->seller_status = $seller;
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderItemStatusHistory::class)->latest();
    }

    public function sellerCashReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_cash_received_by');
    }

    public function disputeResolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_resolved_by');
    }

    #[Scope]
    protected function forSeller(Builder $query, User $seller): Builder
    {
        return $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery->where('seller_id', $seller->id));
    }

    public static function mapLegacySellerStatus(?string $status): string
    {
        return match ($status) {
            self::SELLER_STATUS_CONFIRMED => self::FULFILLMENT_CONFIRMED,
            self::SELLER_STATUS_COMPLETED => self::FULFILLMENT_COMPLETED,
            default => self::FULFILLMENT_PENDING,
        };
    }

    public static function mapFulfillmentToLegacySellerStatus(?string $status): string
    {
        return match ($status) {
            self::FULFILLMENT_COMPLETED => self::SELLER_STATUS_COMPLETED,
            self::FULFILLMENT_PENDING => self::SELLER_STATUS_PENDING,
            default => self::SELLER_STATUS_CONFIRMED,
        };
    }

    public function fulfillmentStatus(): string
    {
        return $this->fulfillment_status ?: self::mapLegacySellerStatus($this->seller_status);
    }

    public function isPickupOrder(): bool
    {
        return $this->relationLoaded('order')
            ? $this->order?->isPickup() === true
            : (string) $this->order()->value('fulfillment_method') === Order::FULFILLMENT_PICKUP;
    }

    public function isDeliveryOrder(): bool
    {
        return $this->relationLoaded('order')
            ? $this->order?->isCashOnDelivery() === true
            : (string) $this->order()->value('fulfillment_method') === Order::FULFILLMENT_CASH_ON_DELIVERY;
    }

    public function canBeConfirmedBySeller(): bool
    {
        return $this->fulfillmentStatus() === self::FULFILLMENT_PENDING;
    }

    public function canBeMarkedReadyForPickup(): bool
    {
        return $this->isPickupOrder() && $this->fulfillmentStatus() === self::FULFILLMENT_CONFIRMED;
    }

    public function canBeMarkedOutForDelivery(): bool
    {
        return $this->isDeliveryOrder() && $this->fulfillmentStatus() === self::FULFILLMENT_CONFIRMED;
    }

    public function canSellerRecordCashReceived(): bool
    {
        return in_array($this->fulfillmentStatus(), [self::FULFILLMENT_READY_FOR_PICKUP, self::FULFILLMENT_OUT_FOR_DELIVERY], true)
            && $this->seller_cash_received_at === null;
    }

    public function canBuyerConfirmReceived(): bool
    {
        return $this->fulfillmentStatus() === self::FULFILLMENT_AWAITING_BUYER_CONFIRMATION
            && $this->seller_cash_received_at !== null
            && $this->buyer_confirmed_received_at === null
            && $this->buyer_disputed_at === null;
    }

    public function canBuyerReportProblem(): bool
    {
        return in_array($this->fulfillmentStatus(), [
            self::FULFILLMENT_CONFIRMED,
            self::FULFILLMENT_READY_FOR_PICKUP,
            self::FULFILLMENT_OUT_FOR_DELIVERY,
            self::FULFILLMENT_AWAITING_BUYER_CONFIRMATION,
        ], true) && $this->buyer_confirmed_received_at === null && $this->buyer_disputed_at === null;
    }

    public function canBeCompletedBySeller(): bool
    {
        return $this->fulfillmentStatus() === self::FULFILLMENT_AWAITING_BUYER_CONFIRMATION
            && $this->seller_cash_received_at !== null
            && $this->buyer_confirmed_received_at !== null
            && $this->buyer_disputed_at === null;
    }

    public function isDisputed(): bool
    {
        return $this->fulfillmentStatus() === self::FULFILLMENT_DISPUTED || $this->buyer_disputed_at !== null;
    }

    public function sellerStatusLabel(): string
    {
        return $this->fulfillmentStatusLabel();
    }

    public function fulfillmentStatusLabel(): string
    {
        return self::fulfillmentStatusLabelFor($this->fulfillmentStatus());
    }

    public static function fulfillmentStatusLabelFor(?string $status): string
    {
        return match ($status) {
            self::FULFILLMENT_PENDING, 'pending' => __('Pending confirmation'),
            self::FULFILLMENT_CONFIRMED, 'confirmed' => __('Confirmed'),
            self::FULFILLMENT_READY_FOR_PICKUP => __('Ready for pickup'),
            self::FULFILLMENT_OUT_FOR_DELIVERY => __('Out for delivery'),
            self::FULFILLMENT_AWAITING_BUYER_CONFIRMATION => __('Cash received - awaiting buyer confirmation'),
            self::FULFILLMENT_COMPLETED, 'completed' => __('Completed'),
            self::FULFILLMENT_DISPUTED => __('Disputed'),
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    public function formattedLineTotal(): string
    {
        return Product::formatMoney($this->price_minor * $this->quantity);
    }
}
