<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ProductFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['seller_id', 'category_id', 'location_id', 'title', 'slug', 'description', 'price_minor', 'condition', 'status', 'rejection_reason'])]
class Product extends Model
{
    public const CONDITION_NEW = 'new';

    public const CONDITION_LIKE_NEW = 'like_new';

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @use HasFactory<ProductFactory> */
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
            'location_id' => 'integer',
            'price_minor' => 'integer',
            'condition' => 'string',
            'status' => 'string',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isOwnedBy(?Authenticatable $user): bool
    {
        return $user !== null && (int) $this->seller_id === (int) $user->getAuthIdentifier();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    #[Scope]
    protected function approved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    #[Scope]
    protected function rejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->approved()
            ->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->active());
    }

    #[Scope]
    protected function search(Builder $query, string $search): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $query) use ($pattern, $grammar): void {
            $query->whereRaw(
                'lower('.$grammar->wrap('title').') like lower(?) escape \'!\'',
                [$pattern],
            )
                ->orWhereRaw(
                    'lower('.$grammar->wrap('description').') like lower(?) escape \'!\'',
                    [$pattern],
                )
                ->orWhereHas('category', function (Builder $categoryQuery) use ($pattern, $grammar): void {
                    $categoryQuery->whereRaw(
                        'lower('.$grammar->wrap('name').') like lower(?) escape \'!\'',
                        [$pattern],
                    );
                });
        });
    }

    #[Scope]
    protected function inLocation(Builder $query, string $location): Builder
    {
        return $query->whereHas('location', fn (Builder $locationQuery): Builder => $locationQuery->where('name', $location));
    }

    #[Scope]
    protected function availableForPurchase(Builder $query): Builder
    {
        return $query->publiclyVisible()
            ->whereNull('reservation_token')
            ->whereDoesntHave('orderItems', function (Builder $itemQuery): void {
                $itemQuery->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED));
            });
    }

    #[Scope]
    protected function unavailable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('reservation_token')
                ->orWhereHas('orderItems', function (Builder $itemQuery): void {
                    $itemQuery->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED));
                });
        });
    }

    public static function formatMoney(int $amountMinor): string
    {
        $format = config('app.locale_formats.'.app()->getLocale(), config('app.locale_formats.en'));

        return (string) config('marketplace.currency').' '.number_format(
            $amountMinor / 100,
            2,
            $format['decimal_separator'],
            $format['thousands_separator'],
        );
    }

    public static function formatDate(?Carbon $date): string
    {
        if ($date === null) {
            return __('previously');
        }

        $months = config('app.locale_formats.'.app()->getLocale().'.months', config('app.locale_formats.en.months'));

        return $date->day.' '.$months[$date->month - 1].' '.$date->year;
    }

    public function formattedPrice(): string
    {
        return self::formatMoney($this->price_minor);
    }

    public function conditionLabel(): string
    {
        return match ($this->condition) {
            self::CONDITION_NEW => __('New'),
            self::CONDITION_LIKE_NEW => __('Like New'),
            self::CONDITION_GOOD => __('Good'),
            self::CONDITION_FAIR => __('Fair'),
            default => ucfirst(str_replace('_', ' ', (string) $this->condition)),
        };
    }
}
