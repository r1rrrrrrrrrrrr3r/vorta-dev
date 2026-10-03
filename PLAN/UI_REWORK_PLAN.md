# Vorta UI Rework: Implementation Plan

> **Untuk eksekutor (Claude Sonnet):** baca dokumen ini sampai habis sebelum menulis kode. Kerjakan per fase secara berurutan. Setiap fase punya **Definition of Done**; jangan lanjut ke fase berikutnya sebelum DoD terpenuhi dan dicek. Kalau ada konflik antara dokumen ini dan kode yang ada, **perilaku backend yang ada menang** (lihat §3 "Aturan wajib"), lalu catat konfliknya di bagian akhir laporanmu.

- Wireframe acuan (low-fi, sudah disetujui user): https://claude.ai/artifact/2vQNjSkVfExMi8UpjmGS6y
- Root project: `vorta-app/` (semua path di dokumen ini relatif terhadap folder ini).
- Bahasa UI: **English** (semua teks yang tampil ke user). Dokumen ini ditulis dalam Bahasa Indonesia.

---

## Daftar isi

1. [Konteks singkat](#1-konteks-singkat)
2. [Keputusan yang sudah dikunci](#2-keputusan-yang-sudah-dikunci)
3. [Aturan wajib](#3-aturan-wajib)
4. [Struktur file baru](#4-struktur-file-baru)
5. [Design tokens (CSS)](#5-design-tokens-css)
6. [Komponen CSS](#6-komponen-css)
7. [Helper PHP (`lib/ui.php`, `lib/icons.php`)](#7-helper-php)
8. [Layout shell (`views/layout/*`)](#8-layout-shell)
9. [JavaScript (`public/js/ui.js`)](#9-javascript-publicjsuijs)
10. [Copy deck (teks UI)](#10-copy-deck)
11. [Fase 0: Persiapan](#fase-0-persiapan)
12. [Fase 1: Fondasi](#fase-1-fondasi)
13. [Fase 2: Komponen & UI kit](#fase-2-komponen--ui-kit)
14. [Fase 3: Halaman staff](#fase-3-halaman-staff)
15. [Fase 4: Halaman admin, Account, Login](#fase-4-halaman-admin-account-login)
16. [Fase 5: Bersih-bersih & QA](#fase-5-bersih-bersih--qa)
17. [Checklist QA lengkap](#17-checklist-qa-lengkap)
18. [Di luar scope (jangan dikerjakan)](#18-di-luar-scope)

---

## 1. Konteks singkat

**Stack:** PHP 8 tanpa framework, MySQL (PDO di `lib/db.php`), Tailwind CSS v4 via CLI (`@tailwindcss/cli`), Chart.js dari CDN. Session-based auth (`lib/auth.php`: `require_login()`, `require_admin()`), CSRF di `lib/csrf.php` (`csrf_field()`, `csrf_verify()`). Setting target di `lib/settings.php`. Preferensi tema di `lib/preferences.php` + `public/save_preferences.php`.

**Masalah yang diperbaiki** (detail di wireframe, bagian "Audit"):

- `public/header.php` mencetak dokumen HTML lengkap sampai `</html>`, lalu halaman mencetak `<!DOCTYPE>` kedua. `footer.php` menutup `</div>` yang tidak pernah dibuka. CSS & `ui_head.php` termuat dua kali.
- Dark mode dibuat dengan menimpa class Tailwind memakai `!important` di `public/css/theme.css`.
- Warna brand indigo Tailwind tidak sesuai logo (navy + teal). Warna tombol per aksi acak.
- 5 gaya kartu statistik, ±8 salinan blok pagination, 3 jenis modal + SweetAlert2 (20 pemanggilan), Font Awesome hanya di 3 halaman.
- Label menu tidak cocok dengan judul halaman; staff harus buka 3 halaman untuk tugas harian.

**Role:** `admin` dan `staff` (kolom `users.role`). Staff punya baris di `employees` (position, phone). Admin mungkin tidak punya baris `employees` — selalu tangani `null`.

**Aturan bisnis yang harus tetap utuh** (jangan diubah):

- Laporan dengan status `Completed` **tidak bisa diedit** (lihat `my_reports.php`, `edit_report.php`). Status DB: `Progress` / `Completed`.
- Minimum laporan harian = `settings_get_daily_min_reports()` (default 2). Target bulanan = `settings_get_monthly_target()` (`min` 50, `max` 88).
- Absensi (`attendance.php`): shift dipilih saat check-in (`Morning`, `Afternoon`, `WFO`, `WAC`, `WFH`, `WFA`). Batas terlambat: Morning > 08:15, Afternoon > 13:30, WFO/WAC/WFH/WFA > 09:30. Terlambat wajib isi alasan ≥ 10 karakter. Absence reason hanya untuk hari ini sampai 23:59. Status leave-type: `Leave`, `Sick`, `Others`, `Absent`, `Forgot`.
- Upload bukti foto: JPG/PNG/WebP, maksimum 1 MB (validasi client di `report_form.php`; validasi server di `save_report.php` / `update_report.php`).
- Ambang status bulanan (dari `dashboard.php`): `total >= min` → on track; `total >= min * 0.6` → behind; selain itu → at risk.

---

## 2. Keputusan yang sudah dikunci

| # | Keputusan | Implikasi |
|---|-----------|-----------|
| 1 | **Sidebar only.** Toggle navbar/sidebar dihapus. | Hapus semua logika `data-nav`, `VortaUI.setLayout`, tombol layout di header & edit_profile. Kolom DB `users.nav_layout` **dibiarkan** (tidak ada migrasi); `lib/preferences.php` & `save_preferences.php` tidak perlu diubah. |
| 2 | **Halaman Today** menggantikan dashboard tim untuk staff. | `dashboard.php` merender view berbeda per role. Ranking tim jadi bagian kecil di Today. |
| 3 | **Bahasa UI tetap English.** | Pakai copy deck di §10. Pesan berbahasa Indonesia yang tampil ke user (mis. di `lib/settings.php`) diterjemahkan. |
| 4 | **Font Plus Jakarta Sans** menggantikan Inter. | Dimuat dari Google Fonts di layout. |
| 5 | **Users & Employees TIDAK digabung** (perlu review). | Tab tetap 4: Users, Employees, Work forces, Job types. |

---

## 3. Aturan wajib

1. **Kontrak backend tidak berubah.** Pertahankan semua `name` field form, key `$_POST`, parameter `$_GET` yang sudah ada, URL endpoint AJAX, dan isi respons JSON. Boleh **menambah** parameter GET baru untuk filter (disebut eksplisit di tiap task). Query SQL boleh ditambah kondisi filter, tidak boleh mengubah arti data yang sudah ada.
2. **CSRF:** setiap form yang sekarang punya `csrf_field()` tetap punya. Jangan menghapus `csrf_verify()`.
3. **Escape output** selalu lewat `e()` (§7). Jangan echo data user mentah.
4. **Tidak ada lagi:** SweetAlert2, Font Awesome, gradien, `shadow-md/lg` pada blok konten, warna Tailwind langsung di halaman (`bg-indigo-*`, `bg-green-600`, `text-gray-*`, dst.), `style="..."` berisi warna, `hover:scale-*`. Gunakan class komponen (§6) dan utilitas token (`bg-surface`, `text-muted`, `border-line`, ...). Utilitas layout Tailwind (`flex`, `grid`, `gap-*`, `md:*`, `hidden`, dst.) **boleh**.
5. **Jangan sentuh** `migrations/`, `seeders/`, `run_*.php`, `vorta_prodtracker.sql`, `cron/`, `lib/db.php`, `lib/xlsx.php`, `lib/mailer.php`. Ada perubahan belum di-commit di `migrations/schema_version.php` milik user: **jangan di-stage, jangan di-revert**.
6. **Git:** kerjakan di branch `ui-rework`. **Jangan commit** kecuali user memintanya. Kalau diminta, satu commit per fase.
7. **Format tanggal/waktu** hanya lewat helper `fmt_date()`, `fmt_month()`, `fmt_time()` (§7). Tidak ada lagi `2026-10-03` atau `2026-10` mentah di UI (kecuali di `value` input).
8. **Status** hanya ditampilkan lewat `status_pill()` (§7). DB value `Progress` tampil sebagai **"In progress"**.
9. Setelah mengubah file PHP, jalankan `php -l <file>`. Setelah mengubah CSS/markup, build Tailwind (§Fase 0).
10. Pertahankan aksesibilitas dasar: setiap input punya `<label for>`, tombol ikon punya `aria-label`, fokus terlihat (`:focus-visible`), dialog bisa ditutup dengan Escape.

---

## 4. Struktur file baru

```
vorta-app/
├─ lib/
│  ├─ ui.php            (BARU) helper tampilan: e(), fmt_*, status_pill(), pagination(), period_picker(), page_header(), kpi_strip(), empty_state(), flash_*(), query_url()
│  ├─ icons.php         (BARU) icon($name) → <svg> Heroicons v2 outline
│  ├─ reports.php       (BARU) report_detail_fetch(), report_daily_completion_stats(), report_daily_counts()
│  └─ attendance.php    (BARU) attendance_messages(), attendance_redirect(), attendance_state()
├─ views/               (BARU, di luar public → tidak bisa diakses langsung lewat URL)
│  ├─ layout/start.php  shell: <!DOCTYPE>, <head>, sidebar, topbar, buka <main>
│  ├─ layout/end.php    tutup <main>, tabbar, toast region, drawer host, flash, scripts
│  ├─ dashboard/admin.php
│  ├─ dashboard/today.php
│  ├─ reports/detail.php        fragmen detail laporan (dipakai admin & staff)
│  ├─ reports/form_fields.php   field form laporan (dipakai create & edit)
│  └─ attendance/dialogs.php    dialog Check in / Request leave / Report an absence
├─ public/
│  ├─ js/ui.js          (BARU) dialog, confirm, drawer, menu, toast, sidebar, period picker
│  ├─ js/attendance.js  (BARU) logika hint shift/terlambat (dipindah dari attendance.php)
│  ├─ settings.php      (BARU) admin: target bulanan & harian
│  ├─ account.php       (BARU) profil + appearance + password
│  ├─ ui_kit.php        (BARU, sementara, admin-only) halaman uji komponen; dihapus di Fase 5
│  ├─ css/theme.css     dikosongkan bertahap (legacy shim), dihapus di Fase 5
│  └─ ... halaman yang ada
└─ src/css/input.css    token + komponen (sumber Tailwind)
```

File yang **dihapus** di Fase 5 (setelah `grep` memastikan tidak ada referensi): `public/header.php`, `public/footer.php`, `public/css/theme.css`, `public/get_report_detail.php`, `public/get_report_detail_user.php` (keduanya sudah tidak dipakai sekarang), `public/ui_kit.php`, `src/css/output.css` (salinan build lama, tidak dipakai).

File yang **menjadi redirect** (tetap ada agar bookmark lama jalan): `public/profile.php`, `public/edit_profile.php`, `public/change_password.php` → `account.php`; `public/admin_not_attendance.php` → `admin_attendance.php?tab=missing`.

Path relatif dari `public/*.php`: `require_once __DIR__ . '/../lib/ui.php';`, `include __DIR__ . '/../views/layout/start.php';`. Gambar logo tetap `../images/vorta.png` (relatif dari URL halaman di `public/`).

---

## 5. Design tokens (CSS)

Ganti seluruh isi `src/css/input.css` dengan blok berikut, lalu tambahkan komponen §6 di bawahnya.

```css
@import "tailwindcss";

/* Dark mode mengikuti atribut data-theme di <html> (diset ui_head.php) */
@custom-variant dark (&:where([data-theme="dark"], [data-theme="dark"] *));

@theme inline {
  --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui, "Segoe UI", sans-serif;

  --color-canvas: var(--vt-canvas);
  --color-surface: var(--vt-surface);
  --color-surface-2: var(--vt-surface-2);
  --color-line: var(--vt-line);
  --color-line-strong: var(--vt-line-strong);
  --color-ink: var(--vt-ink);
  --color-muted: var(--vt-muted);
  --color-faint: var(--vt-faint);
  --color-accent: var(--vt-accent);
  --color-accent-hover: var(--vt-accent-hover);
  --color-accent-soft: var(--vt-accent-soft);
  --color-on-accent: var(--vt-on-accent);
  --color-sidebar: var(--vt-sidebar);
  --color-ok: var(--vt-ok);
  --color-ok-soft: var(--vt-ok-soft);
  --color-warn: var(--vt-warn);
  --color-warn-soft: var(--vt-warn-soft);
  --color-bad: var(--vt-bad);
  --color-bad-soft: var(--vt-bad-soft);
  --color-info: var(--vt-info);
  --color-info-soft: var(--vt-info-soft);
}

@layer base {
  :root {
    --vt-canvas: #F3F5F8;
    --vt-surface: #FFFFFF;
    --vt-surface-2: #F6F8FA;
    --vt-line: #DCE3EB;
    --vt-line-strong: #C2CCD7;
    --vt-ink: #132235;
    --vt-muted: #5A6B80;
    --vt-faint: #8797A9;

    --vt-accent: #0B7E9E;        /* teal dari logo, kontras putih ≥ 4.5 */
    --vt-accent-hover: #09698A;
    --vt-accent-soft: #E2F2F7;
    --vt-on-accent: #FFFFFF;

    --vt-sidebar: #17375E;       /* navy dari logo */
    --vt-sidebar-ink: #F1F5FA;
    --vt-sidebar-ink-2: #B9C8DA;
    --vt-sidebar-muted: #7F97B3;
    --vt-sidebar-hover: rgba(255,255,255,.07);
    --vt-sidebar-active: rgba(255,255,255,.13);
    --vt-sidebar-line: rgba(255,255,255,.12);

    --vt-ok: #1D7F47;   --vt-ok-soft: #E2F3E9;
    --vt-warn: #9A6200; --vt-warn-soft: #FAF0D9;
    --vt-bad: #BE3329;  --vt-bad-soft: #FBE5E3;
    --vt-info: #44607F; --vt-info-soft: #E6ECF3;

    /* palet kategori chart (donut job type) */
    --vt-c1: #0B7E9E; --vt-c2: #17375E; --vt-c3: #63B6CC; --vt-c4: #8DA2B8; --vt-c5: #C9D3DE; --vt-c6: #3E6A96;

    --vt-shadow-float: 0 16px 36px -14px rgba(10, 22, 38, .35);
    --vt-scrim: rgba(8, 18, 31, .42);
    color-scheme: light;
  }

  [data-theme="dark"] {
    --vt-canvas: #0B121B;
    --vt-surface: #111B27;
    --vt-surface-2: #16222F;
    --vt-line: #22314A;
    --vt-line-strong: #33455E;
    --vt-ink: #E3EAF2;
    --vt-muted: #8E9FB3;
    --vt-faint: #66788E;

    --vt-accent: #3CB5D6;
    --vt-accent-hover: #5CC6E3;
    --vt-accent-soft: #0F2E3A;
    --vt-on-accent: #04202A;

    --vt-sidebar: #08121F;
    --vt-sidebar-ink: #E8EEF6;
    --vt-sidebar-ink-2: #A3B4C9;
    --vt-sidebar-muted: #6A819C;

    --vt-ok: #5DCB8C;   --vt-ok-soft: #12301F;
    --vt-warn: #E9B44C; --vt-warn-soft: #33270C;
    --vt-bad: #F07E73;  --vt-bad-soft: #3A1714;
    --vt-info: #9DB3CC; --vt-info-soft: #1B2A3D;

    --vt-c1: #3CB5D6; --vt-c2: #6F93C0; --vt-c3: #2B7F97; --vt-c4: #5D738C; --vt-c5: #34465C; --vt-c6: #9DB8DA;

    --vt-shadow-float: 0 16px 36px -14px rgba(0, 0, 0, .7);
    --vt-scrim: rgba(0, 0, 0, .55);
    color-scheme: dark;
  }

  html { background: var(--vt-canvas); }
  body {
    margin: 0;
    font-family: var(--font-sans);
    font-size: 14px;
    line-height: 1.5;
    background: var(--vt-canvas);
    color: var(--vt-ink);
    -webkit-font-smoothing: antialiased;
  }
  :focus-visible { outline: 2px solid var(--vt-accent); outline-offset: 2px; }
  ::selection { background: var(--vt-accent-soft); }
  * { scrollbar-color: var(--vt-line-strong) transparent; }
}
```

**Skala tipografi** (gunakan hanya ini): 24/700 judul halaman · 17/700 judul drawer/dialog · 15/700 judul card · 14/400 body & tabel · 13/500–600 label, tombol kecil · 12/500 helper, sub-teks · 11/700 uppercase tracking `.08em` untuk header tabel & label KPI. Angka di tabel/KPI: `font-variant-numeric: tabular-nums`.

**Ukuran:** kontrol tinggi 36px (kecil 30px), radius kontrol 6px, card 10px, dialog 12px, spasi kelipatan 4px. Lebar konten maksimum 1200px untuk semua halaman.

---

## 6. Komponen CSS

Tambahkan di `src/css/input.css` setelah blok token, di dalam `@layer components { ... }`. Tulis dengan CSS biasa memakai `var(--vt-*)` (boleh `@apply` untuk utilitas layout). Spesifikasi minimal yang **harus** ada (nama class dipakai di seluruh dokumen ini):

### 6.1 Shell & halaman
| Class | Spesifikasi |
|---|---|
| `.app` | pada `<body>`. |
| `.sidebar` | `position:fixed; inset-block:0; left:0; width:240px; background:var(--vt-sidebar); color:var(--vt-sidebar-ink); display:flex; flex-direction:column; padding:16px 12px; z-index:50; overflow-y:auto`. Di bawah 1024px: `transform:translateX(-100%)`, transisi 200ms; `.sidebar.is-open` → `translateX(0)`. |
| `.sidebar-brand` | flex, gap 10px, padding `4px 8px 16px`; logo 28×28 (`<img src="../images/vorta.png">`, `object-fit:contain`), teks "Vorta" 15/800 + sub "Productivity Tracker" 10/600 uppercase `--vt-sidebar-muted`. |
| `.nav-group-label` | 11/700 uppercase tracking `.1em`, warna `--vt-sidebar-muted`, padding `14px 10px 4px`. |
| `.nav-link` | flex, gap 10px, `height:36px; padding:0 10px; border-radius:6px; color:var(--vt-sidebar-ink-2); font:500 14px`; ikon 18px. Hover bg `--vt-sidebar-hover`. `.is-active`: bg `--vt-sidebar-active`, warna `--vt-sidebar-ink`, weight 600, `aria-current="page"`. |
| `.nav-badge` | `margin-left:auto`; bg `--vt-bad`; putih; 11/700; padding `0 6px`; radius penuh. |
| `.sidebar-user` | `margin-top:auto; border-top:1px solid var(--vt-sidebar-line); padding-top:12px`. Berisi `<button>` full-width (avatar + nama 13/600 + peran 11 muted + chevron) yang membuka `.menu` ke atas. |
| `.sidebar-scrim` | `position:fixed; inset:0; background:var(--vt-scrim); z-index:45`; hanya < 1024px saat sidebar terbuka. |
| `.app-main` | `min-height:100vh`; ≥ 1024px: `padding-left:240px`. |
| `.topbar` | hanya < 1024px: sticky top 0, tinggi 56px, bg surface, border-bottom line, flex, gap 12px, padding `0 16px`, z-index 30. Isi: tombol hamburger (`.btn-icon .btn-ghost`), logo 24px, judul halaman 15/700, spacer, avatar (membuka user menu yang sama). |
| `.page` | `max-width:1200px; margin:0 auto; padding:24px 24px 48px; display:flex; flex-direction:column; gap:20px`. < 640px: padding `16px 16px 40px`. |
| `.page-header` | flex wrap, `align-items:flex-end; justify-content:space-between; gap:12px`. |
| `.page-title` | 24/700, `letter-spacing:-.01em`, `text-wrap:balance`. |
| `.page-subtitle` | 14, muted, margin-top 2px. |
| `.back-link` | 13/600 muted, inline-flex + ikon chevron-left 16px, margin-bottom 6px; hover ink. |
| `.tabbar` | staff only, < 768px: fixed bottom, grid 4 kolom, bg surface, border-top line, `padding-bottom:env(safe-area-inset-bottom)`. Item: ikon 20px + label 11/600 muted; aktif warna accent. Saat ada tabbar, `.page` diberi `padding-bottom:96px`. |
| `.fab` | < 768px saja: fixed `right:16px; bottom:80px`; 52×52; radius 14px; bg accent; warna on-accent; ikon plus 24px; bayangan `--vt-shadow-float`; `aria-label="New report"`. |

### 6.2 Kontrol
| Class | Spesifikasi |
|---|---|
| `.btn` | inline-flex, center, gap 8px, `height:36px; padding:0 14px; border-radius:6px; border:1px solid transparent; font:600 14px; white-space:nowrap`; ikon 16px. `:disabled, [aria-disabled=true]` → opacity .5, `cursor:not-allowed`. |
| `.btn-primary` | bg accent, warna on-accent; hover `--vt-accent-hover`. |
| `.btn-secondary` | bg surface, border `--vt-line-strong`, warna ink; hover bg surface-2. |
| `.btn-ghost` | transparan, warna muted; hover bg surface-2 + ink. |
| `.btn-danger` | bg bad, putih (di dark: warna `#1A0A08`). **Hanya** di dialog konfirmasi & danger zone. |
| `.btn-sm` | height 30px, padding `0 10px`, 13px. |
| `.btn-icon` | width = height, padding 0. |
| `.btn-block` | width 100%. |
| `.link` | warna accent, 600, tanpa underline; hover underline. Untuk aksi teks sekunder ("Request leave", "Mark completed"). |
| `.field` | grid, gap 6px. `.label` 13/600 ink; `.label .optional` 400 muted. `.help` 12 muted. `.field-error` 12 bad. |
| `.input`, `.select`, `.textarea` | width 100%, `height:36px; padding:0 12px; border:1px solid var(--vt-line-strong); border-radius:6px; background:var(--vt-surface); color:var(--vt-ink); font-size:14px`. Focus: border accent + `box-shadow:0 0 0 3px var(--vt-accent-soft)`. `.textarea` → `height:auto; min-height:96px; padding:8px 12px; resize:vertical`. Placeholder warna faint. `[aria-invalid=true]` border bad. |
| `.input-group` | relatif; untuk tombol "Show" di dalam input password (tombol ghost kecil di kanan). |
| `.seg` | segmented control. `display:inline-flex; padding:2px; gap:2px; border:1px solid var(--vt-line); border-radius:8px; background:var(--vt-surface-2)`. Anak: `<a>` atau `<label><input type=radio class="sr-only"><span>…</span></label>`; item `height:28px; padding:0 12px; border-radius:6px; font:600 13px; color:muted`. Aktif (`.is-active` atau `input:checked + span`): bg surface, ink, `box-shadow:0 0 0 1px var(--vt-line)`. Fokus keyboard pada radio → outline pada span. |
| `.tabs` | flex, gap 24px, `border-bottom:1px solid var(--vt-line)`; overflow-x auto. `.tab`: `padding-bottom:10px; margin-bottom:-1px; border-bottom:2px solid transparent; font:600 14px; color:muted`; `.is-active` warna ink + border accent. `.tab-count`: pill kecil bg bad-soft warna bad 11/700. |
| `.toolbar` | flex wrap, gap 8px, align center. `.toolbar-count`: `margin-left:auto`, 13 muted. `.toolbar .input` lebar 240px (search), `.toolbar .select` lebar auto min 140px. |
| `.period` | inline-flex, tinggi 36px, border line-strong, radius 6px, bg surface. Anak: `<a class="period-step">` 32px lebar (ikon chevron), label tengah 14/600 padding `0 8px` min-width 96px, center; `<input type=month|date class="sr-only">` di dalam label (lihat §7 `period_picker`). |

### 6.3 Konten
| Class | Spesifikasi |
|---|---|
| `.card` | bg surface, `border:1px solid var(--vt-line); border-radius:10px`; **tanpa shadow**. `min-width:0`. |
| `.card-header` | flex, between, center, gap 8px, `padding:14px 16px; border-bottom:1px solid var(--vt-line)`. `.card-title` 15/700. `.card-meta` 13 muted. |
| `.card-body` | padding 16px. |
| `.card-footer` | `padding:10px 16px; border-top:1px solid var(--vt-line)`; flex between; 13 muted. |
| `.table-wrap` | `overflow-x:auto`. |
| `.table` | width 100%, collapse, 14px. `th`: kiri, 11/700 uppercase tracking `.08em` muted, `padding:10px 16px`, bg surface-2, border-bottom line, nowrap. `td`: `padding:12px 16px`, border-bottom line, vertical middle. Baris terakhir tanpa border. `.num` kanan + tabular-nums. `.cell-sub` block 12 muted margin-top 2px. `.cell-strong` 600. `tr.is-clickable` cursor pointer, hover bg surface-2. `.col-actions` width 1%, kanan, nowrap. |
| `.pill` | inline-flex, gap 6px, `padding:2px 8px; border-radius:999px; font:600 12px; white-space:nowrap`; `::before` titik 6px `currentColor`. Varian: `.pill-ok` (ok-soft/ok), `.pill-warn`, `.pill-bad`, `.pill-info`, `.pill-neutral` (surface-2/muted). `.pill-role` (tanpa titik, surface-2/ink), `.pill-role-admin` (bg sidebar navy, warna sidebar-ink). |
| `.kpi-strip` | card tanpa padding; `display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr))`. `.kpi`: padding `14px 16px`; pemisah antar item `box-shadow:-1px 0 0 var(--vt-line)` (bukan border, supaya aman saat wrap). `.kpi-label` 11/700 uppercase muted. `.kpi-value` 26/700 tabular-nums `letter-spacing:-.01em`, margin-top 4px; `<small>` di dalamnya 14/600 muted. `.kpi-value.is-bad` warna bad. `.kpi-note` 12 muted. |
| `.progress` | relatif, `height:8px; border-radius:999px; background:var(--vt-line)`. `.progress-fill` absolut kiri, bg accent, radius sama. `.progress-marker` absolut, `top:-3px; bottom:-3px; width:2px; background:var(--vt-ink); opacity:.55`; label opsional `.progress-marker-label` di bawah (11 muted, center). Versi besar `.progress-lg` 10px + ruang label 18px di bawah. |
| `.segbar` | `display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:4px`; anak `span` tinggi 8px radius 4px bg line; `.is-filled` bg accent. Untuk progres laporan harian (N segmen = daily min; lebih dari min tetap penuh). |
| `.daybars` | grafik batang harian tanpa Chart.js: flex, align end, gap 2px, tinggi 72px, relatif. Anak `span` flex 1, radius `2px 2px 0 0`, bg line-strong, `min-height:1px`; `.is-met` (≥ daily min) bg accent; `.is-today` `outline:1.5px solid var(--vt-ink)`; `title` berisi "3 Oct · 2 reports". `.daybars-min`: garis putus-putus absolut di ketinggian daily min, label "min 2" 11 muted di kanan. `.daybars-axis`: flex between 11 muted. |
| `.alert` | flex, gap 10px, `padding:10px 14px; border-radius:8px; border:1px solid; font-size:14px`; ikon 18px. Varian `.alert-ok/-warn/-bad/-info`: border = warna tone, bg = soft tone, teks ink, ikon warna tone. |
| `.empty` | center, grid, justify-items center, gap 8px, `padding:40px 16px; border:1px dashed var(--vt-line-strong); border-radius:10px`. `.empty-title` 15/700, `.empty-text` 14 muted. Di dalam tabel: satu `<td colspan>` berisi `.empty` tanpa border. |
| `.dl` | `display:grid; grid-template-columns:120px 1fr; gap:8px 12px; margin:0`. `dt` muted 13. `dd` 14/600 margin 0, `word-break:break-word`. |
| `.section-label` | 11/700 uppercase tracking muted, margin-bottom 6px. |
| `.avatar` | lingkaran, bg sidebar, warna sidebar-ink, 600, center, inisial 1–2 huruf. Ukuran: `.avatar` 28px/11px, `.avatar-lg` 48px/16px. Di sidebar pakai bg `--vt-sidebar-active`. |
| `.timeline` | flex, center. `.timeline-step`: grid center, min-width 96px; titik 12px (`.is-done` bg accent), waktu 15/700 tabular, label 12 muted. `.timeline-line` flex 1, tinggi 2px, bg line (`.is-done` accent). |
| `.big-number` | 28/700 tabular-nums `letter-spacing:-.02em`. |
| `.dropzone` | `<label>` dengan border `1.5px dashed var(--vt-line-strong)`, radius 8px, padding 12px, flex gap 12px, hover border accent. Thumbnail 72×52 radius 6px `object-fit:cover`. |
| `.legend` | daftar legend donut: grid baris `10px 1fr auto`, gap 10px, padding `7px 0`, pemisah border line; angka tabular, persen muted. |

### 6.4 Overlay
| Class | Spesifikasi |
|---|---|
| `.menu` | `position:absolute; z-index:40; min-width:180px; padding:4px; background:var(--vt-surface); border:1px solid var(--vt-line); border-radius:8px; box-shadow:var(--vt-shadow-float)`; `[hidden]` → none. `.menu-item`: flex gap 8px, `padding:8px 10px; border-radius:6px; font-size:14px; color:ink; width:100%; text-align:left`; hover/focus bg surface-2. `.menu-item-danger` warna bad. `.menu-item[aria-disabled=true]` opacity .5. `.menu-sep` border-top line, margin `4px 0`. `.menu-label` 11/700 uppercase muted padding `6px 10px 2px`. |
| `.dialog` | untuk `<dialog>` native: `border:1px solid var(--vt-line); border-radius:12px; padding:0; width:min(440px, calc(100% - 32px)); background:var(--vt-surface); color:var(--vt-ink); box-shadow:var(--vt-shadow-float)`. `::backdrop` bg `--vt-scrim`. `.dialog-header` padding `18px 20px 0`; `.dialog-title` 17/700; `.dialog-body` padding `10px 20px`, 14, muted untuk teks penjelas, grid gap 14px untuk form; `.dialog-footer` padding `12px 20px 20px`, flex end gap 8px. |
| `.drawer` | `position:fixed; inset-block:0; right:0; width:min(440px,100%); background:var(--vt-surface); border-left:1px solid var(--vt-line); box-shadow:var(--vt-shadow-float); z-index:60; display:flex; flex-direction:column; transform:translateX(100%); transition:transform .2s`. `.drawer.is-open` → `translateX(0)`. `.drawer-header` padding 16px, border-bottom, flex between; `.drawer-eyebrow` 11/700 uppercase muted; `.drawer-title` 17/700. `.drawer-body` padding 16px, `overflow-y:auto; flex:1; display:grid; gap:16px; align-content:start`. `.drawer-footer` `padding:12px 16px; border-top`, flex, gap 8px, justify end. |
| `.drawer-scrim` | fixed inset 0, bg scrim, z-index 55. |
| `.toast-region` | fixed `right:16px; bottom:16px` (≥ 768px) / `left:16px; right:16px; bottom:88px` jika ada tabbar; grid gap 8px; z-index 70. `.toast`: flex gap 12px, center, `padding:10px 14px; border-radius:8px; background:var(--vt-ink); color:var(--vt-canvas); font:600 14px; box-shadow:var(--vt-shadow-float)`. `.toast-bad`: bg bad, putih. Aksi `.toast-action` underline. Animasi masuk 150ms; hormati `prefers-reduced-motion`. |
| `.sr-only` | sudah ada di Tailwind; pakai itu. |

Tambahkan juga `@media (prefers-reduced-motion: reduce) { .sidebar, .drawer, .toast { transition: none; animation: none; } }`.

---

## 7. Helper PHP

### 7.1 `lib/ui.php`

Buat file ini. Signature dan perilaku **wajib** seperti di bawah (implementasi boleh disesuaikan).

```php
<?php
require_once __DIR__ . '/icons.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** $style: short "3 Oct" | medium "3 Oct 2026" | day "Fri, 3 Oct" | long "Fri, 3 Oct 2026" | full "Friday, 3 October 2026" */
function fmt_date(?string $date, string $style = 'medium'): string
{
    $ts = $date ? strtotime($date) : false;
    if (!$ts) return '–';
    return match ($style) {
        'short'  => date('j M', $ts),
        'day'    => date('D, j M', $ts),
        'long'   => date('D, j M Y', $ts),
        'full'   => date('l, j F Y', $ts),
        default  => date('j M Y', $ts),
    };
}

/** "2026-10" → "Oct 2026" */
function fmt_month(string $ym): string
{
    $ts = strtotime($ym . '-01');
    return $ts ? date('M Y', $ts) : $ym;
}

/** "07:52:10" → "07:52"; null → "–" */
function fmt_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '–';
}

/** Validasi "YYYY-MM"; fallback bulan ini. Pakai di semua halaman berparameter month. */
function valid_month(?string $ym): string
{
    return ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) ? $ym : date('Y-m');
}

function valid_date(?string $d): string
{
    return ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) ? $d : date('Y-m-d');
}

const STATUS_TONE = [
    'Completed' => 'ok', 'Present' => 'ok', 'On track' => 'ok', 'Complete' => 'ok', 'Active' => 'ok',
    'Progress' => 'warn', 'Late' => 'warn', 'Behind' => 'warn',
    'Absent' => 'bad', 'Forgot' => 'bad', 'No reports' => 'bad', 'At risk' => 'bad', 'Not checked in' => 'bad',
    'Leave' => 'info', 'Sick' => 'info', 'Others' => 'info',
    'Inactive' => 'neutral', 'Unknown' => 'neutral',
];

const STATUS_LABEL = [
    'Progress' => 'In progress',
    'Forgot'   => 'Forgot to check in',
];

/** Satu-satunya cara menampilkan status. $tone/$label override opsional (mis. "1 of 2" dengan tone warn). */
function status_pill(?string $status, ?string $tone = null, ?string $label = null): string
{
    $status = $status ?: 'Unknown';
    $tone   = $tone ?? (STATUS_TONE[$status] ?? 'neutral');
    $label  = $label ?? (STATUS_LABEL[$status] ?? $status);
    return '<span class="pill pill-' . e($tone) . '">' . e($label) . '</span>';
}

function role_pill(string $role): string
{
    return $role === 'admin'
        ? '<span class="pill pill-role pill-role-admin">Admin</span>'
        : '<span class="pill pill-role">Staff</span>';
}

/** Tone status target bulanan, ambang sama dengan dashboard lama. Return [label, tone]. */
function monthly_target_status(int $total, int $min): array
{
    if ($total >= $min) return ['On track', 'ok'];
    if ($total >= $min * 0.6) return ['Behind', 'warn'];
    return ['At risk', 'bad'];
}

/** URL halaman sekarang dengan $_GET di-merge; nilai null = hapus param. */
function query_url(array $overrides = [], ?string $path = null): string
{
    $q = array_merge($_GET, $overrides);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    $path = $path ?? basename($_SERVER['PHP_SELF']);
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function page_header(string $title, ?string $subtitle = null, string $actionsHtml = '', ?array $back = null): string
{
    // $back = ['href' => 'my_reports.php', 'label' => 'My reports']
    // Output:
    // <div class="page-header"><div>[<a class="back-link">‹ label</a>]<h1 class="page-title">..</h1>[<p class="page-subtitle">..</p>]</div>[<div class="flex flex-wrap gap-2">actions</div>]</div>
}

/**
 * Stepper periode. $type 'month' (value YYYY-MM, label "Oct 2026") atau 'date' (value YYYY-MM-DD, label "Fri, 3 Oct 2026").
 * Prev/next = link (query_url dengan $param diubah dan semua param di $resetParams dihapus, mis. ['page']).
 * Klik label → membuka input native (showPicker) → onchange submit form GET.
 * Form menyertakan hidden input untuk semua $_GET lain kecuali $param dan $resetParams.
 * Tombol next disabled (aria-disabled) bila periode berikutnya > hari ini.
 */
function period_picker(string $param, string $value, string $type = 'month', array $resetParams = ['page']): string {}

/**
 * Footer pagination: "1–20 of 134" + tombol prev/next (.btn .btn-secondary .btn-sm .btn-icon, ikon chevron).
 * Disabled = <span class="btn ... " aria-disabled="true">. Return '' bila $total == 0.
 * Bungkus: <div class="card-footer"><span>…</span><div class="flex gap-1">…</div></div>
 */
function pagination(int $page, int $perPage, int $total, string $param = 'page'): string {}

/** $items: [['label'=>'Reports','value'=>412,'suffix'=>'/ 14','note'=>'Oct 2026','tone'=>'bad'?], ...] */
function kpi_strip(array $items): string {}

function empty_state(string $title, string $text = '', string $actionHtml = ''): string {}

/** Flash untuk toast di request berikutnya. $tone: ok|bad|info */
function flash_set(string $tone, string $message): void
{
    $_SESSION['flash'][] = ['tone' => $tone, 'message' => $message];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}
```

### 7.2 `lib/icons.php`

`function icon(string $name, string $class = 'size-[18px]'): string` → `<svg class="..." fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="..."/></svg>`.

Gunakan path **Heroicons v2 outline (24px)**. Nama yang dibutuhkan:
`home` (Today), `squares` (Dashboard), `document-text` (Reports), `clock` (Attendance), `circle-stack` (Master data), `cog` (Settings), `user` (Account), `plus`, `chevron-left`, `chevron-right`, `chevron-up-down`, `x-mark`, `ellipsis-horizontal`, `magnifying-glass`, `arrow-down-tray` (Export), `arrow-right-on-rectangle` (Log out), `sun`, `moon`, `computer-desktop`, `link`, `photo`, `check-circle`, `exclamation-triangle`, `information-circle`, `x-circle`, `bars-3` (hamburger), `pencil`, `trash`, `eye`, `eye-slash`, `arrow-up-right`.
Path untuk beberapa ikon sudah ada di `public/header.php` (array `$icons`) — boleh dipakai ulang. Ikon dengan lebih dari satu path: pisahkan dengan ` M` dalam satu atribut `d` atau render beberapa `<path>`.

### 7.3 `lib/reports.php`

Pindahkan fungsi yang sekarang terduplikasi di `admin_reports.php` dan `my_reports.php`:

- `report_detail_fetch(PDO $pdo, int $reportId, ?int $ownerId): ?array` — salin persis dari `admin_reports.php`.
- `report_daily_completion_stats(PDO $pdo, string $date, int $dailyMin): array` — dari query `$statsStmt` di `admin_reports.php`; return `['total'=>, 'complete'=>, 'partial'=>, 'none'=>]`.
- `report_daily_counts(PDO $pdo, int $userId, string $start, string $end): array` — map `'Y-m-d' => count` (dari `$stmt2` di `my_reports.php`).
- `report_count_on(PDO $pdo, int $userId, string $date): int`.

Handler `?proof_image=` dan `?detail=` **tetap** di `admin_reports.php` dan `my_reports.php` (URL-nya dipakai drawer dan `<img src>`), hanya memanggil fungsi dari lib dan merender `views/reports/detail.php`.

### 7.4 `lib/attendance.php`

- `attendance_messages(): array` — map kode → teks, gantikan rantai ternary di `attendance.php`:
  - error: `missing_data` "Please fill in all required fields.", `already_checked_in` "You've already checked in today, so you can't request leave.", `leave_already_submitted` "You've already requested leave today, so you can't check in.", `attendance_already_submitted` "Your attendance for today is already recorded.", `explanation_required` "You checked in late. Please give a reason of at least 10 characters.", `invalid_date` "You can only report an absence for today.", `time_expired` "The deadline for today's absence report (23:59) has passed.", `already_attended` "Your attendance for that date is already recorded."
  - success: `absence_reason_submitted` "Absence reported.", default "Done."
- `attendance_redirect(string $query = ''): never` — tujuan dari `$_POST['return_to']` bila ada di whitelist `['attendance.php', 'dashboard.php']`, selain itu `attendance.php`. Ganti semua `header("Location: attendance.php...")` di `attendance.php` dengan fungsi ini (query string tetap sama).
- `attendance_state(?array $row): string` → `'none'` (belum ada record / status kosong), `'checked_in'` (check_in ada, check_out kosong), `'checked_out'`, `'away'` (status Leave/Sick/Others/Absent/Forgot).

---

## 8. Layout shell

### 8.1 Kontrak pemakaian di setiap halaman

```php
<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ui.php';
require_login();               // atau require_admin()

// ... semua logika PHP, POST handling & redirect DI SINI (sebelum output apa pun)

$pageTitle = 'My reports';     // dipakai di <title> "My reports · Vorta" dan topbar mobile
$activeNav = 'my_reports';     // key nav (lihat 8.3)
$pageScripts = [];             // opsional: ['chart'] untuk memuat Chart.js, ['attendance'] untuk js/attendance.js
include __DIR__ . '/../views/layout/start.php';
?>
  <?= page_header('My reports', 'Your submitted work', '<a class="btn btn-primary" href="report_form.php">' . icon('plus','size-4') . 'New report</a>') ?>
  ... konten ...
<?php include __DIR__ . '/../views/layout/end.php'; ?>
```

Halaman **tidak boleh** lagi menulis `<!DOCTYPE>`, `<html>`, `<head>`, `<body>`, `<link output.css>`, `include header.php/footer.php/ui_head.php`.

Untuk halaman tanpa sidebar (login): set `$layout = 'bare';` sebelum include start.

### 8.2 `views/layout/start.php`

Urutan output:

1. `<!DOCTYPE html><html lang="en" data-theme-pref=".." data-theme="..">` (nilai awal dari `$_SESSION['user']['theme']`, sama seperti `header.php` sekarang).
2. `<head>`: charset, viewport, `<title><?= e($pageTitle) ?> · Vorta</title>`, preconnect + `<link>` Google Fonts `Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap`, `<link rel="stylesheet" href="css/output.css">`, lalu `include __DIR__ . '/../../public/ui_head.php'` (script tema, lihat 8.5). Selama Fase 1–4 tambahkan juga `<link rel="stylesheet" href="css/theme.css">` (legacy shim, §Fase 1). Bila `in_array('chart', $pageScripts)` → script Chart.js `https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js`.
3. `<body class="app <?= $hasTabbar ? 'has-tabbar' : '' ?>">`.
4. Jika `$layout === 'bare'`: buka `<main class="min-h-screen grid place-items-center p-4">` dan selesai.
5. Selain itu: `<aside class="sidebar" id="sidebar">` (brand, nav, user button + menu), `<div class="sidebar-scrim" data-sidebar-close hidden></div>`, `<div class="app-main">`, `<header class="topbar lg:hidden">`, lalu `<main class="page" id="main">`.

Sertakan juga skip link `<a href="#main" class="sr-only focus:not-sr-only ...">Skip to content</a>` di awal body.

### 8.3 Navigasi

```php
$role = $_SESSION['user']['role'];
if ($role === 'admin') {
    $nav = [
        ['key' => 'dashboard',   'href' => 'dashboard.php',         'label' => 'Dashboard',   'icon' => 'squares'],
        ['group' => 'Data'],
        ['key' => 'reports',     'href' => 'admin_reports.php',     'label' => 'Reports',     'icon' => 'document-text'],
        ['key' => 'attendance',  'href' => 'admin_attendance.php',  'label' => 'Attendance',  'icon' => 'clock', 'badge' => $notCheckedInToday],
        ['key' => 'master_data', 'href' => 'admin_master_data.php', 'label' => 'Master data', 'icon' => 'circle-stack'],
        ['group' => 'System'],
        ['key' => 'settings',    'href' => 'settings.php',          'label' => 'Settings',    'icon' => 'cog'],
    ];
} else {
    $nav = [
        ['key' => 'today',      'href' => 'dashboard.php',  'label' => 'Today',      'icon' => 'home'],
        ['key' => 'my_reports', 'href' => 'my_reports.php', 'label' => 'My reports', 'icon' => 'document-text'],
        ['key' => 'attendance', 'href' => 'attendance.php', 'label' => 'Attendance', 'icon' => 'clock'],
    ];
}
```

- `$notCheckedInToday` (admin saja): `COUNT` dengan query yang sama seperti `$totalStmt` di `admin_not_attendance.php` untuk tanggal hari ini. Badge disembunyikan bila 0.
- `report_form.php` dan `edit_report.php` memakai `$activeNav = 'my_reports'`. `account.php` tidak menandai item nav (user button diberi state aktif).
- **User menu** (dari tombol `.sidebar-user` dan avatar topbar), item: header kecil (nama + email), `Account` → `account.php`, `.menu-label` "Theme" + 3 item radio `Light` / `Dark` / `System` (ikon sun/moon/computer, item aktif diberi ikon check, memanggil `VortaUI.setTheme(value)`), `.menu-sep`, `Log out` → `logout.php`. Tidak ada lagi tombol logout merah.
- **Tabbar** (staff, < 768px): Today, Reports (`my_reports.php`), Attendance, Account. `$hasTabbar = ($role === 'staff')`.
- **FAB** "New report" ditampilkan oleh halaman Today dan My reports (bukan oleh layout): `<a class="fab md:hidden" href="report_form.php" aria-label="New report">`.

### 8.4 `views/layout/end.php`

1. Tutup `</main>`, `</div>` (app-main) bila bukan bare.
2. Tabbar staff.
3. `<div class="toast-region" id="toasts" aria-live="polite"></div>`.
4. Drawer host global (dipakai untuk konten remote):
   ```html
   <div class="drawer-scrim" data-drawer-scrim hidden></div>
   <aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden>
     <div class="drawer-header"><div><div class="drawer-eyebrow" data-drawer-eyebrow></div><h2 class="drawer-title" id="drawer-title" data-drawer-title></h2></div>
       <button class="btn btn-ghost btn-icon" data-drawer-close aria-label="Close"><?= icon('x-mark') ?></button></div>
     <div class="drawer-body" data-drawer-body></div>
     <div class="drawer-footer" data-drawer-footer></div>
   </aside>
   ```
5. `<script src="js/ui.js"></script>` + skrip tambahan sesuai `$pageScripts`.
6. Flash → toast: `<script>window.VORTA_FLASH = <?= json_encode(flash_take()) ?>;</script>` (ui.js menampilkannya saat load).
7. `</body></html>`.

### 8.5 `public/ui_head.php`

Sederhanakan: **hapus** semua yang terkait layout (`LAYOUTS`, `serverLayout`, `vorta-nav`, `data-nav`, `setLayout`, `getLayout`, akses `.vorta-shell`/`.vorta-backdrop`). Pertahankan: resolusi tema (`light`/`dark`/`system`), listener perubahan OS, `VortaUI.getThemePreference()`, `getTheme()`, `setTheme(pref, persist)`, persist ke `save_preferences.php` (hanya `theme=`), event `vorta:uichange`. Hapus juga `<link rel="stylesheet" href="css/theme.css">` dari file ini (dipindah ke layout start selama masa transisi).

---

## 9. JavaScript (`public/js/ui.js`)

Vanilla JS, tanpa dependensi, satu objek global `window.Vorta`. Semua perilaku dipasang lewat atribut `data-*` + event delegation sehingga markup server-rendered cukup memberi atribut.

```js
(function () {
  const $ = (s, el = document) => el.querySelector(s);

  // ---------- Toast ----------
  function toast(message, opts = {}) {
    // opts: { tone: 'ok'|'bad'|'info', actionText, actionHref, timeout = 4000 }
    // buat .toast (tambah .toast-bad bila tone bad) di #toasts, auto-hilang setelah timeout, tombol aksi opsional
  }

  // ---------- Confirm dialog ----------
  function confirmDialog({ title, message = '', confirmText = 'Confirm', cancelText = 'Cancel', tone = 'primary' }) {
    // buat <dialog class="dialog"> sekali (lazy), isi teks, tombol confirm .btn-primary atau .btn-danger (tone 'danger'),
    // showModal(), fokus ke tombol Cancel, resolve(true/false). Escape / klik backdrop = false.
    return new Promise(resolve => { /* ... */ });
  }

  // ---------- Dialog (form) ----------
  // [data-dialog-open="id"] → document.getElementById(id).showModal()
  // [data-dialog-close] di dalam dialog → close()
  // Klik pada backdrop (event.target === dialog) → close()

  // ---------- Drawer ----------
  const drawer = {
    open({ eyebrow = '', title = '', url = null, html = '', footer = '' }) {
      // isi header; bila url → tampilkan "Loading…" lalu fetch(url, {credentials:'same-origin'}) → body.innerHTML;
      // gagal → '<div class="alert alert-bad">Couldn't load this. Try again.</div>'
      // tampilkan scrim + drawer (hapus hidden, lalu tambah .is-open di frame berikutnya), kunci scroll body, fokus tombol close
    },
    openPanel(id) { /* drawer in-page (form master data): elemen .drawer dengan id tsb */ },
    close() { /* tutup drawer manapun yang terbuka, kembalikan fokus ke pemicu */ },
  };
  // [data-drawer-close], klik scrim, Escape → drawer.close()
  // tr[data-drawer-url] (klik baris, kecuali klik pada a/button/menu) → drawer.open({ url, title: row.dataset.drawerTitle, eyebrow: row.dataset.drawerEyebrow })
  // Baris juga harus bisa dibuka dengan keyboard: tabindex="0", Enter/Space.

  // ---------- Menu ----------
  // [data-menu-trigger] aria-controls="menu-id" aria-expanded → toggle #menu-id (hidden), posisi absolut relatif ke pemicu
  // (wrapper .relative). Klik di luar / Escape → tutup semua. Panah atas/bawah memindah fokus antar .menu-item.

  // ---------- Sidebar (mobile) ----------
  // [data-sidebar-open] → #sidebar.is-open + scrim; [data-sidebar-close] / Escape → tutup

  // ---------- Period picker ----------
  // .period label click → input.showPicker?.() ; input change → input.form.submit()

  // ---------- Confirm links/forms ----------
  // a[data-confirm] atau form[data-confirm]: cegah default, panggil confirmDialog({
  //   title: el.dataset.confirm, message: el.dataset.confirmMessage, confirmText: el.dataset.confirmText, tone: el.dataset.confirmTone })
  // → bila true: lanjutkan (location = href / form.submit()).

  // ---------- Theme menu ----------
  // [data-theme-set="light|dark|system"] → VortaUI.setTheme(value); update tanda aktif;
  // dengar 'vorta:uichange' untuk sinkron.

  // ---------- Flash ----------
  // DOMContentLoaded: (window.VORTA_FLASH || []).forEach(f => toast(f.message, { tone: f.tone }))

  window.Vorta = { toast, confirm: confirmDialog, drawer };
})();
```

Implementasikan lengkap; komentar di atas adalah spesifikasi perilaku.

---

## 10. Copy deck

Pakai **sentence case** di semua label, judul, dan tombol ("New report", bukan "New Report"). Tanpa tanda seru. Tanpa "Please" di label tombol.

| Tempat | Teks |
|---|---|
| Nav admin | Dashboard · Reports · Attendance · Master data · Settings |
| Nav staff | Today · My reports · Attendance |
| User menu | Account · Theme (Light / Dark / System) · Log out |
| `<title>` | `{Page} · Vorta`; login: `Sign in · Vorta` |
| Dashboard admin | title "Dashboard", subtitle "Production overview" |
| Today | title "Today", subtitle = `fmt_date(today,'full')` |
| Reports admin | title "Reports", subtitle "Everything staff submitted"; tab "All reports", "Today's completion" |
| My reports | title "My reports", subtitle "Your submitted work"; tombol "New report" |
| Form | back "My reports"; title "New report" / "Edit report"; tombol "Save report" / "Save changes", "Cancel" |
| Attendance staff | title "Attendance", subtitle "Full-time employee" atau "Intern" |
| Attendance admin | title "Attendance", subtitle "Check-ins, leave and absences"; tab "Daily", "Monthly", "Not checked in"; tombol "Export" |
| Master data | title "Master data", subtitle "People and lists used in reports"; tab "Users", "Employees", "Work forces", "Job types"; tombol "Add user" / "Add employee" / "Add work force" / "Add job type" |
| Settings | title "Settings", subtitle "Targets used across dashboards and reports" |
| Account | title "Account", subtitle "Your profile and preferences"; sub-nav "Profile", "Appearance", "Password" |
| Login | heading "Sign in", tombol "Sign in", error "Email or password is incorrect." |
| Status laporan | "In progress", "Completed" |
| Target bulanan | "On track", "Behind", "At risk" |
| Completion harian | "Complete", "{n} of {min}" (warn), "No reports" |
| Absensi | "Present", "Late", "Leave", "Sick", "Others", "Absent", "Forgot to check in", "Not checked in" |
| Toast | "Report saved", "Report updated", "Couldn't save the report. Try again.", "Marked as completed", "Report deleted", "Checked in", "Checked out", "Leave requested", "Absence reported", "Saved", "Password changed", "Profile updated" |
| Confirm hapus laporan | title "Delete this report?", message "“{title}” on {fmt_date} will be removed. This can't be undone.", tombol "Delete report" (danger) |
| Confirm mark completed | title "Mark as completed?", message "Completed reports can't be edited.", tombol "Mark completed" |
| Confirm check out | title "Check out now?", message "Your check-out time will be {HH:MM}.", tombol "Check out" |
| Confirm hapus master data | title "Delete {entity}?", message "“{name}” will be removed. This can't be undone.", tombol "Delete" (danger) |
| Empty: my reports | "No reports in {Oct 2026}" / "Reports you submit this month will appear here." + tombol "New report" |
| Empty: reports admin | "No reports match these filters" / "Try another month or clear the filters." |
| Empty: attendance history | "No attendance records in {Oct 2026}" |
| Empty: not checked in | "Everyone has checked in" / "All staff have an attendance record for {date}." |

---

## Fase 0: Persiapan

1. `git checkout -b ui-rework` (dari `main`). Jangan sentuh perubahan uncommitted di `migrations/schema_version.php`.
2. Pastikan tool tersedia: `php -v` (8.x), `node -v`, lalu di `vorta-app/`: `npm install`.
3. Tambahkan script ke `package.json` (pertahankan script `build` yang ada):
   ```json
   "scripts": {
     "build": "tailwindcss -i ./src/css/input.css -o ./public/css/output.css --watch",
     "css": "tailwindcss -i ./src/css/input.css -o ./public/css/output.css --minify"
   }
   ```
   Build sekali: `npm run css`. Tailwind v4 mendeteksi sumber otomatis dari folder kerja; pastikan dijalankan dari `vorta-app/` sehingga `public/`, `views/`, `lib/` ikut terpindai. Bila class di `views/` atau `lib/` tidak ikut ter-generate, tambahkan di `input.css`: `@source "../../views"; @source "../../lib"; @source "../../public";`.
4. Menjalankan app lokal (butuh MySQL sesuai `lib/db.php` / env `DB_HOST, DB_NAME, DB_USER, DB_PASS`): dari `vorta-app/` jalankan `php -S localhost:8000`, buka `http://localhost:8000/public/index.php`. Seed: `php run_seeders.php` (admin default `admin@vorta.local` / `password123`). Bila DB tidak tersedia, lakukan verifikasi statis (`php -l`, grep) dan laporkan bahwa verifikasi visual tidak bisa dilakukan.

**DoD Fase 0:** branch ada, `npm run css` sukses.

---

## Fase 1: Fondasi

Tujuan: semua halaman memakai shell baru (sidebar navy, font baru, token) walau isi halaman masih lama.

### 1.1 Token & komponen
- Tulis `src/css/input.css` sesuai §5 + §6.
- `npm run css`.

### 1.2 Legacy shim `public/css/theme.css`
Halaman lama masih memakai `bg-white`, `text-gray-600`, dst. Agar tetap terbaca di dark mode selama transisi:
- **Hapus** dari `theme.css`: semua blok `.vorta-*`, `[data-nav]`, `.vorta-tool*`, `.vorta-logout`, `.vorta-burger`, `.vorta-sidebar-toggle`, `.vorta-backdrop`, dan `:root`/`[data-theme]` lama.
- **Pertahankan** sementara blok override class Tailwind (`.bg-white`, `.text-gray-*`, `.border-gray-*`, `input, select, textarea`, varian `[data-theme="dark"] .bg-green-100`, dst.), tetapi ganti referensi variabel lama ke token baru: `--surface`→`--vt-surface`, `--surface-2`→`--vt-surface-2`, `--surface-3`→`--vt-surface-2`, `--border`→`--vt-line`, `--border-strong`→`--vt-line-strong`, `--text`→`--vt-ink`, `--text-muted`→`--vt-muted`, `--text-faint`→`--vt-faint`, `--brand`→`--vt-accent`, `--brand-soft`→`--vt-accent-soft`, `--brand-soft-text`→`--vt-accent`, `--app-bg`→`--vt-canvas`, `--shadow-card`→`none`.
- **Pertahankan** `.pref-switch` hanya sampai Account selesai (Fase 4), lalu hapus.
- Tambahkan komentar di atas file: `/* LEGACY SHIM — delete in Phase 5 */`.

### 1.3 Helper & layout
- Buat `lib/ui.php`, `lib/icons.php` (§7), `views/layout/start.php`, `views/layout/end.php` (§8), `public/js/ui.js` (§9).
- Sederhanakan `public/ui_head.php` (§8.5).

### 1.4 Migrasi mekanis semua halaman ke shell
Untuk **setiap** file berikut: `dashboard.php`, `report_form.php`, `edit_report.php`, `my_reports.php`, `attendance.php`, `admin_reports.php`, `admin_attendance.php`, `admin_not_attendance.php`, `admin_master_data.php`, `profile.php`, `edit_profile.php`, `change_password.php`:

1. Tambah `require_once __DIR__ . '/../lib/ui.php';`.
2. Ganti `include __DIR__ . '/header.php';` dengan set `$pageTitle`, `$activeNav` (+ `$pageScripts = ['chart']` untuk `dashboard.php` dan `my_reports.php`) lalu `include __DIR__ . '/../views/layout/start.php';`.
3. Hapus blok duplikat setelahnya: `<!DOCTYPE html>`, `<html>`, seluruh `<head>…</head>`, `<body>` pembuka, dan `</body></html>` penutup. **Pindahkan** `<style>` khusus halaman yang masih diperlukan (mis. `.stat-card`, `.donut-wrap` di dashboard, `.prof-*` di profile) ke dalam konten (sebelum markup) untuk sementara — akan dihapus saat halaman di-rework.
4. Ganti `include __DIR__ . '/footer.php';` dengan `include __DIR__ . '/../views/layout/end.php';` dan pastikan `<script>` halaman berada **sebelum** include end (atau setelah, asalkan sebelum `</body>` — end.php yang mencetak `</body>`; letakkan skrip halaman sebelum include end).
5. `dashboard.php` khusus: hapus `<head>` sendiri dan include `ui_head.php` miliknya.
6. **Master data** (`admin_master_data.php`): tab file melakukan POST handling **setelah** output dimulai, sehingga memakai redirect JavaScript (`echo "<script> window.location.href = ..."`). Ubah alurnya:
   ```php
   // admin_master_data.php — sebelum layout
   $active_tab = $_GET['tab'] ?? 'users';
   if ($active_tab === 'settings') { header('Location: settings.php'); exit; } // aktif setelah settings.php ada (Fase 4); sebelum itu biarkan tab settings tetap jalan
   ob_start();
   include __DIR__ . '/admin_master_data/' . $tabFile;   // tab file boleh header()+exit karena output masih di-buffer
   $tabHtml = ob_get_clean();
   // lalu layout start, tabs, echo $tabHtml, layout end
   ```
   Di setiap tab file, ganti `echo "<script> window.location.href = '$redirect'; </script>"; exit;` dengan `header('Location: ' . $redirect); exit;`. Whitelist `$tabFile` dari array tab yang ada (jangan pakai input user langsung sebagai path).
7. `php -l` setiap file.

SweetAlert masih boleh ada di fase ini (dihapus per halaman di Fase 3–4), tapi pindahkan `<script src="...sweetalert2...">` ke bagian konten halaman yang membutuhkannya.

**DoD Fase 1:**
- Semua halaman terbuka tanpa error PHP, dengan sidebar navy, font Plus Jakarta Sans, dan user menu (Account link boleh 404 sementara sampai Fase 4 — arahkan sementara ke `profile.php`).
- `view-source` setiap halaman: tepat **satu** `<!DOCTYPE`, satu `<html>`, satu `<head>`, satu `<body>`.
- Toggle tema dari user menu bekerja dan tersimpan setelah reload; tidak ada lagi tombol layout.
- Di < 1024px: topbar + hamburger membuka sidebar; Escape/scrim menutup.
- `grep -rn "data-nav\|setLayout\|vorta-shell\|vorta-logout" public views lib` → kosong (kecuali `lib/preferences.php` yang memang dibiarkan).

---

## Fase 2: Komponen & UI kit

1. Lengkapi helper `page_header`, `period_picker`, `pagination`, `kpi_strip`, `empty_state` (§7.1) dan semua class §6.
2. Buat `public/ui_kit.php` (admin-only, `require_admin()`) yang menampilkan semua komponen dengan data contoh: tombol (4 varian + kecil + ikon + disabled), field + error, seg, tabs (dengan count), toolbar + period picker (month & date), tabel + pagination + empty state, semua pill dari peta status, KPI strip (termasuk tone bad), progress dengan marker, segbar, daybars, alert 4 tone, timeline, dropzone, menu ⋯, tombol yang membuka confirm dialog, dialog form, drawer remote (pakai `admin_reports.php?detail=<id pertama>`), toast 3 tone.
3. Cek ui_kit di light & dark, lebar 375 / 768 / 1280.

**DoD Fase 2:** semua komponen di ui_kit tampil benar di kedua tema; dialog/drawer/menu bisa dibuka-tutup dengan mouse dan keyboard (Tab, Enter, Escape); fokus kembali ke pemicu setelah ditutup.

---

## Fase 3: Halaman staff

Urutan: 3.1 Attendance (karena dialognya dipakai Today) → 3.2 Today → 3.3 My reports → 3.4 Form laporan.

### 3.1 `attendance.php` (staff) — wireframe "Attendance (staff)"

**Logika:** pertahankan semua POST handler (`submitAbsenceReason`, `submitLeave`, `submitCheckIn`, `check_out`) dan query. Ganti `header("Location: attendance.php…")` → `attendance_redirect('…')` (§7.4). Ganti rantai ternary pesan dengan `attendance_messages()`.

**Ekstrak** tiga modal (absence reason, check-in, leave) ke `views/attendance/dialogs.php` sebagai `<dialog class="dialog" id="dlg-checkin|dlg-leave|dlg-absence">`. Field `name`, `required`, opsi `<select>`, `csrf_field()`, dan tombol submit `name="submitCheckIn|submitLeave|submitAbsenceReason"` **sama persis**. Tambah `<input type="hidden" name="return_to" value="<?= e($returnTo) ?>">` di tiap form (`$returnTo` diset oleh halaman pemanggil: `attendance.php` atau `dashboard.php`). Semua form `action="attendance.php" method="post"`. Copy:
- Check in: title "Check in", teks "Morning 07:30–11:59 · Afternoon 13:00–17:30", field "Shift", "Location" (placeholder "Office, WFH, client site"), "Reason for being late" (tersembunyi sampai JS mendeteksi terlambat; helper "At least 10 characters"), tombol "Check in".
- Leave: title "Request leave", field "Type" (Sick / Leave / Others), "Reason", tombol "Request leave".
- Absence: title "Report an absence", teks "For today only, until 23:59.", field "Type" (opsi sama seperti sekarang), "Reason" (helper "At least 10 characters"), tombol "Report absence". Kotak kuning "Note:" diganti satu baris `.help`.

Pindahkan JS hint shift/terlambat yang ada ke `public/js/attendance.js` tanpa mengubah logika ambangnya; sesuaikan selector ke id baru. Muat lewat `$pageScripts = ['attendance']`.

**Markup halaman:**
1. `page_header('Attendance', $isIntern ? 'Intern' : 'Full-time employee')`.
2. Alert dari `?error=` (alert-bad) / `?success=` (alert-ok) memakai `attendance_messages()`.
3. **Kartu hari ini** (`.card`, `.card-body` flex wrap, gap 24px):
   - kiri: `.section-label` = `fmt_date(today,'day')`, jam sekarang `.big-number` (format `H:i` dari server; boleh diperbarui JS tiap menit).
   - tengah: `.timeline` dua langkah "Check in" (waktu + lokasi) → "Check out".
   - kanan: `status_pill($attendance['status'])` bila ada + **satu** aksi utama sesuai `attendance_state()`:
     - `none` → `<button class="btn btn-primary" data-dialog-open="dlg-checkin">Check in</button>`; di bawahnya baris kecil: `<button class="link" data-dialog-open="dlg-leave">Request leave</button>` dan, bila `$can_input_absence`, `· <button class="link" data-dialog-open="dlg-absence">Report an absence</button>`.
     - `checked_in` → form checkout yang ada (`csrf_field()`, hidden `check_out=1`, `return_to`) dengan `data-confirm="Check out now?"` + `data-confirm-message` + `data-confirm-text="Check out"`; tombol `btn-primary` "Check out".
     - `checked_out` → teks muted "Done for today".
     - `away` → tanpa tombol; tampilkan `explanation` sebagai teks muted di bawah pill.
   - Hapus 3 tombol warna lama dan 5 kotak abu-abu.
4. **KPI strip** bulan berjalan untuk bulan yang dipilih: Present, Late, Leave, Sick, Absent (Absent = Absent + Forgot). Tambah satu query `SELECT status, COUNT(*) FROM attendance WHERE user_id=? AND date BETWEEN ? AND ? GROUP BY status`.
5. Baris "History" (`.card-title`) + `period_picker('month', $month)` di kanan.
6. Tabel history: Date (`fmt_date(...,'day')`), In (`fmt_time`, `.num`), Out, Status (pill), Location, Note (explanation, `truncate max-w-[260px]` + `title` penuh). Page size **20** (ubah `$limit = 7` → 20). Footer `pagination()`. Empty state bila kosong.

**DoD 3.1:** semua alur (check-in tepat waktu, check-in terlambat dengan/tanpa alasan → error tampil, check-out dengan konfirmasi, leave, absence reason, mencoba leave setelah check-in → error) tetap bekerja dan redirect kembali ke `attendance.php`. Tidak ada SweetAlert/modal lama di file ini.

### 3.2 Today — `dashboard.php` (staff) → `views/dashboard/today.php`

`dashboard.php`: setelah `require_login()`, bila role admin jalankan logika admin yang ada lalu render `views/dashboard/admin.php` (Fase 4.1); bila staff jalankan logika Today di bawah lalu render `views/dashboard/today.php`. `$activeNav = 'today'` untuk staff, `'dashboard'` untuk admin. `$pageTitle` = "Today" / "Dashboard".

**Data (staff):**
- `$attendance` hari ini (query seperti di `attendance.php`), `$state = attendance_state($attendance)`, `$can_input_absence` (logika sama dengan `attendance.php`), `$isIntern`.
- `$todayReports`: laporan user hari ini (`title`, `job_type`, `status`, `report_id`), urut terbaru.
- `$dailyMin`, `$target`.
- `$monthCount`: jumlah laporan bulan ini; `$dailyCounts = report_daily_counts(...)` untuk bulan ini.
- `$weekdaysLeft`: jumlah hari Senin–Jumat dari besok sampai akhir bulan (label "weekdays left").
- Ranking: pakai query `$users` dari dashboard lama (bulan ini); ambil top 3 + posisi user sendiri (rank 1-based).

**Markup (wireframe "Today"):**
1. `page_header('Today', fmt_date(today,'full'), tombol "New report" btn-primary → report_form.php)`.
2. Alert dari `?error=`/`?success=` (sama seperti 3.1, karena form attendance bisa redirect ke sini).
3. Grid 2 kolom (`grid gap-4 lg:grid-cols-2`):
   - **Card "Attendance"**: header title + pill status (atau pill neutral "Not checked in"). Body: jam `.big-number` + aksi sesuai state persis seperti 3.1 (pakai `views/attendance/dialogs.php` dengan `$returnTo = 'dashboard.php'`, checkout form dengan `return_to`). Untuk state `checked_in`: tampilkan "Checked in at 07:52 · Office".
   - **Card "Today's reports"**: header meta "minimum {dailyMin} per day". Body: `.big-number` "{n}" + `<small>/ {dailyMin}</small>`, pill (`n >= min` → ok "Complete"; `0 < n < min` → warn "{min-n} more needed"; 0 → bad "No reports yet"), `.segbar` dengan {dailyMin} segmen, daftar laporan hari ini (judul 600, job type muted, pill status) maksimal 5 + link "View all" ke `my_reports.php`. Empty: teks muted "Nothing yet today." + `.link` "Add a report".
4. Grid `lg:grid-cols-3`:
   - **Card "This month"** (`lg:col-span-2`): baris "{monthCount} reports" dan kanan teks muted: bila `monthCount < min` → "{min - monthCount} more to reach {min} · {weekdaysLeft} weekdays left", bila ≥ min → "Minimum reached · max {max}". `.progress.progress-lg` lebar fill `min(100, count/max*100)%`, marker di `min/max*100%` (label "min {min}") dan di 100% (label "max {max}"). Di bawahnya `.daybars` semua hari bulan ini, garis min harian, sumbu (1, 8, 15, 22, akhir bulan).
   - **Card "Team ranking"**: tabel ringkas tanpa header: rank (muted), nama (format "Dimas P."), jumlah (`.num`). Top 3, lalu baris user sendiri disorot (`bg-accent-soft`) bila tidak termasuk top 3; bila termasuk, sorot barisnya.
5. FAB mobile.

**DoD 3.2:** staff yang login langsung melihat Today; check-in/out/leave/absence dari Today kembali ke Today dengan toast/alert yang benar; angka cocok dengan My reports dan Attendance.

### 3.3 `my_reports.php` — wireframe "My reports"

**Logika:** pertahankan handler `?proof_image=` & `?detail=` (pakai `lib/reports.php`, render `views/reports/detail.php`). `$month = valid_month($_GET['month'] ?? null)`. Page size 7 → **20**. Filter baru (aditif): `?status=Progress|Completed` (kosong = semua) dan `?q=` (LIKE pada `title`); terapkan ke query count & list.

**Markup:**
1. `page_header('My reports', 'Your submitted work', tombol "New report")`.
2. Toast dari query lama: `?success=report_saved` → "Report saved"; `?edit=success` → "Report updated"; `?edit=error` → tone bad "Couldn't save the report. Try again." (panggil `Vorta.toast` dari script inline; lalu hapus param dari URL dengan `history.replaceState`).
3. **Panel ringkasan** (`.card .card-body`, grid `md:grid-cols-[240px_1fr]`, gap 24px): kiri `.section-label` `fmt_month($month)`, `.big-number` jumlah laporan bulan itu + `<small>reports</small>`, `.progress.progress-lg` dengan marker min & max. Kanan `.daybars` untuk bulan terpilih (gantikan Chart.js bar chart; hapus `$pageScripts = ['chart']` untuk halaman ini).
4. Toolbar: `period_picker('month', $month, 'month', ['page'])`, `.seg` link All / In progress / Completed (`query_url(['status'=>…, 'page'=>null])`), form search `?q=` (input `.input` dengan ikon), `.toolbar-count` "{total} reports".
5. Tabel (`.card` > `.table-wrap` > `.table`): kolom Date (`fmt_date(...,'short')`), Title (judul `.cell-strong` + `.cell-sub` job type), Work force, Status, kolom aksi.
   - Baris: `class="is-clickable" tabindex="0" data-drawer-url="my_reports.php?detail={id}" data-drawer-title="{title}" data-drawer-eyebrow="Report"`, `id="row-{id}"`.
   - Kolom Status: `status_pill`; bila `Progress`, tambahkan `<button class="link text-[13px] ml-2" data-mark-done="{id}">Mark completed</button>`.
   - Kolom aksi: tombol `⋯` (`btn btn-ghost btn-icon btn-sm`, `aria-label="Actions for {title}"`, `data-menu-trigger`) + `.menu` berisi: "View details" (buka drawer), "Edit" → `edit_report.php?id=` (bila `Completed`: `aria-disabled="true"` + `title="Completed reports can't be edited"`, bukan link), sep, "Delete…" (`.menu-item-danger`, `data-delete-report="{id}"`).
   - Hapus kolom Proof dan fungsi `showProof` (bukti dilihat di drawer).
6. `pagination()` di footer card. Empty state sesuai copy deck.
7. JS halaman (ganti SweetAlert):
   - Mark completed: `Vorta.confirm({title:'Mark as completed?', message:"Completed reports can't be edited.", confirmText:'Mark completed'})` → `fetch('update_status_ajax.php', …)` body **sama** (`report_id=..&action=mark_done`) → sukses: ganti pill jadi Completed, hapus tombol, ubah item menu Edit jadi disabled, `Vorta.toast('Marked as completed')`; gagal → toast bad dengan `d.message` atau "Couldn't update the status. Try again.".
   - Delete: `Vorta.confirm({tone:'danger', ...})` → `fetch('delete_report_ajax.php', …)` body sama (`report_id=`) → sukses: hapus baris, toast "Report deleted"; gagal: toast bad `d.message`.

### 3.4 `report_form.php` & `edit_report.php` — wireframe "New report / Edit report"

**Logika:** pertahankan query (job types, work forces, data laporan untuk edit, guard "completed tidak bisa diedit" → redirect). Tambah (aditif): `$lastWorkforceId` = `workforce_id` laporan terakhir user (dipakai sebagai default `selected` hanya di form baru); `$todayCount = report_count_on($pdo, $user_id, date('Y-m-d'))`.

**Partial `views/reports/form_fields.php`** dipakai kedua halaman, variabel masukan: `$report` (array atau null), `$job_types`, `$work_forces`, `$lastWorkforceId`. Field `name` **tetap**: `report_date`, `job_type`, `title`, `workforce_id`, `description`, `status`, `proof_link`, `proof_image` (+ hidden field yang sudah ada di edit, mis. `report_id`, dan `csrf_field()` bila ada). Susunan (card dengan beberapa `.fsec`-like section, gunakan `.card-body` + pemisah border):
1. Section "What did you work on?": Title (placeholder "e.g. Login page, Export PDF feature"), grid 2 kolom Job type (`.select`, opsi pertama "Select job type") & Work force (helper "Remembered from your last report" bila terisi otomatis), Description (`.textarea`, label + "(optional)", placeholder "What changed, what's left…").
2. Section "Proof": Link (`type=url`, placeholder "https://", label "Link (repo, Figma, screenshot)"), dropzone foto: `<label class="dropzone" for="proof_image">` berisi thumbnail `<img hidden>`, teks "Drop a photo or browse", helper "JPG, PNG or WebP, up to 1 MB", tombol "Remove" (`.btn-ghost .btn-sm`, hidden sampai ada file). Input file `accept="image/jpeg,image/png,image/webp"` dengan `class="sr-only"`. Di edit: bila ada foto lama, tampilkan thumbnail lama (`my_reports.php?proof_image={id}`) dengan teks "Current photo. Choose a new one to replace it." — perilaku replace/keep mengikuti `update_report.php` yang ada (baca dulu file itu; jangan ubah kontraknya).
3. Section tanpa judul: grid 2 kolom Date (`type=date`, default hari ini) & Status sebagai `.seg` radio (`name="status"`, value `Progress` label "In progress", value `Completed` label "Completed"; default Progress, di edit sesuai data).
4. Footer card: `Cancel` (`btn-ghost`, ke `my_reports.php`) + submit `btn-primary` "Save report" / "Save changes".

Halaman (`report_form.php`/`edit_report.php`): `page_header` dengan back link "My reports"; layout `grid gap-4 lg:grid-cols-[1fr_300px] items-start`: kiri form, kanan card "Today" (big number `{todayCount} / {dailyMin}`, pill, segbar) — hanya di form baru; di edit, kartu kanan diganti card kecil "Editing" berisi tanggal & status asli.

**JS:** validasi file client (ukuran ≤ 1 MB, tipe jpeg/jpg/png/webp) → pesan `.field-error` di bawah dropzone + `aria-invalid`, kosongkan input; preview pakai `FileReader` ke thumbnail; "Remove" mengosongkan input. Drag & drop ke dropzone mengisi input (`DataTransfer`). Submit: cegah double submit (tombol disabled + teks "Saving…"). **Hapus** konfirmasi "Is the data correct?" dan semua SweetAlert. Hapus `<p>` "Policy:" box.

**DoD Fase 3:** semua halaman staff tidak memuat SweetAlert/Font Awesome; alur create (dengan & tanpa foto), edit, mark completed, delete, filter, pagination bekerja; toast muncul setelah save/update; tampilan cocok dengan wireframe di desktop dan 375px.

---

## Fase 4: Halaman admin, Account, Login

### 4.1 Dashboard admin → `views/dashboard/admin.php` — wireframe "Dashboard (admin)"

**Logika:** pertahankan query `$users`, `$typeRows`, statistik. `$month = valid_month(...)`. Hapus pagination (tampilkan semua staff aktif). Tambah: `$todayStats = report_daily_completion_stats($pdo, date('Y-m-d'), $dailyMin)`.

**Markup:**
1. `page_header('Dashboard', 'Production overview', period_picker('month', $month))`.
2. `kpi_strip`: Reports (`$totalReportsMonth`, note `fmt_month`), Active staff (`$totalStaff`), On track (`$onTrackStaff` + suffix "/ {totalStaff}", note "≥ {min} reports this month"), Avg per staff (`$avgPerStaff`, note "target {min}–{max}").
3. Grid `lg:grid-cols-5`:
   - Card "Staff performance" (`lg:col-span-3`), meta "Target {min}–{max} / month". Tabel: rank (muted), Staff (+ `role_pill` bila admin, seperti sekarang), Reports (`.num`), "Progress to {max}" (`.progress` + marker min), Status (`monthly_target_status` → `status_pill(null, tone, label)`). Footer: "Showing all {n} staff · marker = minimum {min}".
   - Card "Job types" (`lg:col-span-2`), meta "{typeTotal} reports". Donut Chart.js (pertahankan logika "Others" & `$maxSlices`), warna dari CSS var: baca `getComputedStyle(document.documentElement).getPropertyValue('--vt-c1')` … `--vt-c6`, "Others" pakai `--vt-c5`; `borderColor` = `--vt-surface`; update saat event `vorta:uichange`. Legend pakai `.legend`. Empty state bila tidak ada data.
4. Card strip "Today": `card-body` flex wrap gap: judul "Today · {fmt_date(today,'day')}", tiga pasangan pill + angka (Complete / "Partial" (warn) / No reports), spacer, `.link` "Open daily completion →" ke `admin_reports.php?tab=today`.
5. Hapus `.chart-note` "Rule: …", semua `<style>` lama halaman ini, kartu stat dengan ikon berwarna.

### 4.2 `admin_reports.php` — wireframe "Reports (admin)"

**Logika:** `?tab=all|today` (default all). Pertahankan handler `?proof_image=` & `?detail=` (pakai lib + `views/reports/detail.php`).
- Tab **All**: page size 5 → **20**. Filter aditif ke query count & list: `?q=` (LIKE `pr.title` OR `u.name`), `?user_id=` (int), `?job_type=` (string, cocokkan `pr.job_type`), `?status=` (`Progress|Completed`). Data dropdown: user aktif (`SELECT user_id, name FROM users WHERE is_active=1 ORDER BY name`), job type (`SELECT name FROM job_type ORDER BY name`).
- Tab **Today**: logika "Daily Report Completion" yang ada (`$shortRows`, `$stats`); page size 10 → 20; param `short_page` tetap.
- Badge tab Today = `partial + zero` (dari `report_daily_completion_stats`).

**Markup:**
1. `page_header('Reports', 'Everything staff submitted')` (tanpa tombol Export — tidak ada backend export laporan).
2. `.tabs`: "All reports", "Today's completion" + `.tab-count` "{n} missing" bila n > 0 (link `query_url(['tab'=>…])` dengan param halaman di-reset).
3. Tab All:
   - Toolbar: `period_picker('month', …)`, search, `.select` Staff (opsi "All staff"), `.select` Job type ("All job types"), `.select` Status ("Any status"); select auto-submit on change (form GET, sertakan hidden `tab`, `month`), `.toolbar-count` "{total} reports". Link "Clear filters" (`.link`) bila ada filter aktif.
   - Tabel: Date (`short`), Staff, Title (+ sub job type), Work force, Status, Proof (ikon `link` dan/atau `photo` 16px muted dengan `aria-label`; "–" bila tidak ada). Baris `is-clickable` dengan `data-drawer-url="admin_reports.php?detail={id}"`. Hapus tombol "Detail" dan modal lama.
   - `pagination()`; empty state.
4. Tab Today:
   - Subjudul baris: `.card-title` "Daily completion · {fmt_date(today,'long')}" + tombol `btn-secondary btn-sm` "Refresh" (reload).
   - `kpi_strip`: Complete, Partial, No reports (tone bad bila > 0), Total active.
   - `.progress` completion "{complete} of {total} staff complete".
   - Tabel desktop: Staff (nama + sub email), Position, Reports (`.num`), Submitted work (judul truncate + sub "Types: …"), Status (`status_pill(null, tone, label)`: ≥ min → ok "Complete", >0 → warn "{n} of {min}", 0 → bad "No reports"). Kartu mobile (`md:hidden`) memakai struktur yang sama dengan class komponen.
   - `pagination(... 'short_page')`.
5. Detail fragmen `views/reports/detail.php`: tanpa inline style. Isi: baris pill status; `.dl` Date (`long`), Submitted by, Job type, Work force; `.section-label` "Description" + paragraf (atau teks muted italic "No description."); `.section-label` "Proof": link (`.card` kecil flex: ikon, URL `break-all`, `btn-secondary btn-sm` "Open" `target=_blank rel=noopener`) dan foto (`<img>` rounded, max-h 320px, link buka tab baru, fallback "The photo couldn't be loaded."). Judul laporan tidak diulang di body (sudah di header drawer).
6. (Opsional, bila waktu cukup) tombol Prev/Next di footer drawer berdasarkan urutan baris di halaman.

### 4.3 `admin_attendance.php` + `admin_not_attendance.php` — wireframe "Attendance (admin)"

**Logika:**
- `?tab=daily|monthly|missing`. Kompatibilitas: bila `?recap_type=monthly` tanpa `tab` → tab monthly.
- Pindahkan query dari `admin_not_attendance.php` (not checked in + total + present) ke tab **missing** (param `date`, `page` → pakai `missing_page`). `admin_not_attendance.php` menjadi: `header('Location: admin_attendance.php?tab=missing&date=' . urlencode(valid_date($_GET['date'] ?? null))); exit;`.
- Daily: tambah KPI query aditif untuk tanggal terpilih: Present (status Present), Late, Leave/sick (Leave+Sick+Others), Not checked in (pakai count dari query missing). Page size daily 10 → 20.
- Monthly: pertahankan query & `$monthlyTotals`; page size 10 → 20.
- Export: pertahankan URL `export_excel.php?recap_type=...&date=...&month=...&notes=...&user_id=...` dengan `recap_type` = `daily` atau `monthly` sesuai tab. Sembunyikan tombol Export di tab missing.

**Markup:**
1. `page_header('Attendance', 'Check-ins, leave and absences', tombol btn-secondary ikon download "Export")`.
2. `.tabs`: Daily · Monthly · Not checked in (+ count bila > 0, untuk tanggal terpilih/hari ini).
3. Daily: toolbar `period_picker('date', $date, 'date', ['daily_page'])`, `.select` Shift (opsi dari `$validNotes` yang ada; label ramah: "Present: Morning" → "Morning (on time)" dst. hanya pada label, value tetap), search nama (aditif `?q=`); `kpi_strip` 4 item; tabel: Staff (nama + sub email), In, Out (`.num`, `fmt_time`), Status pill, Location, Note (explanation truncate). `pagination(..., 'daily_page')`.
4. Monthly: toolbar `period_picker('month', …, ['monthly_page'])` + `.select` Staff ("All staff"); bila tanpa filter staff: `kpi_strip` 5 item (Present morning, Present afternoon, Late, Leave, Sick) — **hapus** kotak biru dengan angka warna-warni; tabel kolom yang sama seperti sekarang, header singkat ("Morning", "Afternoon", "Invalid", "Late", "Leave", "Sick", "Present", "Absent"), semua angka `.num`. `pagination(..., 'monthly_page')`.
5. Missing: toolbar `period_picker('date', …, ['missing_page'])`; `kpi_strip` Total staff, Checked in, Not checked in (tone bad); tabel Staff (nama + email), Position (`.pill-role` netral); empty state "Everyone has checked in".

### 4.4 Master data — wireframe "Master data"

**Shell (`admin_master_data.php`):** tab: `users` (default), `employees`, `work_force`, `job_type`. `settings` → redirect `settings.php`. Pola `ob_start` dari Fase 1. Markup: `page_header('Master data', 'People and lists used in reports')`, `.tabs` (link `?tab=`), lalu `$tabHtml`. Hapus `<select id="tab-selector">` mobile (tabs sudah bisa di-scroll horizontal). Hapus card pembungkus `bg-white border … p-6` (tidak ada card di dalam card).

**Setiap tab file** (`users.php`, `employees.php`, `work_force.php`, `job_type.php`):
1. Logika POST/GET tetap (create/update/delete, validasi, pesan). Ganti `$_SESSION['success'] = …` / `$_SESSION['error'] = …` → `flash_set('ok'|'bad', …)`; hapus blok alert sukses/error di atas; redirect pakai `header()` (Fase 1). Untuk error validasi yang tidak redirect, lakukan redirect juga (`flash_set('bad', …)` lalu `header('Location: …')`) agar perilakunya seragam.
2. Toolbar: form GET search (hidden `tab`, input `.input` placeholder "Search by name…" / "Search by name or email…", tombol "Search" `btn-secondary`, link "Clear" bila aktif) + spacer + `btn-primary` "Add …" (`data-drawer-panel="drawer-{entity}" data-mode="create"`).
3. Tabel dalam `.card`: **sembunyikan kolom ID**. Users: Name (+ sub email), Role (`role_pill`), aksi. Employees: Full name (+ sub user/email), Position, Phone, aksi (cek kolom yang ada di file; pertahankan semua data kecuali ID). Work forces / Job types: Name, aksi. Aksi = menu ⋯: "Edit" (memanggil fungsi edit yang ada, mis. `editWorkforce(id, name)`, yang sekarang mengisi form **lalu** `Vorta.drawer.openPanel('drawer-…')`), sep, "Delete…" (danger) → `Vorta.confirm` lalu navigasi ke URL delete GET yang sama seperti sekarang (`?tab=..&delete_work_force=id`).
4. Form dipindah ke drawer in-page: `<aside class="drawer" id="drawer-{entity}" hidden>` (+ scrim) berisi form yang **sama** (field, id, hidden `entity`/`action`/`*_id`, `csrf_field()` bila ada). Header drawer: eyebrow "Add user" / "Edit user" (JS ganti sesuai mode; gantikan `#form-title`), title = nama saat edit. Footer: "Cancel" (`data-drawer-close`, juga reset form ke mode create) + submit "Save" / "Add user". Role di Users memakai `.seg` radio bila field-nya `select role` → ubah ke radio `name="role"` value `staff|admin` (kontrak tetap: key `role` dengan nilai sama).
5. `pagination()` helper menggantikan fungsi `page_url()` dan blok `<nav>` manual (`$perPage` tetap 10 boleh dinaikkan ke 20).
6. Hapus SweetAlert.

Catatan: wireframe menampilkan kolom Position/Status dan "Deactivate user" di tab Users — **tidak diimplementasikan** (data/fitur tidak ada; Users & Employees belum digabung). Tetap pakai Delete yang ada.

### 4.5 `settings.php` (baru)

Admin-only. Pindahkan logika dari `admin_master_data/settings.php` (POST → `settings_save()`, CSRF bila ada). Terjemahkan pesan di `lib/settings.php` ke English: "All target fields are required.", "Targets must be greater than 0.", "Minimum can't be greater than maximum.", "Targets saved." Setelah POST: `flash_set` + redirect ke `settings.php`.

Markup: `page_header('Settings', 'Targets used across dashboards and reports')`; card "Report targets": grid 3 kolom field "Monthly minimum", "Monthly maximum", "Daily minimum" (`type=number min=1`), helper di bawah: "Staff are on track when they reach the monthly minimum. Each staff member should submit at least the daily minimum every working day."; footer card tombol "Save targets". Hapus tabel "Current Setting" (nilai sudah terlihat di input). Hapus `admin_master_data/settings.php` setelah redirect tab bekerja.

### 4.6 `account.php` (baru) — wireframe "Account"

Gabungan `profile.php` + `edit_profile.php` + `change_password.php`. **Baca ketiga file dan `lib/account.php` dulu**; pindahkan logika POST tanpa mengubah validasi. Bedakan POST dengan hidden `form=profile|password`. Setelah POST sukses: `flash_set('ok', 'Profile updated' | 'Password changed')` + redirect `account.php?section=…`; gagal: tampilkan alert-bad di section terkait (boleh lewat flash + redirect).

- `?section=profile|appearance|password` (default profile). Layout `grid gap-6 md:grid-cols-[180px_1fr]`: kiri sub-nav (link vertikal, aktif = bg surface + ring line), kanan section.
- **Profile:** card atas: `.avatar-lg` (inisial, navy solid), nama, email, pill position (`pill-role`) + pill status absensi hari ini (staff saja); di bawahnya form profil (Full name, Phone, Email & Position read-only dengan helper "Ask an admin to change this." — ikuti field yang memang editable di `edit_profile.php`). Untuk staff tambahkan baris kecil "Reports this month: {n} / {min}" + `.progress`. Admin tanpa baris `employees`: sembunyikan Phone/Position bila tidak bisa disimpan; jangan error.
- **Appearance:** card "Theme": `.seg` tiga tombol Light / Dark / System (`data-theme-set`), teks "Saved automatically." Hapus pilihan layout.
- **Password:** form yang sama dengan `change_password.php` (field current/new/confirm, toggle show pakai ikon `eye`/`eye-slash` dari `icon()`, aturan validasi & pesan tetap, tanpa SweetAlert — tampilkan error sebagai `.field-error` / alert).
- `profile.php`, `edit_profile.php` → `header('Location: account.php'); exit;` (pertahankan `#appearance` → `account.php?section=appearance` bila ada referensi). `change_password.php` → `account.php?section=password`. **Hati-hati:** bila POST masih mengarah ke file lama, pastikan form baru menunjuk `account.php`.
- Hapus Font Awesome & `.prof-*`, `.pref-switch` (lalu hapus juga dari legacy shim).

### 4.7 `index.php` (login) — wireframe "Sign in"

Logika login tetap (termasuk set `$_SESSION['user']` dengan `theme` & `nav_layout`). `$layout = 'bare'`, `$pageTitle = 'Sign in'`. Markup: logo (`../images/vorta.png`, 40px) + "Vorta" 18/800, card `max-w-sm w-full` `card-body` grid gap 16px: heading "Sign in" 20/700, alert-bad bila `$error` ("Email or password is incorrect."), field Email (`type=email`, `autocomplete=username`, value lama dipertahankan setelah gagal), field Password dalam `.input-group` dengan tombol "Show"/"Hide" (`autocomplete=current-password`), tombol `btn-primary btn-block` "Sign in". Footer kecil muted "Vorta Productivity Tracker". Hapus `@import` Inter.

**DoD Fase 4:** semua halaman admin, Account, dan login cocok dengan wireframe; Export Excel tetap menghasilkan file yang sama; CRUD master data, simpan target, ganti password, ubah profil bekerja; URL lama (`profile.php`, `edit_profile.php`, `change_password.php`, `admin_not_attendance.php`, `admin_master_data.php?tab=settings`) redirect dengan benar.

---

## Fase 5: Bersih-bersih & QA

1. Hapus (setelah `grep -rn "<nama file>" public views lib` kosong): `public/header.php`, `public/footer.php`, `public/css/theme.css` (+ `<link>`-nya di layout start), `public/get_report_detail.php`, `public/get_report_detail_user.php`, `public/ui_kit.php`, `public/admin_master_data/settings.php`, `src/css/output.css`.
2. Grep harus **kosong** (di `public`, `views`, `lib`):
   ```bash
   grep -rnE "sweetalert|Swal\.|font-awesome|class=\"fas? |indigo-|purple-|violet-|bg-gradient|linear-gradient|shadow-(md|lg|xl)|hover:scale|data-nav|vorta-shell|setLayout|<!DOCTYPE" public views lib --include=*.php
   ```
   Pengecualian yang boleh: satu `<!DOCTYPE` di `views/layout/start.php`; file export/AJAX yang tidak mencetak HTML.
3. Grep warna Tailwind langsung di halaman (seharusnya tidak ada, kecuali yang benar-benar beralasan dan dicatat): `grep -rnE "(bg|text|border)-(gray|red|green|yellow|blue|slate)-[0-9]" public views`.
4. `for f in $(git ls-files '*.php') views/**/*.php lib/*.php; do php -l "$f"; done` — tanpa error (atau loop setara di PowerShell).
5. `npm run css` dan pastikan `public/css/output.css` ter-update.
6. Jalankan checklist QA §17 dan tulis hasilnya (lulus/gagal per item) di laporan akhir.
7. Update `README.md` root bagian "Pages" (route baru: Today, Reports tabs, Attendance tabs, Settings, Account) dan cara build CSS (`npm run css`).

---

## 17. Checklist QA lengkap

Lakukan di **light & dark**, lebar **375px, 768px, 1280px**.

**Global**
- [ ] Satu `<!DOCTYPE>` per halaman; tidak ada error/warning PHP; tidak ada error di console browser.
- [ ] Sidebar: item aktif benar di setiap halaman (form laporan → My reports). Badge Attendance admin benar dan hilang saat 0.
- [ ] User menu: Account, ganti tema (tersimpan setelah reload & login ulang), Log out.
- [ ] Mobile: topbar, hamburger, scrim, Escape; staff punya tabbar + FAB di Today/My reports; konten tidak tertutup tabbar.
- [ ] Tidak ada horizontal scroll halaman di 375px (tabel boleh scroll di dalam `.table-wrap`).
- [ ] Semua tanggal memakai format baru; status memakai pill dengan tone sesuai peta.
- [ ] Fokus keyboard terlihat; dialog/drawer/menu bisa dioperasikan dengan keyboard dan ditutup dengan Escape.

**Staff**
- [ ] Today: check-in tepat waktu; check-in terlambat tanpa alasan → error tampil di Today; dengan alasan → status Late; check-out dengan konfirmasi; request leave; report absence; setiap aksi kembali ke Today.
- [ ] Attendance: sama seperti di atas tapi kembali ke Attendance; history + filter bulan + pagination; KPI bulan cocok dengan tabel.
- [ ] New report tanpa foto, dengan foto valid, dengan foto > 1 MB (ditolak di client dengan pesan inline), dengan tipe salah; toast "Report saved".
- [ ] Edit report (Progress) → toast "Report updated"; laporan Completed tidak bisa dibuka lewat menu Edit maupun URL langsung.
- [ ] My reports: filter status, search, bulan, pagination saling mempertahankan parameter; drawer detail (link & foto); mark completed; delete.

**Admin**
- [ ] Dashboard: KPI, tabel semua staff, status tone sesuai ambang, donut + legend (warna ikut tema setelah toggle), strip Today → link ke tab Today.
- [ ] Reports: filter (bulan, search, staff, job type, status) + pagination; drawer detail; tab Today + badge + pagination `short_page`.
- [ ] Attendance: tab Daily (tanggal, shift, search), Monthly (bulan, staff), Not checked in (tanggal); Export daily & monthly menghasilkan file yang sama seperti sebelum rework.
- [ ] Master data 4 tab: add, edit (drawer terisi), delete (confirm), search, pagination, flash toast; error duplikat nama tampil.
- [ ] Settings: simpan valid; min > max ditolak dengan pesan English.
- [ ] Account (admin & staff): ubah profil, ganti password (salah password lama → error), tema.
- [ ] Redirect URL lama berfungsi.

---

## 18. Di luar scope

Jangan dikerjakan dalam rework ini (catat sebagai saran di laporan akhir bila relevan):

- Menggabungkan Users & Employees (menunggu review user).
- Perubahan skema DB / migrasi, termasuk menghapus kolom `users.nav_layout`.
- Fitur baru di luar yang disebut eksplisit (mis. export laporan produksi, deactivate user, notifikasi).
- Perbaikan keamanan yang mengubah kontrak: `update_status_ajax.php` & `delete_report_ajax.php` belum memverifikasi CSRF, delete master data memakai GET tanpa CSRF. **Laporkan** ke user sebagai follow-up, jangan diubah diam-diam.
- Mengubah logika absensi (ambang terlambat, deadline) atau rumus target.
- Mengganti bahasa UI ke Indonesia.
