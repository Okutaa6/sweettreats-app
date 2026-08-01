# Design Brief — Pengajuan / ROP (Request of Payment)

⚠️ **SATU layar per sesi claude.ai/design.** File ini berisi **4 sesi**.
Tiap sesi, tempel: blok **Konteks produk** + blok **Kamus status** + blok
**Gaya visual** zona yang sesuai + **satu** blok layar. Jangan gabung layar.

Rujukan flow lengkap (untuk pembaca manusia, tak perlu ikut ditempel):
`docs/rencana/rencana-pengajuan-rop.md`.

---

## Konteks produk (tempel di TIAP sesi)

Klinik gigi 5 cabang (Bintaro, Sunter, Kalideres, Pamulang, Cinere) + kantor
(IT, Marketing, Finance). Fitur ini menggantikan **formulir kertas "Request of
Payment"**: pengajuan pembelian/pembayaran yang dibayar transfer bank oleh
Finance. Pengaju hanya kepala departemen (Supervisor, Coordinator Dentist,
Head Marketing, Head IT, Finance) — karyawan biasa titip lewat atasannya.
Yang meng-ACC: **Finance**. Alur status:

```
diajukan → disetujui (ACC Finance) → dibayar (bukti transfer) → selesai (nota diterima)
        ↘ ditolak (wajib alasan, bisa diajukan ulang)     · kadaluarsa (30 hari diam)
```

Pain point yang HARUS terasa terjawab di desain: (1) pengaju selalu tahu
posisi pengajuannya tanpa nanya-nanya; (2) Finance gampang memutuskan mana
yang dibayar duluan (tanggal butuh + urgent + umur antrian); (3) tidak ada
pengajuan yang tenggelam. Bahasa UI: Indonesia awam, bukan istilah akunting
Inggris. **Tanpa emoji** — ikon SVG garis.

## Kamus status (tempel di TIAP sesi — konsisten di semua layar)

Chip status, warna solid teks di atas bg lembut:

| Status | Teks | Warna |
|---|---|---|
| Diajukan | "Menunggu ACC" | kuning `#B5701A` / bg `#FBEFD9` |
| Disetujui | "Disetujui — menunggu dibayar" | biru `#3C5FA8` / bg `#E4EAF6` |
| Ditolak | "Ditolak" | merah `#C0392B` / bg `#FBE3DE` |
| Dibayar | "Sudah dibayar" | hijau `#2F8E55` / bg `#E3F4E8` |
| Selesai | "Selesai" | netral tua `#3B1500` / bg `#EDE4D9` |
| Kadaluarsa | "Kadaluarsa" | abu `#6B6257` / bg `#EFEAE3` |

---

## SESI 1 — Layar: Form Pengajuan Baru (HP-first, zona COKELAT)

Dipakai kepala departemen dari HP (mis. Supervisor lagi keliling cabang).
Satu kolom, scroll pendek, tombol besar.

1. **Header**: judul "Pengajuan Baru", subjudul nomor yang akan didapat
   (mis. "ROP/BIN/2608/012") + cost centre aktif.
2. **Field** (urutan): Dibayarkan kepada (pilih vendor dari daftar ATAU ketik
   bebas — pola combobox) · Keterangan (textarea pendek) · Jumlah (input
   rupiah besar, format ribuan otomatis) · Butuh kapan (date picker) · toggle
   "Darurat" (jarang dipakai — kecil, bukan menonjol) · Cost centre (dropdown
   cabang/departemen) · Lampiran foto penawaran/invoice (tombol kamera +
   galeri, thumbnail preview).
3. **Peringatan dobel** (state penting): kalau ada pengajuan pending dengan
   vendor sama + nominal mirip, muncul kartu kuning di atas tombol kirim:
   "Sudah ada pengajuan serupa: ROP/BIN/2608/007 oleh Anisa (Rp 2.400.000,
   menunggu ACC). Tetap kirim?" — desainkan state ini.
4. **Tombol kirim** full-width. Sesudah kirim: layar sukses ringkas — nomor
   besar, status chip "Menunggu ACC", tombol "Lihat pengajuanku".
