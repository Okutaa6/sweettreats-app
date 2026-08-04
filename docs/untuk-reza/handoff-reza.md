# Handoff untuk Reza (+ Claude-nya) — Shell Redesign & Struktur Role/Hierarki

> **Dokumen ini self-contained** — bisa langsung dikasih ke Claude tanpa konteks obrolan lain.
> Ditulis 2026-07-21. Repo: SweetTreats (Laravel + Blade + Bootstrap 4).

## 0. Ringkasan & pembagian kerja

Tim kebagi 2 lane paralel biar nggak tabrakan:

- **Lane Reza (dokumen ini):** desain shell (sidebar+header) + evolusi struktur **role/hierarki/RBAC** + `sys_user_position`.
- **Lane Absensi (TIM LAIN — JANGAN disentuh):** fitur rekonsiliasi absensi (import→reconcile→form→inbox→recap). Logikanya sudah jadi & terverifikasi.

Aturan emas: **kerjakan hanya yang di lane Reza. File lane absensi ada di daftar "JANGAN SENTUH" (§7).**

---

## 1. Boundary kepemilikan (disepakati)

| Domain | Owner | Catatan |
|---|---|---|
| Shell (sidebar/header): CSS + struktur + toggle JS | **Reza** | look + logic-nya nyatu, satu tangan |
| Sistem visual global (warna, komponen, mobile) | **Reza** | |
| Role / hierarki / RBAC (`group_path`, CRUD setgroup, §6.1) | **Reza** | fitur aktif Reza |
| `sys_user_position` (assign jabatan ke user) | **Reza** | absensi cuma *baca* |
| `canSeeAllBranches` hardcode → flag kapabilitas | **Reza** (koord) | JANGAN ubah nama/signature fungsi (dipakai guard absensi) |
| Fitur absensi (semua) | Absensi | JANGAN sentuh (§7) |
| Approval by-posisi (`att_approver` + UI) | Absensi (koord) | *baca* `sys_user_position` punya Reza |

Kontrak sambungan: **Reza nulis jabatan (`sys_user_position`), absensi cuma baca.** Approval absensi independen dari pohon role.

---

## 2. TUGAS A — Redesign shell (sidebar + header), fokus MOBILE

### Masalah
- **Mobile parah**: buka halaman apa pun di HP → sidebar admin **nutupin konten**, konten kesqueeze jadi kolom sempit, header wrap vertikal. Praktis tak kepakai di HP.
- Desktop udah lumayan (sidebar coklat gelap + cards) — **jangan diregres**.

### Diagnosis akar (sudah ditelusuri — hemat waktu)
1. **`public/assets/css/style.css` `@media (max-width: 1024px)`** → `.left-sidenav { position: fixed }` TANPA menyembunyikan & TANPA nyesuaiin `.page-content` → sidebar nge-float nutupin konten. **Biang utama.**
2. **`resources/views/layouts/sidebar.blade.php` (script)**: `STATES = ['normal','collapsed']`. Hamburger (`#headerHamburger`) cuma cycle normal↔collapsed (268↔70px). **Tak ada state off-canvas/drawer.**
3. **`public/assets/css/shell-redesign.css` NOL `@media`** → tak ada perilaku responsive.

### Requirement
- **Desktop (>1024px):** pertahankan yang sekarang.
- **Mobile/tablet (≤1024px):** sidebar jadi **drawer off-canvas** (default tersembunyi), dibuka hamburger, ada **backdrop**, tutup saat tap backdrop / tap menu / resize ke desktop. Konten **full-width**. Header ramping (brand→logo, judul mengecil, 1 baris).
- **Pertimbangkan layout self-service terpisah** untuk halaman staff HP (`akun/*`, `persetujuan`): bottom-nav, tanpa sidebar admin. Paradigma "sidebar admin gelap" canggung di HP. Blast-radius kecil, hasil buat staff jauh lebih clean.
- **Bug**: di sidebar collapsed, label parent (mis. "Akun Saya") tak kelihatan (cuma ikon). Beresin.

