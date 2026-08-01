# Design Brief: Pusat Akun — layar ROLE + SETTING (re-skin ringan)

2026-07-24. **Satu sesi claude.ai/design** (dua layar ringan, boleh sekali jalan).
Bagian keluarga "Pusat Akun" (induk: `design-brief-pusat-akun.md`). Berdiri sendiri.

## Konteks singkat

Sweet Treats = klinik gigi 5 cabang. **Role** = kelola jabatan/role + hierarki
(pohon: Executive → Supervisor/Head → staf). **Setting** = profil sistem (nama, logo).
Dua-duanya CRUD yang SUDAH jalan — ini **re-skin**, bukan fitur baru. Pemakai IT.

## Batasan visual (WAJIB — samakan keluarga absen HP / Pengawasan / Kesehatan)

- Cream `#F4ECE2`, coklat brand `#461500` (terkunci), aksen `#C77A1E`, hijau `#2F8E55`.
  Plus Jakarta Sans. Desktop-first (±1440px). Ikon SVG garis, **TANPA emoji**.
- Shell DataCenter (top-bar tipis) — jangan bikin shell sendiri.

## Layar 1 — Role

- **Pohon hierarki role** (data sudah ada). Node = kartu kecil: nama role, **jumlah
  anggota**, penanda ⚠ kalau **landing page role tak di-grant** (nyambung detektor
  Kesehatan — role yang penggunanya bakal 403).
- Panel kanan (klik node): detail role — daftar anggota, landing page, ringkasan akses.
- CRUD role pakai form lama, cukup re-skin ringan (field sama).

## Layar 2 — Setting

- Form profil sistem: nama sistem, logo (upload/preview), info dasar. Kartu tunggal
  rapi, ikut keluarga. Ringan — tak perlu banyak state.

## Deliverable

HTML/PNG (Role: pohon + panel detail · Setting: form). Desktop ±1440px, ~/Downloads.
