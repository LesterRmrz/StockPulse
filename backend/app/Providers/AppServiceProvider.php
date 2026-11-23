<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\StockTransfer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // stock_movements.reference_type stores these short names ("order")
        // instead of PHP class names ("App\Models\Order"). Renaming or moving a
        // model class later then won't break old ledger rows.
        Relation::enforceMorphMap([
            'order' => Order::class,
            'purchase_order' => PurchaseOrder::class,
            'stock_transfer' => StockTransfer::class,
        ]);

        // Outside production, fail loudly on common Eloquent mistakes:
        // lazy loading in loops (N+1 queries), assigning attributes that aren't
        // fillable, and reading attributes that weren't selected.
        Model::shouldBeStrict(! $this->app->isProduction());

        // At most 5 login attempts per minute per email + IP address.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
