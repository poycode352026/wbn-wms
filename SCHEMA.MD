# WBN-WMS — Database Schema

> MySQL · Laravel 11 Migrations
> Scope aktif: Gudang KM-17

---

## Daftar Tabel

1. users
2. departments
3. warehouses
4. locations (rak)
5. employees
6. vehicles (LV)
7. item_categories
8. items
9. item_variants
10. item_uoms
11. stock_ledgers (stok per variant per lokasi)
12. goods_issues
13. goods_issue_items
14. goods_issue_approvals
15. goods_receipts
16. goods_receipt_items
17. notifications
18. cooldown_logs

---

## 1. users
```sql
id              bigint PK
name            varchar(100)
email           varchar(100) unique
password        varchar(255)
role            enum('super_admin','admin_dept','manager_dept','warehouse_manager','supervisor','operator')
department_id   bigint FK → departments.id (nullable, tidak perlu untuk super_admin)
warehouse_id    bigint FK → warehouses.id (nullable)
is_active       boolean default true
last_login_at   timestamp nullable
timestamps
```

---

## 2. departments
```sql
id              bigint PK
code            varchar(20) unique
name            varchar(100)
warehouse_id    bigint FK → warehouses.id
is_active       boolean default true
timestamps
```

---

## 3. warehouses
```sql
id              bigint PK
code            varchar(20) unique   -- KM-17, KM-28
name            varchar(100)
location        varchar(255)
is_active       boolean default true
timestamps
```

---

## 4. locations (Rak/Lokasi dalam gudang)
```sql
id              bigint PK
warehouse_id    bigint FK → warehouses.id
code            varchar(50) unique   -- contoh: A-01-01
name            varchar(100)
description     text nullable
is_active       boolean default true
timestamps
```

---

## 5. employees
```sql
id              bigint PK
employee_id     varchar(50) unique   -- ID Karyawan
name            varchar(100)
department_id   bigint FK → departments.id
position        varchar(100) nullable
is_active       boolean default true
timestamps
```
> Import via Excel oleh Admin Dept

---

## 6. vehicles (LV — Light Vehicle)
```sql
id              bigint PK
lv_number       varchar(50) unique   -- LV-001, LV-002
name            varchar(100)
type            varchar(100) nullable
department_id   bigint FK → departments.id nullable
is_active       boolean default true
timestamps
```
> Import via Excel oleh Admin Dept

---

## 7. item_categories
```sql
id              bigint PK
warehouse_id    bigint FK → warehouses.id
code            varchar(20) unique
name_id         varchar(100)   -- Bahasa Indonesia
name_en         varchar(100)   -- English
name_zh         varchar(100)   -- 中文
is_active       boolean default true
timestamps
```

---

## 8. items
```sql
id                  bigint PK
warehouse_id        bigint FK → warehouses.id
category_id         bigint FK → item_categories.id
part_number         varchar(100) unique
name_id             varchar(255)   -- Bahasa Indonesia
name_en             varchar(255)   -- English
name_zh             varchar(255)   -- 中文
description         text nullable
base_uom            varchar(50)    -- satuan terkecil: pcs, liter, kg
alt_uom             varchar(50) nullable   -- satuan besar: box, carton
alt_uom_conversion  decimal(10,2) nullable -- 1 box = 12 pcs
minimum_stock       decimal(10,2) default 0
has_cooldown        boolean default false
cooldown_days       int nullable            -- periode cooldown dalam hari
cooldown_track_by   enum('lv_number','employee_id') nullable
is_active           boolean default true
timestamps
```

---

## 9. item_variants
```sql
id          bigint PK
item_id     bigint FK → items.id
brand       varchar(100) nullable
model       varchar(100) nullable
size        varchar(50) nullable
color       varchar(50) nullable
sku         varchar(100) unique nullable   -- auto generate
is_active   boolean default true
timestamps
```
> Setiap kombinasi variant = 1 baris = stok sendiri

---

## 10. stock_ledgers (Stok per Variant per Lokasi)
```sql
id              bigint PK
item_variant_id bigint FK → item_variants.id
location_id     bigint FK → locations.id
warehouse_id    bigint FK → warehouses.id
qty_on_hand     decimal(10,2) default 0    -- stok tersedia
qty_reserved    decimal(10,2) default 0    -- stok sedang diproses GI
qty_available   decimal(10,2) generated    -- on_hand - reserved
last_updated_at timestamp
timestamps
```
> Stok awal diinput Super Admin langsung ke tabel ini

---

## 11. goods_issues (Header Request GI)
```sql
id                  bigint PK
gi_number           varchar(50) unique    -- GI-2025-0001
warehouse_id        bigint FK → warehouses.id
department_id       bigint FK → departments.id
requested_by        bigint FK → users.id  -- Admin Dept
purpose             text                  -- alasan penggunaan
usage_location      varchar(255)          -- lokasi pemakaian
status              enum('draft','pending_manager_dept','pending_manager_wh',
                         'pending_supervisor','assigned','in_picking',
                         'ready_to_pickup','completed','rejected')
assigned_to         bigint FK → users.id nullable   -- Operator
submitted_at        timestamp nullable
completed_at        timestamp nullable
notes               text nullable
timestamps
```

