# DESIGN SYSTEM — WBN Warehouse Management System
# Weda Bay Nickel

**Versi:** 1.0  
**Status:** Approved  
**Berlaku untuk:** Web App (Laravel + Vue.js + Inertia.js)  
**Update berikutnya:** Mobile App (Operator lapangan)

> File ini adalah referensi tunggal (single source of truth) untuk semua
> keputusan visual di project WBN-WMS. Claude Code WAJIB membaca file ini
> sebelum membuat atau memodifikasi komponen UI apapun.

---

## DAFTAR ISI

1. [Brand Identity](#1-brand-identity)
2. [Color System](#2-color-system)
3. [Typography](#3-typography)
4. [Spacing & Layout](#4-spacing--layout)
5. [Dark & Light Mode](#5-dark--light-mode)
6. [Komponen — Sidebar](#6-komponen--sidebar)
7. [Komponen — Navbar](#7-komponen--navbar)
8. [Komponen — Cards & Stat Cards](#8-komponen--cards--stat-cards)
9. [Komponen — Buttons](#9-komponen--buttons)
10. [Komponen — Form & Inputs](#10-komponen--form--inputs)
11. [Komponen — Tables](#11-komponen--tables)
12. [Komponen — Badges & Status](#12-komponen--badges--status)
13. [Komponen — Modals](#13-komponen--modals)
14. [Komponen — Toggles & Switches](#14-komponen--toggles--switches)
15. [Komponen — Charts](#15-komponen--charts)
16. [Halaman — Login Page](#16-halaman--login-page)
17. [Halaman — Dashboard](#17-halaman--dashboard)
18. [Halaman — Permission Management](#18-halaman--permission-management)
19. [Halaman — Menu Management](#19-halaman--menu-management)
20. [Animasi & Transisi](#20-animasi--transisi)
21. [Icons](#21-icons)
22. [Language & i18n](#22-language--i18n)
23. [Responsive Breakpoints](#23-responsive-breakpoints)
24. [Tailwind Config](#24-tailwind-config)

---

## 1. BRAND IDENTITY

### Perusahaan
- **Nama:** Weda Bay Nickel
- **Sistem:** WBN Warehouse Management System (WBN-WMS)
- **Industri:** Mining & Construction
- **Karakter brand:** Industrial, Professional, Trustworthy, Precise

### Logo
```
Komponen logo Weda Bay Nickel:
├── Flame/leaf icon — gradien gold (#f97316) + purple (#4c1d95)
├── Company name — "Weda Bay Nickel" bold, dark navy
└── System name  — "Warehouse Management System" smaller, muted

Penggunaan di app:
├── Sidebar expanded  → flame icon (44px) + teks perusahaan + badge "WMS"
├── Sidebar collapsed → flame icon only (32px)
├── Login page        → flame icon (52px) + nama perusahaan + nama sistem
└── Favicon           → flame icon saja
```

### Logo Flame SVG (referensi)
```svg
<svg viewBox="0 0 120 130" xmlns="http://www.w3.org/2000/svg">
  <!-- Left petal — orange -->
  <path d="M55,110 C30,90 15,60 25,30 C35,10 55,5 60,20 
           C40,40 38,70 55,110Z" fill="#f97316"/>
  <!-- Center petal — amber -->
  <path d="M60,115 C45,95 42,65 52,38 C57,22 65,18 68,30 
           C58,55 57,82 60,115Z" fill="#d97706"/>
  <!-- Right petal — gold -->
  <path d="M65,112 C72,88 80,62 75,35 C71,18 62,12 60,20 
           C75,42 78,72 65,112Z" fill="#fbbf24"/>
  <!-- Inner petal — deep purple -->
  <path d="M58,108 C50,85 48,55 56,32 C59,22 63,20 64,28 
           C56,50 55,78 58,108Z" fill="#4c1d95" opacity="0.85"/>
  <!-- Highlight -->
  <path d="M62,100 C66,78 70,55 66,36 C64,26 61,22 61,28 
           C65,48 66,72 62,100Z" fill="#fde68a" opacity="0.65"/>
</svg>
```

---

## 2. COLOR SYSTEM

### Primary — Blue (Struktur & Data)
```css
--blue-50:  #eff6ff;   /* Background sangat terang */
--blue-100: #dbeafe;   /* Background hover */
--blue-200: #bfdbfe;   /* Border terang */
--blue-400: #60a5fa;   /* Icon, secondary accent */
--blue-500: #3b82f6;   /* Warna utama */
--blue-600: #2563eb;   /* Hover state */
--blue-700: #1d4ed8;   /* Active/pressed */
--blue-800: #1e3a8a;   /* Dark blue — header, sidebar dark */
--blue-900: #1e2d4a;   /* Sangat gelap — dark mode surface */
```

### Accent — Orange (Aksi & Alert)
```css
--orange-50:  #fff7ed;  /* Background tint */
--orange-100: #ffedd5;  /* Background hover */
--orange-300: #fdba74;  /* Border terang */
--orange-400: #fb923c;  /* Icon accent */
--orange-500: #f97316;  /* Warna aksi utama */
--orange-600: #ea580c;  /* Hover CTA */
--orange-700: #c2410c;  /* Active CTA */
--orange-amber:#f59e0b; /* Amber — stat cards, highlights */
--orange-gold: #fbbf24; /* Gold — logo, premium elements */
```

### Neutral — Gray
```css
--gray-50:  #f9fafb;   /* Page background (light mode) */
--gray-100: #f3f4f6;   /* Card background alternatif */
--gray-200: #e5e7eb;   /* Border default */
--gray-300: #d1d5db;   /* Border input */
--gray-400: #9ca3af;   /* Placeholder, icon muted */
--gray-500: #6b7280;   /* Text secondary */
--gray-600: #4b5563;   /* Text body */
--gray-700: #374151;   /* Text label */
--gray-800: #1f2937;   /* Text primary */
--gray-900: #111827;   /* Text heading */
```

### Dark Mode Surfaces
```css
--dark-bg:       #0d1117;   /* Page background */
--dark-surface:  #111827;   /* Content area */
--dark-card:     #1e2535;   /* Card background */
--dark-border:   #2a3550;   /* Border */
--dark-border-2: #1e2d3a;   /* Border subtle */
--dark-text:     #f9fafb;   /* Text primary */
--dark-muted:    #9ca3af;   /* Text secondary */
```

### Status Colors
```css
/* Success / Approved */
--success-50:  #f0fdf4;
--success-500: #22c55e;
--success-700: #15803d;

/* Warning / Pending */
--warning-50:  #fffbeb;
--warning-500: #f59e0b;
--warning-700: #b45309;

/* Danger / Rejected / Low Stock */
--danger-50:   #fef2f2;
--danger-500:  #ef4444;
--danger-700:  #b91c1c;

/* Info / Transfer */
--info-50:     #eff6ff;
--info-500:    #3b82f6;
--info-700:    #1d4ed8;
```

### Permission Group Colors
```css
--perm-system:    #3b82f6;  /* Blue  — System group */
--perm-warehouse: #f97316;  /* Orange — Warehouse group */
--perm-inventory: #22c55e;  /* Green — Inventory group */
--perm-reports:   #a855f7;  /* Purple — Reports group */
```

---

## 3. TYPOGRAPHY

### Font Family
```css
font-family: 'Inter', system-ui, -apple-system, sans-serif;

/* Import di app.css */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
```

### Type Scale
```css
--text-xs:   0.75rem;    /* 12px — labels, badges, captions */
--text-sm:   0.875rem;   /* 14px — tabel, form labels, secondary */
--text-base: 1rem;       /* 16px — body text default */
--text-lg:   1.125rem;   /* 18px — card titles */
--text-xl:   1.25rem;    /* 20px — section headings */
--text-2xl:  1.5rem;     /* 24px — page titles */
--text-3xl:  1.875rem;   /* 30px — stat card numbers */
--text-4xl:  2.25rem;    /* 36px — hero numbers (dashboard big) */
```

### Font Weights
```css
--font-light:    300;   /* Decorative only */
--font-normal:   400;   /* Body text */
--font-medium:   500;   /* UI labels, nav items */
--font-semibold: 600;   /* Card titles, table headers */
--font-bold:     700;   /* Page titles, CTA buttons, numbers */
```

### Letter Spacing
```css
/* Field labels (uppercase) */
letter-spacing: 0.06em;

/* Badge text */
letter-spacing: 0.04em;

/* Heading */
letter-spacing: -0.01em;

/* Normal body */
letter-spacing: 0;
```

### Usage Rules
```
Page title (h1):    text-2xl font-bold text-gray-900
Section title (h2): text-xl font-semibold text-gray-800
Card title:         text-lg font-semibold text-gray-800
Table header:       text-xs font-semibold uppercase text-gray-500 tracking-wide
Body text:          text-sm font-normal text-gray-600
Label input:        text-xs font-semibold uppercase text-gray-700 tracking-wider
Placeholder:        text-sm font-normal text-gray-300
Stat number:        text-3xl font-bold text-gray-900
Muted/sub text:     text-sm font-normal text-gray-400
```

---

## 4. SPACING & LAYOUT

### Spacing Scale (Tailwind default)
```
1  =  4px
2  =  8px
3  = 12px
4  = 16px
5  = 20px
6  = 24px
8  = 32px
10 = 40px
12 = 48px
16 = 64px
```

### Layout Dimensions
```css
/* Sidebar */
--sidebar-width-expanded:  240px;
--sidebar-width-collapsed:  64px;

/* Navbar */
--navbar-height: 64px;

/* Content area */
--content-padding: 24px;        /* p-6 */
--content-max-width: 1440px;    /* max lebar konten */

/* Cards */
--card-padding:        24px;    /* p-6 */
--card-padding-sm:     16px;    /* p-4 */
--card-border-radius:  12px;    /* rounded-xl */
--card-border-radius-sm: 8px;   /* rounded-lg */

/* Inputs */
--input-height:    44px;
--input-height-sm: 36px;
--input-height-lg: 52px;
--input-radius:    10px;        /* rounded-[10px] */
--input-padding-x: 14px;

/* Buttons */
--btn-height:    46px;
--btn-height-sm: 36px;
--btn-height-lg: 52px;
--btn-radius:    10px;

/* Modal */
--modal-radius:  16px;
```

### Grid System
```css
/* Stat cards row — 4 kolom */
grid-template-columns: repeat(4, 1fr);
gap: 24px;

/* Dashboard row 2 — Chart + Table */
grid-template-columns: 3fr 2fr;
gap: 24px;

/* Dashboard row 3 — Warehouse + Low Stock */
grid-template-columns: 1fr 1fr;
gap: 24px;

/* Permission matrix */
/* Full width table */

/* Menu Management */
grid-template-columns: 2fr 3fr;  /* tree + preview */
gap: 24px;
```

---

## 5. DARK & LIGHT MODE

### Implementasi di Vue (Tailwind dark mode)
```javascript
// tailwind.config.js
module.exports = {
  darkMode: 'class',  // toggle via class="dark" di <html>
  // ...
}
```

```javascript
// composables/useDarkMode.js
import { ref, watch } from 'vue'

const isDark = ref(localStorage.getItem('theme') === 'dark')

export function useDarkMode() {
  const toggle = () => {
    isDark.value = !isDark.value
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  return { isDark, toggle }
}
```

### Mapping Warna Light → Dark
```
Light Mode                  Dark Mode
──────────────────────────────────────────
bg-white                →  bg-[#1e2535]    (card)
bg-gray-50              →  bg-[#111827]    (page)
bg-gray-100             →  bg-[#0d1117]    (sidebar/navbar)
border-gray-200         →  border-[#2a3550]
text-gray-900           →  text-white
text-gray-500           →  text-gray-400
text-gray-700           →  text-gray-300
shadow-sm               →  shadow-none (border instead)
```

### Toggle Button Style
```
Light mode icon: 🌙 moon icon
Dark mode icon:  ☀️ sun icon
Animation: rotate 180deg + fade, 300ms ease
Position: Navbar right side, sebelum notification bell
```

---

## 6. KOMPONEN — SIDEBAR

### States
```
Expanded  : width 240px — icon + label
Collapsed : width 64px  — icon only + tooltip on hover
```

### Structure (Expanded)
```
┌─────────────────────────────┐
│ [Logo] Weda Bay Nickel  [←] │  ← header + toggle btn
│        WMS                  │
├─────────────────────────────┤
│ MAIN                        │  ← group label
│ [icon] Dashboard            │  ← active item
│                             │
│ SYSTEM                      │
│ [icon] Users                │
│ [icon] Departments          │
│ [icon] Permissions          │
│ [icon] Menu Management      │
│                             │
│ WAREHOUSE                   │
│ [icon] Warehouses           │
│ [icon] Rack Management      │
│                             │
│ INVENTORY                   │
│ [icon] Item Master          │
│ [icon] Goods Receipt    GR  │  ← badge
│ [icon] Goods Issue      GI  │
│ [icon] Transfer             │
│                             │
│ REPORTS                     │
│ [icon] Stock Report         │
│ [icon] Transaction Log      │
├─────────────────────────────┤
│ [Avatar] John Doe      [→]  │  ← user + logout
│          Super Admin        │
└─────────────────────────────┘
```

### CSS Specs
```css
/* Sidebar container */
.sidebar {
  width: 240px;               /* expanded */
  /* width: 64px; */          /* collapsed */
  background: white;          /* light mode */
  /* background: #0d1117; */  /* dark mode */
  border-right: 1px solid #e5e7eb;
  transition: width 250ms ease;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* Group label */
.sidebar-group-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #9ca3af;
  padding: 16px 16px 6px;
}

/* Nav item — default */
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  margin: 2px 8px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #4b5563;
  cursor: pointer;
  transition: all 150ms ease;
  white-space: nowrap;
}
.sidebar-item:hover {
  background: #f0f9ff;
  color: #1d4ed8;
}

/* Nav item — ACTIVE */
.sidebar-item.active {
  background: #fff7ed;
  color: #f97316;
  border-left: 3px solid #f97316;
  margin-left: 8px;
  padding-left: 11px;  /* compensate border */
  font-weight: 600;
}

/* Icon in nav item */
.sidebar-icon {
  font-size: 18px;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}

/* Badge (GR, GI) */
.sidebar-badge {
  margin-left: auto;
  background: #f97316;
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 20px;
}

/* Collapsed state — tooltip */
/* Show tooltip on hover when collapsed */
.sidebar-tooltip {
  position: absolute;
  left: 72px;
  background: #1f2937;
  color: white;
  font-size: 13px;
  padding: 6px 12px;
  border-radius: 6px;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity 150ms;
}
.sidebar-item:hover .sidebar-tooltip { opacity: 1; }
```

---

## 7. KOMPONEN — NAVBAR

### Structure
```
┌──────────────────────────────────────────────────────────────┐
│ [≡] Dashboard  / Home / Dashboard    [🔍] [EN|中|ID] [🌙] [🔔3] [👤▼] │
└──────────────────────────────────────────────────────────────┘
```

### CSS Specs
```css
.navbar {
  height: 64px;
  background: white;              /* dark: #0d1117 */
  border-bottom: 1px solid #f3f4f6; /* dark: #1e2535 */
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 16px;
  position: sticky;
  top: 0;
  z-index: 40;
}

/* Notification bell badge */
.notif-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: #f97316;
  color: white;
  font-size: 10px;
  font-weight: 700;
  width: 18px; height: 18px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}

/* Notification dropdown */
.notif-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 320px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.12);
  animation: dropIn 200ms ease;
}
@keyframes dropIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
```

---

## 8. KOMPONEN — CARDS & STAT CARDS

### Stat Card Structure
```
┌──────────────────────────────────┐
│ [Icon Box]   +12 this month  ↑  │  ← icon + trend
│                                  │
│ 1,284                            │  ← big number
│ Total Items                      │  ← label
└──────────────────────────────────┘
  ↑ left border 4px orange
```

### CSS Specs
```css
.stat-card {
  background: white;                /* dark: #1e2535 */
  border: 1px solid #e5e7eb;       /* dark: #2a3550 */
  border-left: 4px solid;          /* color per card */
  border-radius: 12px;
  padding: 24px;
  transition: all 200ms ease;
  cursor: default;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

/* Icon box */
.stat-icon-box {
  width: 48px; height: 48px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
  margin-bottom: 16px;
}

/* Per card color */
.stat-card--items   { border-left-color: #f97316; }
.stat-icon--items   { background: #fff7ed; color: #f97316; }

.stat-card--soh     { border-left-color: #3b82f6; }
.stat-icon--soh     { background: #eff6ff; color: #3b82f6; }

.stat-card--trx     { border-left-color: #f59e0b; }
.stat-icon--trx     { background: #fffbeb; color: #f59e0b; }

.stat-card--alert   { border-left-color: #ef4444; }
.stat-icon--alert   { background: #fef2f2; color: #ef4444; }
/* Alert card: tambah subtle red glow */
.stat-card--alert   { box-shadow: 0 0 0 1px rgba(239,68,68,0.15); }
```

### Regular Card
```css
.card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}
```

---

## 9. KOMPONEN — BUTTONS

### Variants
```css
/* PRIMARY — orange gradient (CTA utama) */
.btn-primary {
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: white;
  font-weight: 700;
  box-shadow: 0 4px 14px rgba(249,115,22,0.3);
}
.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 22px rgba(249,115,22,0.4);
}

/* SECONDARY — outline */
.btn-secondary {
  background: white;
  border: 1px solid #e5e7eb;
  color: #374151;
  font-weight: 500;
}
.btn-secondary:hover { background: #f9fafb; }

/* DANGER */
.btn-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

/* GHOST */
.btn-ghost {
  background: transparent;
  color: #f97316;
  font-weight: 500;
}
.btn-ghost:hover { background: #fff7ed; }

/* ICON only */
.btn-icon {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}

/* Shared */
.btn {
  height: 44px;
  padding: 0 20px;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
  border: none;
  transition: all 200ms ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

/* Loading state */
.btn.loading .btn-text { opacity: 0; }
.btn.loading::after {
  content: '';
  position: absolute;
  width: 18px; height: 18px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
```

---

## 10. KOMPONEN — FORM & INPUTS

### Input Field
```css
.form-group { margin-bottom: 16px; }

.form-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #374151;              /* dark: #d1d5db */
  margin-bottom: 7px;
}

.form-input {
  width: 100%;
  height: 44px;
  background: #f9fafb;         /* dark: rgba(255,255,255,0.06) */
  border: 1px solid #e5e7eb;  /* dark: rgba(255,255,255,0.1) */
  border-radius: 10px;
  padding: 0 14px 0 42px;     /* 42px = space for left icon */
  font-size: 14px;
  color: #111827;              /* dark: white */
  outline: none;
  transition: all 220ms ease;
  font-family: inherit;
}
.form-input::placeholder { color: #d1d5db; }
.form-input:focus {
  background: white;
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
}
.form-input.error {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.1);
}

/* Left icon */
.input-icon {
  position: absolute;
  left: 13px; top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 16px;
  pointer-events: none;
}

/* Error message */
.form-error {
  margin-top: 6px;
  font-size: 12px;
  color: #ef4444;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Select */
.form-select {
  /* Same as input, add chevron icon right */
  padding-right: 36px;
  appearance: none;
  cursor: pointer;
}
```

### Error Banner (Login / Form)
```css
.error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 13px;
  color: #dc2626;
  animation: shake 0.38s ease;
}
@keyframes shake {
  0%,100% { transform: translateX(0); }
  25%,75% { transform: translateX(-6px); }
  50%     { transform: translateX(6px); }
}
```

---

## 11. KOMPONEN — TABLES

```css
.table-container {
  overflow-x: auto;
  border-radius: 10px;
  border: 1px solid #e5e7eb;   /* dark: #2a3550 */
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

thead th {
  background: #f9fafb;         /* dark: #111827 */
  padding: 12px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
  border-bottom: 1px solid #e5e7eb;
  white-space: nowrap;
}

tbody td {
  padding: 14px 16px;
  color: #374151;              /* dark: #d1d5db */
  border-bottom: 1px solid #f3f4f6; /* dark: #1e2535 */
  vertical-align: middle;
}

tbody tr:hover { background: #f9fafb; }  /* dark: rgba(255,255,255,0.02) */
tbody tr:last-child td { border-bottom: none; }

/* Action buttons in table */
.table-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 150ms;
}
.table-action-edit   { color: #2563eb; background: #eff6ff; }
.table-action-delete { color: #dc2626; background: #fef2f2; }
.table-action-view   { color: #374151; background: #f3f4f6; }
```

### Permission Matrix Table (khusus)
```css
/* Group header row */
.perm-group-row td {
  background: #f0f9ff;         /* per group color */
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 10px 16px;
}
.perm-group-system    { background: rgba(59,130,246,0.08); color: #1d4ed8; }
.perm-group-warehouse { background: rgba(249,115,22,0.08); color: #c2410c; }
.perm-group-inventory { background: rgba(34,197,94,0.08);  color: #15803d; }
.perm-group-reports   { background: rgba(168,85,247,0.08); color: #7c3aed; }

/* Permission checkbox cell */
.perm-check {
  width: 48px;
  text-align: center;
}
.perm-check--on  { color: #f97316; font-size: 20px; cursor: pointer; }
.perm-check--off { color: #d1d5db; font-size: 20px; cursor: pointer; }
.perm-check--locked { color: #d1d5db; cursor: not-allowed; }
```

---

## 12. KOMPONEN — BADGES & STATUS

```css
/* Base badge */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

/* Transaction type */
.badge-gr       { background: #eff6ff; color: #1d4ed8; }   /* GR — blue */
.badge-gi       { background: #fff7ed; color: #c2410c; }   /* GI — orange */
.badge-transfer { background: #f3f4f6; color: #374151; }   /* Transfer — gray */

/* Approval status */
.badge-approved { background: #f0fdf4; color: #15803d; }   /* green */
.badge-pending  { background: #fffbeb; color: #b45309; }   /* yellow */
.badge-rejected { background: #fef2f2; color: #b91c1c; }   /* red */
.badge-draft    { background: #f3f4f6; color: #4b5563; }   /* gray */

/* Role badges */
.badge-superadmin { background: #fef3c7; color: #d97706; } /* gold */
.badge-manager    { background: #eff6ff; color: #1d4ed8; } /* blue */
.badge-staff      { background: #f0fdf4; color: #15803d; } /* green */

/* Stock status */
.badge-instock  { background: #f0fdf4; color: #15803d; }
.badge-low      { background: #fffbeb; color: #b45309; }
.badge-out      { background: #fef2f2; color: #b91c1c; }
```

---

## 13. KOMPONEN — MODALS

```css
/* Overlay */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 50;
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 200ms ease;
}

/* Modal box */
.modal {
  background: white;            /* dark: #1e2535 */
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.2);
  width: 100%;
  animation: scaleIn 200ms cubic-bezier(.22,1,.36,1);
}
.modal-sm  { max-width: 400px; }
.modal-md  { max-width: 560px; }
.modal-lg  { max-width: 800px; }
.modal-xl  { max-width: 1024px; }

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #f3f4f6;
}
.modal-title { font-size: 17px; font-weight: 600; color: #111827; }
.modal-body  { padding: 24px; }
.modal-footer {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #f3f4f6;
}

@keyframes scaleIn {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
```

---

## 14. KOMPONEN — TOGGLES & SWITCHES

```css
/* Menu Management toggle */
.toggle {
  position: relative;
  width: 44px; height: 24px;
  cursor: pointer;
}
.toggle input { display: none; }

.toggle-track {
  width: 100%; height: 100%;
  background: #e5e7eb;        /* OFF state */
  border-radius: 12px;
  transition: background 200ms ease;
}
.toggle input:checked + .toggle-track {
  background: #f97316;        /* ON state — orange */
}

.toggle-thumb {
  position: absolute;
  top: 3px; left: 3px;
  width: 18px; height: 18px;
  background: white;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  transition: transform 200ms ease;
}
.toggle input:checked ~ .toggle-thumb {
  transform: translateX(20px);
}

/* Dark mode toggle (sun/moon) */
.theme-toggle {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all 200ms;
  color: #6b7280;
}
.theme-toggle:hover { background: #f3f4f6; color: #111827; }
.theme-toggle .icon { transition: transform 300ms ease; }
.theme-toggle:active .icon { transform: rotate(180deg); }
```

---

## 15. KOMPONEN — CHARTS

```css
/* Chart container */
.chart-container {
  position: relative;
  height: 260px;
}

/* Tab selector (7 Days | 30 Days | 3 Months) */
.chart-tabs {
  display: flex;
  gap: 4px;
  background: #f3f4f6;
  padding: 4px;
  border-radius: 8px;
  width: fit-content;
}
.chart-tab {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 150ms;
}
.chart-tab.active {
  background: white;
  color: #111827;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
```

### Chart.js Config (vue-chartjs)
```javascript
// Warna chart sesuai design system
const chartColors = {
  goodsReceipt: {           // IN — Blue
    line:   '#3b82f6',
    fill:   'rgba(59,130,246,0.1)',
  },
  goodsIssue: {             // OUT — Orange
    line:   '#f97316',
    fill:   'rgba(249,115,22,0.1)',
  },
}

const defaultOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top', align: 'end' },
    tooltip: {
      backgroundColor: '#1f2937',
      titleColor: '#f9fafb',
      bodyColor: '#d1d5db',
      borderColor: '#374151',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
    }
  },
  scales: {
    x: { grid: { display: false } },
    y: { grid: { color: '#f3f4f6' }, border: { display: false } }
  }
}
```

---

## 16. HALAMAN — LOGIN PAGE

### Layout
```
Full-page background (warehouse interior illustration)
+ outdoor mining scene visible through window
+ Login box floating right (z-index overlay)
```

### Login Box Specs
```css
.login-box {
  position: fixed;
  right: 0; top: 0; bottom: 0;
  width: 420px;
  background: white;
  border-left: 1px solid rgba(255,255,255,0.1);
  padding: 40px 36px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-shadow: -20px 0 60px rgba(0,0,0,0.3);
}
/* Top accent line */
.login-box::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, #f59e0b, #f97316);
}
```

### Background Illustration Elements
```
Warehouse interior:
  - Ceiling dengan industrial beam lights (blue glow beam)
  - Left & right: heavy-duty rack systems dengan boxes warna-warni
  - Center: forklift sedang bergerak (animasi slow horizontal loop)
  - Conveyor belt system berjalan
  - Pallet stacks di lantai
  - Large window/open bay → outdoor mining site:
      excavator, dump truck, mining tower, sun/moon rising
  - Lantai dengan grid perspektif
  - Ambient dust particles melayang (animasi)

Color tone background: dark navy #0d1117
Illustration opacity: 0.6 (tidak terlalu dominan)
Overlay gradient: left-to-right fade ke arah login box
```

### i18n Keys — Login
```javascript
{
  en: {
    system: 'Warehouse Management System',
    welcome: 'Welcome back',
    subtitle: 'Sign in to your account to continue',
    email: 'Email Address',
    password: 'Password',
    remember: 'Remember me',
    forgot: 'Forgot password?',
    signin: 'Sign In',
    status: 'System operational',
    error: 'Invalid email or password. Please try again.',
  },
  zh: {
    system: '仓库管理系统',
    welcome: '欢迎回来',
    subtitle: '登录您的账户以继续操作',
    email: '电子邮件地址',
    password: '密码',
    remember: '记住我',
    forgot: '忘记密码？',
    signin: '登 录',
    status: '系统运行正常',
    error: '邮箱或密码错误，请重试。',
  },
  id: {
    system: 'Sistem Manajemen Gudang',
    welcome: 'Selamat datang',
    subtitle: 'Masuk ke akun Anda untuk melanjutkan',
    email: 'Alamat Email',
    password: 'Kata Sandi',
    remember: 'Ingat saya',
    forgot: 'Lupa kata sandi?',
    signin: 'Masuk',
    status: 'Sistem berjalan normal',
    error: 'Email atau kata sandi salah. Coba lagi.',
  }
}
```

---

## 17. HALAMAN — DASHBOARD

### Layout Grid
```
┌─────────────────────────────────────────────────┐
│ "Dashboard"  Good morning, John 👋  [date]       │  ← page header
├──────────┬──────────┬──────────┬────────────────┤
│ Total    │   SOH    │  Today   │  Low Stock     │  ← stat cards row
│ Items    │          │  Trx     │  Alert 🔴      │
├──────────┴──────────┴──────────┴────────────────┤
│                                                  │
│  Transaction Chart (60%)  │ Recent Trx (40%)    │  ← row 2
│                           │                     │
├───────────────────────────┴─────────────────────┤
│                                                  │
│  Warehouse Capacity (50%) │ Low Stock Items (50%)│  ← row 3
│                           │                     │
└───────────────────────────┴─────────────────────┘
```

### Stat Cards Data
```
Card 1 — Total Items
  border-color: #f97316
  icon: ti-package (orange)
  number: dynamic
  trend: green if positive

Card 2 — Stock on Hand
  border-color: #3b82f6
  icon: ti-building-warehouse (blue)
  sub: "across X warehouses"

Card 3 — Today's Transactions
  border-color: #f59e0b
  icon: ti-activity (amber)
  sub: "X GR · X GI · X Transfer"

Card 4 — Low Stock Alert
  border-color: #ef4444
  icon: ti-alert-triangle (red)
  card: subtle red glow border
  sub: "Needs immediate attention"
  number: links to low stock table
```

### Warehouse Capacity Widget
```
Per warehouse card:
  Name + Location
  Progress bar:
    < 70%  → green  (#22c55e)
    70-90% → yellow (#f59e0b)
    > 90%  → red    (#ef4444)
  Rack breakdown di bawah bar
```

---

## 18. HALAMAN — PERMISSION MANAGEMENT

### Layout
```
URL: /system/permissions
Access: Super Admin only

Top: Role tabs [Super Admin 🔒] [Manager Department] [Staff]
Main: Permission matrix table (grouped by category)
Bottom: Fixed action bar
```

### Permission Groups & Colors
```
SYSTEM      → blue header    rgba(59,130,246,0.08)
WAREHOUSE   → orange header  rgba(249,115,22,0.08)
INVENTORY   → green header   rgba(34,197,94,0.08)
REPORTS     → purple header  rgba(168,85,247,0.08)
```

### Matrix Rules
```
Super Admin column:
  - Semua ✅ tercentang
  - Semua LOCKED (tidak bisa diubah)
  - Tampilkan 🔒 icon di header kolom
  - Tooltip: "Super Admin always has full access"

Manager & Staff columns:
  - Bisa di-toggle (click untuk on/off)
  - Animasi smooth saat toggle
  - Perubahan belum tersimpan sampai klik "Save Changes"
  - Highlight row yang berubah (light yellow bg)

Unsaved indicator:
  - Orange dot di tab yang ada perubahan belum disimpan
  - Bottom bar muncul: "You have unsaved changes"
```

---

## 19. HALAMAN — MENU MANAGEMENT

### Layout
```
URL: /system/menus
Access: Super Admin only

Top: 3 role cards (Super Admin 🔒 | Manager | Staff)
Main: Split — Menu tree + toggles (40%) | Live sidebar preview (60%)
Bottom: Fixed action bar
```

### Menu Tree Item
```
[⋮⋮ drag] [icon] Menu Name              [toggle ON/OFF]
  └── [⋮⋮ drag] [icon] Sub Menu         [toggle ON/OFF]
```

### Live Preview Rules
```
Preview panel:
  - Mini sidebar (scale down, non-interactive)
  - Label: "Preview — How [Role] sees the sidebar"
  - Update INSTANTLY saat toggle diubah
  - Menu yang OFF hilang dari preview dengan fade animation
  - Menu yang ON muncul dengan fade animation
  - Smooth reflow — items shift up/down smoothly
```

### Warning Banner (unsaved changes)
```css
.unsaved-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 10px 16px;
  font-size: 13px;
  color: #b45309;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
```

---

## 20. ANIMASI & TRANSISI

### Durasi Standard
```css
--duration-fast:   150ms;   /* hover states */
--duration-normal: 200ms;   /* dropdown, modal */
--duration-slow:   300ms;   /* dark mode toggle, sidebar */
--duration-sidebar: 250ms;  /* sidebar collapse/expand */
```

### Easing
```css
--ease-default: ease;
--ease-smooth:  cubic-bezier(0.22, 1, 0.36, 1);  /* spring-like */
--ease-in:      cubic-bezier(0.4, 0, 1, 1);
--ease-out:     cubic-bezier(0, 0, 0.2, 1);
```

### Animasi per Komponen
```
Sidebar collapse/expand  : width 250ms ease, opacity 150ms
Modal open               : scaleIn 200ms cubic-bezier(.22,1,.36,1)
Modal close              : scaleOut 150ms ease
Dropdown open            : dropIn 200ms ease (translateY + opacity)
Dark mode toggle         : fade + icon rotate 300ms
Notification badge       : pulse animation (scale 1→1.2→1)
Stat card hover          : translateY(-2px) + shadow, 200ms
Button hover             : translateY(-1px) + shadow, 200ms
Toggle switch            : thumb slide 200ms ease
Chart tooltip            : opacity 150ms
Error shake              : 380ms (horizontal shake keyframe)
Forklift (login bg)      : translateX loop 8s linear infinite
Particle float (login)   : translateY + opacity, 7-15s
Status dot glow          : box-shadow pulse, 2s ease infinite
```

---

## 21. ICONS

### Library
```
Tabler Icons (ti-*)
CDN: https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css
Vue package: npm install @tabler/icons-vue
```

### Icon Map per Menu
```javascript
const menuIcons = {
  dashboard:          'ti-layout-dashboard',
  users:              'ti-users',
  departments:        'ti-building',
  permissions:        'ti-shield-check',
  menuManagement:     'ti-layout-grid',
  warehouses:         'ti-building-warehouse',
  racks:              'ti-layout-rows',
  items:              'ti-package',
  goodsReceipt:       'ti-arrow-bar-down',
  goodsIssue:         'ti-arrow-bar-up',
  transfer:           'ti-arrows-exchange',
  stockReport:        'ti-chart-bar',
  transactionLog:     'ti-list',
  settings:           'ti-settings',
  logout:             'ti-logout',
  // Navbar
  search:             'ti-search',
  notification:       'ti-bell',
  darkMode:           'ti-moon',
  lightMode:          'ti-sun',
  language:           'ti-language',
  // Actions
  add:                'ti-plus',
  edit:               'ti-pencil',
  delete:             'ti-trash',
  view:               'ti-eye',
  download:           'ti-download',
  filter:             'ti-filter',
  approve:            'ti-check',
  reject:             'ti-x',
}
```

### Icon Sizes
```css
--icon-xs:  14px;   /* inline text icon */
--icon-sm:  16px;   /* input icon, table action */
--icon-md:  18px;   /* sidebar nav icon */
--icon-lg:  22px;   /* stat card icon */
--icon-xl:  28px;   /* empty state icon */
```

---

## 22. LANGUAGE & i18n

### Supported Languages
```
EN  — English (default)
中文 — Chinese Simplified
ID  — Bahasa Indonesia
```

### Implementasi Vue
```javascript
// composables/useI18n.js
import { ref, computed } from 'vue'

const lang = ref(localStorage.getItem('lang') || 'en')

export function useI18n(translations) {
  const t = computed(() => translations[lang.value] || translations['en'])

  const setLang = (l) => {
    lang.value = l
    localStorage.setItem('lang', l)
  }

  return { t, lang, setLang }
}
```

### Language Switcher UI
```
Style: pill buttons di navbar
[EN] [中文] [ID]
Active: orange pill background + orange text
Inactive: gray text, transparent bg
Transition: 150ms color change
```

---

## 23. RESPONSIVE BREAKPOINTS

```css
/* Tailwind breakpoints */
sm:  640px    /* mobile landscape */
md:  768px    /* tablet portrait  */
lg:  1024px   /* tablet landscape / small desktop */
xl:  1280px   /* desktop */
2xl: 1536px   /* large desktop */
```

### Behavior per Breakpoint
```
Desktop (≥ 1024px):
  - Sidebar selalu tampil (expanded atau collapsed)
  - 4 stat cards dalam 1 baris
  - Chart + table side by side

Tablet (768–1023px):
  - Sidebar collapsed by default
  - 2 stat cards per baris
  - Chart full width, table di bawah

Mobile (< 768px):
  - Sidebar tersembunyi, toggle dengan hamburger
  - 1 stat card per baris (atau 2 kecil)
  - Semua sections stack vertical
  - Tabel horizontal scroll
  - [Khusus mobile: akan dikembangkan untuk operator lapangan]
```

---

## 24. TAILWIND CONFIG

```javascript
// tailwind.config.js
const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  darkMode: 'class',
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        primary: {
          50:  '#fff7ed',
          100: '#ffedd5',
          300: '#fdba74',
          400: '#fb923c',
          500: '#f97316',
          600: '#ea580c',
          700: '#c2410c',
        },
        brand: {
          blue:       '#1e3a8a',
          'blue-mid': '#3b82f6',
          orange:     '#f97316',
          amber:      '#f59e0b',
          gold:       '#fbbf24',
        },
        dark: {
          bg:      '#0d1117',
          surface: '#111827',
          card:    '#1e2535',
          border:  '#2a3550',
        }
      },
      borderRadius: {
        'xl':  '12px',
        '2xl': '16px',
        '3xl': '20px',
      },
      boxShadow: {
        'card':   '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
        'card-hover': '0 8px 24px rgba(0,0,0,0.08)',
        'orange': '0 4px 14px rgba(249,115,22,0.3)',
        'orange-lg': '0 8px 22px rgba(249,115,22,0.4)',
        'modal':  '0 24px 64px rgba(0,0,0,0.15)',
      },
      animation: {
        'spin-slow': 'spin 2s linear infinite',
        'pulse-glow': 'pulse 2s ease infinite',
        'fade-in': 'fadeIn 200ms ease',
        'slide-down': 'slideDown 200ms ease',
      },
      keyframes: {
        fadeIn: {
          from: { opacity: '0' },
          to:   { opacity: '1' },
        },
        slideDown: {
          from: { opacity: '0', transform: 'translateY(-8px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
      },
      transitionDuration: {
        '250': '250ms',
      },
    }
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
```

---

## QUICK REFERENCE CARD

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 WBN-WMS DESIGN QUICK REFERENCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 Orange #f97316  → CTA, active, ON, highlights
 Blue   #3b82f6  → structure, data, info
 Navy   #1e3a8a  → dark panels, headers
 Gold   #fbbf24  → logo, premium elements

 Font: Inter
 Radius: 10px (input/btn) | 12px (card) | 16px (modal)
 Shadow: card-shadow → card-hover-shadow on hover
 Sidebar: 240px expanded | 64px collapsed | 250ms transition
 Navbar: 64px height | sticky top
 Content padding: 24px

 Dark mode: class="dark" on <html>
 Lang: localStorage('lang') → 'en'|'zh'|'id'
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```