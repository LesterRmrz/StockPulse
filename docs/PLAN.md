# StockPulse — Project Plan

> A full-stack enterprise e-commerce dashboard and inventory manager built with React, TypeScript, Tailwind CSS, and Laravel. Features real-time stock analytics, relational database architecture, and containerized deployment with Docker.

## Decisions

| Topic | Decision |
|---|---|
| Order source | Built-in **order simulator** (no real storefront) |
| Locations | **Multi-warehouse** |
| Product variants | **No** (one product = one SKU) |
| Purpose | **Portfolio** project — public demo mode required |
| Deployment | Single **VPS** running Docker Compose |

## Visual design

**Theme: Black & Yellow.** Dark is the default; a light theme (black sidebar, light content) is optional.
Design canvas: https://claude.ai/artifact/Q8J3ELtgWdTVGMcmKhjFCv

| Token | Dark | Light |
|---|---|---|
| Base | `#0A0A0B` | `#F5F5F4` |
| Surface (cards) | `#141416` | `#FFFFFF` |
| Border | `#26262A` | `#E4E4E7` |
| Text | `#F4F4F5` | `#0A0A0B` |
| Muted text | `#A1A1AA` | `#52525B` |
| Accent | `#FACC15` (text on it: `#0A0A0B`) | same |
| Success | `#4ADE80` | `#15803D` |
| Warning (low stock) | `#FB923C` | `#C2410C` |
| Danger (critical) | `#F87171` | `#B91C1C` |

Rules:
- Yellow is used sparingly: the primary button, active nav item, live indicators and highlighted data points.
- Never yellow text on a light background; in light mode yellow is only a fill with black text on it.
- Warnings are **orange**, not yellow, so they don't blend with the brand color.
- Fonts: **Space Grotesk** for headings and big numbers, **Inter** for everything else (tabular numbers on).

## Schema

### Core reference tables

| Table | Key columns |
|---|---|
| `users` | name, email, password, `role` (admin / manager / staff) |
| `categories` | name, slug, `parent_id` (nullable, allows nested categories) |
| `suppliers` | name, contact_name, email, phone, `lead_time_days` |
| `warehouses` | `code` (unique, e.g. `MNL-01`), name, address, is_active |
| `products` | `sku` (unique), name, description, `category_id`, `default_supplier_id`, `cost_price`, `sell_price`, image_path, is_active, soft deletes |

### Inventory core

| Table | Key columns |
|---|---|
| `stock_levels` | `product_id`, `warehouse_id`, `quantity`, `reorder_point`, `reorder_qty`. Each product+warehouse pair is unique; quantity can't go below 0. |
| `stock_movements` | `product_id`, `warehouse_id`, `quantity_change` (+ or −), `quantity_after`, `type` (receipt / sale / adjustment / transfer_in / transfer_out / return), `reference_type` + `reference_id` (polymorphic link to the PO, order or transfer that caused it), `unit_cost`, `user_id`, note, created_at. **Append-only.** |
| `stock_transfers` + `stock_transfer_items` | from_warehouse, to_warehouse, status, user; per item: product and qty |

### Purchasing and sales

| Table | Key columns |
|---|---|
| `purchase_orders` | `po_number`, supplier, destination warehouse, `status` (draft → ordered → partially_received → received / cancelled), expected_at, received_at |
| `purchase_order_items` | product, `qty_ordered`, `qty_received`, unit_cost |
| `customers` | name, email, city, country (simulated) |
| `orders` | `order_number`, customer, fulfilling warehouse, `channel` (web / marketplace / pos), `status` (pending → paid → fulfilled / cancelled / refunded), totals, placed_at |
| `order_items` | product, quantity, `unit_price`, `unit_cost` (copied at sale time so profit stays accurate if prices change later) |

### Analytics and ops

| Table | Purpose |
|---|---|
| `daily_product_stats` | Rollup per date, product and warehouse: units_sold, revenue, cogs, closing_qty. Filled by a nightly job so dashboard charts don't scan raw orders. |
| `notifications` | Laravel's built-in table, used for low-stock alerts |
| `activity_log` | Audit trail (e.g. spatie/laravel-activitylog) |

## Architecture rules

1. **All stock changes go through one `InventoryService`.** Nothing else writes to `stock_levels` directly. Each change writes the ledger row and updates the level in a single DB transaction.
2. **Prevent overselling with row locks.** Fulfillment locks the stock row (`SELECT … FOR UPDATE`) before deducting. The simulator places orders concurrently, so this is exercised for real.
3. **The simulator uses the real code path.** A queued job creates orders through the same `OrderService` a human would use. It picks products weighted by popularity, chooses a warehouse with stock, and backorders or cancels when nothing is available. Admins can start, stop and set its speed from the UI.
4. **Real-time events through Laravel Reverb:**
   - `StockLevelChanged` and `LowStockDetected` on a `warehouse.{id}` channel
   - `OrderPlaced` on a `dashboard` channel

   The frontend listens with Laravel Echo and updates KPIs, tables and alert toasts live.
5. **Demo mode.** "Log in as Manager / Staff" demo buttons, destructive actions blocked for demo users, and a nightly database reset.

## VPS deployment

- **One VPS with a production Docker Compose file** running:
  - Caddy (automatic HTTPS; serves the built React app and proxies `/api` and the WebSocket)
  - php-fpm
  - queue worker
  - scheduler
  - Reverb
  - MySQL
- **GitHub Actions:** run tests and lint → build images and push to GHCR → SSH to the VPS, pull and restart.
- **Ops basics:**
  - Daily `mysqldump` backup
  - `.env` kept only on the server, never in the repo
  - A domain name

## Milestones

### M1 — Foundation
- [ ] Rename the project to StockPulse; write the ER diagram
- [ ] Migrations and models with relationships
- [ ] Seeders: ~3 warehouses, ~10 categories, ~150 products, ~20 suppliers, 90 days of past orders
- [ ] Sanctum SPA auth, roles and policies
- [ ] App shell: sidebar layout, routing, auth pages

### M2 — Catalog and inventory
- [ ] Product, category, supplier and warehouse CRUD with search, filter and pagination
- [ ] `InventoryService` and the stock ledger
- [ ] Stock adjustment and transfer screens
- [ ] Product detail page: stock per warehouse and movement history
- [ ] Feature tests for stock correctness

### M3 — Purchasing and sales
- [ ] Purchase order lifecycle, including partial receiving
- [ ] Order list and detail pages, fulfillment and refunds
- [ ] Low-stock detection → "suggest PO" action

### M4 — Simulator and real-time
- [ ] Simulator job, its controls, and Docker services for the queue worker and scheduler
- [ ] Reverb container, broadcast events, Echo on the frontend
- [ ] Live activity feed and alert toasts

### M5 — Analytics
- [ ] Nightly `daily_product_stats` rollup job
- [ ] Dashboard: KPI cards, revenue trend, top sellers, stock value by warehouse, turnover, days of stock left
- [ ] CSV export

### M6 — Ship it
- [ ] Production Dockerfiles, Caddy config, prod Compose file
- [ ] CI/CD pipeline; deploy to the VPS with a domain and HTTPS
- [ ] Demo mode and nightly reset
- [ ] README with screenshots, GIF, architecture diagram and live demo link
