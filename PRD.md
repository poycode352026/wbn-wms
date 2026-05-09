# WBN-WMS — Product Requirements Document (PRD)

> Warehouse Management System · Weda Bay Nickel
> Fokus scope: **Gudang KM-17** (ATK + Sparepart)

---

## 1. Roles & Akses

| Role | Kode | Akses Utama |
|---|---|---|
| Super Admin | `super_admin` | Full sistem, master data, stok awal, user management |
| Admin Dept | `admin_dept` | Buat request GI untuk departmentnya, lihat stok real-time |
| Manager Dept | `manager_dept` | Approve/Reject GI tahap 1 |
| Warehouse Manager | `warehouse_manager` | Approve/Reject GI tahap 2, notif GR, lihat stok & laporan |
| Supervisor | `supervisor` | Approve/Reject GI tahap 3, assign operator, approve GR |
| Operator | `operator` | Eksekusi job picking & GR — hanya job miliknya |

> - Setiap Reject **wajib isi alasan**
> - History approve/reject tercatat beserta timestamp & nama approver

---

## 2. Master Data (Super Admin)

### 2.1 Gudang
- Nama gudang, kode, lokasi
- KM-17 (ATK + Sparepart) — scope aktif
- KM-28 (Bahan Kimia) — scope future

### 2.2 Department
- Nama department, kode
- Assign user ke department

### 2.3 User Management
- Tambah / edit / nonaktifkan user
- Assign role & department
- 1 user bisa 1 role saja

### 2.4 Lokasi / Rak
- Kode rak, nama rak, gudang
- Digunakan saat GR untuk input lokasi penyimpanan

### 2.5 Item Master
Setiap item memiliki:
- **Nama item** — input 3 bahasa (ID, EN, 中文) via language toggle
- **Kategori** — ATK / Sparepart
- **Part Number** — wajib diisi
- **Description**
- **Variant** — kombinasi Brand, Model, Size, Color (setiap kombinasi = stok sendiri)
- **Base UoM** — satuan terkecil yang boleh keluar (contoh: pcs)
- **Alt UoM** — satuan besar + konversi ke Base UoM (contoh: box = 12 pcs)
- **Minimum stok** — threshold alert stok hampir habis
- **Cooldown setting**:
  - Aktif: Yes / No
  - Period: X hari / bulan
  - Track by: **LV Number** atau **Employee ID**

### 2.6 Stok Awal
- Super Admin input stok awal per item variant per lokasi/rak

---

## 3. Goods Issue (GI) — Pengeluaran Barang

### 3.1 Alur Lengkap

```
[Admin Dept] Buat Request
→ Pilih item (lihat stok real-time)
→ Sistem cek cooldown:
   - Jika masih cooldown → muncul alert "Item ini sudah diambil pada [tgl], 
     baru bisa request lagi [tgl]" — tetap bisa lanjut request
→ Isi qty (pilih Base UoM atau Alt UoM, sistem konversi otomatis)
→ Isi alasan penggunaan + lokasi pemakaian
→ Isi LV Number (jika item track by LV) atau Employee ID (jika track by employee)
→ SAVE DRAFT atau SUBMIT

STATUS: draft
↓ (submit)
STATUS: pending_manager_dept
[Manager Dept] notifikasi → APPROVE atau REJECT + alasan
↓
STATUS: pending_manager_wh
[Warehouse Manager] notifikasi → APPROVE atau REJECT + alasan
↓
STATUS: pending_supervisor
[Supervisor] notifikasi → APPROVE atau REJECT + alasan
           → jika APPROVE: assign Operator
↓
STATUS: assigned
[Operator] notifikasi job di HP → klik "Mulai Proses"
↓
STATUS: in_picking
→ Siapkan barang satu per satu
→ SAVE DRAFT (bisa bertahap)
→ Jika barang tidak ada → REJECT + alasan (stok habis, dll)
→ Jika selesai → SUBMIT
↓
STATUS: ready_to_pickup
Barang di Staging Area
[Admin Dept] cetak BARCODE (nomor request)
[Requester] datang ke gudang, tunjukkan barcode
[Warehouse Team] scan barcode → konfirmasi barang diambil
↓
STATUS: completed ✓
Stok otomatis berkurang
Cooldown tracking terupdate
```

### 3.2 Status GI

| Status | Keterangan |
|---|---|
| `draft` | Disimpan Admin Dept, belum disubmit, tidak visible ke role lain |
| `pending_manager_dept` | Menunggu approve/reject Manager Dept |
| `pending_manager_wh` | Menunggu approve/reject Warehouse Manager |
| `pending_supervisor` | Menunggu approve/reject + assign Supervisor |
| `assigned` | Di-assign ke Operator, belum dikerjakan |
| `in_picking` | Operator sedang mempersiapkan barang |
| `ready_to_pickup` | Barang siap di Staging Area |
| `completed` | Barang sudah diambil requester |
| `rejected` | Ditolak + alasan tersimpan |

