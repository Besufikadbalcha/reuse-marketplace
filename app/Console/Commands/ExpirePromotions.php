<?php

namespace App\Console\Commands;

use App\Models\ProductPromotion;
use App\Notifications\PromotionExpiredNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('promotions:expire {--chunk=100 : Maximum promotions inspected per batch}')]
#[Description('Expire active promotions whose promotion window has ended.')]
class ExpirePromotions extends Command
{
    public function handle(): int
    {
        $now = now();
        $chunkSize = max(1, (int) $this->option('chunk'));
        $expired = [];

        do {
            $candidates = ProductPromotion::query()
                ->expirable($now)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get(['id']);

            foreach ($candidates as $candidate) {
                DB::transaction(function () use ($candidate, $now, &$expired): void {
                    $promotion = ProductPromotion::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if ($promotion === null
                        || $promotion->status !== ProductPromotion::STATUS_ACTIVE
                        || $promotion->ends_at === null
                        || $promotion->ends_at->greaterThan($now)) {
                        return;
                    }

                    $promotion->expireAt($now);
                    $promotion->save();

                    $expired[] = $promotion;
                }, attempts: 3);
            }
        } while ($candidates->count() === $chunkSize);

        foreach ($expired as $promotion) {
            $promotion->seller?->notify(new PromotionExpiredNotification($promotion));
        }

        $this->info(sprintf('Expired %d promotion(s).', count($expired)));

        return self::SUCCESS;
    }
}
