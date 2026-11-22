# M1 walkthrough: Foundation

This explains every part of what milestone M1 added, in the order a request flows through the system.
Read it next to the code: each section links the files.

**What M1 gives you:** a MySQL schema for the whole inventory domain, 90 days of realistic demo data,
cookie-based login with roles, and the React app shell (sign-in page + sidebar layout) in the Black & Yellow design.

---

## 1. Running it

```bash
docker compose up -d
docker compose exec php php artisan migrate:fresh --seed   # rebuild DB + demo data (~1 min)
docker compose exec php php artisan test                   # 33 backend tests
```

Open http://localhost:5173 and sign in:

| Account | Email | Password | Notes |
|---|---|---|---|
| Admin | `admin@stockpulse.test` | `password` | Your own account |
| Demo Manager | `manager@stockpulse.test` | `password` | Also via the "Manager" demo button |
| Demo Staff | `staff@stockpulse.test` | `password` | Also via the "Staff" demo button |

The password comes from `SEED_USER_PASSWORD` (default `password`). Change it before deploying anywhere public.

---

## 2. The big picture

```
Browser ──► Vite dev server :5173 ──(proxy /api, /sanctum)──► Laravel :8000 ──► MySQL :3306
            serves the React app                              php artisan serve    stockpulse (dev)
                                                                                   stockpulse_testing (tests)
```

The browser only ever talks to `localhost:5173`. Vite forwards `/api/*` and `/sanctum/*` to the Laravel
container ([vite.config.ts](../frontend/vite.config.ts)). To the browser, the app and the API are the
**same origin**, which is why:

- there is no CORS configuration,
- the session cookie set by Laravel "belongs" to localhost:5173 and is sent automatically.

In production (M6) Caddy plays the same role as Vite's proxy here.

---

## 3. Database (migrations)

Files: [`backend/database/migrations/2026_11_18_*`](../backend/database/migrations). Full reference with the
ER diagram: [DATABASE.md](DATABASE.md).

A migration is a PHP class with `up()` (create/change tables) and `down()` (undo it). Laravel records
which ones ran in the `migrations` table, so `php artisan migrate` only runs new ones.

The files are numbered so **every foreign key points at a table that already exists**: users →
categories → suppliers → warehouses → products → stock_levels → stock_movements → transfers →
purchase orders → customers → orders.

Decisions worth knowing:

| Decision | Where | Why |
|---|---|---|
| Money is `DECIMAL(10,2)` | products, orders, POs | `FLOAT` can't store 0.10 exactly; money must be exact |
| `role` is a string, not a MySQL `ENUM` | `000001_add_role_to_users` | Adding a role later is a code change in `App\Enums\Role`, no `ALTER TABLE` |
| `stock_levels.quantity` is `UNSIGNED` | `000006` | MySQL itself refuses negative stock, even if PHP code has a bug |
| Unique `(product_id, warehouse_id)` | `000006` | Exactly one stock row per product per warehouse, which is the row we'll lock in M2 |
| `stock_movements` has `created_at` only | `000007` | Ledger rows are never updated |
| `nullableMorphs('reference')` | `000007` | Creates `reference_type` + `reference_id`: one column pair that can point at an order, a PO or a transfer |
| `order_items` copies `unit_price` and `unit_cost` | `000011` | Changing a product's price later must not rewrite past revenue |
| `placed_at` separate from `created_at` | `000011` | Seeded orders were *placed* 90 days ago but *inserted* today |
| Composite indexes | `000007`, `000011` | Match the queries the dashboard and history pages will run |

Until the first deploy (M6), we edit these M1 migrations directly and run `migrate:fresh`.
After deploying, every schema change becomes a **new** migration.

---

## 4. Enums

Files: [`backend/app/Enums`](../backend/app/Enums)

PHP 8.1 *backed enums* give each status column a fixed list of allowed values:

```php
enum MovementType: string {
    case Receipt = 'receipt';
    case Sale = 'sale';
    ...
}
```

The database stores the string (`'sale'`). Eloquent casts it to the enum when you read it, so
`$movement->type === MovementType::Sale` and typos fail loudly instead of silently saving `'sael'`.

