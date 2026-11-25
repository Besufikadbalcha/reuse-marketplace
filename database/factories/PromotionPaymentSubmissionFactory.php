<?php

namespace Database\Factories;

use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionPaymentSubmission>
 */
class PromotionPaymentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => ProductPromotion::factory(),
            'attempt' => fake()->unique()->numberBetween(1, 1000000),
            'method' => fake()->randomElement(PromotionPaymentSubmission::METHODS),
            'transaction_reference' => 'TXN-'.fake()->unique()->numerify('########'),
            'transaction_details' => fake()->sentence(),
            'proof_path' => 'promotion-payment-proofs/pending/'.fake()->uuid().'.jpg',
            'proof_checksum' => fake()->sha256(),
            'status' => PromotionPaymentSubmission::STATUS_PENDING,
            'pending_promotion_id' => null,
            'approved_promotion_id' => null,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PromotionPaymentSubmission $submission): void {
            if (str_starts_with((string) $submission->proof_path, 'promotion-payment-proofs/pending/')) {
                $promotion = $submission->promotion()->firstOrFail();

                $submission->proof_path = sprintf(
                    'promotion-payment-proofs/%d/%d/attempt-%d/%s.jpg',
                    (int) $promotion->seller_id,
                    (int) $promotion->id,
                    (int) $submission->attempt,
                    fake()->uuid(),
                );
            }

            $this->syncState($submission);
        });
    }

    public function telebirr(): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => PromotionPaymentSubmission::METHOD_TELEBIRR,
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => PromotionPaymentSubmission::METHOD_BANK_TRANSFER,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionPaymentSubmission::STATUS_APPROVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionPaymentSubmission::STATUS_REJECTED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionPaymentSubmission::STATUS_SUPERSEDED,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    private function syncState(PromotionPaymentSubmission $submission): void
    {
        $submission->pending_promotion_id = $submission->status === PromotionPaymentSubmission::STATUS_PENDING
            ? $submission->promotion_id
            : null;
        $submission->approved_promotion_id = $submission->status === PromotionPaymentSubmission::STATUS_APPROVED
            ? $submission->promotion_id
            : null;

        $submission->saveQuietly();
    }
}
