<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory ledger: one row per change in stock, never updated or deleted.
 *
 * - quantity_change is signed (+100 for a receipt, -2 for a sale).
 * - quantity_after is the warehouse's quantity right after this movement,
 *   so history charts don't need to re-add the whole ledger.
 * - reference_type / reference_id (a polymorphic link) point at whatever caused
 *   the movement: an order, a purchase order or a stock transfer. NULL for manual
 *   adjustments.
 * - Only created_at, no updated_at: rows are immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_after');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // "History of product X in warehouse Y, newest first"
            $table->index(['product_id', 'warehouse_id', 'created_at']);
            // "All sales in the last 30 days" style analytics
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
