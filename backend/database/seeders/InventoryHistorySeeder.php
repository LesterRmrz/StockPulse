<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesChannel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplHeap;

/**
 * Generates N days of realistic business history by *simulating* it:
 *
 *   1. Every product starts with an opening stock balance in each warehouse.
 *   2. Day by day, customers place orders. Each sale lowers stock and writes a
 *      "sale" row to the ledger. A small share of orders gets cancelled, which
 *      puts the stock back with a "return" row.
 *   3. When stock falls to its reorder point, a purchase order is raised at the
 *      end of the day. It arrives after the supplier's lead time (sometimes late)
 *      and writes a "receipt" row.
 *
 * Events are processed strictly in time order (a priority queue), so every
 * ledger row's quantity_after is correct, and at the end each stock_levels
 * quantity equals the sum of its movements.
 *
 * For speed it runs in memory and bulk-inserts the result instead of calling
 * InventoryService once per sale. That's fine here because it builds the
 * starting dataset on empty tables; at runtime all stock changes go through
 * InventoryService (M2).
 */
class InventoryHistorySeeder extends Seeder
{
    private const HOUR_WEIGHTS = [1, .5, .3, .3, .3, .5, 1, 2, 3, 4, 5, 5, 6, 5, 5, 5, 5, 6, 7, 8, 8, 7, 5, 3];

    private const WAREHOUSE_SHARE = ['MNL-01' => .5, 'CEB-01' => .3, 'DVO-01' => .2];

    private const LINES_PER_ORDER = [1 => 55, 2 => 28, 3 => 12, 4 => 5];

    private const UNITS_PER_LINE = [1 => 70, 2 => 22, 3 => 8];

    private const CHANNELS = ['web' => 60, 'marketplace' => 30, 'pos' => 10];

    private int $now;

    /** @var array<int, array{cost: float, price: float, supplier: int}> */
    private array $products = [];

    /** @var array<int, int> supplier id => lead time in days */
    private array $leadTimes = [];

    /** @var array<int, int> warehouse id => share used for weighting */
    private array $warehouses = [];

    /** @var int[] */
    private array $customerIds = [];

    /** Live state of the simulation: [productId][warehouseId] => quantity */
    private array $qty = [];

    /** [productId][warehouseId] => [reorder_point, reorder_qty] */
    private array $reorder = [];

    /** "productId:warehouseId" => true while a purchase order is open for it */
    private array $openPo = [];

    /** "supplierId:warehouseId" => [productId => quantity] waiting for tonight's reorder run */
    private array $needs = [];

    private array $cumProducts = [];

    private array $cumWarehouses = [];

    private SplHeap $events;

    private int $seq = 0;

    // Rows collected for bulk insert
    private array $orderRows = [];

    private array $orderItemRows = [];

    private array $poRows = [];

    private array $poItemRows = [];

    private array $movementRows = [];

    private int $nextOrderId = 1;

    private int $nextPoId = 1;

    public function run(): void
    {
        if (Order::query()->exists() || StockMovement::query()->exists()) {
            throw new RuntimeException('InventoryHistorySeeder needs empty tables. Run: php artisan migrate:fresh --seed');
        }

        $config = config('stockpulse.seed');
        $days = max(1, $config['history_days']);

        $this->now = now()->getTimestamp();
        $start = now()->startOfDay()->subDays($days - 1)->getTimestamp();

        $this->loadReferenceData();
        $this->buildDemandModel($config, $start);

        $this->events = $this->newEventQueue();
        for ($d = 0; $d < $days; $d++) {
            $dayStart = $start + $d * 86400;
            $this->push($dayStart, 'day', ['ordersPerDay' => $this->ordersForDay($d, $days, $config, $dayStart)]);
        }

        while (! $this->events->isEmpty()) {
            [$ts, , $type, $payload] = $this->events->extract();
            if ($ts > $this->now) {
                continue; // the future hasn't happened yet
            }
            match ($type) {
                'day' => $this->startDay($ts, $payload['ordersPerDay']),
                'order' => $this->placeOrder($ts),
                'cancel' => $this->cancelOrder($ts, $payload),
                'reorder' => $this->raisePurchaseOrders($ts),
                'arrival' => $this->receivePurchaseOrder($ts, $payload),
            };
        }

        $this->persist($start);
    }

