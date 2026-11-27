<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'archived_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function completedTradeItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function sellerArchiveEvents(): HasMany
    {
        return $this->hasMany(SellerArchiveEvent::class, 'seller_id');
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isActiveAdmin(): bool
    {
        return $this->isAdmin() && ! $this->isArchived();
    }

    public function completedTradesCount(): int
    {
        return (int) OrderItem::query()
            ->where('fulfillment_status', OrderItem::FULFILLMENT_COMPLETED)
            ->whereHas('product', fn (Builder $query): Builder => $query->where('seller_id', $this->id))
            ->whereHas('order', fn (Builder $query): Builder => $query->where('status', '!=', Order::STATUS_CANCELLED))
            ->count();
    }

    public function activeApprovedListingsCount(): int
    {
        return (int) $this->products()->availableForPurchase()->count();
    }

    public function publicCityRegion(): ?string
    {
        foreach (['city', 'region', 'city_region', 'location'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public function profileImageUrl(): ?string
    {
        foreach (['profile_image_url', 'avatar_url', 'profile_photo_url'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Scope]
    protected function activeAdmins(Builder $query): Builder
    {
        return $query->where('is_admin', true)->whereNull('archived_at');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
