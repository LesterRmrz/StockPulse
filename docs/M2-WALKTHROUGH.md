# M2 walkthrough: Catalog and inventory

M2 turns the M1 skeleton into a working inventory system. This document explains every part, in the order a
request flows through it. Read it next to the code: each section links the files.

**What M2 gives you:**
- Product, category, supplier and warehouse screens, with search, filters and pagination.
- `InventoryService`: the only code that changes stock, with row locks and the ledger.
- Stock adjustments (add, remove, set to a counted number) and transfers between warehouses.
- A product page with stock per warehouse and the full movement history.
- Per-role permissions (policies), and 37 new backend tests.

---

## 1. Try it

```bash
docker compose up -d
docker compose exec php php artisan test        # 77 backend tests
```

Open http://localhost:5173 and sign in as admin (`admin@stockpulse.test` / `password`). Then try this:

1. **Products** → open *USB-C Hub 7-Port*. You'll see stock per warehouse, then its movement history: sales,
   purchase order receipts, returns.
2. Click **Adjust** on MNL-01, add 10 with reason *Found*. The level, the status badge and the history all
   update, and the new ledger row shows who did it and why.
3. **Inventory → New transfer**: from MNL-01 to CEB-01, add the hub, try a quantity larger than what's
   available (the form stops you), then move 5. The transfer appears under **Transfers**, and the product page
   shows a *Transfer out* / *Transfer in* pair.
4. Sign out and use the **Staff** demo button. Products can be viewed and stock adjusted, but the catalog
   can't be edited (no *New product*, *Edit* or *Delete* buttons, and the API returns 403 if you try anyway).

---

## 2. The big picture: one request, start to finish

Every write in M2 follows the same path. Here is *"remove 3 units from MNL-01"*:

```
React (AdjustStockDialog)
  │  useAdjustStock().mutate({ product_id, warehouse_id, mode: 'change', quantity: -3, reason })
  ▼
POST /api/stock-adjustments                        routes/api.php
  │  middleware: auth:sanctum                       → 401 if not signed in
  ▼
StockAdjustmentController::__invoke                 thin: translate HTTP into a service call
  │  StockAdjustmentRequest                         → 422 if the input is invalid
  │  Gate::authorize('adjust', StockLevel::class)   → 403 if the policy says no
  ▼
InventoryService::adjust()                          the business rules
  │  BEGIN
  │  SELECT … FROM stock_levels … FOR UPDATE        lock the row
  │  new quantity < 0 ?  → InsufficientStockException → 422
  │  UPDATE stock_levels SET quantity = …
  │  INSERT INTO stock_movements (…)                the ledger row
  │  COMMIT
  ▼
StockLevelResource                                  shapes the JSON that leaves the server
  ▼
React: invalidate cached lists → every screen showing that stock refetches
```

Each layer has one job:

| Layer | Job | M2 examples |
|---|---|---|
| **Route** | URL → controller, plus middleware | [`routes/api.php`](../backend/routes/api.php) |
| **Form Request** | Validate input | [`app/Http/Requests`](../backend/app/Http/Requests) |
| **Policy** | Who may do it | [`app/Policies`](../backend/app/Policies) |
| **Controller** | Glue: call the right thing, return a resource | [`app/Http/Controllers`](../backend/app/Http/Controllers) |
| **Service** | Rules that change data | [`InventoryService`](../backend/app/Services/InventoryService.php) |
| **Model + scopes** | Data access, reusable filters | `Product::search()`, `StockLevel::withStatus()` |
| **Query class** | A multi-table report | [`WarehouseStockSummary`](../backend/app/Queries/WarehouseStockSummary.php) |
| **Resource** | The JSON shape | [`app/Http/Resources`](../backend/app/Http/Resources) |

