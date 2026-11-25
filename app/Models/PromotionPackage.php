<?php

namespace App\Models;

use Database\Factories\PromotionPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'price_minor', 'duration_days', 'is_active', 'sort_order'])]
class PromotionPackage extends Model
{
    /** @use HasFactory<PromotionPackageFactory> */
    use HasFactory;

    public const CODE_BASIC = 'basic';

    public const CODE_STANDARD = 'standard';

    public const CODE_PREMIUM = 'premium';

    public const PRIORITY_BASIC = 1;

    public const PRIORITY_STANDARD = 2;

    public const PRIORITY_PREMIUM = 3;

    /** @var array<string, int> */
    public const PRIORITIES = [
        self::CODE_BASIC => self::PRIORITY_BASIC,
        self::CODE_STANDARD => self::PRIORITY_STANDARD,
        self::CODE_PREMIUM => self::PRIORITY_PREMIUM,
    ];

    /** @var array<int, string> */
    public const CODES = [
        self::PRIORITY_BASIC => self::CODE_BASIC,
        self::PRIORITY_STANDARD => self::CODE_STANDARD,
        self::PRIORITY_PREMIUM => self::CODE_PREMIUM,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'priority' => self::PRIORITY_BASIC,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (self $package): void {
            $package->priority = self::priorityFor((string) $package->code);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'duration_days' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(ProductPromotion::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('priority')->orderBy('id');
    }

    public static function priorityFor(string $code): int
    {
        return self::PRIORITIES[$code] ?? self::PRIORITY_BASIC;
    }
}
