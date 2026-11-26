<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** PATCH /api/stock-levels/{stockLevel}: planning numbers only, never the quantity. */
class ReorderSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reorder_point' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
