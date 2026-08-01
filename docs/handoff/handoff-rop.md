# Handoff — Pengajuan / ROP (backend fase 1)

> Branch `feature/rop`, base `feature/absensi` (df8b6cb). Dikerjakan 2026-07-31.
> Rencana lengkap: `docs/rencana/rencana-pengajuan-rop.md` (ada di working tree
> utama, belum ter-commit saat handoff ini ditulis).
> Semua kerjaan **sengaja dibiarkan unstaged** untuk direview dulu.

## 1. Cara menjalankan

Worktree terpisah supaya testing reskin Kehadiran di working tree utama tidak
terganggu (dua checkout, satu DB lokal yang sama):

```
cd C:\Users\Ekky13\Downloads\sweettreats-rop
php artisan serve --host=127.0.0.1 --port=8900     # 8899 dipakai working tree utama
```

Login uji: `muhamad.ikhsan@sweettreats.local` / `sweettreats2026`.
Layar: `/pengajuan` (pengaju, cokelat) · `/finance/rop` (antrian, burgundy) ·
`/finance/rop/rekap` (rekap).

> Catatan worktree: folder `vendor/` di sini **salinan fisik**, bukan junction.
> Junction sempat dipakai dan membuat autoload Composer menunjuk balik ke
> `app/` repo utama — kode worktree tidak jalan sama sekali. Jangan diulang.

## 2. Yang sudah jadi

**SQL** (`database/sql/`, dijalankan berurutan, lalu `php artisan access:rebuild`)
1. `2026_07_31_rop_core.sql` — 5 tabel: `rop_cost_centre`, `vendors`,
   `rop_counter`, `rop_requests`, `rop_status_log`.
2. `2026_07_31_rop_access.sql` — 3 node menu di section "Keuangan" + grant BY LINK.

**Kode**
- `app/Models/` — `RopRequest`, `RopStatusLog`, `RopCostCentre`, `Vendor`.
- `app/Services/RopService.php` — **satu-satunya pintu transisi status**.
  Controller tidak boleh meng-update kolom `status` sendiri; lewat sini audit
  log & notifikasi pasti ikut tertulis, dan transisi ilegal tertolak (409).
- `app/Services/RopCoaCatalog.php` — daftar akun beban per buku Accurate.
- `app/Services/RopAttachment.php` — lampiran: file biasa atau foto base64
  hasil kompresi browser.
- `app/Http/Controllers/Rop/PengajuanController.php` — sisi pengaju.
- `app/Http/Controllers/Finance/RopQueueController.php` — antrian & ACC.
- `app/Http/Controllers/Finance/RopRekapController.php` — rekap & 2 export.
- `app/Console/Commands/RopSapu.php` (`rop:sapu`, harian 07:00) dan
  `RopTarikVendor.php` (`rop:tarik-vendor`, Senin 04:10) — sudah didaftarkan
  di `app/Console/Kernel.php`.
- `resources/views/rop/pengajuan/*` dan `resources/views/finance/rop/*`.

## 3. Keputusan teknis yang jangan dibongkar tanpa baca ini

**Akun beban per buku, bukan gabungan.** Nomor akun yang sama berarti lain di
tiap buku Accurate (`600010` = Beban Tunjangan Karyawan di bintaro, Beban THR
di cinere, Beban Training di holding; 33 nomor punya nama berbeda). Buku mana
untuk cost centre mana ada di `rop_cost_centre.accurate_slug`. Nama akun
di-snapshot ke `rop_requests.coa_name` saat ACC supaya riwayat tidak berubah
arti waktu staging di-resync.

**Penomoran atomik.** `rop_counter` + `LAST_INSERT_ID(last_no + 1)`.
`LAST_INSERT_ID(1)` di klausa VALUES itu wajib — tabelnya tanpa AUTO_INCREMENT,
jadi INSERT baris baru tidak menyentuh nilai itu dan cost centre baru sempat
dapat nomor `003` alih-alih `001`. Jangan diganti `MAX(number)+1`.

**Vendor dedup per nama.** `accurate_id` hanya unik di dalam satu buku (id 300
milik vendor berbeda di sunter dan pamulang), jadi kuncinya `UNIQUE(nama)`.
Untuk push fase 2, pemetaan id per buku harus tabel terpisah.

**Gerbang akses.** Segment `finance` ada di `AuthGuard::SKIP_RBAC` → dua layar
Finance dijaga controller (`RopService::ROLE_ANTRIAN` / `ROLE_REKAP`), bukan
RBAC menu. Segment `pengajuan` TIDAK di-skip → node ber-link `pengajuan` yang
menentukan siapa boleh membuka. **Kalau daftar role di service diubah, ubah
juga grant di SQL** — menu tampil tapi 403 adalah kegagalan senyap.

