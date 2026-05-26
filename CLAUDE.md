# WBN-WMS — Claude Code Context
> Warehouse Management System · Weda Bay Nickel

---

## Stack
- **Backend**: Laravel 11
- **Frontend**: Vue 3 `<script setup>` + Inertia.js
- **Styling**: Tailwind CSS
- **Build**: Vite
- **Local**: Laragon (Apache + MySQL)
- **Auth**: Laravel Breeze (Inertia + Vue)

---

## Perintah
```bash
php artisan serve      # terminal 1
npm run dev            # terminal 2
php artisan migrate
php artisan migrate:fresh --seed
```

---

## Struktur Folder
```
resources/js/
├── Pages/
│   ├── Auth/
│   ├── Dashboard/
│   ├── GoodsIssue/
│   ├── GoodsReceipt/
│   ├── Operator/        # UI simple mobile
│   ├── Master/          # Item, Lokasi, Dept, User
│   └── Reports/
├── Components/
├── Layouts/
│   ├── AppLayout.vue
│   ├── AuthLayout.vue
│   └── OperatorLayout.vue
└── app.js
```

---

## Roles
| Kode                 | Akses                                          |
|----------------------|------------------------------------------------|
| `super_admin`        | Full sistem, master data, stok awal            |
| `admin_dept`         | Buat request Goods Issue departmentnya         |
| `manager_dept`       | Approve/Reject GI tahap 1                      |
| `warehouse_manager`  | Approve/Reject GI tahap 2, notif GR            |
| `supervisor`         | Approve/Reject GI tahap 3, assign operator, approve GR |
| `operator`           | Eksekusi job (picking/GR) — hanya job miliknya |

> Setiap Reject **wajib isi alasan**. History approval tercatat semua.

---

## Alur Singkat

**Goods Issue**
```
Admin Dept (draft→submit) → Manager Dept → WH Manager → Supervisor (assign) → Operator (picking) → Staging Area → Barcode scan → Completed
```

**Goods Receipt**
```
Supervisor (buat job→assign) → Operator (cek qty, kondisi, input lokasi/rak) → Supervisor approve → Stok update
```

Status lengkap & user stories → lihat `PRD.md`

---

## Konvensi Kode
- Vue: selalu `<script setup>`, PascalCase filename
- Laravel: Controller → `GoodsIssueController`, Model → singular `GoodsIssue`
- Route name: dot notation → `goods-issue.index`
- Inertia: pakai `useForm()` dan `router.visit()` — **bukan Axios**
- Tailwind: utility class saja, tidak perlu CSS custom

---

## Larangan
- ❌ Jangan pakai Axios
- ❌ Jangan install library baru tanpa konfirmasi
- ❌ Tidak ada fitur PR / pembelian di sistem ini
- ✅ Validasi selalu di FormRequest Laravel
- ✅ Semua route wajib middleware auth + role check
- ✅ Ikuti design system → `DESIGN.md`

---

## Environment
```env
APP_NAME="WBN WMS"
APP_URL=http://wbn-wms
DB_DATABASE=wbn_wms
DB_USERNAME=root
DB_PASSWORD=
```

---

## Dokumen Pendukung
| File         | Isi                                  |
|--------------|--------------------------------------|
| `PRD.md`     | Fitur lengkap, status, user stories  |
| `SCHEMA.md`  | Struktur database & relasi           |
| `DESIGN.md`  | Design system, warna, komponen UI    |
| `ROUTES.md`  | Daftar halaman & route per role      |