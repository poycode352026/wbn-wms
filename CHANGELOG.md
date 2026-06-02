# WBN-WMS — Changelog

---

## v1.2.0 — 2026-06-02

### Features

#### Employee Portal
- Employee portal (mobile-first) — dashboard, riwayat pengambilan, profil
- Mandatory item tracking: tampilkan item overdue di dashboard employee
- Multi-bahasa item name: EN / ID / 中文 di halaman employee
- "All good" empty state ketika semua item sudah terpenuhi
- Submit request dari dashboard (personal + LV-based items)
- APAR pre-fill: jika `lv_id` null, sistem fallback ke kendaraan employee

#### Goods Issue — Multi-Warehouse
- Per-item `item_warehouse_id`: 1 GI bisa sourcing dari berbagai gudang
- `warehouses.sort_order`: GNR=1, IT=2, KM17=3 (proximity priority)
- `autoSelectWarehouse()`: otomatis pilih gudang terdekat yang stoknya mencukupi
- Variant dropdown: tampilkan semua variant yang ada stok di gudang manapun
- Insufficient stock warning per baris item

#### Operator Portal (Mobile)
- `OperatorLayout.vue`: layout mobile tersendiri mirip employee portal
- `/operator/scan` — daftar GI yang diasignkan ke operator/WH admin
- `/operator/scan/{gi}` — detail picking + **lokasi/rack** per item + checklist + upload foto + submit
- QR barcode mini di header picking page
- Scan input: operator scan barcode GI → langsung buka halaman picking
- Progress bar picking (centang per item)
- Foto bukti wajib sebelum submit

#### Multi-Role User
- Kolom `extra_roles` JSON di tabel `users`
- WH Admin bisa di-assign akses operator portal via Users management (checkbox)
- `CheckRole` middleware: cek primary role + extra_roles
- `User::hasRole()` helper

#### Auth
- Employee login: redirect langsung ke `/portal` (skip `intended()`)
- Operator login: redirect langsung ke `/operator/scan`
- WH Admin dengan extra operator: tetap ke dashboard, ada menu "Scan & Pickup" di sidebar

### Bug Fixes
- Notification click tidak berfungsi: `'url'` → `'route'` di notification data
- Picking form qty `.00`: `parseFloat()` untuk strip desimal tidak perlu
- WH Admin bisa self-assign sebagai operator picking
- QR barcode bisa di-download (PNG dengan filename GI number)
- Picking form tampilkan lokasi/rack per item

### Schema Changes
- `warehouses.sort_order` SMALLINT default 99
- `goods_issue_items.item_warehouse_id` FK → warehouses (nullable)
- `users.extra_roles` JSON nullable

---

## v1.1.0 — 2026-05-31

### Features
- Goods Receipt module: create, inspect, approve, stok update
- Employee management: import, login creation, LV assignment
- Vehicle (LV) management
- Cooldown log tracking (per employee / per LV)
- Mandatory PPE tracking dengan cooldown period
- Employee request system (dari portal ke GI)
- Notification system: WmsNotification model, bell icon, mark-as-read
- Item categories: 7 kategori standar, global (tidak per gudang)
- SKU auto-generate + photo upload di item variants
- Item part number auto-generate

### Schema Changes
- `goods_receipts`, `goods_receipt_items`, `goods_receipt_photos`
- `goods_issue_photos`
- `employees` + `employee_requests` + `employee_request_items`
- `vehicles` (LV) dengan driver assignment
- `cooldown_logs`
- `wms_notifications`
- `item_categories` global
- `items.is_mandatory`, `items.cooldown_days`, `items.cooldown_track_by`

---

## v1.0.0 — 2026-05-10

### Initial Release
- Auth: Laravel Breeze, role-based middleware
- Master data: Gudang, Lokasi/Rak, Department, User, Item Master
- Stock input: stok awal per variant per lokasi
- Goods Issue: full approval flow (draft → pending → approved → assigned → picking → completed)
- Multi-level approval: Dept Manager → WH Manager → Supervisor
- Dashboard: stat cards, transaction chart, low stock alert
- i18n: 3 bahasa (EN / 中文 / ID)
- Dark mode + Light mode
