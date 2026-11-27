<?php

namespace App\Http\Requests;

class ProductSuggestionRequest extends ProductIndexRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
    }
}
