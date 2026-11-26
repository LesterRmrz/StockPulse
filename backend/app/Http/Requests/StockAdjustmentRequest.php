<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Two ways to correct stock:
 *   mode "change": add or remove an amount (+5 found, -2 damaged)
 *   mode "count":  set it to what you physically counted (the server works out the difference)
 */
class StockAdjustmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::in(['change', 'count'])],
            'quantity' => [
                'required',
                'integer',
                'between:-1000000,1000000',
                Rule::when($this->input('mode') === 'count', ['min:0'], ['not_in:0']),
            ],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'Enter an amount to add or remove (not 0).',
            'quantity.min' => 'A counted quantity can\'t be negative.',
            'warehouse_id.exists' => 'Choose an active warehouse.',
        ];
    }
}