    private function loadReferenceData(): void
    {
        $this->leadTimes = Supplier::query()->pluck('lead_time_days', 'id')->all();

        foreach (Product::query()->get(['id', 'cost_price', 'sell_price', 'default_supplier_id']) as $p) {
            $this->products[$p->id] = [
                'cost' => (float) $p->cost_price,
                'price' => (float) $p->sell_price,
                'supplier' => $p->default_supplier_id,
            ];
        }

        foreach (Warehouse::query()->get(['id', 'code']) as $w) {
            $this->warehouses[$w->id] = self::WAREHOUSE_SHARE[$w->code] ?? .2;
        }

        $this->customerIds = Customer::query()->pluck('id')->all();

        if (! $this->products || ! $this->warehouses || ! $this->customerIds) {
            throw new RuntimeException('Seed warehouses, the catalog and customers before InventoryHistorySeeder.');
        }
    }

    /**
     * Gives each product a popularity (a few best sellers, a long tail) and sets
     * each product+warehouse's reorder point, reorder quantity and opening stock
     * from its expected daily demand.
     */
    private function buildDemandModel(array $config, int $start): void
    {
        $ids = array_keys($this->products);
        shuffle($ids);

        $popularity = [];
        foreach ($ids as $rank => $id) {
            $popularity[$id] = 1 / (($rank + 4) ** 0.85);
        }
        $this->cumProducts = $this->cumulative($popularity);
        $this->cumWarehouses = $this->cumulative($this->warehouses);

        // Expected units sold per day across the whole shop, at the current
        // (end-of-history) sales rate. Sizing for the average rate would leave
        // reorder points too low by the end of the history, since sales grow.
        $unitsPerDay = $config['orders_per_day_end'] * $this->mean(self::LINES_PER_ORDER) * $this->mean(self::UNITS_PER_LINE);

        $popTotal = array_sum($popularity);
        $whTotal = array_sum($this->warehouses);
        $openingAt = $start - 86400 + 8 * 3600;

        foreach ($this->products as $pid => $product) {
            $lead = $this->leadTimes[$product['supplier']] ?? 7;

            foreach ($this->warehouses as $wid => $share) {
                $daily = $unitsPerDay * ($popularity[$pid] / $popTotal) * ($share / $whTotal);

                $reorderPoint = max(5, (int) ceil($daily * ($lead + 3)));
                $reorderQty = max(12, (int) ceil($daily * 30 / 6) * 6); // ~30 days of stock, in cartons of 6
                $opening = $reorderPoint + (int) round($reorderQty * mt_rand(50, 100) / 100);

                $this->qty[$pid][$wid] = $opening;
                $this->reorder[$pid][$wid] = [$reorderPoint, $reorderQty];

                $this->movement($openingAt, $pid, $wid, MovementType::Adjustment, $opening, null, null, 'Opening balance');
            }
        }
    }

    private function ordersForDay(int $d, int $days, array $config, int $dayStart): int
    {
        $progress = $days > 1 ? $d / ($days - 1) : 1;
        $base = $config['orders_per_day_start'] + ($config['orders_per_day_end'] - $config['orders_per_day_start']) * $progress;
        $weekend = in_array((int) date('N', $dayStart), [6, 7], true) ? 1.2 : 1.0;

        return (int) round($base * $weekend * mt_rand(85, 115) / 100);
    }

    private function startDay(int $dayStart, int $ordersPerDay): void
    {
        for ($i = 0; $i < $ordersPerDay; $i++) {
            $hour = $this->pickWeighted(self::HOUR_WEIGHTS);
            $this->push($dayStart + $hour * 3600 + mt_rand(0, 3599), 'order');
        }

        // Tonight's reorder run (or right now, if today isn't over yet).
        $this->push(min($dayStart + 23 * 3600 + 1800, $this->now - 60), 'reorder');
    }

