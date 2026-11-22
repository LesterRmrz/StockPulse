<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current quantity of each product in each warehouse.
 *
 * This is a *cached* value: the source of truth is the stock_movements ledger,
 * and quantity must always equal the sum of that product+warehouse's movements.
 * Both are written in the same DB transaction (InventoryService, M2).
 *
 * - The unique (product_id, warehouse_id) pair means there is exactly one row per
 *   product per warehouse, and gives us the row to lock with SELECT ... FOR UPDATE.
 * - quantity is UNSIGNED, so MySQL itself rejects a negative stock level even if
 *   application code has a bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('reorder_qty')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
