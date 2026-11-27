<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        $user = $this->user();

        return $product instanceof Product
            && $user !== null
            && $product->seller_id === $user->id
            && $product->status === Product::STATUS_APPROVED
            && Product::query()->whereKey($product->id)->notArchived()->exists();
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    public function rules(): array
    {
        return [
            'package_id' => [
                'required',
                'integer',
                Rule::exists('promotion_packages', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                        ->whereIn('code', array_values(PromotionPackage::CODES)),
                ),
            ],
            'method' => ['required', Rule::in(PromotionPaymentSubmission::METHODS)],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'transaction_details' => ['required', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'extensions:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if (! $product instanceof Product) {
                    return;
                }

                if (ProductPromotion::query()->forProduct((int) $product->id)->open()->exists()) {
                    $validator->errors()->add('product', __('This listing already has an open promotion request.'));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required' => __('Choose a promotion package.'),
            'package_id.exists' => __('Choose an active promotion package.'),
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
