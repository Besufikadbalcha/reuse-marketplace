<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSellerProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $product = $this->route('product');
        $sellerId = $this->user()?->id;

        if (! $product instanceof Product || $sellerId === null || $product->seller_id !== $sellerId) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public function prepareForValidation(): void
    {
        $product = $this->route('product');

        if ($product instanceof Product && ! $this->exists('condition')) {
            $this->merge([
                'condition' => $product->condition,
            ]);
        }

        $imageOrder = $this->input('image_order');

        if (! is_array($imageOrder)) {
            return;
        }

        $normalizedOrder = array_is_list($imageOrder)
            ? array_values(array_map('intval', $imageOrder))
            : collect($imageOrder)
                ->filter(fn (mixed $position): bool => is_numeric($position))
                ->sortBy(fn (mixed $position): int => (int) $position)
                ->keys()
                ->map(fn (mixed $imageId): int => (int) $imageId)
                ->values()
                ->all();

        $this->merge(['image_order' => $normalizedOrder]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : 0;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
            'condition' => ['required', 'string', Rule::in([
                Product::CONDITION_NEW,
                Product::CONDITION_LIKE_NEW,
                Product::CONDITION_GOOD,
                Product::CONDITION_FAIR,
            ])],
            'images' => ['nullable', 'array', 'min:1', 'max:5'],
            'images.*' => ['file', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array', 'max:5'],
            'remove_images.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $productId)],
            'image_order' => ['nullable', 'array', 'max:5'],
            'image_order.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $productId)],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.min' => __('Please keep at least one product image.'),
            'images.max' => __('A product may have at most five images.'),
            'images.*.mimes' => __('Each image must be a JPEG, PNG, or WebP file.'),
            'images.*.max' => __('Each image must be 5 MB or smaller.'),
            'remove_images.*.exists' => __('The selected image does not belong to this product.'),
            'remove_images.*.distinct' => __('Each image may be removed only once.'),
            'image_order.*.distinct' => __('Each image may appear only once in the order.'),
            'image_order.*.exists' => __('The selected image does not belong to this product.'),
            'price.decimal' => __('Enter a price with at most two decimal places.'),
            'price.max' => __('The price is too high.'),
        ];
    }

    /**
     * Add validation that depends on the product's current images.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $product = $this->route('product');

            if (! $product instanceof Product || $validator->errors()->isNotEmpty()) {
                return;
            }

            $removedIds = array_map('intval', $this->input('remove_images', []));
            $orderIds = array_map('intval', $this->input('image_order', []));
            $existingCount = $product->images()->count();
            $newCount = count($this->file('images', []));
            $finalCount = $existingCount - count($removedIds) + $newCount;

            if ($finalCount < 1) {
                $validator->errors()->add('images', __('Please keep at least one product image.'));
            }

            if ($finalCount > 5) {
                $validator->errors()->add('images', __('A product may have at most five images.'));
            }

            $remainingIds = $product->images()
                ->whereNotIn('id', $removedIds)
                ->pluck('id')
                ->map(fn (mixed $imageId): int => (int) $imageId)
                ->all();

            if (array_diff($orderIds, $remainingIds) !== []) {
                $validator->errors()->add('image_order', __('The selected image does not belong to this product.'));
            }
        }];
    }
}