`StockStatus` is different: it is **never stored**. `StockStatus::for($quantity, $reorderPoint)` computes
OK / Low / Critical / Out on the fly, so it can never be out of date.

---

## 5. Models

Files: [`backend/app/Models`](../backend/app/Models)

A model is a PHP class for one table. Three things in each:

1. **`#[Fillable([...])]`**: which columns can be mass-assigned with `Model::create([...])`. Anything else is
   rejected, so a request can't sneak in `role=admin`.
2. **`casts()`**: converts columns when reading them: `'decimal:2'` (money stays an exact string like `"39.00"`),
   `'datetime'` (Carbon dates), enum classes.
3. **Relationships**: methods that describe the foreign keys, so you can write
   `$product->stockLevels`, `$order->items`, `$movement->reference`.

| Relationship | Example | Meaning |
|---|---|---|
| `belongsTo` | `Product::category()` | This row has the foreign key |
| `hasMany` | `Category::products()` | Other rows point at this one |
| `belongsToMany` via `stock_levels` | `Product::warehouses()` | Many-to-many, with `quantity` on `->pivot` |
| `morphTo` / `morphMany` | `StockMovement::reference()` / `Order::stockMovements()` | The polymorphic link |

Special cases:

- **`StockMovement` is immutable.** Its `booted()` hook throws if anyone calls `update()` or `delete()` on it.
  Mistakes are fixed with a new `adjustment` row, like an accountant's ledger.
- **`Product` uses `SoftDeletes`.** "Deleting" sets `deleted_at`, so orders and movements that reference it stay valid.

### AppServiceProvider

File: [`backend/app/Providers/AppServiceProvider.php`](../backend/app/Providers/AppServiceProvider.php). It runs on every request and sets three things:

- **Morph map**: `reference_type` stores `'order'` instead of `'App\Models\Order'`. Renaming a class later won't break old rows.
- **`Model::shouldBeStrict()`** outside production: lazy loading inside a loop (the N+1 query problem), assigning a
  non-fillable attribute, or reading a column you didn't select **throws an error** in development instead of
  quietly being slow or wrong.
- **The `login` rate limiter**: at most 5 attempts per minute per email + IP address.

---

## 6. Seeders: the demo data

Files: [`backend/database/seeders`](../backend/database/seeders), settings in
[`backend/config/stockpulse.php`](../backend/config/stockpulse.php)

`DatabaseSeeder` runs, in order:

| Seeder | Creates |
|---|---|
| `UserSeeder` | Admin, Demo Manager, Demo Staff |
| `WarehouseSeeder` | MNL-01 Manila, CEB-01 Cebu, DVO-01 Davao |
| `CatalogSeeder` | 10 categories, 20 suppliers, 150 hand-named products with realistic prices |
| `CustomerSeeder` | 500 customers (Faker) |
| `InventoryHistorySeeder` | 90 days of orders, purchase orders and stock movements |

### How `InventoryHistorySeeder` works

It doesn't generate random rows. It **simulates the business** over 90 days:

1. **Demand model.** Each product gets a popularity (a few best sellers, a long tail). From that and the supplier's
   lead time it sets each product+warehouse's `reorder_point` (enough stock to last the lead time + 3 days) and
   `reorder_qty` (about 30 days of stock), plus an opening balance (`adjustment` movement, "Opening balance").
2. **Event queue.** A priority queue (`SplHeap`) holds future events, always handing back the earliest one:
   - `day`: schedule today's orders at realistic hours (busier evenings, more on weekends, growing over time)
   - `order`: pick a warehouse, 1-4 products and quantities. Each line in stock → decrement stock, write a `sale`
     movement. Out of stock → that line is a lost sale. 3% of orders get cancelled a few hours later (`cancel` event → `return` movement).
   - `reorder` (every night): every product that fell to its reorder point today goes on a purchase order,
     one PO per supplier + warehouse
   - `arrival`: after the lead time (80% on time, some late) the PO is received → `receipt` movements
3. **Bulk insert.** Everything is collected in PHP arrays and inserted in chunks of 1,000 inside one transaction.
   About 14,000 orders and 25,000 movements take ~4 seconds.

