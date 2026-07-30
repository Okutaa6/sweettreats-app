# Design Brief: Pusat Akun — layar AKSES (+ guardrail)

2026-07-24. **Satu layar per sesi claude.ai/design.** Bagian keluarga "Pusat Akun"
(induk: `design-brief-pusat-akun.md`). File ini berdiri sendiri.

## Konteks singkat

Sweet Treats = klinik gigi 5 cabang. **Akses** = halaman IT mengelola pohon menu &
hak akses (node → di-grant ke role). Ini area **paling rawan** di sistem: salah edit =
menu hilang senyap tanpa error. Jadi desainnya harus penuh **pengaman (guardrail)**,
bukan cuma cantik. Pemakainya teknis; bahasa Indonesia jelas, istilah teknis boleh
(node, grant, role, rebuild) asal konsisten.

## Batasan visual (WAJIB — samakan keluarga absen HP / Pengawasan / Kesehatan)

- Cream `#F4ECE2`, coklat brand `#461500` (terkunci), aksen `#C77A1E`/`#E8842B`,
  hijau `#2F8E55`, merah `#C0392B`. Plus Jakarta Sans. Desktop-first (±1440px).
- Ikon SVG garis (currentColor), **TANPA emoji**. Shell DataCenter (top-bar tipis).

## Layar — Akses

- **Pohon node akses** (parent → child, bisa collapse). Tiap node: nama, link,
  ikon, jumlah role yang di-grant. Penanda visual:
  - **node yatim** (tak di-grant ke role mana pun) — badge abu "tak dipakai".
  - **link dobel** (2 node link sama) — badge kuning peringatan.
- **Panel/aksi per node**: lihat role mana yang punya akses; tambah/hapus/ubah.

## Guardrail (INTI — jangan didesain sebagai form polos)

1. **Dialog hapus/ubah node yang masih di-grant**: sebutkan role yang bakal
   KEHILANGAN akses ("3 role: Finance, IT, Supervisor akan kehilangan menu ini"),
   minta konfirmasi eksplisit. Warna hati-hati (bukan merah alarm penuh, tapi tegas).
2. **Banner "Perubahan belum hidup"**: SETELAH ada perubahan grant, muncul banner
   menonjol: "Perubahan akses belum berlaku. Terapkan sekarang." + tombol
   **Terapkan (rebuild)**. Ini nutup penyebab kegagalan-senyap #1 di sistem ini
   (SQL sukses tapi lupa rebuild → menu tak muncul).
3. **Penanda child-tanpa-parent**: node yang di-grant ke role tapi parent-nya tidak
   → gugur dari menu. Tandai (badge "parent tak di-grant") biar ketahuan.

## Komponen

- Dialog konfirmasi berbobot (dampak dijelaskan, bukan "yakin? ya/tidak" polos).
- Banner aksi persisten (rebuild). Badge status node (yatim/dobel/parent-hilang).
- Toast sukses/gagal.

## Deliverable

HTML/PNG per state (pohon normal · dialog hapus-ter-grant · banner belum-rebuild ·
node bermasalah). Desktop ±1440px, folder ~/Downloads.
