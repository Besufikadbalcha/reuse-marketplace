<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    public const PRIVATE_PATH_PREFIX = 'seller-products/';

    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        if (! $this->isServable()) {
            return $this->placeholderUrl();
        }

        return route('product-images.show', [$this->product_id, $this->id]);
    }

    public function isServable(): bool
    {
        if ($this->id === null || $this->product_id === null) {
            return false;
        }

        $disk = $this->servingDisk();

        return $disk !== null && Storage::disk($disk)->exists($this->path);
    }

    public function isServableForSeller(int $sellerId): bool
    {
        if (! $this->exists || $this->product_id === null) {
            return false;
        }

        $productSellerId = $this->product()->value('seller_id');

        if ($productSellerId === null || (int) $productSellerId !== $sellerId) {
            return false;
        }

        $disk = $this->servingDisk();

        if ($disk === null || ! Storage::disk($disk)->exists($this->path)) {
            return false;
        }

        return $disk === 'local'
            ? $this->isPrivateForSeller($sellerId)
            : $this->isLegacyPublicPath();
    }

    public function isPrivate(): bool
    {
        return str_starts_with($this->path, self::PRIVATE_PATH_PREFIX);
    }

    public function isPrivateForSeller(int $sellerId): bool
    {
        if (! $this->isPrivate()) {
            return false;
        }

        $pattern = '/^'.preg_quote(self::PRIVATE_PATH_PREFIX, '/').$sellerId.'\/[A-Za-z0-9_-]+\.(?:jpeg|jpg|png|webp)$/';

        return preg_match($pattern, $this->path) === 1;
    }

    public function isLegacyPublicPath(): bool
    {
        return preg_match('/^products\/[A-Za-z0-9_-]+\.(?:jpeg|jpg|png|webp)$/', $this->path) === 1;
    }

    public function servingDisk(): ?string
    {
        if ($this->isPrivate()) {
            return 'local';
        }

        if (config('marketplace.allow_legacy_public_images') && $this->isLegacyPublicPath()) {
            return 'public';
        }

        return null;
    }

    public function previewUrl(): string
    {
        if (! $this->isServable()) {
            return $this->placeholderUrl();
        }

        return route('seller.products.images.show', [$this->product_id, $this->id]);
    }

    public function adminUrl(): string
    {
        if (! $this->isServable()) {
            return $this->placeholderUrl();
        }

        return route('admin.products.images.show', [$this->product_id, $this->id]);
    }

    private function placeholderUrl(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600" role="img" aria-label="Image coming soon">
  <defs>
    <linearGradient id="background" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f8f7f5"/>
      <stop offset="100%" stop-color="#e7e5e2"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#background)"/>
  <rect x="290" y="180" width="220" height="180" rx="18" fill="#d6d3d1" stroke="#a8a29e" stroke-width="8"/>
  <circle cx="350" cy="235" r="20" fill="#a8a29e"/>
  <path d="M302 342l62-68 44 48 34-36 52 56z" fill="#a8a29e"/>
  <text x="400" y="440" fill="#57534e" font-family="sans-serif" font-size="30" font-weight="600" text-anchor="middle">Image coming soon</text>
  <text x="400" y="475" fill="#78716c" font-family="sans-serif" font-size="18" text-anchor="middle">Temporary placeholder</text>
</svg>
SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
