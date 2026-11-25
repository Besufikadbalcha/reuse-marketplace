<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResubmitPromotionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');
        $user = $this->user();

        if (! $promotion instanceof ProductPromotion || $user === null) {
            return false;
        }

        $latestSubmission = PromotionPaymentSubmission::query()
            ->forPromotion((int) $promotion->id)
            ->latestFirst()
            ->first();

        return $promotion->seller_id === $user->id
            && Product::query()
                ->whereKey($promotion->product_id)
                ->where('seller_id', $user->id)
                ->where('status', Product::STATUS_APPROVED)
                ->exists()
            && $promotion->status === ProductPromotion::STATUS_PAYMENT_REJECTED
            && $latestSubmission?->isRejected() === true;
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(PromotionPaymentSubmission::METHODS)],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'transaction_details' => ['required', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'extensions:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => __('Choose how you made the payment.'),
            'method.in' => __('Choose Telebirr or bank transfer.'),
            'transaction_details.required' => __('Add payment details so we can verify your submission.'),
            'proof.required' => __('Upload your payment proof.'),
            'proof.mimes' => __('Payment proof must be a JPEG, PNG, WebP, or PDF file.'),
            'proof.extensions' => __('Payment proof must be a JPEG, PNG, WebP, or PDF file.'),
            'proof.max' => __('Payment proof must be 5 MB or smaller.'),
        ];
    }
}