    private function placeOrder(int $ts): void
    {
        $wid = $this->pickCumulative($this->cumWarehouses);
        $lineCount = $this->pickWeighted(self::LINES_PER_ORDER);

        $orderId = $this->nextOrderId;
        $items = [];
        $subtotal = 0.0;

        for ($i = 0; $i < $lineCount; $i++) {
            $pid = $this->pickCumulative($this->cumProducts);
            if (isset($items[$pid])) {
                continue;
            }
            $units = $this->pickWeighted(self::UNITS_PER_LINE);
            if ($this->qty[$pid][$wid] < $units) {
                continue; // out of stock here: this line is a lost sale
            }

            $this->qty[$pid][$wid] -= $units;
            $this->movement($ts, $pid, $wid, MovementType::Sale, -$units, 'order', $orderId);
            $this->checkReorder($pid, $wid);

            $price = $this->products[$pid]['price'];
            $items[$pid] = $units;
            $subtotal += $price * $units;

            $this->orderItemRows[] = [
                'order_id' => $orderId,
                'product_id' => $pid,
                'quantity' => $units,
                'unit_price' => $price,
                'unit_cost' => $this->products[$pid]['cost'],
                'line_total' => round($price * $units, 2),
            ];
        }

        if (! $items) {
            return; // nothing in stock: the customer left without ordering
        }

        $this->nextOrderId++;
        $channel = (string) $this->pickWeighted(self::CHANNELS);
        $shipping = match ($channel) {
            'web' => $subtotal >= 50 ? 0 : 4.99,
            'marketplace' => 2.99,
            default => 0,
        };

        $status = OrderStatus::Paid;
        $fulfilledAt = null;
        $cancelledAt = null;

        if (mt_rand(1, 100) <= 3 && ($cancelAt = $ts + mt_rand(1, 6) * 3600) <= $this->now) {
            $status = OrderStatus::Cancelled;
            $cancelledAt = $cancelAt;
            $this->push($cancelAt, 'cancel', ['order' => $orderId, 'warehouse' => $wid, 'items' => $items]);
        } else {
            $fulfilAt = $channel === 'pos' ? $ts : $ts + mt_rand(4, 36) * 3600;
            if ($fulfilAt <= $this->now) {
                $status = OrderStatus::Fulfilled;
                $fulfilledAt = $fulfilAt;
            }
        }

        $this->orderRows[] = [
            'id' => $orderId,
            'order_number' => 'SP-'.(10000 + $orderId),
            'customer_id' => $this->customerIds[mt_rand(0, count($this->customerIds) - 1)],
            'warehouse_id' => $wid,
            'channel' => SalesChannel::from($channel)->value,
            'status' => $status->value,
            'subtotal' => round($subtotal, 2),
            'shipping_fee' => $shipping,
            'total' => round($subtotal + $shipping, 2),
            'placed_at' => $this->dt($ts),
            'fulfilled_at' => $fulfilledAt ? $this->dt($fulfilledAt) : null,
            'cancelled_at' => $cancelledAt ? $this->dt($cancelledAt) : null,
            'created_at' => $this->dt($ts),
            'updated_at' => $this->dt($cancelledAt ?? $fulfilledAt ?? $ts),
        ];
    }

    /** A cancelled order puts its stock back on the shelf. */
    private function cancelOrder(int $ts, array $payload): void
    {
        foreach ($payload['items'] as $pid => $units) {
            $this->qty[$pid][$payload['warehouse']] += $units;
            $this->movement($ts, $pid, $payload['warehouse'], MovementType::Return, $units, 'order', $payload['order'], 'Order cancelled');
        }
    }

    private function checkReorder(int $pid, int $wid): void
    {
        [$reorderPoint, $reorderQty] = $this->reorder[$pid][$wid];

        if ($this->qty[$pid][$wid] <= $reorderPoint && ! isset($this->openPo["$pid:$wid"])) {
            $this->openPo["$pid:$wid"] = true;
            $this->needs[$this->products[$pid]['supplier'].':'.$wid][$pid] = $reorderQty;
        }
    }

    /** One purchase order per supplier+warehouse for everything that ran low today. */
    private function raisePurchaseOrders(int $ts): void
    {
        foreach ($this->needs as $key => $lines) {
            [$supplierId, $wid] = array_map('intval', explode(':', $key));
            $poId = $this->nextPoId++;
            $lead = $this->leadTimes[$supplierId] ?? 7;

            // Most deliveries are on time, some are 1-2 days late, a few are very late.
            $roll = mt_rand(1, 100);
            $delay = $roll <= 80 ? 0 : ($roll <= 95 ? mt_rand(1, 2) : mt_rand(3, 6));
            $arrivalDay = strtotime(date('Y-m-d', $ts + ($lead + $delay) * 86400));
            $receivedAt = $arrivalDay + mt_rand(8, 16) * 3600 + mt_rand(0, 3599);
            $received = $receivedAt <= $this->now;

            $total = 0.0;
            foreach ($lines as $pid => $units) {
                $cost = $this->products[$pid]['cost'];
                $total += $cost * $units;
                $this->poItemRows[] = [
                    'purchase_order_id' => $poId,
                    'product_id' => $pid,
                    'quantity_ordered' => $units,
                    'quantity_received' => $received ? $units : 0,
                    'unit_cost' => $cost,
                ];
            }

            $this->poRows[] = [
                'id' => $poId,
                'po_number' => 'PO-'.(1000 + $poId),
                'supplier_id' => $supplierId,
                'warehouse_id' => $wid,
                'status' => ($received ? PurchaseOrderStatus::Received : PurchaseOrderStatus::Ordered)->value,
                'total' => round($total, 2),
                'ordered_at' => $this->dt($ts),
                'expected_at' => date('Y-m-d', $ts + $lead * 86400),
                'received_at' => $received ? $this->dt($receivedAt) : null,
                'created_by' => null,
                'created_at' => $this->dt($ts),
                'updated_at' => $this->dt($received ? $receivedAt : $ts),
            ];

            if ($received) {
                $this->push($receivedAt, 'arrival', ['po' => $poId, 'warehouse' => $wid, 'lines' => $lines]);
            }
        }

        $this->needs = [];
    }