### Mekanik yang perlu dijaga
- Header tinggi 72px; sidebar 268px (collapsed 70px). Body class `sidebar-collapsed`.
- Dua mode sidebar: `.left-sidenav.no-icon-bar` (role single-parent) vs icon-rail (multi-parent). Menu digerakkan permission DB (`getSidebarChild/Parent`).
- Tema: `body[data-theme="marketing"]` = teal override; default = brown.
- Header komponen di `resources/views/layouts/header.blade.php`: `.st-topbar`, `.st-brand`, `.st-hamburger`, `.st-pagehead`, `.st-actions`.

### File lane ini
`public/assets/css/shell-redesign.css` (utama), `resources/views/layouts/{sidebar,header,app}.blade.php`, `public/assets/css/style.css` (@media base), `public/assets/css/style.sweettreats.css`.

---

## 3. TUGAS B — Struktur Role / Hierarki / RBAC

### B1. Granularity role — DISKUSI, jangan asal pecah
Sekarang 6 role akses flat (mayoritas user di "Cabang"). **Itu wajar** karena **role = AKSES menu**, dan GS/FO/Asdent/Sales butuh menu yang sama. Jabatan mereka yang beda (jam kerja, approver) disimpan di **`att_user_rule` + `sys_user_position` + `att_approver`**, bukan di role akses.

- **Jangan** pecah role akses jadi GS/Sales/Asdent cuma demi rapi → nol nilai akses, maintenance ×5, dan **banyak kode hardcode nama role** (lihat B2) bakal jebol.
- **Dev katanya sudah punya ~13 role org** (local baru 6) → cek dev dulu; kemungkinan "rapinya" sudah ada di sana, tinggal sinkron.
- Isi **Bagan / garis atasan-bawahan** (`group_path.parent_id`) yang sekarang kosong ("0 TERHUBUNG") — ini nilai struktur org yang sebenarnya dicari.

### B2. Hardcode nama role → flag kapabilitas
`app/Helpers.php` → `canSeeAllBranches()` hardcode string `['Super Admin','Admin General','Admin Backup','Finance']`. Tambah role admin baru = harus edit kode. **Rapuh.**

**Fix:** jadikan **flag kapabilitas per role** (mis. kolom `group_path.can_see_all_branches` atau tabel capability), lalu `canSeeAllBranches()` baca flag itu. **PENTING: JANGAN ubah nama/signature `canSeeAllBranches()`** — guard absensi (`AbsensiUploadController`, `AbsensiRecapController`) memanggilnya; cukup ganti isinya. Idem untuk cek nama-role lain (`isFinance`, dsb).

### B3. Kaitan checklist (§6.1) — jangan sampai bocor
`checklistVisibleDivisionIds()` (`app/Helpers.php`): divisi yang dilihat seseorang = divisi milik semua role keturunannya di pohon. **Efek: posisi di pohon punya efek samping langsung ke checklist.** Kalau nyusun ulang pohon role, pastikan role tanpa divisi (Finance/IT/Marketing) **di-samping**, bukan di-atas operasional — biar checklist operasional tak bocor.

---

## 4. Kontrak sambungan — Approval PER-ROLE (dibangun lane absensi)

**Keputusan (2026-07-21, setelah intip DB dev + POC):** attendance approval = **aturan per-ROLE, routing
SENDIRI (decoupled), cuma SHARE vocabulary role sama checklist.** BUKAN diturunkan dari pohon parent
checklist — karena data dev buktiin dua hierarki itu **nyimpang** (Asdent→Coordinator Dentist di pohon
vs →Nisa+Irma any-one di attendance; Supervisor→Executive di pohon vs →Dani di attendance; apex Dani gak
ada di pohon). Maksa ke satu pohon = salah.

```
General Services / Front Office → [Supervisor]                       (→ Nisa)
Assistant Dentist               → [Coordinator Dentist, Supervisor]  (any-one: Irma ATAU Nisa)
Sales                           → [Supervisor]                       (orphan di pohon, rule eksplisit)
Supervisor/Koord/Head           → [COO]                              (→ Dani, apex)
```

