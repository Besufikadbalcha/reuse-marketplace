<?php

namespace App\Models;

use Database\Factories\PromotionPaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_id',
    'attempt',
    'method',
    'transaction_reference',
    'transaction_details',
    'proof_path',
    'proof_checksum',
    'status',
    'pending_promotion_id',
    'approved_promotion_id',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
    'submitted_at',
])]
class PromotionPaymentSubmission extends Model
{
    /** @use HasFactory<PromotionPaymentSubmissionFactory> */
    use HasFactory;

    public const METHOD_TELEBIRR = 'telebirr';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    /** @var array<int, string> */
    public const METHODS = [
        self::METHOD_TELEBIRR,
        self::METHOD_BANK_TRANSFER,
    ];

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SUPERSEDED,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'attempt' => 1,
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
            'attempt' => 'integer',
            'method' => 'string',
            'status' => 'string',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(ProductPromotion::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    #[Scope]
    protected function approved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    #[Scope]
    protected function rejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    #[Scope]
    protected function superseded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUPERSEDED);
    }

    #[Scope]
    protected function forPromotion(Builder $query, int $promotionId): Builder
    {
        return $query->where('promotion_id', $promotionId);
    }

    #[Scope]
    protected function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('attempt')->orderByDesc('submitted_at');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }
}