Catatan temuan lain: `hasMenuAccess()` hanya menemukan node **level 3**
(parent → sub → child), padahal `finance/pettycash` dan `absensi/cuti` ada di
level 2. Jadi helper itu tidak dipakai sebagai gerbang ROP. Ini bukan bug yang
diperkenalkan ROP, tapi layak diperiksa terpisah.

## 4. Sudah diuji (lokal, lewat HTTP sungguhan)

Alur pengaju (kirim, deteksi dobel, batal, konfirmasi terima), alur Finance
(tinjau, akun ngawur ditolak, setujui, tandai dibayar, tolak wajib alasan),
ajukan ulang berantai `resubmit_of`, transisi ilegal ditolak 409, timeline
audit lengkap, notifikasi ke role Finance & balik ke pengaju, rekap + 2 export
CSV, `rop:sapu` (reminder + anti-spam + kadaluarsa), `rop:tarik-vendor`
(73 vendor).

Data uji tertinggal di DB lokal: 3 pengajuan (selesai / ditolak / kadaluarsa)
+ 73 vendor. Hapus kapan saja:
`DELETE FROM rop_status_log; DELETE FROM rop_requests; DELETE FROM rop_counter;`

## 5. UI final (dari design handoff)

Sumber: `C:\Users\Ekky13\Downloads\design_handoff_rop` (README + 7 screenshot
HP + 3 prototipe HTML). Dibangun ulang di Blade, bukan disalin — prototipenya
memang bukan kode produksi.

- `public/assets/css/rop.sweettreats.css` — token warna, komponen, kamus status.
  **Di-scope ke `.rop-surface` / `.rop-fin`, bukan body-class.** Alasannya:
  `layouts/app.blade.php` sedang dimodifikasi reskin di working tree utama, dan
  `zona.css` (pembawa `body.zona-finance`) belum ada di branch ini. Chrome
  burgundy ditulis eksplisit supaya halaman berdiri sendiri.
- `public/assets/js/rop-foto.js` — kompresi foto di browser (1280px, JPEG 0.75).
- `resources/views/rop/_pill.blade.php` — satu-satunya tempat status jadi warna
  + teks, dipakai kedua zona supaya kamusnya tidak pernah berbeda.
- `resources/views/rop/_ikon.blade.php` — ikon SVG garis; tidak bergantung
  kelengkapan FontAwesome 5.8 dan tidak ada emoji.

Yang diterapkan dari handoff: 3 kartu angka yang sekaligus filter · kartu
pengajuan dengan baris *nudge* ("upload nota untuk menutup") · master–detail di
≥992px · pintasan "Ajukan lagi" · input rupiah besar + chip cepat · peringatan
dobel dua-klik (klik pertama mengakui, kedua mengirim) · layar sukses · antrian
Finance berkelompok per tingkat mendesak + subtotal · badge umur bertingkat ·
drawer ACC dengan saran akun → chip akun terakhir → combobox · alasan tolak
cepat · modal tandai dibayar.

**Beda yang disengaja dari handoff (dan alasannya):**

| Hal | Handoff | Di sini | Alasan |
|---|---|---|---|
| Kode cost centre | `KDL`, `PML`, `CNR` | `KAL`, `PAM`, `CIN` | Mengikuti `rencana-pengajuan-rop.md` §6 yang sudah di-ACC. Kode ini masuk ke nomor ROP di dokumen resmi — perlu keputusan user kalau mau ikut handoff. |
| Nomor urut | `MAX(nomor)+1` | tabel `rop_counter` atomik | Handoff sendiri minta "server yang menerbitkan no (hindari race)". MAX+1 balapan saat dua orang kirim bersamaan. |
| Sidebar | grup OPERASIONAL | grup Keuangan | Satu tempat dengan Petty Cash — dua-duanya sisi pengeluaran, dirilis sepaket. |

Kode cost centre **di-ACC user 2026-07-31**: tetap `KAL` / `PAM` / `CIN`.

## 5a. Urungkan, batas petty cash, link silang (2026-07-31)

