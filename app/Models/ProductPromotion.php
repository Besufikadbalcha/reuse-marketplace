<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ProductPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'seller_id',
    'product_id',
    'package_id',
    'funding_type',
    'status',
    'open_product_id',
    'package_code_snapshot',
    'price_minor_snapshot',
    'duration_days_snapshot',
    'priority_snapshot',
    'starts_at',
    'ends_at',
    'paused_at',
    'remaining_seconds',
    'stopped_at',
    'stopped_by_order_id',
])]
class ProductPromotion extends Model
{
    /** @use HasFactory<ProductPromotionFactory> */
    use HasFactory;

    public const FUNDING_PAYMENT = 'payment';

    public const FUNDING_CREDIT = 'credit';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAYMENT_REJECTED = 'payment_rejected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_STOPPED_SOLD = 'stopped_sold';

    /** @var array<int, string> */
    public const FUNDING_TYPES = [
        self::FUNDING_PAYMENT,
        self::FUNDING_CREDIT,
    ];

    /** @var array<int, string> */
    public const OPEN_STATUSES = [
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_PAYMENT_REJECTED,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
    ];

    /** @var array<int, string> */
    public const CLOSED_STATUSES = [
        self::STATUS_EXPIRED,
        self::STATUS_STOPPED_SOLD,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'funding_type' => self::FUNDING_PAYMENT,
        'status' => self::STATUS_PAYMENT_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'funding_type' => 'string',
            'status' => 'string',
            'price_minor_snapshot' => 'integer',
            'duration_days_snapshot' => 'integer',
            'priority_snapshot' => 'integer',
            'remaining_seconds' => 'integer',
            'stopped_by_order_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PromotionPackage::class, 'package_id');
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PromotionPaymentSubmission::class, 'promotion_id');
    }

    public function creditUsages(): HasMany
    {
        return $this->hasMany(PromotionCreditUsage::class);
    }

    public function promotionCredit(): HasOne
    {
        return $this->hasOne(PromotionCredit::class, 'source_promotion_id');
    }

    public function stoppedByOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'stopped_by_order_id');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    #[Scope]
    protected function closed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    #[Scope]
    protected function awaitingPayment(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAYMENT_PENDING);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    #[Scope]
    protected function paused(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    #[Scope]
    protected function expirable(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->active()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $at);
    }

    #[Scope]
    protected function displayable(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->active()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at);
    }

    #[Scope]
    protected function stoppedByOrderId(Builder $query, int $orderId): Builder
    {
        return $query->where('stopped_by_order_id', $orderId);
    }

    #[Scope]
    protected function forSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    #[Scope]
    protected function forProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    #[Scope]
    protected function currentlyActive(Builder $query): Builder
    {
        return $query->displayable();
    }

    public function remainingSecondsAt(Carbon $at): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return max(0, $this->ends_at->getTimestamp() - $at->getTimestamp());
    }

    public function isDisplayableAt(Carbon $at): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lessThanOrEqualTo($at)
            && $this->ends_at->greaterThan($at);
    }

    public function pauseAt(Carbon $at): void
    {
        $this->remaining_seconds = $this->remainingSecondsAt($at);
        $this->paused_at = $at;
        $this->status = self::STATUS_PAUSED;
    }

    public function resumeAt(Carbon $at): void
    {
        $this->ends_at = $at->copy()->addSeconds((int) ($this->remaining_seconds ?? 0));
        $this->status = self::STATUS_ACTIVE;
        $this->open_product_id = $this->product_id;
        $this->paused_at = null;
        $this->stopped_at = null;
        $this->remaining_seconds = null;
    }

    public function expireAt(Carbon $at): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->open_product_id = null;
        $this->paused_at = null;
        $this->remaining_seconds = null;
        $this->stopped_at = null;
    }

    public function stopForOrderAt(int $orderId, Carbon $at): void
    {
        $this->remaining_seconds = $this->remainingSecondsAt($at);
        $this->stopped_at = $at;
        $this->stopped_by_order_id = $orderId;
        $this->status = self::STATUS_STOPPED_SOLD;
        $this->open_product_id = null;
        $this->paused_at = null;
    }

    public function restoreAfterOrderAt(Carbon $at): void
    {
        $this->ends_at = $at->copy()->addSeconds((int) ($this->remaining_seconds ?? 0));
        $this->status = self::STATUS_ACTIVE;
        $this->open_product_id = $this->product_id;
        $this->paused_at = null;
        $this->stopped_at = null;
        $this->remaining_seconds = null;
        $this->stopped_by_order_id = null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAYMENT_PENDING => __('Payment pending'),
            self::STATUS_PAYMENT_REJECTED => __('Payment rejected'),
            self::STATUS_ACTIVE => __('Active'),
            self::STATUS_PAUSED => __('Paused'),
            self::STATUS_EXPIRED => __('Expired'),
            self::STATUS_STOPPED_SOLD => __('Stopped after sale'),
            default => __(ucfirst(str_replace('_', ' ', (string) $this->status))),
        };
    }
}