5. **Jalan pintas "Ajukan lagi"**: di atas form ada baris horizontal-scroll
   kartu kecil pengajuan terakhirku ("Galon Aqua — Rp 340rb", "Cahaya Tiga
   Bintang — Rp 2,1jt") → tap = form terisi otomatis, tinggal ubah.

## SESI 2 — Layar: Pengajuanku — riwayat & detail (HP-first, zona COKELAT)

Daftar pengajuan milik si pengaju + detail satu pengajuan (dua state dalam
satu layar; detail boleh sebagai halaman lanjutan).

1. **Daftar**: kartu per pengajuan — nomor kecil, keterangan 1 baris, nominal
   menonjol, chip status (kamus), umur ("3 hari lalu"). Filter chip atas:
   Semua · Menunggu · Disetujui · Dibayar · Selesai · Ditolak.
2. **Detail**: nominal + status besar di atas; lalu **timeline vertikal**
   status (diajukan → disetujui → dibayar → selesai) dengan siapa & kapan per
   langkah; lampiran (foto penawaran, bukti transfer Finance bisa dilihat);
   alasan penolakan kalau ditolak (kartu merah).
3. **Aksi kontekstual per status**: Ditolak → tombol "Ajukan ulang"
   (prefilled). Dibayar → tombol utama "Barang/dana diterima — upload nota"
   (kamera) yang menutup pengajuan jadi Selesai. Menunggu → tombol sekunder
   "Batalkan".
4. Desainkan juga **empty state** ("Belum ada pengajuan — buat yang pertama").

## SESI 3 — Layar: Antrian ROP — Finance (desktop, zona BURGUNDY)

Layar kerja utama Finance. Harus menjawab "hari ini bayar apa dulu?" dalam
5 detik. Model interaksi mirip inbox.

1. **Header**: judul "Antrian Pengajuan" + baris statistik: Menunggu ACC (n) ·
   Disetujui belum dibayar (n) · Total nominal antre (Rp) · Paling lama
   menunggu (X hari — merah kalau > 3 hari).
2. **Tabel antrian** (default sort: darurat dulu → butuh-kapan terdekat →
   paling lama antre): kolom nomor, pengaju + cost centre, keterangan,
   nominal (tabular, rata kanan), **butuh kapan** (merah kalau lewat/besok),
   **umur** badge ("5 hari" — makin tua makin mencolok), chip status, aksi.
   Baris darurat: strip kiri merah tipis. Tab atas: Menunggu ACC ·
   Disetujui (antrian bayar) · Riwayat.
3. **Panel ACC** (drawer kanan saat klik baris): detail + lampiran; di sinilah
   Finance memilih **akun beban** (dropdown searchable ~53 akun: "600102 —
   Beban Perlengkapan Kantor") — wajib sebelum ACC; tombol besar "Setujui" +
   sekunder "Tolak" (tolak wajib isi alasan, textarea muncul).
4. **Tab Disetujui**: aksi per baris "Tandai dibayar" → modal upload bukti
   transfer + tanggal bayar.
5. **Tombol export** kanan atas: "Export impor Accurate" (dari yang sudah
   dibayar, filter periode) — cukup tombol + menu kecil pilih bulan.

## SESI 4 — Layar: Rekap Pengeluaran ROP (desktop, zona BURGUNDY)

Laporan bulanan untuk Finance + Executive (read-only untuk Executive).

1. **Header**: judul "Rekap Pengajuan", pemilih periode bulan, filter cost
   centre, tombol "Export CSV".
2. **Kartu ringkas** (4): Total dibayar bulan ini · Jumlah pengajuan ·
   Rata-rata lama proses (diajukan→dibayar, hari) · Masih antre (Rp).
3. **Dua tabel berdampingan**: per **cost centre** (BIN/SUN/…/IT/MKT/FIN:
   jumlah, total Rp, bar horizontal proporsi) dan per **akun beban** (akun,
   jumlah, total Rp) — angka tabular, total di footer.
4. **Daftar transaksi periode** di bawah (nomor, tanggal bayar, pengaju,
   keterangan, akun, nominal, chip status) — padat, bisa di-filter status.

---

## Gaya visual — zona COKELAT (sesi 1 & 2)

- Latar cream `#F4ECE2`, teks ink `#3B1500`, kartu putih radius besar,
  aksen/tombol primer cokelat `#461500` (locked brand). Font Plus Jakarta
  Sans; angka tabular.
- Mobile-first 390px; sentuhan target besar; controls jangan bersaing dengan
  data — nominal & status = hal pertama terbaca.
- **Tanpa emoji**; ikon SVG garis stroke currentColor.

## Gaya visual — zona BURGUNDY / Finance (sesi 3 & 4)

- Sama dengan cokelat (cream + kartu putih + Plus Jakarta Sans) TAPI chrome
  halaman pakai burgundy: header/panel aksen `#8A2E43`, gradient header tabel
  `linear-gradient(120deg,#7A2739,#5A1B29)` teks terang, chip aktif
  `linear-gradient(135deg,#8A2E43,#6B2333)`, bg lembut `#F6E3E7`.
- Disiplin: burgundy hanya untuk chrome (header, tab, tombol) — merah terang
  `#C0392B` tetap khusus makna negatif (ditolak/terlambat), jangan ketukar.
- Desktop-first 1280px+.

## Yang TIDAK usah didesain

- Approval Executive / threshold 2 jt (fase 2).
- Modul budget & budget number (kolom disiapkan di backend saja).
- Push otomatis ke Accurate & multi-item per pengajuan (fase 2).
- Halaman petty cash (sudah ada, cuma akan diberi link silang).
- Manajemen master vendor (v1 cukup combobox di form).