**Toast "Urungkan"** — setelah ACC, muncul toast berisi nomor + akun yang
dipilih, dengan garis waktu menipis ~5 detik. `RopService::urungkanPersetujuan()`
sengaja BUKAN bagian dari peta `TRANSISI`: kalau disetujui→diajukan jadi
transisi biasa, ia bisa dipanggil dari mana saja kapan saja. Syaratnya ketat —
status masih `disetujui` (yang sudah dibayar berarti uang keluar), pelakunya
orang yang menyetujui, dan masih dalam `DETIK_URUNG` (120 detik; toast di layar
lebih pendek, tapi yang menjaga adalah syarat statusnya). Jejak lama **tidak
dihapus**: ada baris log tambahan "Persetujuan diurungkan", dan pengaju yang
sudah terlanjur diberi tahu mendapat notifikasi koreksi.

**Batas petty cash** — `RopService::AMBANG_PETTY_CASH` (Rp 300.000). Di form,
saat nominal di bawah itu muncul saran (bukan larangan) untuk lewat petty cash
cabang. Batasnya aturan operasional yang bisa bergeser, dan pengaju yang tahu
konteksnya (vendor minta transfer, misalnya).

**Link silang** — dari halaman petty cash Finance ke antrian ROP, dan
sebaliknya. Tombol "Petty Cash" di antrian ROP **hanya tampil untuk
`canSeeAllBranches()`**: halaman petty cash dijaga helper itu saja, sedangkan
antrian ROP juga boleh dibuka Head IT/IT — tanpa penjagaan ini, tombolnya
berujung 403 (ketahuan saat uji).

Efek samping yang ikut diperbaiki: semua aksi Finance memakai
`back(302, [], route('finance.rop'))`, bukan `back()` polos. `back()` tanpa
Referer jatuh ke `/`, dan pesan sukses serta toast ikut hilang ke halaman
yang salah.

## 5c. Hasil audit mandiri + uji terima (2026-08-01)

Uji terima oleh user di HP & desktop: lolos. Jejaknya terlihat di data —
pengajuan dengan lampiran foto dari kamera HP, dan satu persetujuan yang
di-ACC lalu diurungkan. Kompresi browser bekerja (foto invoice jadi 42 KB).

Audit mandiri menemukan enam hal, semuanya sudah diperbaiki:

1. **Nama pengaju tidak tampil** — dipakai `session('nama')` padahal key yang
   benar `session('name')`, dengan fallback `auth()->user()` yang tidak pernah
   berlaku (aplikasi ini memakai session manual, bukan Auth guard).
2. **`resubmit_of` tidak dicek kepemilikan** — validasi `exists` saja lolos
   untuk id milik orang lain, dan nomor pengajuan mereka ikut tampil sebagai
   "pengajuan ulang dari …". Sekarang harus milik sendiri DAN berstatus layak
   diulang; kalau tidak, rantainya dibuang diam-diam (bukan error — pengaju
   tidak melakukan kesalahan apa pun dari layar).
3. **Pintasan "Ajukan lagi" tidak unik** — diambil 6 terakhir apa adanya, jadi
   belanja rutin yang sama berulang memenuhi keenam kartu. Sekarang di-dedup
   per penerima + keterangan.
4. **Endpoint `pengajuan/vendor` tidak pernah dipanggil** — form memakai
   `datalist`. Dihapus; kalau daftar vendor tumbuh besar dan perlu pencarian,
   kembalikan dari riwayat git.
5. **`back()` tanpa fallback** di controller pengaju (2 tempat) — isian bisa
   hilang ke '/' saat tidak ada Referer.
6. **Nota saat "selesai"** — diputuskan tetap **tidak wajib**: menutup keras
   membuat pengajuan yang notanya benar-benar hilang menggantung selamanya di
   status `dibayar` dan angka rekap ikut melenceng. Sebagai gantinya penutupan
   tanpa nota dicatat di jejak audit ("ditutup tanpa nota"), jadi kelonggaran
   itu tetap terlihat saat diperiksa.

Perbaikan tampilan dari screenshot HP user: label **Darurat** sempat tampil
biru karena `<label>`/`<b>` kena warna tautan dari CSS global tema (sekarang
`.rop-toggle` menulis warnanya eksplisit), dan kontrol berkas bawaan browser
("Choose file — No file chosen") diganti tombol **Kamera** / **Galeri**.
Tombolnya memakai satu input yang sama; bedanya cuma atribut `capture`, yang
membuat HP langsung membuka kamera alih-alih pemilih berkas.

## 5b. Yang tidak bisa diuji tanpa manusia

Sisi server sudah ditutup uji otomatis, termasuk simpan lampiran (base64 &
berkas biasa), penolakan bukan-gambar / data URL rusak / terlalu besar, dan
satu unggahan sungguhan lewat HTTP sampai berkasnya ada di disk. Yang tersisa
butuh browser & jari:

