# Vorta: Navigasi Tanpa Reload (Hotwire Turbo): Implementation Plan

> **Untuk eksekutor (Claude Sonnet):** baca dokumen ini sampai habis sebelum menulis kode. Kerjakan per fase secara berurutan. Setiap fase punya **Definition of Done (DoD)**; jangan lanjut sebelum DoD terpenuhi dan dicek. Kalau dokumen ini bertentangan dengan kode yang ada, **perilaku backend yang ada menang**: jangan ubah aturan bisnis, query, atau validasi server. Catat konfliknya di laporan akhir.

- Root project: `vorta-app/` (semua path relatif terhadap folder ini).
- Rencana ini dikerjakan **setelah** `UI_REWORK_PLAN.md` (sudah selesai di branch `xmeet-ui-rework`). Jangan ubah desain visual.
- Bahasa UI tetap **English**. Komentar kode boleh Bahasa Indonesia (ikuti gaya `ui.js` yang ada).

---

## Daftar isi

1. [Tujuan & keputusan](#1-tujuan--keputusan)
2. [Cara kerja Turbo (ringkas)](#2-cara-kerja-turbo-ringkas)
3. [Aturan wajib](#3-aturan-wajib)
4. [Inventaris masalah di kode sekarang](#4-inventaris-masalah-di-kode-sekarang)
5. [Fase 0: Persiapan](#fase-0-persiapan)
6. [Fase 1: Turbo Drive di layout](#fase-1-turbo-drive-di-layout)
7. [Fase 2: `ui.js` siap Turbo](#fase-2-uijs-siap-turbo)
8. [Fase 3: Script per halaman & handler server](#fase-3-script-per-halaman--handler-server)
9. [Fase 4: Turbo Frames untuk search, filter, pagination](#fase-4-turbo-frames-untuk-search-filter-pagination)
10. [Fase 5: Morph refresh setelah POST](#fase-5-morph-refresh-setelah-post)
11. [Checklist QA lengkap](#11-checklist-qa-lengkap)
12. [Di luar scope](#12-di-luar-scope)

---

## 1. Tujuan & keputusan

**Tujuan:** pindah halaman, search, filter, pagination, dan submit form **tanpa full page reload**, tanpa menulis ulang rendering ke JSON/SPA.

**Keputusan (sudah disetujui user, jangan diganti):**

| # | Keputusan | Alasan |
|---|---|---|
| D1 | Pakai **Hotwire Turbo 8** (Drive + Frames). Bukan htmx, bukan SPA, bukan AJAX manual per halaman. | Server PHP tetap merender HTML penuh; satu sumber kebenaran untuk markup. |
| D2 | Turbo **di-vendor** ke `public/js/vendor/turbo.js` (bukan CDN di runtime). | Tidak tergantung CDN; versi terkunci. |
| D3 | **Prefetch Turbo dimatikan global** (`<meta name="turbo-prefetch" content="false">`). | Turbo 8 mem-prefetch link saat hover. App ini punya link GET yang mengubah data (delete master data, `logout.php`). Prefetch = data terhapus/logout hanya karena hover. **Wajib, tidak boleh dihilangkan.** |
| D4 | Search/filter/pagination memakai **Turbo Frame**; server tetap mengembalikan halaman penuh, Turbo yang mengambil frame-nya. | Tidak perlu endpoint partial; input search tidak kehilangan fokus. |
| D5 | Endpoint AJAX yang ada (`update_status_ajax.php`, `delete_report_ajax.php`, `save_preferences.php`) **tidak diubah**. | Sudah tanpa reload. |

---

## 2. Cara kerja Turbo (ringkas)

Baca ini supaya paham kenapa aturan di §3 ada.

- **Turbo Drive** mencegat klik `<a>` same-origin dan submit `<form>`, mengambil halaman lewat `fetch`, lalu **mengganti `<body>`** dan **menggabungkan `<head>`** (elemen head baru ditambahkan; script head yang sudah ada tidak dieksekusi ulang). URL di-push ke history.
- Akibatnya:
  - `DOMContentLoaded` **hanya terjadi sekali** (load pertama). Event per halaman adalah `turbo:load`.
  - `window` dan `document` **tidak diganti**. Listener yang dipasang ke `document`/`window` **bertahan dan menumpuk** kalau dipasang ulang tiap halaman.
  - Variabel global (termasuk `const`/`let` top-level di `<script>` klasik) **bertahan**. Deklarasi `const x` kedua kali → `SyntaxError: Identifier 'x' has already been declared`.
  - `<script>` di dalam `<body>` **dieksekusi ulang setiap render**, termasuk saat restore dari cache (tombol back).
  - Script **eksternal** di `<body>` (mis. `<script src="js/ui.js">`) juga dieksekusi ulang → listener dobel. Karena itu script app dipindah ke `<head>` dengan `defer`.
  - Script eksternal yang **baru muncul** di `<head>` halaman tujuan dimuat async; Turbo **tidak menunggunya** sebelum menjalankan script inline di body. (Masalah untuk Chart.js, lihat Fase 3.)
- **Form:** respons POST **wajib redirect** (302/303) kalau sukses. Kalau gagal validasi dan merender ulang form, status **wajib 4xx** (pakai 422). Respons 200 dari POST akan ditolak Turbo (error di console, halaman tidak berubah).
- **Cache/snapshot:** sebelum meninggalkan halaman Turbo menyimpan salinan DOM (`turbo:before-cache`). Tombol back menampilkan salinan ini dulu. State UI yang terbuka (drawer, dialog, menu, toast, flash) harus dibersihkan sebelum snapshot.
- **Turbo Frame** (`<turbo-frame id="x">`): link/form di dalam frame (atau yang menunjuk frame lewat `data-turbo-frame="x"`) hanya mengganti isi frame tersebut. Server mengembalikan halaman penuh; Turbo mengambil `<turbo-frame id="x">` dari respons.

---

## 3. Aturan wajib

1. **Jangan ubah perilaku backend** selain yang disebut eksplisit di Fase 3 (status 422, redirect 303, query param `success` → flash).
2. **Prefetch mati global (D3).** Jangan tambahkan `data-turbo-prefetch="true"` di mana pun.
3. **Script inline di halaman** (setelah Fase 3) harus:
   - dibungkus IIFE `(function () { ... })();`, tanpa `const`/`let`/`class` top-level;
   - **tidak** memanggil `document.addEventListener` / `window.addEventListener` / `setInterval` tanpa mendaftarkan pembersihnya lewat `Vorta.onLeave(fn)`;
   - tidak memakai `DOMContentLoaded` (jalankan langsung; DOM sudah siap saat script inline dieksekusi);
   - fungsi yang dipanggil dari atribut `onclick` diekspor eksplisit: `window.editUser = function (...) {...}`.
4. **Behavior lintas halaman** masuk ke `public/js/ui.js` sebagai delegasi di `document` yang dipasang **sekali**, dipicu oleh atribut `data-*`. Jangan menempel listener ke elemen di `ui.js` kecuali di dalam fungsi `initPage(root)` yang idempoten.
5. Ganti semua `form.submit()` dengan `form.requestSubmit()` (supaya event submit terjadi dan Turbo bisa mencegat). Ganti `window.location = url` dengan `Turbo.visit(url)`.
6. Link/aksi yang **bukan halaman HTML** wajib `data-turbo="false"`: download (`export_excel.php`), `logout.php`. Link `target="_blank"` aman (Turbo mengabaikannya).
7. Semua halaman harus tetap berfungsi **tanpa JS** seperti sebelumnya (progressive enhancement): jangan menghapus atribut `method`/`action`/`href` asli.
8. Tidak ada library tambahan selain Turbo.
9. Setelah tiap fase: `php -l` untuk setiap file PHP yang diubah, dan cek console browser bersih (tanpa error) saat navigasi bolak-balik minimal 5 halaman.

---

## 4. Inventaris masalah di kode sekarang

Hasil audit awal. Saat Fase 0, **verifikasi ulang dengan grep** karena kode bisa sudah berubah.

| # | Lokasi | Masalah dengan Turbo | Ditangani di |
|---|---|---|---|
| I1 | `views/layout/end.php`: `<script src="js/ui.js">`, `js/attendance.js` di akhir body | Dieksekusi ulang tiap navigasi → semua listener `document` di `ui.js` dobel | Fase 1 |
| I2 | `views/layout/start.php`: Chart.js dimuat kondisional di head | Tidak ditunggu Turbo; `window.Chart` belum ada saat script dashboard jalan | Fase 3 |
| I3 | `public/ui_head.php`: script tema inline di head | Kalau isinya berubah (tema user berubah) Turbo menambah & menjalankannya lagi → listener `matchMedia` menumpuk | Fase 1 |
| I4 | `ui.js`: inisialisasi sekali-jalan (period-label `tabIndex`, binding live search per input, `syncTheme()`, `showFlash()`, restore fokus via `sessionStorage`) | Tidak jalan untuk halaman yang dibuka lewat Turbo | Fase 2 |
| I5 | `ui.js`: `confirmEl` (dialog konfirmasi) di-append ke `body` dan disimpan di variabel | Setelah body diganti, `confirmEl` terlepas dari DOM → `showModal()` melempar error | Fase 2 |
| I6 | `ui.js`: `activeDrawer`, `drawerOpener`, `document.body.style.overflow` | State basi setelah body diganti | Fase 2 |
| I7 | `ui.js:319, 336, 393` (`form.submit()`, `window.location = a.href`, `f.submit()`) | Melewati Turbo → full reload | Fase 2 |
| I8 | `views/layout/end.php`: `<script>window.VORTA_FLASH = ...</script>` | Ikut tersimpan di snapshot → toast muncul lagi saat tombol back | Fase 2 |
| I9 | `public/my_reports.php:170`: `document.addEventListener('click', ...)` + `DOMContentLoaded` + `history.replaceState` untuk `?success=` | Listener menumpuk; toast tidak muncul; `replaceState` manual merusak state history Turbo | Fase 3 |
| I10 | `views/dashboard/admin.php:100`: Chart + `document.addEventListener('vorta:uichange')` | Listener menumpuk & memegang chart yang sudah tidak ada | Fase 3 |
| I11 | `public/admin_master_data/employees.php:247`: `const empUserSelect` top-level | `SyntaxError` saat tab Employees dibuka kedua kali | Fase 3 |
| I12 | `public/admin_master_data/{users,job_type,work_force,employees}.php`: fungsi global dipanggil dari `onclick` | Aman kalau tetap global, tapi harus eksplisit `window.x =` setelah dibungkus IIFE | Fase 3 |
| I13 | `public/admin_reports.php:290`, `public/admin_attendance.php:417`: autosubmit select via `forEach` + `s.form.submit()` | Full reload; listener per elemen | Fase 2 (delegasi) + Fase 3 (hapus inline) |
| I14 | `public/js/attendance.js`: `setInterval` jam, listener per elemen saat load | Tidak jalan setelah navigasi Turbo; interval menumpuk | Fase 3 |
| I15 | `views/reports/form_fields.php:91`: guard submit manual (`data-submitting`, tombol `disabled` + "Saving…") | Snapshot menyimpan tombol dalam keadaan disabled → tombol mati saat user tekan back | Fase 3 |
| I16 | `public/index.php:25` (login gagal), POST handler lain yang merender ulang dengan 200 | Turbo menolak respons 200 untuk POST | Fase 3 |
| I17 | `?success=` / `?edit=error` di URL: `save_report.php:126`, `update_report.php:68`, `attendance.php:62`, `my_reports.php:64` | Pola pesan lewat query string; diganti flash yang sudah ada (`flash_set`) | Fase 3 |
| I18 | `views/master_data/toolbar.php`, `public/my_reports.php:99`, `public/admin_reports.php:141`, `public/admin_attendance.php`: search/filter GET | Full reload tiap ketik; fokus dipulihkan lewat hack `sessionStorage` | Fase 4 |
| I19 | `views/layout/start.php:62` (logout), `public/admin_attendance.php:222` (export) | Bukan halaman HTML / mengubah sesi | Fase 1 |

---

## Fase 0: Persiapan

1. Pastikan working tree bersih atau commit dulu perubahan yang ada (tanyakan user kalau ada perubahan yang belum di-commit, jangan di-commit sendiri tanpa izin).
2. Install & vendor Turbo:
   ```bash
   npm install --save-dev @hotwired/turbo@^8
   ```
   Tambahkan script di `package.json`:
   ```json
   "vendor": "node -e \"require('fs').mkdirSync('public/js/vendor',{recursive:true});require('fs').copyFileSync('node_modules/@hotwired/turbo/dist/turbo.es2017-umd.js','public/js/vendor/turbo.js')\""
   ```
   Jalankan `npm run vendor`. File `public/js/vendor/turbo.js` **di-commit** (karena server produksi tidak menjalankan npm).
3. Jalankan ulang inventaris §4 dengan grep, dan tambahkan temuan baru ke laporan:
   ```bash
   grep -rn "<script" public views --include=*.php
   grep -rn "addEventListener\|DOMContentLoaded\|setInterval\|\.submit()\|window.location\|history\.\(push\|replace\)State" public views --include=*.php --include=*.js
   grep -rn "method=\"get\"\|method=\"GET\"\|pagination(" public views --include=*.php
   grep -rn "header(.Location" public lib
   ```
4. Cara menjalankan app lokal: dari `vorta-app/` jalankan `php -S localhost:8000`, buka `http://localhost:8000/public/index.php`. Admin seed: `admin@vorta.local` / `password123` (lihat `UI_REWORK_PLAN.md` §Fase 0). Kalau DB tidak tersedia, lakukan verifikasi statis (`php -l`, grep) dan **laporkan** bahwa verifikasi browser tidak bisa dilakukan.

**DoD Fase 0:** `public/js/vendor/turbo.js` ada; daftar inventaris terverifikasi.

---

## Fase 1: Turbo Drive di layout

### 1.1 `views/layout/start.php`: `<head>`

Ganti blok aset di `<head>` menjadi (urutan penting; semua script app `defer`):

```php
<?php $asset = fn(string $p): string => $p . '?v=' . @filemtime(__DIR__ . '/../../public/' . $p); ?>
<meta name="turbo-prefetch" content="false">
<link rel="stylesheet" href="<?= e($asset('css/output.css')) ?>" data-turbo-track="reload">
<?php include __DIR__ . '/../../public/ui_head.php'; ?>
<script src="<?= e($asset('js/vendor/turbo.js')) ?>" defer data-turbo-track="reload"></script>
<script src="<?= e($asset('js/ui.js')) ?>" defer data-turbo-track="reload"></script>
<script src="<?= e($asset('js/attendance.js')) ?>" defer data-turbo-track="reload"></script>
```

- `data-turbo-track="reload"` + `?v=filemtime` → kalau file berubah (deploy), Turbo otomatis full reload sekali supaya user dapat JS/CSS terbaru.
- Font Google tetap seperti sekarang (tanpa track).
- **Hapus** blok Chart.js kondisional dari head (diganti loader di Fase 3). Hapus juga `$pageScripts`-related `<script>` untuk `attendance` di `end.php`; `attendance.js` sekarang selalu dimuat (kecil, dan di-guard per halaman di Fase 3).
- Halaman `bare` (login) memakai head yang **sama persis** supaya login → dashboard tidak memicu full reload.

### 1.2 `views/layout/end.php`

- Hapus `<script src="js/ui.js">` dan `<script src="js/attendance.js">`.
- Tag flash diberi penanda: `<script data-vorta-flash>window.VORTA_FLASH = ...;</script>` (dibersihkan di Fase 2).
- `$pageScriptHtml` tetap dicetak (script inline per halaman), tapi isinya dibereskan di Fase 3.

### 1.3 `public/ui_head.php`

Tambahkan guard di awal IIFE supaya kalau Turbo menjalankan ulang script ini (isi berubah karena tema berubah), listener `matchMedia` tidak dipasang dua kali:

```js
if (window.VortaUI) { return; }
```
(Tema sudah diterapkan client-side oleh `VortaUI.setTheme`, jadi tidak ada yang hilang.)

### 1.4 Link non-HTML (I19)

- `views/layout/start.php`: link `logout.php` di `$renderUserMenu` → tambahkan `data-turbo="false"`.
- `public/admin_attendance.php`: link/tombol yang memakai `$exportUrl` → `data-turbo="false"`.
- Grep link lain ke `export_excel.php` atau endpoint yang mengembalikan file/JSON dan beri `data-turbo="false"`.

### 1.5 Progress bar

Di `src/css/input.css` (layer components yang sudah ada), tambahkan:

```css
.turbo-progress-bar { height: 2px; background-color: var(--vt-accent); }
```
Lalu `npm run css`.

**DoD Fase 1:** klik menu sidebar berpindah halaman tanpa full reload (cek tab Network: request bertipe `fetch`, bukan `document`); progress bar teal muncul; logout & export tetap full request. Boleh masih ada bug listener dobel (diperbaiki Fase 2), tapi **jangan commit/serahkan fase ini terpisah**; Fase 1 dan 2 adalah satu unit.

---

## Fase 2: `ui.js` siap Turbo

Struktur `ui.js` tetap satu IIFE. Prinsipnya: **listener delegasi di `document` dipasang sekali** (sudah begitu sebagian besar), dan semua yang menyentuh elemen spesifik dipindah ke `initPage(root)` yang dipanggil di `turbo:load` dan `turbo:frame-load`.

### 2.1 Siklus hidup halaman (tambahkan di `ui.js`)

```js
// ---------- Page lifecycle (Turbo) ----------
const leaveFns = [];
function onLeave(fn) { leaveFns.push(fn); }
function runLeave() {
  while (leaveFns.length) {
    try { leaveFns.pop()(); } catch (e) { /* abaikan */ }
  }
}

function resetUiState() {
  closeMenus();
  $$('dialog[open]').forEach(d => d.close());
  $$('.drawer.is-open, .drawer:not([hidden])').forEach(el => { el.classList.remove('is-open'); el.hidden = true; });
  $$('[data-drawer-scrim]').forEach(s => { s.hidden = true; });
  activeDrawer = null;
  drawerOpener = null;
  document.body.style.overflow = '';
  const sb = $('#sidebar');
  if (sb) sb.classList.remove('is-open');
  const scrim = $('.sidebar-scrim');
  if (scrim) scrim.hidden = true;
  $$('.toast').forEach(t => t.remove());
}

document.addEventListener('turbo:before-cache', () => {
  resetUiState();
  $$('script[data-vorta-flash]').forEach(s => s.remove());
  window.VORTA_FLASH = [];
});
document.addEventListener('turbo:before-render', runLeave);
document.addEventListener('turbo:load', () => initPage(document));
document.addEventListener('turbo:frame-load', (e) => initPage(e.target));
```

Catatan:
- `resetUiState` menutup drawer **tanpa animasi** (jangan pakai `hideDrawer`, karena `setTimeout`-nya berjalan setelah snapshot diambil).
- Urutan render Turbo: `before-render` (old page cleanup via `runLeave`) → body baru dipasang & script inline baru jalan (boleh memanggil `Vorta.onLeave`) → `turbo:load`. Jadi `onLeave` yang didaftarkan halaman baru tidak ikut terhapus.
- Ekspor: `window.Vorta = { toast, confirm: confirmDialog, drawer, escapeHtml, onLeave, loadScript };` (`loadScript` dari 2.7).

### 2.2 `initPage(root)` (idempoten)

Pindahkan semua inisialisasi sekali-jalan ke sini. Setiap langkah harus aman kalau dipanggil berkali-kali pada elemen yang sama (pakai penanda `dataset`):

```js
function initPage(root) {
  $$('.period-label', root).forEach(l => {
    if (l.dataset.init) return;
    l.dataset.init = '1';
    l.tabIndex = 0;
    l.setAttribute('role', 'button');
  });
  $$('[data-filter-clear]', root).forEach(syncClearButton); // dari 2.6
  syncTheme();
  showFlash();
}
```

Hapus pemanggilan langsung `$$('.period-label').forEach(...)`, `syncTheme()`, dan blok `showFlash` + `DOMContentLoaded` yang ada sekarang di akhir file. `turbo:load` juga terjadi pada load pertama, jadi `initPage` cukup dipanggil dari sana.

**Fallback tanpa Turbo:** kalau `window.Turbo` tidak ada (mis. file vendor gagal dimuat), `turbo:load` tidak pernah terjadi. Tambahkan:

```js
if (!window.Turbo) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initPage(document));
  else initPage(document);
}
```
Letakkan di akhir IIFE. (`ui.js` dimuat `defer` setelah `turbo.js`, jadi `window.Turbo` sudah ada kalau Turbo berhasil dimuat.)

### 2.3 Dialog konfirmasi (I5)

Di `confirmDialog`, ganti `if (!confirmEl)` menjadi `if (!confirmEl || !confirmEl.isConnected)`.

### 2.4 Navigasi & submit lewat Turbo (I7)

Tambahkan helper:

```js
function visit(url, opts) {
  if (window.Turbo) window.Turbo.visit(url, opts);
  else window.location = url;
}
```

- Konfirmasi link: `if (a.tagName === 'A') visit(a.href);` lalu `else if (a.form) a.form.requestSubmit(a);`.
- Konfirmasi form (`form[data-confirm]`): hapus trik membuat `<input type=hidden>` untuk submitter; ganti `f.submit()` dengan `f.requestSubmit(e.submitter || undefined)`. Flag `f._confirmed` tetap dipakai supaya handler tidak membuka dialog lagi. Reset `f._confirmed = false` setelah `requestSubmit` dipanggil (form yang sama bisa disubmit lagi kalau server merespons 422).
- Link konfirmasi yang mengarah ke GET delete master data: `visit(a.href)` (Drive akan mengikuti redirect server). **Jangan** memakai `data-turbo-method`; perilaku GET tetap seperti sekarang (perbaikan CSRF di luar scope, §12).
- Period picker: `e.target.form.requestSubmit()`.

**Penting:** handler `click`/`submit` di `ui.js` memanggil `e.preventDefault()`. Turbo memeriksa `defaultPrevented` dan tidak akan memproses event itu. Jadi alurnya: `ui.js` mencegah → dialog → `visit`/`requestSubmit` memicu jalur Turbo yang normal. Verifikasi di browser bahwa konfirmasi tidak dilewati dan tidak terjadi dua request.

### 2.5 Autosubmit select (I13)

Ganti script inline di `admin_reports.php` & `admin_attendance.php` dengan delegasi di `ui.js`:

```js
document.addEventListener('change', (e) => {
  const f = e.target.form;
  if (f && f.matches('form[data-autosubmit]') && e.target.matches('select')) f.requestSubmit();
});
```

### 2.6 Live search (I18): versi baru

Hapus seluruh blok `// ---------- Live search ----------` lama (termasuk `LIVE_KEY` dan restore fokus via `sessionStorage`). Ganti dengan delegasi:

```js
// ---------- Live search ----------
// Input search di form GET men-submit form-nya sendiri (debounced). Form diarahkan ke
// <turbo-frame> (Fase 4), jadi input tidak ikut diganti dan fokus tetap.
const liveTimers = new WeakMap();
let composing = false;
document.addEventListener('compositionstart', () => { composing = true; });
document.addEventListener('compositionend', (e) => { composing = false; queueLive(e.target); });
document.addEventListener('input', (e) => { if (!composing) queueLive(e.target); });

function queueLive(input) {
  if (!input.matches || !input.matches('form[method="get" i] input[type="search"]')) return;
  const form = input.form;
  clearTimeout(liveTimers.get(form));
  liveTimers.set(form, setTimeout(() => {
    const value = input.value.trim();
    if (form.dataset.lastQuery === undefined) form.dataset.lastQuery = input.defaultValue.trim();
    if (value === form.dataset.lastQuery) return;
    form.dataset.lastQuery = value;
    form.requestSubmit();
  }, 400));
}
document.addEventListener('submit', (e) => {
  const t = liveTimers.get(e.target);
  if (t) clearTimeout(t);
}, true);
```

Tombol clear generik (menggantikan link "Clear"/"Clear filters" yang visibilitasnya ditentukan server, karena setelah Fase 4 bagian toolbar tidak dirender ulang):

```js
// <button type="button" data-filter-clear="form-id" hidden>Clear</button>
function formHasFilters(form) {
  return Array.from(form.elements).some(el =>
    el.name && el.type !== 'hidden' && el.type !== 'submit' && el.value !== '');
}
function syncClearButton(btn) {
  const form = document.getElementById(btn.dataset.filterClear);
  if (form) btn.hidden = !formHasFilters(form);
}
document.addEventListener('input', (e) => syncClearFor(e.target.form));
document.addEventListener('change', (e) => syncClearFor(e.target.form));
function syncClearFor(form) {
  if (!form || !form.id) return;
  $$('[data-filter-clear="' + form.id + '"]').forEach(syncClearButton);
}
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-filter-clear]');
  if (!btn) return;
  const form = document.getElementById(btn.dataset.filterClear);
  if (!form) return;
  Array.from(form.elements).forEach(el => {
    if (!el.name || el.type === 'hidden' || el.type === 'submit') return;
    if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
  });
  form.dataset.lastQuery = '';
  syncClearFor(form);
  form.requestSubmit();
  const first = form.querySelector('input[type="search"]');
  if (first) first.focus();
});
```

Server tetap merender tombol ini dengan `hidden` sesuai kondisi awal (`<?= $hasFilters ? '' : 'hidden' ?>`) supaya benar tanpa JS. Tanpa JS, tombol `type="button"` tidak berguna; jadi **tetap pertahankan link `<a>` lama di dalam `<noscript>`**, atau jadikan tombolnya `<a href="(url tanpa filter)" data-filter-clear="...">` dan `preventDefault()` di handler. **Pilih opsi `<a>`** (lebih sederhana): tambahkan `e.preventDefault()` di handler klik di atas.

### 2.7 Loader script (untuk Chart.js)

```js
const scriptCache = {};
function loadScript(src) {
  if (!scriptCache[src]) {
    scriptCache[src] = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.onload = resolve;
      s.onerror = () => { delete scriptCache[src]; reject(new Error('load failed: ' + src)); };
      document.head.appendChild(s);
    });
  }
  return scriptCache[src];
}
```

### 2.8 Error jaringan & frame yang hilang

```js
document.addEventListener('turbo:fetch-request-error', () => {
  toast("Couldn't reach the server. Check your connection and try again.", { tone: 'bad' });
});
// Respons frame tanpa frame yang cocok (mis. sesi habis → redirect ke login): lakukan visit penuh.
document.addEventListener('turbo:frame-missing', (e) => {
  e.preventDefault();
  e.detail.visit(e.detail.response);
});
```

**DoD Fase 2:** navigasi bolak-balik 10× antar halaman lalu buka menu user, drawer, dan dialog konfirmasi: masing-masing terbuka **sekali** (tidak dobel), Escape menutup, tidak ada error console. Toast flash tidak muncul lagi saat menekan back. Tombol back tidak menampilkan drawer/menu yang masih terbuka.

---

## Fase 3: Script per halaman & handler server

Kerjakan per file. Setelah tiap file: buka halaman itu, pindah ke halaman lain, kembali (via link **dan** via tombol back), ulangi 3×, cek console.

### 3.1 `public/my_reports.php` (I9, I17)

- Server: hapus pembacaan `$_GET['success']`/`$_GET['edit']` untuk toast. Pindahkan pesannya ke `flash_set()` di handler asal:
  - `save_report.php:126`: `flash_set('ok', <pesan sama seperti toast sekarang>); header('Location: my_reports.php', true, 303);`
  - `update_report.php:68`: `flash_set('bad', <pesan sama>); header('Location: my_reports.php', true, 303);`. Cek juga redirect sukses di file yang sama.
  - Pakai teks pesan yang **sama persis** dengan yang dipetakan sekarang di `my_reports.php` (variabel `$toast`).
- Script inline: hapus blok `DOMContentLoaded` + `history.replaceState`. Ganti `document.addEventListener('click', ...)` dengan listener di elemen root halaman: beri `<section>` utama halaman `id="my-reports"` (atau elemen pembungkus terdekat yang memuat tabel & tombol), lalu `root.addEventListener('click', ...)`. Karena elemen itu baru tiap render, listener tidak menumpuk. Pastikan tombol aksi yang berada di **menu** (`.menu`, diposisikan `fixed`) tetap berada di dalam root secara DOM. Kalau tidak, pakai `document.addEventListener` + `Vorta.onLeave(() => document.removeEventListener('click', handler))`.
- Kalau tabel nanti dibungkus frame (Fase 4), root harus **di luar/mencakup** frame supaya listener tetap ada setelah frame diganti.

### 3.2 `public/attendance.php` (I17)

`attendance_redirect('?success=absence_reason_submitted')` dan pola sejenis: ubah ke `flash_set('ok', <pesan yang sama dengan yang ditampilkan sekarang untuk param itu>)` + `attendance_redirect()` tanpa query. Cari tempat param `success` dibaca (grep `success` di `attendance.php`, `views/attendance/*`, `views/dashboard/today.php`) dan hapus pembacaannya. `attendance_redirect` di `lib/attendance.php` gunakan status 303.

### 3.3 `public/js/attendance.js` (I14)

Ubah dari "jalan sekali saat load" menjadi fungsi init per halaman:

```js
(function () {
  'use strict';
  function init() {
    const checkInForm = document.getElementById('checkInForm');
    const absenceReasonForm = document.getElementById('absenceReasonForm');
    const clock = document.querySelector('[data-clock]');
    if (!checkInForm && !absenceReasonForm && !clock && !document.querySelector('[data-checkout-form]')) return;
    // ... isi lama, tanpa perubahan logika ...
    if (clock) {
      const id = setInterval(tick, 15000);
      window.Vorta.onLeave(() => clearInterval(id));
    }
  }
  document.addEventListener('turbo:load', init);
  if (!window.Turbo) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  }
})();
```
`attendance.js` dimuat setelah `ui.js` (urutan `defer`), jadi `window.Vorta` sudah ada. Logika `isLate` dan ambang waktu **tidak boleh berubah**.

### 3.4 `views/dashboard/admin.php` (I2, I10)

```js
(function () {
  // ... labels, data, tokens, details, css(), colors() seperti sekarang ...
  const canvas = document.getElementById('pie');
  if (!canvas) return;
  Vorta.loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js').then(() => {
    if (!canvas.isConnected) return; // user sudah pindah halaman
    const chart = new Chart(canvas, { /* config sama */ });
    const onTheme = () => { /* update warna seperti sekarang */ };
    document.addEventListener('vorta:uichange', onTheme);
    Vorta.onLeave(() => { document.removeEventListener('vorta:uichange', onTheme); chart.destroy(); });
  });
})();
```
Hapus `'chart'` dari `$pageScripts` di file yang mengirimnya (grep `pageScripts`). Grep juga penggunaan Chart.js lain dan terapkan pola yang sama.

### 3.5 Master data (I11, I12)

Untuk `public/admin_master_data/{users,employees,job_type,work_force}.php`:

- Bungkus script dengan IIFE.
- `const empUserSelect` dan sejenisnya masuk ke dalam IIFE.
- Fungsi yang dipanggil dari `onclick` diekspor: `window.editUser = editUser;` (begitu juga `editEmployee`, `editJobType`, `editWorkforce`; cek nama persisnya).
- Listener ke elemen drawer (`drawer:mode`, `drawer:close`) aman (elemen baru tiap render).

### 3.6 `views/reports/form_fields.php` (I15)

- Hapus guard submit manual (`form.dataset.submitting`, `btn.disabled = true`, `btn.textContent = 'Saving…'`).
- Di tombol submit form laporan (`report_form.php`, `edit_report.php`, cari `type="submit"` di form yang memuat `form_fields.php`), tambahkan `data-turbo-submits-with="Saving…"`. Turbo otomatis men-disable tombol selama submit dan memulihkan teks/keadaannya sesudahnya (termasuk di snapshot).
- Logika dropzone tetap; sudah dalam IIFE dan menempel ke elemen halaman.

### 3.7 `public/account.php`, `public/index.php`

- `account.php`: script sudah aman (IIFE + listener elemen). Bungkus bagian `document.querySelectorAll('[data-toggle-password]')...` ke dalam IIFE yang sama. **Lebih baik**: pindahkan toggle password ke `ui.js` sebagai delegasi `[data-toggle-password]` dan pakai juga untuk tombol `#toggle-password` di `index.php` (samakan markup-nya ke `data-toggle-password="password"`). Pertahankan perilaku `aria-pressed`/`aria-label` dan ikon.
- `index.php:25`: saat login gagal, sebelum merender ulang: `http_response_code(422);`.

### 3.8 Audit semua POST handler (I16)

Untuk setiap file dari `grep -rln "REQUEST_METHOD\|\$_POST" public`:

| Kasus | Yang harus terjadi |
|---|---|
| Sukses | `header('Location: ...', true, 303); exit;` (tambahkan `true, 303`; jangan ubah tujuan redirect) |
| Gagal validasi & merender ulang halaman | `http_response_code(422);` sebelum output |
| Gagal validasi & redirect dengan flash | Tidak perlu diubah selain 303 |
| Endpoint JSON/AJAX (`*_ajax.php`, `save_preferences.php`) | **Jangan diubah** |

Catat daftar file yang diubah di laporan.

**DoD Fase 3:** grep tidak menemukan `DOMContentLoaded` di file PHP, tidak ada `.submit()` di file mana pun, tidak ada `const`/`let` top-level di script inline; semua POST form (login sukses & gagal, buat/edit/hapus laporan dengan & tanpa foto, check-in tepat waktu & terlambat, check-out, absence reason, leave, master data create/update/delete, settings, account profile & password) berfungsi lewat Turbo tanpa error console; pesan flash muncul sekali.

---

## Fase 4: Turbo Frames untuk search, filter, pagination

Tujuan: mengetik di search / mengganti filter / pindah halaman tabel **hanya mengganti area hasil**.

### 4.1 Pola

```php
<form method="get" id="ar-filters" data-turbo-frame="ar-results" data-turbo-action="replace" ...>
  ... input search, select ...
</form>
<a href="<?= e(query_url([...filter => null])) ?>" class="link" data-filter-clear="ar-filters" data-turbo-frame="ar-results" <?= $hasFilters ? '' : 'hidden' ?>>Clear filters</a>

<turbo-frame id="ar-results" data-turbo-action="advance" class="results-frame">
  <span class="toolbar-count">...</span>   <!-- dipindah ke dalam frame, lihat 4.2 -->
  <section class="card"> ... tabel / empty state ... </section>
  <?= pagination(...) ?>
</turbo-frame>
```

- `data-turbo-frame` di form → submit hanya mengganti frame tersebut.
- `data-turbo-action="replace"` di form search → URL ikut berubah tanpa menumpuk history per ketikan.
- `data-turbo-action="advance"` di frame → klik pagination di dalam frame mengubah URL dan bisa di-back.
- Server **tidak berubah**: tetap merender halaman penuh dengan frame yang sama id-nya.
- Link/form di luar frame yang mengubah konteks halaman (tab, period picker bulan/tanggal) **tetap Drive biasa** (mengganti seluruh body). Jangan arahkan ke frame.

CSS (tambahkan ke `src/css/input.css`):

```css
turbo-frame { display: block; }
turbo-frame[busy] { opacity: .6; transition: opacity .15s; }
@media (prefers-reduced-motion: reduce) { turbo-frame[busy] { transition: none; } }
```

### 4.2 Elemen yang bergantung pada hasil

Elemen di luar frame tidak dirender ulang saat frame diganti. Maka:

- **Jumlah hasil** (`.toolbar-count`): pindahkan ke **dalam frame**. Kalau desain toolbar mengharuskan count di baris toolbar, letakkan frame sehingga baris toolbar kedua (count) ada di dalamnya, atau tampilkan count di header kartu. Visual harus tetap rapi di mobile (cek 375px).
- **Link Clear / Clear filters**: pakai `data-filter-clear` (Fase 2.6) supaya visibilitasnya diatur client.
- **Badge tab** (mis. `missing` di tab "Today's completion") boleh tetap di luar frame. Tidak berubah karena filter.

### 4.3 Halaman yang dikerjakan

Untuk setiap halaman, identifikasi area hasil dan id frame. Verifikasi dengan grep `pagination(` dan `method="get"`:

| Halaman | Form | Frame id | Catatan |
|---|---|---|---|
| `admin_reports.php` (tab All) | form filter (`data-autosubmit`): beri `id="ar-filters"` | `ar-results` | Period picker bulan tetap Drive. Hidden input `month` di form tetap. |
| `admin_reports.php` (tab Today) | cek apakah ada filter/pagination | `ar-today-results` | Kalau tidak ada GET form/pagination, lewati. |
| `my_reports.php` | form search (baris 99): beri `id` | `mr-results` | Root listener 3.1 harus mencakup frame. |
| `admin_master_data.php` + `views/master_data/toolbar.php` | form search: `id="md-filters"` | `md-results` | Toolbar dipakai 4 tab; frame dibungkus di file tab masing-masing atau di `admin_master_data.php` sekitar konten tabel (bukan drawer form). Pindahkan count ke dalam frame. Drawer form (`<aside>` create/edit) **di luar** frame. Link tab tetap Drive. |
| `admin_attendance.php` | form filter (`data-autosubmit`) | satu frame per area hasil | Halaman ini punya **lebih dari satu** pagination dengan param berbeda (mis. `missing_page`). Setiap section yang punya pagination sendiri = frame sendiri (`aa-records`, `aa-missing`, …). Form filter diarahkan ke frame yang hasilnya dipengaruhi filter; kalau filter mempengaruhi beberapa section, bungkus semua section itu dalam **satu** frame dan biarkan pagination di dalamnya mengganti frame yang sama. Link export (`data-turbo="false"`) di luar frame. Export URL dibangun dari filter saat render; kalau link export berada di luar frame dan filter berubah via frame, **pindahkan link export ke dalam frame** supaya URL-nya ikut terbarui. |
| `admin_not_attendance.php`, `views/attendance/*`, dashboard | cek | | Terapkan hanya bila ada search/filter/pagination GET. |

### 4.4 Drawer detail & menu di dalam frame

- Baris tabel dengan `data-drawer-url` dan menu `[data-menu-trigger]` memakai delegasi di `document` → tetap berfungsi setelah frame diganti. Verifikasi.
- Kalau frame diganti saat menu masih terbuka, `closeMenus()` aman (hanya memproses trigger yang masih ada). Verifikasi tidak ada menu "yatim" yang tetap tampil. Kalau ada, panggil `closeMenus()` di `turbo:before-frame-render`.

### 4.5 Scroll

Pagination di frame tidak menggulir halaman. Tambahkan atribut `autoscroll data-autoscroll-block="start"` pada frame yang berisi pagination, supaya setelah pindah halaman tabel, bagian atas frame terlihat.

**DoD Fase 4:** di setiap halaman di tabel 4.3: mengetik di search tidak kehilangan fokus/caret, hasil terbarui ±400ms setelah berhenti mengetik, URL ikut berubah, refresh browser menampilkan hasil yang sama, tombol back setelah pagination kembali ke halaman tabel sebelumnya, count & tombol Clear sinkron, ketik cepat lalu hapus tidak menampilkan hasil basi (urutan respons). Tanpa JS (matikan JS di DevTools), search via Enter & pagination tetap berfungsi dengan full reload.

---

## Fase 5: Morph refresh setelah POST

Saat form di drawer master data/settings disubmit, server redirect kembali ke **URL yang sama**. Dengan morphing, Turbo hanya memperbarui bagian yang berubah dan mempertahankan posisi scroll.

1. Tambahkan di `<head>` (`views/layout/start.php`):
   ```html
   <meta name="turbo-refresh-method" content="morph">
   <meta name="turbo-refresh-scroll" content="preserve">
   ```
2. Pastikan `resetUiState()` juga dipanggil sebelum morph: tambahkan pada listener `turbo:before-render`:
   ```js
   document.addEventListener('turbo:before-render', (e) => {
     if (e.detail.renderMethod === 'morph') resetUiState();
   });
   ```
   (Tanpa ini, drawer yang terbuka bisa tersisa dalam state setengah: `activeDrawer` basi, `body` overflow terkunci.)
3. Uji khusus: buat user baru dari drawer saat halaman di-scroll ke bawah → drawer tertutup, baris baru muncul, posisi scroll tetap, toast flash muncul sekali, form di drawer kosong saat dibuka lagi.
4. **Kalau morph menimbulkan bug yang tidak bisa diselesaikan dalam scope ini** (mis. dialog `<dialog>` di top-layer tidak tertutup benar, chart rusak), **hapus kedua meta tag** (kembali ke replace biasa, tetap tanpa full reload) dan laporkan alasannya. Fase ini opsional; fase 1–4 tidak boleh dikorbankan.

**DoD Fase 5:** skenario poin 3 lolos di Users, Job types, Workforce, Employees, Settings; atau meta dihapus dengan alasan tercatat.

---

## 11. Checklist QA lengkap

Jalankan sebagai **admin** dan **staff**, di desktop (≥1280px) dan mobile (375px), tema light & dark.

**Navigasi**
- [ ] Semua item sidebar & tabbar staff berpindah tanpa full reload (Network: `fetch`), progress bar muncul untuk request lambat.
- [ ] Item aktif sidebar/tabbar dan `<title>` berubah sesuai halaman.
- [ ] Badge "not checked in" di sidebar admin terbarui saat pindah halaman.
- [ ] Back/forward browser bekerja dan tidak menampilkan drawer/menu/dialog/toast basi.
- [ ] Sidebar mobile tertutup setelah memilih menu.
- [ ] Logout full request; setelah logout, tombol back **tidak** menampilkan halaman berisi data (kalau tampil dari cache, tambahkan `<meta name="turbo-cache-control" content="no-cache">` di layout `bare` login dan laporkan).
- [ ] Sesi habis (hapus cookie PHPSESSID) lalu klik menu/ketik search → berakhir di halaman login, tanpa layar kosong/error frame.
- [ ] Mengubah `ui.js` (sentuh file) lalu navigasi → Turbo melakukan full reload sekali (asset tracking).

**Form & aksi**
- [ ] Login sukses → dashboard; login gagal → pesan error tampil, input email tetap.
- [ ] Buat laporan dengan foto (≤1MB) & tanpa foto; foto >1MB ditolak client; tombol "Saving…" lalu pulih saat back.
- [ ] Edit laporan; laporan Completed tetap tidak bisa diedit.
- [ ] Mark completed & delete di My reports (AJAX lama) tetap berfungsi, juga setelah search via frame.
- [ ] Check-in tepat waktu, check-in terlambat (alasan <10 karakter ditolak), check-out (pesan konfirmasi menampilkan jam saat ini), absence reason, leave.
- [ ] Master data: create/update/delete di 4 tab; dialog konfirmasi delete muncul **sekali**; delete hanya terjadi setelah konfirmasi.
- [ ] **Hover lama di link delete & logout tidak memicu request apa pun** (Network kosong). Ini verifikasi D3.
- [ ] Settings & Account (profile, password mismatch check, toggle show password).
- [ ] Ganti tema dari menu user di beberapa halaman berturut-turut; chart dashboard ikut berganti warna; tidak ada error.
- [ ] Export Excel men-download file dengan filter yang sedang aktif.

**Search / filter / pagination** (lihat DoD Fase 4)

**Teknis**
- [ ] Console bersih setelah 20 navigasi acak + 5 back.
- [ ] Tidak ada listener menumpuk: buka dashboard admin 5× lalu ganti tema → handler `vorta:uichange` hanya 1 (cek dengan `getEventListeners(document)` di Chrome DevTools).
- [ ] `php -l` untuk semua file PHP yang diubah.
- [ ] `npm run css` dijalankan; `public/css/output.css` ter-update.
- [ ] JS dimatikan: semua halaman & form tetap bekerja dengan full reload.

---

## 12. Di luar scope

Jangan dikerjakan. Catat saja di laporan kalau relevan:

- Menambah CSRF ke endpoint AJAX dan mengubah delete master data dari GET ke POST (follow-up keamanan yang sudah diketahui). **Catatan:** setelah perbaikan itu, link delete bisa diubah memakai `data-turbo-method="post"`.
- Mengubah desain visual, copy, atau struktur halaman di luar yang diperlukan Fase 4.2.
- Turbo Streams / WebSocket / real-time update.
- Menulis ulang endpoint AJAX lama ke Turbo.
- Menggabungkan tab Users & Employees (masih menunggu review user).
- Service worker / offline.

---

## Laporan akhir (wajib)

Di akhir, tulis ringkasan:
1. File yang diubah per fase.
2. Hasil checklist §11 (centang/gagal + alasan); sebutkan yang tidak bisa diverifikasi (mis. DB tidak tersedia).
3. Konflik dengan dokumen ini & keputusan yang diambil.
4. Status Fase 5 (aktif / dibatalkan + alasan).
