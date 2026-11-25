<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'location'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }

        foreach (['min_price', 'max_price'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmedValue = trim($value);

                $this->merge([$field => $trimmedValue === '' ? null : $trimmedValue]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'location' => [
                'nullable',
                'string',
                'max:120',
                Rule::when(
                    $this->locationFilterIsAvailable(),
                    [Rule::exists('locations', 'name')->where('is_active', true)],
                    ['exclude'],
                ),
            ],
            'condition' => ['nullable', 'string', Rule::in([
                Product::CONDITION_NEW,
                Product::CONDITION_LIKE_NEW,
                Product::CONDITION_GOOD,
                Product::CONDITION_FAIR,
            ])],
            'min_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'max_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
        ];
    }

    public function locationFilterIsAvailable(): bool
    {
        return Schema::hasTable('locations') && Schema::hasColumn('products', 'location_id');
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['min_price', 'max_price'])) {
                    return;
                }

                $minimumPrice = $this->input('min_price');
                $maximumPrice = $this->input('max_price');

                if ($minimumPrice === null || $maximumPrice === null) {
                    return;
                }

                if ((float) $minimumPrice > (float) $maximumPrice) {
                    $validator->errors()->add(
                        'max_price',
                        __('The maximum price must be greater than or equal to the minimum price.'),
                    );
                }
            },
        ];
    }
}