---

## 12. goods_issue_items (Detail Item per GI)
```sql
id                  bigint PK
goods_issue_id      bigint FK → goods_issues.id
item_variant_id     bigint FK → item_variants.id
requested_qty       decimal(10,2)
requested_uom       varchar(50)           -- base atau alt uom
qty_in_base_uom     decimal(10,2)         -- konversi ke base uom
actual_qty          decimal(10,2) nullable -- qty aktual disiapkan operator
uom_used            varchar(50)
lv_id               bigint FK → vehicles.id nullable
employee_id         bigint FK → employees.id nullable
cooldown_until      date nullable          -- dihitung saat completed
notes               text nullable
status              enum('pending','picking','ready','rejected')
timestamps
```

---

## 13. goods_issue_approvals (Audit Log Approval GI)
```sql
id              bigint PK
goods_issue_id  bigint FK → goods_issues.id
step            enum('manager_dept','warehouse_manager','supervisor','operator')
action          enum('approved','rejected')
acted_by        bigint FK → users.id
reason          text nullable        -- wajib diisi jika rejected
acted_at        timestamp
timestamps
```
> Satu baris per step per action — rekam jejak lengkap

---

## 14. goods_receipts (Header GR)
```sql
id              bigint PK
gr_number       varchar(50) unique    -- GR-2025-0001
warehouse_id    bigint FK → warehouses.id
created_by      bigint FK → users.id  -- Supervisor / WH Manager
assigned_to     bigint FK → users.id  -- Operator
description     text nullable
status          enum('assigned','in_checking','pending_supervisor','completed','rejected')
submitted_at    timestamp nullable
completed_at    timestamp nullable
notes           text nullable
timestamps
```

---

## 15. goods_receipt_items (Detail Item per GR)
```sql
id                  bigint PK
goods_receipt_id    bigint FK → goods_receipts.id
item_variant_id     bigint FK → item_variants.id
expected_qty        decimal(10,2)
actual_qty          decimal(10,2) nullable
uom                 varchar(50)
location_id         bigint FK → locations.id nullable   -- diinput operator
condition           enum('good','damaged','broken','other') default 'good'
condition_notes     text nullable
timestamps
```

---

## 16. goods_receipt_approvals (Audit Log Approval GR)
```sql
id                  bigint PK
goods_receipt_id    bigint FK → goods_receipts.id
step                enum('supervisor')
action              enum('approved','rejected')
acted_by            bigint FK → users.id
reason              text nullable
acted_at            timestamp
timestamps
```

---

## 17. notifications
```sql
id              bigint PK
user_id         bigint FK → users.id
type            varchar(100)    -- GI_SUBMITTED, GI_APPROVED, GR_ASSIGNED, dll
title           varchar(255)
message         text
data            json nullable   -- { gi_id: 1, gi_number: "GI-2025-001" }
is_read         boolean default false
read_at         timestamp nullable
timestamps
```

---

## 18. cooldown_logs (Tracking Cooldown per Item)
```sql
id                  bigint PK
item_id             bigint FK → items.id
item_variant_id     bigint FK → item_variants.id
track_type          enum('lv_number','employee_id')
lv_id               bigint FK → vehicles.id nullable
employee_id         bigint FK → employees.id nullable
goods_issue_id      bigint FK → goods_issues.id
taken_at            date
cooldown_until      date
timestamps
```
> Dicek saat Admin Dept pilih item saat buat GI request

---

## Relasi Utama

```
warehouses
  ├── departments
  ├── locations
  └── items
        └── item_variants
              └── stock_ledgers (per location)

goods_issues
  ├── goods_issue_items (→ item_variants)
  └── goods_issue_approvals (audit log)

goods_receipts
  ├── goods_receipt_items (→ item_variants, locations)
  └── goods_receipt_approvals (audit log)

cooldown_logs (→ items, item_variants, vehicles/employees, goods_issues)

notifications (→ users)
```

---

## Catatan Implementasi

- Semua tabel menggunakan `bigint` auto increment PK
- Soft delete (`deleted_at`) untuk: items, item_variants, users, departments
- `gi_number` & `gr_number` auto generate: `GI-YYYY-XXXX` format
- `qty_available` di stock_ledgers = `qty_on_hand - qty_reserved` (computed / generated column)
- Saat GI submitted → `qty_reserved` bertambah
- Saat GI completed → `qty_on_hand` berkurang, `qty_reserved` berkurang
- Saat GR completed (approved SPV) → `qty_on_hand` bertambah sesuai lokasi
- Index wajib pada: `status`, `department_id`, `warehouse_id`, `user_id` di tabel transaksi