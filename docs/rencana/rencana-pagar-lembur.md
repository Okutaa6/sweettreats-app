# Rencana — Dua pagar untuk pengajuan lembur

> **Status**: rencana, ACC Ekky 2026-08-24. Belum dibangun. Tanpa tenggat.
> Lahir dari audit fitur DODO (`rencana` tak ada — lihat memori
> `project_dodo_lembur_libur`). Kedua temuan **bukan bug DODO**; DODO cuma yang
> membuatnya kelihatan.
>
> Semua angka diverifikasi ke DB **prod** 2026-08-23/24.

---

## Kenapa dua-duanya kecil tapi layak

Keduanya soal **uang yang salah bayar tanpa ada yang bersuara**. Satu sudah
terjadi; satu sedang menunggu di antrean ACC saat dokumen ini ditulis.

---

## 1. Tak ada pagar untuk pengajuan lembur KEMBAR

### Yang terjadi

**Afrina Apriliani, 10 Agustus 2026.** Dua pengajuan lembur:

| | req#30 | req#31 |
|---|---|---|
| dibuat | 11 Agu **21:32:10** | 11 Agu **21:34:06** |
| jam | 19:15–21:10 | 19:15–21:10 |
| menit | 115 | 115 |
| alasan | identik, kata per kata | identik |
| di-ACC | Anisa, 14 Agu 13:33 | Anisa, 14 Agu 13:25 |

Berselang **116 detik**. Bedanya cuma nama berkas foto — dia meng-upload ulang,
mungkin mengira kiriman pertama gagal. Anisa menyetujui keduanya, berselang 8
menit, tanpa tanda apa pun bahwa itu kiriman yang sama.

`AttendanceReconciler` menjumlahkan **semua** lembur yang di-ACC pada tanggal
itu → `att_day.lembur_menit_disetujui = 230` untuk sesi yang panjangnya **115
menit**. Dibayar dobel, diam-diam.

Sudah dibetulkan 23 Agu (req#31 di-`rejected`, bukan dihapus, + jejak di
`att_request_approval`; `att:reconcile` → 230 → 115). Prod kini **0 kasus**.
**Tapi pagarnya belum ada.**

### Yang perlu dibangun

Pagar di **titik simpan** dan di **titik ACC** — dua-duanya, karena tombol yang
disembunyikan tetap bisa dikirim lewat form buatan sendiri (pelajaran yang sama
dengan `jenisUntuk()`).

- **Saat mengajukan**: kalau sudah ada pengajuan lembur ber-`status` aktif
  (`pending_l1` atau `approved`) untuk **user + tanggal_from + jam_from +
  jam_to** yang sama → tolak dengan pesan yang menyebut nomor pengajuan yang
  sudah ada, bukan sekadar "gagal".
- **Saat meng-ACC**: penyetuju diberi tahu kalau ada pengajuan lain yang
  bertindihan jam-nya di tanggal yang sama — termasuk yang **belum** di-ACC.
  Tindihan sebagian (mis. 19:00–21:00 vs 19:15–21:10) tak boleh lolos hanya
  karena jamnya tak persis sama.

⚠️ **Jangan** menolak diam-diam atau menggabungkan otomatis. Dua pengajuan
mirip bisa saja sah (dua sesi terpisah di hari yang sama). Yang salah adalah
**tak ada yang diberi tahu**, bukan keberadaan dua barisnya.

---

## 2. Peringatan chip datang SESUDAH keputusan diambil

### Yang terjadi

`LemburDodo::chipMencurigakan()` menyaring `status = 'approved'`. Jadi ia
memperingatkan Finance tentang chip yang salah **yang sudah terlanjur
disetujui** — dan **tidak menolong penyetuju** di detik dia memutuskan.

Uangnya memang masih terlindungi: peringatannya muncul di Lembar Gaji, layar
terakhir sebelum gaji dihitung. Jadi ini **bukan lubang**. Tapi menangkap lebih
awal jauh lebih murah daripada membatalkan ACC.

### Bukti bahwa ini bukan teori

