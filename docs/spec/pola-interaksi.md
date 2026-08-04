# Pola Interaksi — biar app terasa intuitif tanpa dikasih tahu

Pendamping `peta-tema.md`. Peta tema mengatur **warna & zona**; dokumen ini
mengatur **perilaku**: bagaimana elemen memberi tahu pengguna apa yang bisa
dilakukan, dan apa yang baru saja terjadi. Diputuskan bareng Ekky 2026-08-01
(diskusi dari kasus tombol "Cek & ACC" ROP).

> Prinsip payung: **pengguna tidak boleh butuh diberi tahu.** Kalau sebuah
> elemen perlu kalimat instruksi ("klik kartu untuk…"), tanda visualnya gagal.

---

## 1. Kamus pola (kosakata tetap — jangan bikin dialek baru)

### P1 · Afordansi — yang bisa dibuka harus kelihatan bisa dibuka
- **Baris/kartu yang membuka detail → chevron `›` di ujung kanan.**
  Referensi: Reservasi `.st-chev-cell` (FA, 38px, hover geser 2px + aksen) dan
  ROP `.rop-chev-cell` (SVG `lanjut`). Nilai final yang dipakai seragam:
  ukuran 16–18px (12–13px utk konteks padat/kartu kecil), goresan 2.2–2.6,
  **diam opacity .8, hover opacity 1 + geser 2px** — versi tipis/redup nyaris
  tak terlihat, percuma.
- Warna chevron ikut **aksen zona** halamanya (burgundy di Finance, cokelat
  di operasional) — dia penanda interaktif, itu jatah aksen zona (peta-tema §2).
- Ditambah `cursor: pointer` + hover latar baris. Ketiganya satu paket.
- **Rambu:** jangan ditebar ke elemen yang tidak membuka apa-apa (afordansi
  palsu = merusak kepercayaan), dan jangan semua elemen diberi penanda —
  kalau semua berteriak, tidak ada yang terdengar.

### P2 · Umpan balik — tiap aksi langsung terasa akibatnya
- Aksi selesai → **toast** singkat; aksi yang bisa disesali → toast dengan
  **"Urungkan"** (±5 detik), BUKAN dialog "yakin?" di depan. Dialog konfirmasi
  hanya untuk aksi yang butuh input tambahan (mis. bukti transfer).
- Aksi yang menunggu jaringan → tombol berubah teks + mati ("Mengecek…",
  "Menyimpan…"); konten yang menunggu fetch → penanda loading, jangan hening.
- Antrian (ACC, review, approval): setelah satu diputuskan → **langsung pindah
  ke item berikutnya**, jangan lempar balik ke daftar.

### P3 · Tombol mati wajib bilang kenapa
- Disabled tanpa penjelasan terasa seperti bug. Label tombolnya sendiri yang
  bicara ("Pilih akun beban dulu", "Pilih alasan dulu") atau hint satu baris
  di dekatnya ("Masih perlu diisi: …"). Referensi: footer drawer ROP.

### P4 · Konsistensi kosakata internal
- Status = kamus terpusat (KAH.ST untuk absensi, `_pill` untuk ROP): warna +
  ikon + KATA, tidak pernah warna saja.
- Satu pola untuk satu maksud di seluruh app: buka detail = klik baris ber-
  chevron; aksi cepat = tombol di kolom Aksi **hanya kalau melompati langkah**
  (bukan sekadar membuka detail — itu tugas baris).
- Utang diketahui: gaya tab masih ±4 dialek (fin-tabs, rop-tabs, aws-tabs,
  segmented absk). Disatukan belakangan, bareng item Reza.

### P5 · Empty state mengajarkan, bukan sekadar kosong
- Jawab "terus aku ngapain?": sebab + langkah berikutnya (± tombolnya).
  Referensi: "Antrian bersih — tidak ada pengajuan yang tenggelam".

### P6 · Petunjuk tercetak, bukan dihafal
- Pintasan keyboard dicetak di elemennya (Pengawasan: `1 2 3 · Enter · J/K`).
- Placeholder input = contoh nyata, bukan pengulangan label.

---

## 2. Mapping status per halaman (audit 2026-08-01)

✔ = sudah · ◐ = sebagian · ✗ = belum · − = tidak relevan

