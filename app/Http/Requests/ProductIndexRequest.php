<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductIndexRequest extends FormRequest
{
    public const SORT_LATEST = 'latest';

    public const SORT_RELEVANCE = 'relevance';

    public const SORT_TITLE_ASC = 'title_asc';

    public const SORT_PRICE_ASC = 'price_asc';

    public const SORT_PRICE_DESC = 'price_desc';

    public const SORTS = [
        self::SORT_LATEST,
        self::SORT_RELEVANCE,
        self::SORT_TITLE_ASC,
        self::SORT_PRICE_ASC,
        self::SORT_PRICE_DESC,
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('q');

        if (is_string($search)) {
            $this->merge(['q' => $this->normalizeSearch($search)]);
        }

        $location = $this->input('location');

        if (is_string($location)) {
            $this->merge(['location' => trim($location)]);
        }

        foreach (['min_price', 'max_price'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmedValue = trim($value);

                $this->merge([$field => $trimmedValue === '' ? null : $trimmedValue]);
            }
        }
    }

    public function normalizeSearch(string $search): string
    {
        $trimmed = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $search) ?? trim($search);

        return preg_replace('/[\p{Z}\s]+/u', ' ', $trimmed) ?? $trimmed;
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
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
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
