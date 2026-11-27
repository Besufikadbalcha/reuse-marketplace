<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_id', 'actor_id', 'action', 'reason', 'listings_affected', 'listings_restored', 'metadata'])]
class SellerArchiveEvent extends Model
{
    public const ACTION_ARCHIVE = 'archive';

    public const ACTION_RESTORE = 'restore';

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'listings_affected' => 'integer',
            'listings_restored' => 'integer',
            'metadata' => 'array',
        ];
    }
}