| Halaman | P1 afordansi | P2 umpan balik | P3 alasan mati | P5 empty | P6 petunjuk |
|---|---|---|---|---|---|
| ROP Antrian (Finance) | ✔ chevron | ✔ fetch+toast+Urungkan+auto-lanjut+angka hidup (#1) · kerangka drawer + toast gagal-muat (#2) | ✔ | ✔ | ✗ (Esc dsb tak tercetak) |
| ROP Pengajuan HP | ◐ (kartu → detail: cek chevron, blm diaudit) | ✔ (layar sukses + busy-state #2) | ✔ hint "Masih perlu diisi" TERNYATA SUDAH ADA sejak awal | ✔ | − |
| Reservasi list | ✔ chevron | ◐ | − | ◐ | ◐ |
| Pasien / Doctor list | ✔ chevron (Doctor ternyata sudah punya sejak awal; Pasien ditambah #4) | ◐ | − | ◐ | − |
| Kehadiran (matriks) | ✔ sel (hover angkat) · nama ber-chevron (#4) | ✔ | − | ✔ | ✔ tercetak di legenda |
| Kehadiran Saya (/akun) | ✔ kalender · kartu riwayat ber-chevron (#4) | ◐ (submit busy-state ✔ #2; masih reload) | ✔ label-bicara koreksi & ajukan (#3) | ✔ | ◐ |
| Pengawasan | ✔ (tile foto = jelas) | ✔ (dicek otomatis, keputusan tersimpan) | ✔ ("Pilih penyelesaian dulu") | ✔ | ✔ terbaik |
| Petty Cash | ✔ chevron kartu; teks instruksi dihapus (#4) | ◐ | − | ✔ | − |
| Absen HP | ✔ | ✔ (state kamera lengkap) | ✔ | − | ✔ |

## 3. Backlog (urutan disepakati Ekky 2026-08-01)

1. ~~ROP: toast + Urungkan + auto-lanjut~~ **SELESAI 2026-08-01.** ACC/tolak/
   bayar via fetch (fallback form biasa tetap hidup), toast + Urungkan utk
   ACC & tolak (service urungkanPenolakan baru, cermin urungkanPersetujuan),
   drawer auto-lanjut ke item berikutnya, statistik/badge/subtotal grup turun
   tanpa reload, toast galat utk error/validasi. Urungkan = reload (jalur
   langka, resync jujur). Teruji end-to-end net-zero.
2. ~~Loading feedback~~ **SELESAI 2026-08-01.** Drawer ROP terbuka SEKETIKA
   dengan kerangka berkilau (.rop-muat__bar) sampai fetch selesai; gagal muat
   → drawer menutup + toast galat. Tiga tombol submit yang tadinya hening kini
   ber-state "Mengirim…" + kebal klik dobel: Kirim Koreksi (_koreksi_form),
   Kirim Permohonan (_pengajuan_form), Kirim pengajuan (form ROP HP — tetap
   menghormati alur dua-klik akui-dobel). Pesan validasi bukti transfer
   di-Indonesia-kan sekalian.
3. ~~P3 ke form yang tersisa~~ **SELESAI 2026-08-01.** Koreksi Absen: tombol
   "Pilih dulu apa yang keliru" → "Kirim Koreksi" saat alasan dipilih; Ajukan
   Permohonan: "Pilih jenis dulu" → "Kirim Permohonan". Form ROP HP ternyata
   SUDAH punya hint "Masih perlu diisi: …" sejak awal — tidak disentuh.
4. ~~Sebar chevron (P1)~~ **SELESAI 2026-08-01.** Kartu Riwayat /akun · kartu
   cabang Petty Cash (teks instruksi "Klik kartu…" DIHAPUS) · nama karyawan
   Kehadiran (→ drawer sebulan) · baris Pasien list (+kolom th, colspan 8).
   Doctor list ternyata sudah ber-chevron sejak awal (dx-chev). Opacity diam
   diseragamkan .8 di semua implementasi.
5. **Satukan dialek tab (P4)** — jangka panjang, bareng redesign Reza
   (Reservasi header & Role page).

Aturan main: satu nomor = satu batch kerja + audit; nomor berikutnya baru
jalan setelah yang sebelumnya direview. Checklist P1-P6 dipakai tiap bikin
halaman baru (tambahan dari checklist peta-tema §7).