There's no repository layer: Eloquent already is the data-access layer. Reusable filters live in **scopes** on
the model, and big reports live in **query classes**. See [PLAN.md, architecture rule 6](PLAN.md#architecture-rules).

---

## 3. InventoryService: the heart of M2

File: [`app/Services/InventoryService.php`](../backend/app/Services/InventoryService.php)

### The rule it protects

```
stock_levels.quantity  ==  SUM(stock_movements.quantity_change)     (for every product + warehouse)
```

`stock_levels` is a fast cache, and `stock_movements` is the history. If code could change one without the
other, they would drift apart and nobody could explain the numbers. So **nothing except InventoryService
changes a quantity**, and it always writes both rows **in one transaction**: either both are saved, or neither.

### Every change is: lock → check → write

```php
private function lock(int $productId, int $warehouseId): StockLevel
{
    // SELECT * FROM stock_levels WHERE product_id = ? AND warehouse_id = ? FOR UPDATE
    return StockLevel::query()->where(…)->lockForUpdate()->first();
}

private function apply(StockLevel $level, int $change, …): StockMovement
{
    $newQuantity = $level->quantity + $change;
    if ($newQuantity < 0) throw new InsufficientStockException($level, $change);

    $level->update(['quantity' => $newQuantity]);
    // + a StockMovement with quantity_change and quantity_after
}
```

**Why the lock?** Two cashiers each sell the last unit at the same moment:

| Without a lock | With `FOR UPDATE` |
|---|---|
| A reads quantity 1 | A locks the row, reads 1 |
| B reads quantity 1 | B tries to lock, **waits** |
| A writes 0 | A writes 0, commits, releases the lock |
| B writes 0 → **sold twice** | B gets the lock, reads **0**, refuses: "Not enough stock" |

`FOR UPDATE` only works inside a transaction (the lock lasts until COMMIT), so every public method wraps its
work in `DB::transaction()`. This is also why tests run on MySQL, not SQLite: SQLite has no row locks.

### The public methods

| Method | Used by | What it does |
|---|---|---|
| `adjust($product, $warehouse, $change, $user, $reason)` | Adjust dialog: Add / Remove | `+5` found, `-2` damaged |
| `recount($product, $warehouse, $counted, $user, $reason)` | Adjust dialog: Set to count | Works out the difference **after** locking |
| `transfer($from, $to, $items, $user, $note)` | New transfer page | One `transfer_out` + one `transfer_in` per product |
| `record(…, MovementType $type, …, $reference)` | M3: sales, receipts, returns | The generic version the others build on |
| `createLevelsForProduct()` / `createLevelsForWarehouse()` | Creating a product / warehouse | Empty (0) rows in every warehouse. No ledger row needed: the sum of zero movements is 0 |

**Why `recount` calculates the difference inside the lock:** say the screen shows 20, someone counts 18 and
types "18". Meanwhile a sale takes one more unit (now 19). If the browser had computed "-2" and sent that, the
result would be 17, which is wrong. Sending "set to 18" and letting the server compute `18 - 19 = -1` under the
lock gives exactly 18.

### Transfers: all or nothing, and no deadlocks

```php
// Lock every row first, always in the same order (product id, then warehouse id)
foreach ($quantities->keys() as $productId) {            // sorted
    foreach ($warehouseIds as $warehouseId) {             // sorted
        $levels[$productId][$warehouseId] = $this->lock($productId, $warehouseId);
    }
}
```

- **All or nothing:** if the 3rd line of a transfer is short, the exception rolls back the whole transaction,
  so lines 1 and 2 don't move either. `InventoryServiceTest::test_a_transfer_is_all_or_nothing` proves it.
- **Lock ordering:** transfer A (MNL→CEB) and transfer B (CEB→MNL) running at the same time could each lock one
  row and wait forever for the other's row: a *deadlock*. Locking in a fixed order (lowest ids first) means
  whoever gets the first row gets them all, and the other simply waits.
- **Transfer numbers** like `TR-1001` need the row's id, so the transfer is created with a temporary random
  number and renamed right after, inside the same transaction.

### When there isn't enough stock

[`InsufficientStockException`](../backend/app/Exceptions/InsufficientStockException.php) is a plain PHP
exception, because the service shouldn't know about HTTP. It has a `render()` method, which Laravel calls if the
exception reaches the HTTP layer. That method returns a **422 in the same shape as a validation error**:

```json
{ "message": "Not enough stock: SP-ELC-0001 has 2 in MNL-01, so you can't remove 5.",
  "errors": { "quantity": ["Not enough stock: …"] } }
```

So the frontend shows it like any other form error, without special handling.

---

## 4. The API

All routes require a signed-in user (`auth:sanctum`). List them with
`docker compose exec php php artisan route:list --path=api`.

| Method & URL | Does | Who |
|---|---|---|
| `GET /products?search=&category_id=&supplier_id=&stock=attention\|out&sort=&direction=&page=` | Paginated catalog with total stock | everyone |
| `GET /products?deleted=1` | Only the soft-deleted products (`onlyTrashed()`) | admin, manager |
| `GET /products/{id}` | One product with stock per warehouse | everyone |
| `POST /products`, `PUT /products/{id}` | Create / edit | admin, manager |
| `DELETE /products/{id}` | Soft delete, **only when stock is 0 everywhere** | admin, manager |
| `POST /products/{id}/restore` | Undo a delete. `->withTrashed()` on the route lets `{product}` find a deleted one | admin, manager |
| `GET /products/{id}/movements?warehouse_id=&type=&page=` | The ledger for one product, newest first | everyone |
| `GET /stock-levels?search=&warehouse_id=&category_id=&status=&sort=urgency\|name\|quantity` | The Inventory table | everyone |
| `PATCH /stock-levels/{id}` | Reorder point / reorder quantity only | admin, manager |
| `POST /stock-adjustments` | `{product_id, warehouse_id, mode: change\|count, quantity, reason}` | everyone |
| `GET /stock-transfers`, `GET /stock-transfers/{id}`, `POST /stock-transfers` | List / show / create transfers | everyone |
| `GET /categories`, `POST`, `PUT`, `DELETE` | All categories (short list, no paging) | write: admin, manager |
| `GET /suppliers?search=&page=`, `POST`, `PUT`, `DELETE` | Suppliers | write: admin, manager |
| `GET /warehouses`, `POST`, `PUT` | Warehouses with stock totals. No delete: deactivate instead | write: admin |

### Things worth noticing in the controllers

- **Never trust a sort column from the URL.** `ProductController` only accepts sorts listed in `SORTS`;
  anything else is a 422. Passing user input straight into `orderBy()` is an injection risk.
- **`->orderBy('id')` as a tie-breaker.** Without it, two products with the same name could swap places between
  page 1 and page 2, so one appears twice and the other never.
- **`withSum('stockLevels as total_quantity', 'quantity')`** adds a `total_quantity` column using a subquery:
  one query for the whole page instead of one per product.
- **`whenHas` / `whenLoaded` / `whenCounted` in resources** leave a key out unless the controller loaded it. The
  list and the detail page share `ProductResource` without loading things they don't need.
- **Eager loading everywhere** (`->with([...])`). M1 turned on `Model::shouldBeStrict()`, which throws an error
  on lazy loading in development, so a forgotten `with()` fails loudly instead of quietly running 25 extra queries.

---

## 5. Who can do what: policies

| | Admin | Manager | Staff |
|---|:-:|:-:|:-:|
| View everything | ✓ | ✓ | ✓ |
| Adjust stock, create transfers | ✓ | ✓ | ✓ |
| Products, categories, suppliers: create / edit / delete | ✓ | ✓ | |
| Reorder point and reorder quantity | ✓ | ✓ | |
| Warehouses: create / edit / deactivate | ✓ | | |

A **policy** is a class of yes/no questions about one model. Laravel finds it by name
(`App\Models\Product` → `App\Policies\ProductPolicy`):

```php
class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }
}
```

Controllers ask it with `Gate::authorize('update', $product)`, which throws a **403** if the answer is no.

**The frontend asks the same policies.** `UserResource` now includes a `can` object:

```json
"can": { "manage_catalog": true, "manage_warehouses": false, "adjust_stock": true, "transfer_stock": true, "edit_reorder_settings": true }
```

It's computed with `$user->can('create', Product::class)`, so the rules live in **one place**. The React code
only uses it to hide buttons (`user?.can.manage_catalog && <Link …>`). The API still checks every request, so
hiding a button is about user experience, not security.

---

## 6. Validation (Form Requests)

| Request | Interesting rules |
|---|---|
| [`ProductRequest`](../backend/app/Http/Requests/ProductRequest.php) | SKU is upper-cased **before** validation (`prepareForValidation`). `Rule::unique(...)->ignore($product)` lets a product keep its own SKU when edited, and it also sees soft-deleted products, like the database's unique index does. Reorder settings are only accepted when creating. |
| [`CategoryRequest`](../backend/app/Http/Requests/CategoryRequest.php) | A parent must be top-level, so categories nest at most two deep and loops are impossible. `after()` runs a check across fields: a category with subcategories can't get a parent. |
| [`StockAdjustmentRequest`](../backend/app/Http/Requests/StockAdjustmentRequest.php) | `Rule::when(mode === 'count', ['min:0'], ['not_in:0'])`: a count can be 0, a change can't. Only active warehouses, and only products that aren't deleted. |
| [`StockTransferRequest`](../backend/app/Http/Requests/StockTransferRequest.php) | `items.*.product_id` validates every line; `distinct` rejects the same product twice; `different:from_warehouse_id` rejects A → A. Errors come back as `items.1.quantity`, so the form can point at the exact line. |

### Delete rules

| Delete a… | Rule | Why |
|---|---|---|
| Product | Only when its stock is 0 everywhere; then **soft** deleted. Restore it from **Products → Deleted products** | Otherwise units silently vanish from reports. History still points at it, and its SKU stays reserved, so a restore never clashes |
| Category | Only without products (deleted ones included); subcategories become top-level | The foreign key is `restrict` |
| Supplier | Only without purchase orders; its products lose their default supplier | POs are financial history |
| Warehouse | Not possible: set it **inactive** | Every movement references it. Inactive warehouses can't be adjusted or used in transfers |

---

## 7. Scopes and the query class

**Scopes** are reusable query pieces defined on the model ([`StockLevel.php`](../backend/app/Models/StockLevel.php)):

```php
public function scopeWithStatus(Builder $query, StockStatus $status): void { … }   // StockLevel::withStatus(StockStatus::Low)
public function scopeNeedsAttention(Builder $query): void { … }                     // quantity <= reorder_point
```

`scopeWithStatus` repeats the rules of `StockStatus::for()` (out / critical / low / ok) as SQL, so the database
can filter 450 rows instead of PHP loading them all. `Product::search($term)` matches the name or the SKU.

**[`WarehouseStockSummary`](../backend/app/Queries/WarehouseStockSummary.php)** builds the Warehouses page totals in
**one** grouped query. Note `SUM(sl.quantity > 0)`: in MySQL a comparison is 1 or 0, so summing it counts the rows.

---

## 8. Frontend

### New folders

```
frontend/src/
├── api/            # One file per area: the requests + TanStack Query hooks
│   ├── products.ts       useProducts, useProduct, useProductMovements, useSaveProduct, useDeleteProduct
│   ├── inventory.ts      useStockLevels, useAdjustStock, useTransfers, useCreateTransfer, invalidateStock
│   └── catalog.ts        categories, suppliers, warehouses
├── components/     # Generic UI: Button, Field/Input/Select, Modal, ConfirmDialog, Pagination,
│                   # SearchInput, StatusBadge, States (Loading/Error/Empty), Table, Busy (Spinner/BusyOverlay)
├── lib/            # format.ts (₱, dates), useUrlFilters, useDebouncedValue, queryClient, types
└── pages/
    ├── products/   ProductsPage, ProductDetailPage, ProductFormPage
    ├── inventory/  InventoryLayout (tabs), StockLevelsPage, TransfersPage, NewTransferPage,
    │               AdjustStockDialog, ReorderSettingsDialog, ProductPicker
    └── CategoriesPage, SuppliersPage, WarehousesPage
```

### TanStack Query: how screens get data

M1 called axios directly in AuthProvider. With many screens sharing data, M2 uses
**[TanStack Query](https://tanstack.com/query)**, which keeps a **cache** of API responses keyed by arrays:

```ts
export function useProducts(filters: ProductFilters) {
  return useQuery({
    queryKey: ['products', 'list', filters],        // the cache key
    queryFn: () => api.get('/products', { params: cleanParams(filters) }).then((res) => res.data),
    placeholderData: keepPreviousData,              // keep page 1 visible while page 2 loads
  })
}
```

In a component:

```tsx
const { data, isPending, isError, error, refetch } = useProducts({ search, page })
if (isPending) return <Loading />
if (isError) return <ErrorState error={error} onRetry={refetch} />
// here TypeScript knows `data` is loaded
```

What you get without writing it yourself: loading and error states, no duplicate requests when two components
ask for the same key, cached results when you go back a page, and retries on network errors (configured in
[`lib/queryClient.ts`](../frontend/src/lib/queryClient.ts): never retry a 4xx).

**Writes are mutations, and they invalidate the cache:**

```ts
export function useAdjustStock() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (data: AdjustmentInput) => api.post('/stock-adjustments', data).then((res) => res.data.data),
    onSuccess: () => void invalidateStock(queryClient),
  })
}
```

`invalidateStock` marks `['products']`, `['stock-levels']`, `['warehouses']` and `['stock-transfers']` stale:
every screen showing stock refetches. Because cache keys are prefixes, invalidating `['products']` hits every
product list, detail page and movement history at once. The `void` means "don't wait": the dialog closes right
away and the numbers refresh a moment later.

### Showing that something is loading

TanStack Query gives every query two flags that matter here:

| Flag | True when | What the screen shows |
|---|---|---|
| `isPending` | The **first** load, nothing to show yet | `<Loading />`: "Loading…" with a spinner |
| `isFetching` | **Any** request is running, including a refresh of data already on screen | (see below) |

`isFetching && !isPending` means "refreshing data that's already visible": after changing a filter or page, or
after a save invalidated the cache. Lists pass it to `TableCard busy={…}`, which wraps the table in
[`BusyOverlay`](../frontend/src/components/Busy.tsx): a moving yellow bar, an **"Updating…"** label and dimmed
rows. The product page and the warehouse cards use the same overlay. Without it, the old numbers sat on screen
for a few seconds after a save and it looked like nothing had happened.

Buttons show their own progress: "Saving…", "Signing out…", and on the login page only the button you clicked
(password, Manager or Staff) shows "Signing in…". The login page tracks *which* one with
`useState<'password' | 'manager' | 'staff' | null>`.

**Sign-out clears the whole cache** (`queryClient.clear()` in `AuthProvider.signedOut`), so the next person on
the same browser never sees the previous user's data.

### Filters live in the URL

[`useUrlFilters`](../frontend/src/lib/useUrlFilters.ts) stores list filters in the query string
(`/products?search=usb&stock=attention&page=2`) instead of `useState`. Refresh, Back and shared links restore the
exact view, and the Warehouses page can link to `/inventory?warehouse_id=1&status=attention`. Changing any filter
resets to page 1.

Because filters live in the URL, the sign-in redirect must keep them: `RequireAuth` remembers
`location.pathname + location.search` (not just the path), so opening a filtered link while signed out brings you
back to that exact view after signing in.

Search boxes update the URL on every keystroke but **debounce** the request:

```ts
const search = useDebouncedValue(filters.search)   // waits until typing pauses for 300 ms
```

### Patterns used in the dialogs and forms

- **Render dialogs only while open:** `{adjusting && <AdjustStockDialog target={adjusting} … />}`. Each time it
  opens, its `useState` starts fresh, so there are no leftover values from last time and no effect to reset them.
- **Split a page into "loader" and "content":** `ProductDetailPage` handles loading and errors, then renders
  `<ProductDetail product={product} />`, where `product` is guaranteed to exist. The same idea with
  `key={id}` in `ProductFormPage` gives each product a fresh form.
- **One state object for a form, with a typed setter:**

  ```ts
  function set<K extends keyof ProductInput>(key: K) {
    return (value: ProductInput[K]) => setForm((previous) => ({ ...previous, [key]: value }))
  }
  ```

  `K extends keyof ProductInput` means K must be one of the form's field names, and `ProductInput[K]` is that
  field's type. So `set('cost_price')` only accepts a string, and `set('is_active')` only a boolean.
- **Server errors under the right field:** [`fieldErrors(error)`](../frontend/src/lib/api.ts) turns Laravel's 422
  into `{ sku: "The SKU has already been taken." }`, and each `<Field error={errors.sku}>` shows its own.
- **[`Modal`](../frontend/src/components/Modal.tsx)** uses the browser's `<dialog>` and `showModal()`, which give
  you the backdrop, Esc to close and focus kept inside the dialog, all for free.
- **The transfer product picker** searches **stock levels in the source warehouse** (not products), so it can
  show "14 available" for each result without a second request.

### Money

Prices arrive as strings (`"86.50"`) and are only turned into numbers to display them
(`formatMoney` → `₱86.50`). Floats can't store most decimals exactly (`0.1 + 0.2 === 0.30000000000000004`), so
money is never added up as floats in the browser.

---

## 9. Tests

40 new tests (77 in total). They run against MySQL, like M1.

| File | Proves |
|---|---|
| [`InventoryServiceTest`](../backend/tests/Feature/InventoryServiceTest.php) (9) | Adjustments write exactly one ledger row; stock can't go negative and nothing is written when refused; recounts record only the difference; transfers write an out/in pair and are all-or-nothing; the row is locked (`FOR UPDATE`) before the update |
| [`StockApiTest`](../backend/tests/Feature/StockApiTest.php) (11) | Adjust and count through the API; 422 when there isn't enough; inactive warehouses refused; transfers and their validation; only managers edit reorder settings; status filters; deleted products disappear; inactive products stay, flagged |
| [`ProductApiTest`](../backend/tests/Feature/ProductApiTest.php) (12) | Search and filters; new products get a row in every warehouse; unique SKUs; staff can view but not edit; delete rules; listing and restoring deleted products (staff can't); movement history; the `can` object |
| [`CatalogApiTest`](../backend/tests/Feature/CatalogApiTest.php) (8) | Unique slugs; two-level nesting; delete rules for categories and suppliers; only admins manage warehouses; warehouse totals |

`TestCase::assertLedgerMatches()` runs the inventory-rule SQL from [DATABASE.md](DATABASE.md#the-inventory-rule),
and most stock tests end with it.

> A true "two requests at the same instant" test needs two database connections running in parallel, which
> PHPUnit doesn't do. Instead, `test_changes_lock_the_stock_row_first` checks that the `FOR UPDATE` query runs
> before the `UPDATE`. The simulator in M4 places orders concurrently and will exercise the locks for real.

---

## 10. Dev environment changes

The API was slow (2–3 s per request) because Laravel reads thousands of PHP files on every request, and the
project folder is shared from Windows into the Linux container, where each file read is slow. Three changes in
[`docker-compose.yml`](../docker-compose.yml) brought it down to **about 0.15 s** (and the test suite from ~36 s to ~15 s):

- **`vendor/` lives in a Docker volume** (`backend_vendor`). The ~6,800 Composer package files are read from the
  container's own fast storage. Your code (`app/`, `routes/`, `config/`...) stays in the shared folder, so edits
  still apply immediately. This was the big win: from ~2.6 s to ~0.15 s.
- `PHP_CLI_SERVER_WORKERS: 4`: `php artisan serve` handled **one request at a time**, so a page loading three
  lists waited for each in turn. Now they run in parallel.
- [`docker/php/dev.ini`](../docker/php/dev.ini) turns on OPcache for the dev server (it's off by default for
  the CLI version of PHP) and re-checks changed files at most every 2 seconds.

What the volume changes for you:
- Packages are installed with `docker compose run --rm php composer install`, as before; they just land in the
  volume instead of `backend/vendor`.
- `backend/vendor` on your PC is no longer updated. Your editor uses it for autocomplete, so after adding a
  package, either run `composer install` on your PC too (needs PHP) or accept slightly stale suggestions.
- `docker compose down -v` deletes the volume along with the database; run `composer install` again afterwards.

The frontend already did the same with `node_modules`. Production (M6) runs from built images, without shared
folders, so none of this applies there.

---

## 11. Deliberately left for later

| Not in M2 | Comes in |
|---|---|
| Purchase orders and receiving (will call `InventoryService::record()` with `MovementType::Receipt`) | M3 |
| Sales orders, cancellations and refunds (sales deduct at placement, cancellations write `return`) | M3 |
| "Suggest PO" from low stock | M3 |
| Live updates when stock changes (`StockLevelChanged` broadcasts) | M4 |
| Blocking destructive actions for demo users | M6 |
| Frontend component tests (Vitest) | when the screens settle |

---

## 12. Try it yourself

```bash
# Watch the lock happen: run this in tinker, then try to adjust the same product in the browser.
# The browser request waits until you exit tinker (the transaction is still open).
docker compose exec php php artisan tinker
>>> DB::beginTransaction();
>>> App\Models\StockLevel::where('product_id', 1)->where('warehouse_id', 1)->lockForUpdate()->first();
>>> // now click Adjust → Save in the browser for USB-C Hub at MNL-01, and watch it hang
>>> DB::rollBack();   // the browser request completes (MySQL gives up on its own after 50 s)

# Try to break the ledger: this is refused by the model (the ledger is append-only)
>>> App\Models\StockMovement::latest('id')->first()->update(['quantity_change' => 999]);
```

Exercises:
1. ~~Add a supplier dropdown to the Products toolbar.~~ Done: it's the Supplier filter on the Products page.
   Next step: add the same dropdown to the Inventory page (the API needs a `supplier_id` filter in
   `StockLevelController` first).
2. Add an `InventoryService` test proving that a transfer with the same product listed twice merges the lines.
3. Make `StockLevelPolicy::adjust` deny staff for warehouses they don't work at. (Hint: you'd need a
   `user_warehouse` table first.)