**Di HP (paling penting — layar pengaju memang HP-first):**
1. Tombol kamera di form: ambil foto invoice **dengan kamera**, pastikan
   pratinjau muncul dan ukurannya turun jadi ratusan KB (tertulis di bawah
   pratinjau). Kirim, lalu buka detailnya dan pastikan fotonya terbaca.
2. Isi jumlah pakai chip +50rb/+100rb/+500rb dan ketik manual — titik ribuan
   harus muncul sendiri, dan tombol kirim redup selama ada isian wajib kosong.
3. Coba kirim pengajuan yang mirip dengan yang sudah ada (vendor sama, nominal
   beda tipis): kartu kuning muncul, tombol kirim berubah, **klik pertama
   tidak mengirim**, klik kedua baru mengirim.
4. Di pengajuan berstatus "Sudah dibayar": tombol hijau upload nota → status
   jadi Selesai.
5. Combobox vendor: ketik sebagian nama, pastikan daftarnya keluar (perilaku
   `datalist` berbeda-beda antar browser HP).

**Di desktop (Finance):**
6. Klik "Cek & ACC": drawer terbuka, tombol Setujui **mati** sampai akun beban
   dipilih. Coba jalur saran akun, chip akun terakhir, dan combobox.
7. Setujui satu pengajuan → toast muncul → klik **Urungkan** dalam 5 detik →
   pengajuan kembali ke antrian tanpa akun beban.
8. "Tandai dibayar": modal, unggah bukti transfer, cek muncul di detail pengaju.
9. Tolak dengan alasan cepat; cek kartu merah alasannya terlihat oleh pengaju.
10. Layar daftar pengaju di jendela lebar: klik kartu kiri → panel kanan ikut
    berganti.

## 6. Yang belum & butuh input

1. **Template impor Accurate — DIPAUSE (keputusan user 2026-08-01).**
   Perlu dibicarakan dengan Finance dulu dan itu makan waktu, jadi tidak
   ditunggu. **Tidak memblokir apa pun**: seluruh alur ROP lengkap tanpa ini.

   Yang berlaku selama pause: tombolnya berlabel **"Draf untuk Accurate"**
   (gaya sekunder, bukan tombol utama) dan halaman rekap menyatakan berkas itu
   belum siap diimpor. Susunan kolom sekarang mengikuti handoff desain
   (`Tanggal, No Bukti, Kode Akun, Nama Akun, Cost Centre, Penerima,
   Keterangan, Nominal`; pemisah koma, UTF-8 BOM) — itu rancangan desainer,
   bukan template resmi Accurate.

   **Cara mencabut pause:** unduh berkas draf itu, bawa ke Finance, cocokkan
   dengan template impor resmi Accurate (bisa diunduh dari menu Impor di
   Accurate, atau ambil berkas yang pernah dipakai Della). Setelah susunan
   kolomnya diketahui, yang perlu diubah hanya array header & baris di
   `RopRekapController::exportAccurate()`, lalu hapus catatan kuning di
   `finance/rop/rekap.blade.php` dan kembalikan label tombolnya.

   Catatan arah: kalau fase 2 (push API ke Accurate) jadi dikerjakan lebih
   dulu, export CSV ini bisa saja tidak pernah dibutuhkan — jadi jangan
   diinvestasikan lebih dari sekadar mencocokkan header.
2. **Peta tema** — baris untuk 3 halaman ROP belum ditambahkan ke
   `docs/spec/peta-tema.md`; file itu sedang dimodifikasi di working tree
   utama (reskin), sengaja tidak disentuh dari sini supaya tidak bentrok.
3. **Uji terima oleh Finance** — Della belum pernah memakainya.

## 7. Deploy (jangan dijalankan sebelum diminta)

Prasyarat prod yang belum terpenuhi:
- 8 role absensi (Supervisor, Coordinator Dentist, Finance, Head IT, IT, …)
  belum ada di prod — ROP satu gerbong dengan `docs/deploy/deploy-absensi.md`.
- **`accurate_staging_glaccount` belum ada di prod** (dicek 2026-07-31) →
  tanpa itu dropdown akun beban kosong dan Finance tidak bisa meng-ACC.
  Ini dependensi baru yang sebelumnya tidak tercatat di runbook mana pun.

Urutan: SQL core → SQL access → `php artisan access:rebuild` →
`php artisan rop:tarik-vendor`.
