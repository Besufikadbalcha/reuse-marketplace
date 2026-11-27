<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('condition')) {
            $this->merge(['condition' => Product::CONDITION_LIKE_NEW]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
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
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
            'condition' => ['required', 'string', Rule::in([
                Product::CONDITION_NEW,
                Product::CONDITION_LIKE_NEW,
                Product::CONDITION_GOOD,
                Product::CONDITION_FAIR,
            ])],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['file', 'mimes:jpeg,png,webp', 'max:5120'],
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
            'images.required' => __('Please upload at least one product image.'),
            'images.min' => __('Please upload at least one product image.'),
            'images.max' => __('A product may have at most five images.'),
            'images.*.mimes' => __('Each image must be a JPEG, PNG, or WebP file.'),
            'images.*.max' => __('Each image must be 5 MB or smaller.'),
            'price.decimal' => __('Enter a price with at most two decimal places.'),
            'price.max' => __('The price is too high.'),
        ];
    }
}