### 3.3 Rules GI
- Draft hanya visible ke Admin Dept pembuat
- Admin Dept bisa lihat stok real-time saat membuat request
- Alert cooldown muncul saat item dipilih (tidak memblokir, hanya peringatan)
- Konversi UoM otomatis: request 2 box = 24 pcs keluar dari stok
- Barcode berisi nomor request untuk pickup di staging area

---

## 4. Goods Receipt (GR) — Penerimaan Barang

### 4.1 Alur Lengkap

```
[Supervisor / Warehouse Manager] Buat Job GR
→ Input detail barang yang datang (item, qty, keterangan)
→ Assign ke Operator
↓
STATUS: assigned
[Operator] notifikasi job di HP → mulai cek fisik
↓
STATUS: in_checking
→ Cek qty aktual vs dokumen
→ Cek kondisi barang (baik / rusak / pecah / dll)
→ Input lokasi/rak penyimpanan (pilih dari master data)
→ SUBMIT hasil cek
↓
STATUS: pending_supervisor
[Supervisor] APPROVE atau REJECT + alasan
→ Warehouse Manager dapat NOTIFIKASI (tidak perlu approve)
↓
STATUS: completed ✓
Stok otomatis terupdate sesuai lokasi/rak yang diinput
```

### 4.2 Status GR

| Status | Keterangan |
|---|---|
| `assigned` | Job dibuat & di-assign ke Operator |
| `in_checking` | Operator sedang cek fisik barang |
| `pending_supervisor` | Menunggu approve/reject Supervisor |
| `completed` | Disetujui, stok terupdate |
| `rejected` | Ditolak Supervisor, perlu tindak lanjut |

---

## 5. Dashboard per Role

| Role | Konten Dashboard |
|---|---|
| `super_admin` | Total user, total item, total dept, stok hampir habis (alert minimum stok) |
| `admin_dept` | Request saya: draft, pending, approved, rejected — ringkasan per status |
| `manager_dept` | Pending approval masuk, history approval saya |
| `warehouse_manager` | Pending approval GI, notif GR selesai, overview stok, alert minimum stok |
| `supervisor` | Pending assign, job operator berjalan, GR pending approve, alert minimum stok |
| `operator` | Job hari ini, job selesai — tampilan simpel mobile |

---

## 6. Notifikasi (In-System)

| Trigger | Penerima |
|---|---|
| GI disubmit Admin Dept | Manager Dept |
| GI approved Manager Dept | Warehouse Manager |
| GI approved Warehouse Manager | Supervisor |
| GI approved Supervisor | Operator (job assigned) |
| GI ready to pickup | Admin Dept |
| GR job dibuat | Operator |
| GR submitted Operator | Supervisor |
| GR approved Supervisor | Warehouse Manager (notif saja) |
| Reject di tahap manapun | Role sebelumnya (pengirim) |
| Stok mencapai minimum | Super Admin, Warehouse Manager, Supervisor |

---

## 7. Operator Mobile UI

Tampilan simpel, diakses via browser mobile (responsive):
- **Home**: daftar job hari ini (GI picking & GR checking)
- **Job Detail**: list barang, qty, instruksi
- **Aksi**: Mulai → Save Draft → Submit / Reject + alasan
- **Scan**: scan barcode rak untuk verifikasi lokasi (opsional)
- Layout menggunakan `OperatorLayout.vue` — simple, besar, touch-friendly

---

## 8. Reports

### Akses per Role
| Role | Akses Laporan |
|---|---|
| `super_admin` | Semua laporan semua gudang |
| `admin_dept` | History request GI departmentnya |
| `manager_dept` | History approval + transaksi departmentnya |
| `warehouse_manager` | Semua transaksi GI & GR, laporan stok |
| `supervisor` | Semua transaksi GI & GR yang dia handle |

### Jenis Laporan
1. **Laporan Transaksi GI** — semua request keluar per periode
2. **Laporan Transaksi GR** — semua penerimaan barang per periode
3. **Laporan Stok** — stok saat ini per item per lokasi/rak
4. **Laporan Transaksi Harian** — ringkasan hari ini di gudang
5. **Laporan Mingguan** — ringkasan minggu ini
6. **Laporan Bulanan** — ringkasan bulan ini

### Filter Tersedia
- Tanggal / range tanggal custom
- Minggu ini / Bulan ini / Hari ini (shortcut)
- Per gudang
- Per department
- Per item / kategori
- Per status

### Export
- ✅ Export PDF
- ✅ Export Excel

---

## 9. Scope & Batasan (KM-17)

- ✅ Goods Issue — pengeluaran barang ke department
- ✅ Goods Receipt — penerimaan & pengecekan barang masuk
- ✅ Master data: item (3 bahasa, variant, UoM, cooldown), lokasi/rak, dept, user
- ✅ Stok awal diinput Super Admin
- ✅ Multi-variant item dengan stok terpisah
- ✅ Cooldown tracking by LV Number atau Employee ID
- ✅ Staging Area + Barcode pickup
- ✅ Alert minimum stok
- ✅ Multi bahasa: ID, EN, 中文
- ❌ Proses pembelian / PR — tidak ada
- ❌ KM-28 (Bahan Kimia) — future scope
- ❌ Integrasi sistem eksternal — future scope