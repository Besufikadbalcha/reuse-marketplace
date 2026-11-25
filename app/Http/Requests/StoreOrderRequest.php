<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');
        $user = $this->user();

        return $user !== null
            && $product instanceof Product
            && ! $product->isOwnedBy($user);
    }

    protected function failedAuthorization(): void
    {
        abort(403, __('Sellers cannot buy their own products.'));
    }

    public function prepareForValidation(): void
    {
        foreach (['contact_name', 'contact_phone', 'delivery_address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
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
            'idempotency_key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            ],
            'fulfillment_method' => ['required', Rule::in([Order::FULFILLMENT_PICKUP, Order::FULFILLMENT_CASH_ON_DELIVERY])],
            'contact_name' => ['exclude_unless:fulfillment_method,cash_on_delivery', 'required', 'string', 'max:255'],
            'contact_phone' => ['exclude_unless:fulfillment_method,cash_on_delivery', 'required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'delivery_address' => ['exclude_unless:fulfillment_method,cash_on_delivery', 'required', 'string', 'max:1000'],
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
            'idempotency_key.required' => __('Restart checkout to place this order.'),
            'idempotency_key.regex' => __('Restart checkout to place this order.'),
            'fulfillment_method.required' => __('Choose pickup or cash on delivery.'),
            'fulfillment_method.in' => __('Choose pickup or cash on delivery.'),
            'contact_name.required' => __('Enter a contact name for cash on delivery.'),
            'contact_phone.required' => __('Enter a phone number for cash on delivery.'),
            'contact_phone.regex' => __('Enter a valid phone number.'),
            'delivery_address.required' => __('Enter a delivery address for cash on delivery.'),
        ];
    }
}
