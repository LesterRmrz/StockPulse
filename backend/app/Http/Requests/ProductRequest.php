<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST /api/products) and edit (PUT /api/products/{product}). */
class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
        }
    }

    public function rules(): array
    {
        /** @var Product|null $product null when creating */
        $product = $this->route('product');

        $rules = [
            // unique() also sees soft-deleted products, like the database index does
            'sku' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'default_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'sell_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        // Only when creating: the starting reorder settings for every warehouse.
        // After that they're edited per warehouse on the product page.
        if ($product === null) {
            $rules['reorder_point'] = ['sometimes', 'integer', 'min:0', 'max:1000000'];
            $rules['reorder_qty'] = ['sometimes', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['sku.regex' => 'The SKU may only contain letters, numbers and dashes.'];
    }

    /** How field names appear in messages: "The SKU has already been taken." */
    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'category_id' => 'category',
            'default_supplier_id' => 'supplier',
        ];
    }
}