**Yang dibutuhkan dari Reza:** cukup **struktur role yang rapi & stabil** (13 role dev — Asdent/FO/GS/
Supervisor/Coordinator Dentist/dst) + user ter-assign ke role yang bener. Absensi **baca `group_path` role**
(bukan `sys_user_position`) untuk resolve approver via aturan per-role sendiri. **Reza TIDAK bikin logika
approval** — cukup role structure yang bener. Build att_approval_rule + resolver = lane absensi (POC lolos,
scratchpad `poc_approval_byrole.php`). Eksekusi = pra-prod, selaras migrasi role dev.

---

## 5. Fundamental UI/UX (pegangan tim — jangan hilang saat redesign)

1. **Focal point** = size + contrast + isolation. Tiap layar SATU aksi utama dominan (mis. "Ajukan", "Setujui"). Jangan semua rata.
2. **Proximity** — yang berhubungan didekatkan, yang beda dijarakkan.
3. **Color = meaning, bukan dekorasi.** Badge status pakai warna semantik konsisten. **Brand `#461500` KEKUNCI**; accent (brown-2/3/pale, cream `#F4ECE2`) fleksibel.
4. **Controls ≠ content.** Chrome (nav/header/tombol) beda bobot visual dari data.
5. **Mobile-first untuk self-service.** Touch target ≥44px, aksi utama di jangkauan jempol, hindari kepadatan desktop di HP.

---

## 6. Batasan keras (JANGAN dilanggar)

- **Brand `#461500` kekunci.** Sidebar brown default; `data-theme="marketing"` = teal (jangan dihapus).
- **AuthGuard cek `access_path.link == segment(1)`** — jangan ubah mekanisme akses; route baru wajib segment-1 + node access + `php artisan access:rebuild`.
- **Jangan ubah signature `canSeeAllBranches()`** (dipakai guard absensi).
- **Checklist §6.1** — jangan bikin divisi bocor saat rombak pohon role.
- FontAwesome **5.8.2** (bukan 6) + dripicons + Bootstrap 4 — jangan pakai ikon FA6-only (jadi kotak kosong).

---

## 7. ⛔ JANGAN SENTUH (lane absensi — punya tim lain)

File-file ini logika absensi yang sudah jadi & terverifikasi. **Jangan edit** (kalau redesign butuh ubah tampilannya, lakukan via shell global, bukan ngedit file ini):

```
app/Services/AttendanceReconciler.php
app/Services/AttendanceCsvImporter.php
app/Services/AttendanceRequestWorkflow.php
app/Http/Controllers/Absensi/*.php        (Upload, AkunAbsensi, AkunPengajuan, Persetujuan, AbsensiRecap)
app/Models/Att*.php                        (AttScan, AttDay, AttRequest, dll)
resources/views/absensi/**                 (akun, persetujuan, recap, upload)
database/sql/*absensi*.sql, *lebaran*.sql
database/seeders/AbsensiAccessPathSeeder.php   (node menu absensi — koord kalau perlu)
routes/web.php → blok "Absensi" & "Akun Saya" & "Persetujuan"
```

> Kalau butuh perubahan di zona sambungan (`canSeeAllBranches`, `sys_user_position`), **koordinasi dulu** — jangan diam-diam.

---

## 8. Cara verifikasi
- `php artisan serve` + login. Akun tes staff: `<slug>@sweettreats.local` / `sweettreats2026`.
- Setelah ubah menu/akses: `php artisan access:rebuild` + `php artisan view:clear`.
- Uji shell di **desktop DAN mobile (DevTools device mode ~390px)**: drawer buka/tutup, backdrop, konten full-width, header muat 1 baris.
- Halaman yang harus rapi (khusus absensi, restyle via shell): `akun` (Akun Saya + Ajukan), `persetujuan` (inbox), `absensi` (upload + rekap).