Because events are processed strictly in time order, every movement's `quantity_after` is correct, and each final
`stock_levels.quantity` equals the sum of its movements.

**Why not call InventoryService for every sale?** The seeder builds a starting dataset on empty tables, and
doing it in memory is ~100x faster. At *runtime* (M2 onward) every stock change goes through InventoryService.

`fake()->seed(2026)` fixes the random generator: every run makes the same choices (only the dates shift with today's date).

---

## 7. Authentication (Laravel Sanctum, SPA mode)

Files: [`AuthController`](../backend/app/Http/Controllers/AuthController.php),
[`routes/api.php`](../backend/routes/api.php), [`bootstrap/app.php`](../backend/bootstrap/app.php),
[`LoginRequest`](../backend/app/Http/Requests/LoginRequest.php), [`UserResource`](../backend/app/Http/Resources/UserResource.php)

Sanctum has two modes: API **tokens** (for mobile apps and third parties) and **SPA cookie** auth (for your own
frontend on the same site). We use the cookie mode because the session cookie is **HttpOnly**: JavaScript can't
read it, so an XSS bug can't steal it. A token in `localStorage` can be stolen that way.

### The login flow

```
React                                   Laravel
  │ GET /sanctum/csrf-cookie  ───────────►  sets cookie XSRF-TOKEN (readable by JS)
  │ POST /api/login {email, password}
  │   header X-XSRF-TOKEN: <cookie value> ►  1. CSRF check: header must match the session's token
  │                                          2. LoginRequest validates the input (422 if invalid)
  │                                          3. throttle:login: max 5/min (429 after that)
  │                                          4. Auth::attempt() checks the bcrypt hash
  │                                          5. session()->regenerate(): new session id
  │  ◄─────────────────────────────────────  sets cookie stockpulse-session (HttpOnly)
  │                                          returns {"data": {id, name, email, role, ...}}
  │ GET /api/user (cookie sent automatically) ► auth:sanctum middleware → 200 or 401
```

Pieces that make it work:

- `$middleware->statefulApi()` in `bootstrap/app.php` turns on sessions + CSRF for `/api` requests coming from
  the domains listed in `SANCTUM_STATEFUL_DOMAINS` (`.env`): `localhost:5173`.
- axios is created with `withCredentials: true` (send cookies) and `withXSRFToken: true` (copy the
  `XSRF-TOKEN` cookie into the `X-XSRF-TOKEN` header).
- **CSRF** (cross-site request forgery): another website could make your browser POST to StockPulse with your
  cookie attached, but it can't read your `XSRF-TOKEN` cookie to put it in the header. So the request is rejected.
- **Session regeneration** on login prevents *session fixation* (an attacker planting a known session id before you log in).
- **Same error for wrong email or wrong password**, so the form can't be used to find out which emails have accounts.
- **`UserResource`** decides exactly which fields are sent: never the password hash.

### Demo login

`POST /api/demo-login {role: "manager" | "staff"}` logs in the account with `is_demo = true` and that role.
Admin is not allowed (validation), and a real (non-demo) manager can't be logged into this way (404).

### Roles

[`EnsureUserHasRole`](../backend/app/Http/Middleware/EnsureUserHasRole.php) is registered as `role`:

```php
Route::post('/simulator/start', ...)->middleware('role:admin,manager');
```

Anyone else gets `403`. Finer rules ("staff can adjust stock but not delete products") will be **policies**, added
with each CRUD screen from M2.

---

## 8. Tests

Files: [`backend/tests`](../backend/tests). Run: `docker compose exec php php artisan test`

Tests run against a **separate MySQL database, `stockpulse_testing`** ([phpunit.xml](../backend/phpunit.xml)),
not SQLite. M2 relies on MySQL row locking (`SELECT … FOR UPDATE`), which SQLite doesn't do, so tests must use
the same engine as production. `RefreshDatabase` migrates it once and wraps each test in a transaction that's rolled back.

| Test | Proves |
|---|---|
| `AuthTest` (9) | Login, wrong password, validation, 401 for guests, logout, demo login rules, rate limiting |
| `RoleMiddlewareTest` (3) | `role:` lets the right roles through, 403 otherwise |
| `StockMovementTest` (3) | The ledger can't be edited or deleted |
| `SchemaTest` (8) | Relationships and database rules: nested categories, delete behaviour, one stock row per product/warehouse, quantity can't go negative, unique SKUs, soft deletes, the `order` morph alias |
| `DatabaseSeederTest` (4) | Seed counts, **stock levels equal the ledger**, order totals add up, received POs wrote receipts |
| `StockStatusTest` (6) | OK / Low / Critical / Out boundaries |

`tests/TestCase.php::fromSpa()` adds the `Origin`/`Referer` headers the browser would send, so Sanctum treats
the test request like the real SPA.

The frontend has no automated tests yet. It's checked by `npm run lint` (ESLint, including the hooks rules)
and `npm run build` (which runs the TypeScript compiler first). Component tests can be added with Vitest
once there are real screens.

The test database is created by [`docker/mysql/init.sql`](../docker/mysql/init.sql) when the MySQL volume is
first created. On an existing volume, create it once:

```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS stockpulse_testing"
```

---

## 9. Frontend

Files: [`frontend/src`](../frontend/src)

```
src/
├── main.tsx            Entry: <AuthProvider> around <RouterProvider>
├── router.tsx          Route tree
├── navigation.ts       Sidebar items (also generates the placeholder routes)
├── index.css           Tailwind + design tokens
├── lib/
│   ├── api.ts          Configured axios client + error messages
│   └── types.ts        TypeScript shapes of API responses
├── auth/
│   ├── auth-context.ts Context + useAuth() hook
│   ├── AuthProvider.tsx Holds the user, login/logout/demoLogin
│   └── guards.tsx      <RequireAuth> and <GuestOnly>
├── layouts/AppLayout.tsx  Sidebar + content area (drawer on mobile)
├── components/         Logo, PageHeader
└── pages/              LoginPage, DashboardPage, ComingSoonPage, NotFoundPage
```

### Design tokens → Tailwind

[`index.css`](../frontend/src/index.css) declares the palette from the design once:

```css
@theme {
  --color-surface: #141416;
  --color-accent: #facc15;
  ...
}
```

Tailwind v4 generates utilities from each `--color-*`: `bg-surface`, `text-accent`, `border-line`, and so on.
Components never hard-code hex values, so a light theme later only redefines these variables.

### Auth state

- On page load, `AuthProvider` calls `GET /api/user`. The session cookie is HttpOnly, so asking the server is
  the only way to know if we're logged in. While it waits, `status` is `'loading'`.
- `login()` = fetch CSRF cookie → `POST /api/login` → store the returned user.
- An axios **interceptor** sets the user to `null` on any `401`, so an expired session drops you to the login page.

### Route guards

```
/login            <GuestOnly>   logged in? → redirect into the app
/, /products, …   <RequireAuth> guest? → redirect to /login (remembering where you were going)
                    └ <AppLayout> sidebar + <Outlet/> (the page)
```

After a successful login, the page doesn't navigate itself: the user becomes `authenticated`, `<GuestOnly>`
re-renders and redirects. One source of truth, no race between two redirects.

Pages for later milestones render `ComingSoonPage`, generated from `navigation.ts`. Each milestone replaces its
routes with real pages.

---

## 10. Things deliberately left for later

| Not in M1 | Comes in |
|---|---|
| `InventoryService` (the only code allowed to change stock at runtime) | M2 |
| Policies per model, CRUD screens | M2 |
| `notifications`, `activity_log` tables | M3 |
| Reverb / WebSockets | M4 |
| `daily_product_stats` rollup, real dashboard | M5 |
| Blocking destructive actions for demo users, nightly reset | M6 |

---

## 11. Try it yourself

```bash
# Explore the data with Laravel's REPL
docker compose exec php php artisan tinker
>>> App\Models\Product::where('sku', 'SP-ELC-0001')->first()->stockLevels
>>> App\Models\StockMovement::latest('id')->first()->reference      // the order that caused it
>>> App\Models\StockMovement::first()->delete()                     // LogicException: immutable

# List every API route
docker compose exec php php artisan route:list --path=api
```
