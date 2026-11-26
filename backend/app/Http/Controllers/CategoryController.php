<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /** GET /api/categories: all of them (a short list, so no pagination). */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        Gate::authorize('create', Category::class);

        $category = Category::create([
            ...$request->validated(),
            'slug' => Category::uniqueSlug($request->validated('name')),
        ]);

        return new CategoryResource($category->load('parent')->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        $category->update([
            ...$request->validated(),
            'slug' => Category::uniqueSlug($request->validated('name'), $category->id),
        ]);

        return new CategoryResource($category->load('parent')->loadCount('products'));
    }

    /** Subcategories become top-level (nullOnDelete). Products block the delete. */
    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        // withTrashed: the foreign key also counts soft-deleted products
        $count = Product::withTrashed()->where('category_id', $category->id)->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'category' => "{$category->name} has {$count} products. Move them to another category first.",
            ]);
        }

        $category->delete();

        return response()->noContent();
    }
}
