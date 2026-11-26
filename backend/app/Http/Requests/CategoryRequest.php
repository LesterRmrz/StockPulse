<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Categories nest at most two levels (Electronics > Accessories). Allowing
 * only top-level parents is a simple way to make loops (A inside B inside A)
 * impossible.
 */
class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The parent must be a top-level category.',
            'parent_id.not_in' => 'A category can\'t be its own parent.',
        ];
    }

    /** Checks that need more than one field, run after the rules pass. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $category = $this->route('category');

                if ($category && $this->filled('parent_id') && $category->children()->exists()) {
                    $validator->errors()->add('parent_id', 'This category has subcategories, so it must stay top-level.');
                }
            },
        ];
    }
}