Dua kejadian nyata dalam 48 jam, **dua arah berlawanan**:

- **Ekky sendiri, req#172** — 23 Agu (**Minggu**), 660 menit, chip `jam`.
  Aturannya `Fleksibel Kantor` yang libur **Minggu & Senin** → itu DODO yang
  akan dibayar tarif biasa. Ketahuan hanya karena dia kebetulan bertanya.
  Sudah dibetulkan 24 Agu selagi masih `pending_l1`.
- **Melani Safrida, req#162** — 22 Agu (**Sabtu**), 129 menit, chip
  `tambah_hari`. Aturannya `FO-Asdent` yang libur **Senin saja** → Sabtu hari
  kerja biasa, jadi lembur biasa akan dibayar tarif hari libur. **Masih
  `pending_l1` saat dokumen ini ditulis** — menunggu konfirmasi Ekky ke Nissa,
  jangan diubah dari chat build.

Dijalankan dengan menyertakan `pending_l1`, pemeriksaannya **langsung menangkap
keduanya**. Dengan aturan sekarang: nol.

### Yang perlu dibangun

Perubahan intinya **satu baris**: `whereIn('r.status', ['approved','pending_l1'])`
menggantikan `where('r.status','approved')` di `chipMencurigakan()`.

Yang lebih berharga: **tampilkan peringatannya di layar persetujuan**, bukan
cuma di Lembar Gaji. Penyetuju (Anisa/Irma/Fajri) yang punya konteks "hari itu
dia libur atau tidak" — Finance sering tidak.

⚠️ **Jangan menghitung ulang "hari libur" di layar persetujuan.** Pakai
`AttendanceReconciler::liburUntuk()` yang sudah ada. Definisinya menyangkut
aturan mingguan + kalender libur + **cabang bertugas**; salinan yang lupa satu
saja akan berbohong dengan meyakinkan. (Penulis dokumen ini sempat memakai
`att_day.status='libur'` sebagai jalan pintas dan mendapat **2 positif palsu** —
reconciler memang menulis `hadir` ketika orang bekerja di hari liburnya.)

---

## 3. Uji terima

Uji HTTP "hijau" tak membuktikan UI jalan — `curl -L` bisa mengikuti pentalan
ke halaman login lalu melapor 200. **Periksa URL akhirnya.**

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | Ajukan lembur kembar persis (user+tanggal+jam sama) | **ditolak**, pesannya menyebut nomor pengajuan yang sudah ada |
| 2 | Ajukan lembur yang jamnya **bertindih sebagian** | penyetuju **diberi tahu**; tidak ditolak diam-diam |
| 3 | Dua sesi terpisah sah di hari yang sama (mis. 08–10 dan 19–21) | **tetap boleh** |
| 4 | Kiriman kembar lewat form buatan sendiri (bypass UI) | tetap ditolak di server |
| 5 | Chip salah pada pengajuan **pending** | muncul di daftar mencurigakan |
| 6 | Chip salah pada pengajuan **approved** | tetap muncul (tak ada regresi) |
| 7 | Chip yang cocok | **tidak** diganggu |
| 8 | Orang ber-aturan `Fleksibel` (tanpa baris hari) | **tidak dituduh** — jawabannya "tak bisa dijawab" |
| 9 | Layar persetujuan | peringatan tampil, dan `liburUntuk()` yang dipakai — bukan definisi kedua |
| 10 | `LemburDodo` 9 uji lama | tetap hijau |
| 11 | Invarian `biasa + dodo = total` | tetap pegang di seluruh data |

---

## 4. Yang sengaja TIDAK dikerjakan

1. **Jangan sentuh req#162 (Melani).** Menunggu Ekky mengonfirmasi ke Nissa.
2. **Jangan menggabungkan pengajuan kembar otomatis.** Manusia yang memutuskan
   mana yang sah; sistem cukup memberi tahu.
3. **Jangan mengubah `LemburDodo::dasar()`** — invarian `biasa + dodo = total`
   bergantung padanya, dan sudah terverifikasi di 31 pasangan orang-bulan prod.
4. Prod menunggu dev lulus §3.
