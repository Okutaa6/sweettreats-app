# Design Brief — Shell Global (Sidebar + Header) · Sweet Treats

Brief untuk **claude.ai/design**. Yang didesain: **rangka (shell) global aplikasi** —
sidebar + header — yang ngelilingin SEMUA halaman. Ini bukan konten halaman; ini kerangka
tetap yang dipakai lintas modul (dashboard, reservasi, absensi, checklist, finance, dst).

Tujuan: satu shell yang **responsive (HP + desktop)** dan **rapi walau menu panjang**,
supaya tiap fitur baru ke depan otomatis dapet rangka yang bener — nggak nambal per-halaman.

---

## 0. ATURAN WARNA — PENTING, BACA DULU

**Shell ini NETRAL WARNA. Jangan hardcode satu warna brand.**

Warna aksen **di-swap per departemen** lewat satu variabel (mis. `--accent`):

| Departemen | Aksen (contoh, jangan dikunci) |
|---|---|
| Default | coklat hangat |
| Finance | merah |
| Executive | biru |
| IT | hitam |
| (dst) | nyusul |

Artinya:
- Desain pakai **basis netral** (putih/krem/abu hangat) + **satu aksen** yang gampang diganti.
- Aksen dipakai buat: item menu aktif, highlight, badge, tombol utama, garis identitas.
- **Jangan** bikin komposisi yang cuma "jadi" kalau warnanya coklat. Harus tetap enak dilihat
  kalau aksen diganti merah / biru / hitam.
- Sediakan aksen dalam bentuk **satu variabel** (plus turunannya kalau perlu: aksen-terang,
  aksen-teks). Sisanya netral.

Font: **Plus Jakarta Sans** (konsisten sama modul lain). Sudut membulat, rasa hangat & bersih.

---

## 1. Struktur

Dua bagian tetap: **Sidebar (kiri)** + **Header (atas)**. Konten halaman mengisi sisanya.

### Sidebar (desktop) — tiga zona vertikal

```
┌─────────────────────┐
│  [logo / brand]     │  ← atas: identitas app
├─────────────────────┤
│  MENU (alat kerja)  │  ← tengah: navigasi per-role.
│   • item            │     BISA PANJANG → area ini yang scroll,
│   • item            │     bukan seluruh sidebar. Header & akun tetap diam.
│   • item            │     Dukung juga mode "ciut" (ikon saja) opsional.
│   • ...             │
│                     │
├─────────────────────┤
│  ┌───────────────┐  │  ← BAWAH: blok AKUN (avatar + nama + role).
│  │ (A) Nama      │  │     Klik → DROP-UP (buka ke ATAS) berisi:
│  │     Role      ▲│  │        · Absensi Saya
│  └───────────────┘  │        · Checklist Saya
└─────────────────────┘        · Persetujuan   (boleh ada badge angka)
                               · ── garis ──
                               · Profil / Ganti kata sandi
                               · Keluar
```

**Kenapa drop-up akun:** Absensi/Checklist/Persetujuan itu **personal & lintas-role** (hampir
semua staff punya, sama buat semua) — beda kelas dari menu "alat kerja" yang per-departemen.
Kalau ditaruh di menu utama, sidebar makin panjang (user tertentu udah punya BANYAK halaman →
harus scroll). Dipisah ke menu akun di bawah = menu utama lebih pendek, dan di HP posisinya
bawah = gampang dijangkau jempol.

- Blok akun **selalu nempel di bawah** (nggak ikut ke-scroll sama menu).
- Drop-up buka ke **atas** (karena anchor-nya di bawah), nutup kalau klik luar.
- Item drop-up muncul sesuai yang dimiliki user (sebagian bisa nggak ada → sembunyikan).

### Header (bar atas)

- Kiri: **judul halaman** + konteks singkat (mis. tanggal / breadcrumb).
- Kanan: area aksi global — **lonceng notifikasi** (dengan badge angka), avatar kecil (opsional).
- Di HP: muncul **tombol hamburger** buat buka sidebar (lihat §2).

---

## 2. Perilaku Mobile (INI YANG PALING KRUSIAL)

Di HP, shell harus berubah jadi pola aplikasi mobile beneran:

1. **Sidebar disembunyikan default** (geser keluar ke kiri / off-canvas). Konten **full-width**.
2. **Hamburger** di header → sidebar masuk sebagai **drawer overlay** dari kiri, di atas konten,
   dengan **scrim gelap** di belakang. Tap scrim / tombol close → drawer nutup.
3. Blok akun + drop-up tetap kepakai di dalam drawer (atau versi ringkasnya).
4. Transisi halus (slide), nggak ngejeglong.

> Konteks teknis (buat implementasi, bukan buat desain): shell sekarang punya bug — di layar
> ≤1024px sidebar jadi `fixed` tapi nggak disembunyiin, dan toggle cuma normal↔ciut, nggak ada
> off-canvas. Desain ini yang jadi acuan benerinnya.

Sasaran perangkat: HP (≤640), tablet (641–1024), desktop (>1024). Titik putus boleh disesuaikan.

---

## 3. State & detail yang perlu didesain

- **Item menu aktif** (halaman yang lagi dibuka) — pakai aksen.
- **Menu panjang** — area menu scroll mulus; scrollbar tipis; header/akun tetap diam.
- **Sidebar ciut** (ikon-only) di desktop — opsional tapi bagus (hemat ruang buat power user).
- **Badge angka** — di item Persetujuan (jumlah menunggu) & lonceng notifikasi.
- **Avatar** — inisial di lingkaran kalau nggak ada foto.
- **Drop-up akun** — state terbuka & tertutup, animasi buka-ke-atas.
- **Drawer mobile** — state terbuka (dengan scrim) & tertutup.
- **Hover / fokus** — buat aksesibilitas keyboard.

---

## 4. Yang TIDAK usah didesain

- **Konten halaman** (tabel, form, kartu absensi, dll) — itu modul terpisah, desain sendiri.
- **Palet warna spesifik** — netral + satu aksen swappable (§0). Departemen-departemen
  ngisi warnanya belakangan lewat theme.
- Halaman login / error.

---

## 5. Deliverable yang diharapkan

Layar/variasi buat dokumentasi:
1. Desktop — sidebar penuh + header + area konten (placeholder).
2. Desktop — **drop-up akun terbuka** (Absensi/Checklist/Persetujuan/Profil/Keluar).
3. Desktop — sidebar **ciut** (ikon-only) [opsional].
4. Mobile — konten full-width, sidebar tertutup (hamburger keliatan).
5. Mobile — **drawer terbuka** (overlay + scrim), blok akun di bawah.
6. Contoh **aksen diganti** (mis. tampilkan default vs 1 departemen lain) buat mbuktiin
   netral-warna beneran jalan.

> Catatan buat developer (dari kami): warna final dipasang lewat body-class per-role
> (`theme-*`) yang nge-set variabel aksen — jadi 1 komponen shell, banyak warna departemen.
> Mohon aksen diekspos sebagai **satu variabel** biar gampang di-wire.
