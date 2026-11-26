<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    /** GET /api/suppliers?search=&page= (per_page=100 for dropdowns) */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $term = trim($filters['search'] ?? '');

        $suppliers = Supplier::query()
            ->withCount(['products', 'purchaseOrders'])
            ->when($term !== '', fn ($q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return SupplierResource::collection($suppliers);
    }

    public function store(SupplierRequest $request): SupplierResource
    {
        Gate::authorize('create', Supplier::class);

        $supplier = Supplier::create($request->validated());

        return new SupplierResource($supplier->loadCount(['products', 'purchaseOrders']));
    }

    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
    {
        Gate::authorize('update', $supplier);

        $supplier->update($request->validated());

        return new SupplierResource($supplier->loadCount(['products', 'purchaseOrders']));
    }

    /**
     * Purchase orders are financial history, so a supplier that has any can't
     * be deleted. Products just lose their default supplier (nullOnDelete).
     */
    public function destroy(Supplier $supplier): Response
    {
        Gate::authorize('delete', $supplier);

        $orders = $supplier->purchaseOrders()->count();

        if ($orders > 0) {
            throw ValidationException::withMessages([
                'supplier' => "{$supplier->name} has {$orders} purchase orders, so it can't be deleted.",
            ]);
        }

        $supplier->delete();

        return response()->noContent();
    }
}
