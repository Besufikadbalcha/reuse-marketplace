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

#[Fillable(['seller_id', 'category_id', 'location_id', 'title', 'slug', 'description', 'price_minor', 'condition', 'status', 'rejection_reason', 'archived_at', 'seller_archived_at'])]
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
            'archived_at' => 'datetime',
            'seller_archived_at' => 'datetime',
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

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isSellerArchived(): bool
    {
        return $this->seller_archived_at !== null;
    }

    public function isEligibleForSellerRestore(): bool
    {
        if ($this->seller_archived_at === null || $this->archived_at !== null) {
            return false;
        }

        if ($this->status === self::STATUS_REJECTED || $this->reservation_token !== null) {
            return false;
        }

        return ! $this->orderItems()
            ->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->where('status', '!=', Order::STATUS_CANCELLED))
            ->exists();
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
    protected function notArchived(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->whereNull('seller_archived_at')
            ->whereHas('seller', fn (Builder $sellerQuery): Builder => $sellerQuery->whereNull('archived_at'));
    }

    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->approved()
            ->notArchived()
            ->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->active());
    }

    public static function searchTermList(string $search): array
    {
        return preg_split('/[\p{Z}\s]+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function escapeLikeWildcards(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    #[Scope]
    protected function searchTerms(Builder $query, string $search): Builder
    {
        $terms = self::searchTermList($search);

        if ($terms === []) {
            return $query;
        }

        $grammar = $query->getQuery()->getGrammar();
        $titleColumn = $grammar->wrap('title');
        $descriptionColumn = $grammar->wrap('description');
        $nameColumn = $grammar->wrap('name');

        return $query->where(function (Builder $query) use ($terms, $titleColumn, $descriptionColumn, $nameColumn): void {
            foreach ($terms as $term) {
                $pattern = '%'.self::escapeLikeWildcards($term).'%';

                $query->where(function (Builder $query) use ($pattern, $titleColumn, $descriptionColumn, $nameColumn): void {
                    $query->whereRaw('lower('.$titleColumn.') like lower(?) escape \'!\'', [$pattern])
                        ->orWhereRaw('lower('.$descriptionColumn.') like lower(?) escape \'!\'', [$pattern])
                        ->orWhereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->whereRaw(
                            'lower('.$nameColumn.') like lower(?) escape \'!\'',
                            [$pattern],
                        ));
                });
            }
        });
    }

    #[Scope]
    protected function rankBySearchTerms(Builder $query, string $search): Builder
    {
        $terms = self::searchTermList($search);

        if ($terms === []) {
            return $query;
        }

        $grammar = $query->getQuery()->getGrammar();
        $productsTable = $query->getModel()->getTable();
        $titleColumn = $grammar->wrap($productsTable.'.title');
        $descriptionColumn = $grammar->wrap($productsTable.'.description');
        $categoryIdColumn = $grammar->wrap($productsTable.'.category_id');
        $categoryNameColumn = $grammar->wrap('c.name');
        $categoryIdAlias = $grammar->wrap('c.id');
        $categoryTable = $grammar->wrapTable((new Category)->getTable()).' as c';

        $titleConditions = [];
        $categoryConditions = [];
        $descriptionConditions = [];

        foreach ($terms as $term) {
            $pattern = $grammar->quoteString('%'.self::escapeLikeWildcards($term).'%');

            $titleConditions[] = 'lower('.$titleColumn.') like lower('.$pattern.') escape \'!\'';
            $categoryConditions[] = 'lower('.$categoryNameColumn.') like lower('.$pattern.') escape \'!\'';
            $descriptionConditions[] = 'lower('.$descriptionColumn.') like lower('.$pattern.') escape \'!\'';
        }

        $categoryCondition = implode(' or ', $categoryConditions);
        $score = 'case when '.implode(' or ', $titleConditions)
            .' then 3 when exists (select 1 from '.$categoryTable.' where '.$categoryIdAlias.' = '.$categoryIdColumn
            .' and ('.$categoryCondition.')) then 2 when '.implode(' or ', $descriptionConditions)
            .' then 1 else 0 end';

        return $query
            ->orderByRaw($score.' desc')
            ->orderByDesc($productsTable.'.created_at')
            ->orderByDesc($productsTable.'.id');
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
    protected function catalogFilters(Builder $query, array $filters, bool $locationFilterAvailable): Builder
    {
        return $query
            ->when(($filters['category'] ?? '') !== '', fn (Builder $query): Builder => $query->where('category_id', $filters['category']))
            ->when(
                $locationFilterAvailable && ($filters['location'] ?? '') !== '',
                fn (Builder $query): Builder => $query->inLocation((string) $filters['location']),
            )
            ->when(($filters['condition'] ?? '') !== '', fn (Builder $query): Builder => $query->where('condition', $filters['condition']))
            ->when(
                ($filters['min_price'] ?? '') !== '',
                fn (Builder $query): Builder => $query->where('price_minor', '>=', (int) round(((float) $filters['min_price']) * 100)),
            )
            ->when(
                ($filters['max_price'] ?? '') !== '',
                fn (Builder $query): Builder => $query->where('price_minor', '<=', (int) round(((float) $filters['max_price']) * 100)),
            );
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
