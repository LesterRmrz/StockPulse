<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function rules(): array
    {
        $activeWarehouse = Rule::exists('warehouses', 'id')->where('is_active', true);

        return [
            'from_warehouse_id' => ['required', 'integer', $activeWarehouse],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', $activeWarehouse],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // "items.*" means "every element of the items array"
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'Choose two different warehouses.',
            'items.required' => 'Add at least one product.',
            'items.*.product_id.distinct' => 'Each product can only appear once.',
            'items.*.quantity.min' => 'Each quantity must be at least 1.',
        ];
    }
}