    private function receivePurchaseOrder(int $ts, array $payload): void
    {
        $wid = $payload['warehouse'];

        foreach ($payload['lines'] as $pid => $units) {
            $this->qty[$pid][$wid] += $units;
            $this->movement($ts, $pid, $wid, MovementType::Receipt, $units, 'purchase_order', $payload['po'], null, $this->products[$pid]['cost']);
            unset($this->openPo["$pid:$wid"]);
            // Still low after the delivery (demand was high)? Reorder again tonight.
            $this->checkReorder($pid, $wid);
        }
    }

    private function movement(int $ts, int $pid, int $wid, MovementType $type, int $change, ?string $refType, ?int $refId, ?string $note = null, ?float $unitCost = null): void
    {
        $this->movementRows[] = [
            'product_id' => $pid,
            'warehouse_id' => $wid,
            'type' => $type->value,
            'quantity_change' => $change,
            'quantity_after' => $this->qty[$pid][$wid],
            'unit_cost' => $unitCost ?? $this->products[$pid]['cost'],
            'reference_type' => $refType,
            'reference_id' => $refId,
            'user_id' => null,
            'note' => $note,
            'created_at' => $this->dt($ts),
        ];
    }

    private function persist(int $start): void
    {
        $createdAt = $this->dt($start - 86400);
        $levels = [];
        foreach ($this->qty as $pid => $byWarehouse) {
            foreach ($byWarehouse as $wid => $quantity) {
                [$reorderPoint, $reorderQty] = $this->reorder[$pid][$wid];
                $levels[] = [
                    'product_id' => $pid,
                    'warehouse_id' => $wid,
                    'quantity' => $quantity,
                    'reorder_point' => $reorderPoint,
                    'reorder_qty' => $reorderQty,
                    'created_at' => $createdAt,
                    'updated_at' => $this->dt($this->now),
                ];
            }
        }

        DB::transaction(function () use ($levels) {
            foreach ([
                'purchase_orders' => $this->poRows,
                'purchase_order_items' => $this->poItemRows,
                'orders' => $this->orderRows,
                'order_items' => $this->orderItemRows,
                'stock_movements' => $this->movementRows,
                'stock_levels' => $levels,
            ] as $table => $rows) {
                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });

        $this->command?->info(sprintf(
            'History: %s orders, %s purchase orders, %s stock movements.',
            number_format(count($this->orderRows)),
            number_format(count($this->poRows)),
            number_format(count($this->movementRows)),
        ));
    }

    // ---------------------------------------------------------------- helpers

    private function push(int $ts, string $type, array $payload = []): void
    {
        $this->events->insert([$ts, $this->seq++, $type, $payload]);
    }

    private function dt(int $ts): string
    {
        return date('Y-m-d H:i:s', $ts);
    }

    /** @param array<int|string, float|int> $weights  @return array<int|string, float> */
    private function cumulative(array $weights): array
    {
        $total = array_sum($weights);
        $running = 0;
        $cum = [];
        foreach ($weights as $key => $weight) {
            $running += $weight / $total;
            $cum[$key] = $running;
        }

        return $cum;
    }

    private function pickCumulative(array $cumulative): int|string
    {
        $r = mt_rand() / mt_getrandmax();
        foreach ($cumulative as $key => $threshold) {
            if ($r <= $threshold) {
                return $key;
            }
        }

        return array_key_last($cumulative);
    }

    /** Picks a key from [key => weight]. */
    private function pickWeighted(array $weights): int|string
    {
        return $this->pickCumulative($this->cumulative($weights));
    }

    private function mean(array $weights): float
    {
        $sum = 0;
        foreach ($weights as $value => $weight) {
            $sum += $value * $weight;
        }

        return $sum / array_sum($weights);
    }

    /**
     * Min-heap of [timestamp, sequence, type, payload]: always hands back the
     * earliest event; the sequence number keeps same-second events in insert order.
     */
    private function newEventQueue(): SplHeap
    {
        return new class extends SplHeap
        {
            protected function compare(mixed $a, mixed $b): int
            {
                return [$b[0], $b[1]] <=> [$a[0], $a[1]];
            }
        };
    }
}
