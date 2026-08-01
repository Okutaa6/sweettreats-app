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

## 5d. Audit bug & inkonsistensi (2026-08-01, sebelum push)

Tujuh temuan, semuanya sudah diperbaiki dan diuji. Dua yang pertama tidak akan
pernah ketahuan dari membaca kode — keduanya baru muncul saat cron dijalankan
dengan data bertanggal mundur:

1. **`rop:sapu` menyapu status `disetujui` untuk dikadaluarsakan**, padahal
   peta transisi tidak mengizinkan `disetujui → kadaluarsa`. Akibatnya cron
   melempar error tiap hari dan pengajuan itu tidak pernah tertutup.
   Perbaikan: kadaluarsa **hanya** untuk yang masih `diajukan`. Yang sudah
   di-ACC berarti Finance sudah berkomitmen membayar — menutupnya diam-diam
   justru menyembunyikan kewajiban. Sebagai gantinya ditambah pengingat baru:
   *"disetujui N hari lalu, belum dibayar"*.
2. **Pengingat harian membuat kadaluarsa tidak pernah terjadi.** Menulis
   `reminded_at` lewat Eloquent ikut memperbarui `updated_at`, padahal
   `updated_at` itulah ukuran "sudah didiamkan berapa lama". Pengajuan tua
   diingatkan tiap hari → umurnya ter-reset tiap hari → tidak pernah
   kadaluarsa. Perbaikan: stempel pengingat ditulis dengan `timestamps = false`,
   dan daftar kadaluarsa dikunci **sebelum** pengingat dikirim.
3. **`?bulan=ngawur` di rekap → halaman 500.** `Carbon::createFromFormat`
   melempar untuk masukan yang bukan tanggal. Nilai query string tidak pernah
   bisa dipercaya bentuknya (salah ketik, tautan lama, perayap). Sekarang
   jatuh ke bulan berjalan.
4. **Cost centre nonaktif masih bisa dipakai mengajukan** — `exists` saja
   meloloskan yang sudah dipensiunkan. Sekarang `Rule::exists(...)->where('aktif', 1)`.
5. **`RopCoaCatalog::kosongkanCache()` tidak pernah dipanggil** (kode mati).
   Sekarang dijalankan di akhir `rop:tarik-vendor` — satu-satunya momen rutin
   "data master Accurate baru saja disentuh".
6. **Pesan validasi masih bahasa Inggris** di layar yang seluruhnya berbahasa
   Indonesia awam. Diganti pesan sendiri untuk semua aturan di form pengajuan.
7. **Aturan "boleh lihat" tidak konsisten**: Head IT/IT bisa membaca isi
   pengajuan siapa pun lewat drawer antrian, tapi kena 403 saat membuka
   halaman detailnya. Sekarang keduanya memakai daftar role yang sama
   (`RopService::ROLE_ANTRIAN` + `canSeeAllBranches`). Aksi mengubah (batalkan,
   konfirmasi selesai) tetap khusus pemilik — diuji: 403 dan status tidak berubah.

Plus satu penghalusan: pengajuan yang akan ditutup hari itu tidak lagi ikut
dikirimi pengingat "tolong di-ACC" beberapa detik sebelum kadaluarsa.

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

---

## 8. Perbaikan drawer ACC (2026-08-01) — jebakan yang mudah terulang

Drawer terlihat "berantakan" dibanding handoff: kepala turun, kaki menggantung,
tombol Setujui sempit & pucat. Empat sebab, semuanya tak kentara:

1. **`.rop-fin` menanggung dua peran** — pembawa token burgundy DAN padding
   halaman (`18px 22px 40px`). Drawer memakai kelas itu demi tokennya, jadi ikut
   kena padding halaman → kepala turun 18px, kaki menggantung 40px dari dasar.
   Ditegaskan: `.rop-drawer.rop-fin, .rop-fin .modal-dialog { padding: 0 }`.
   **Ingat ini saat menambah modal/panel ber-`rop-fin` berikutnya.**
2. **Badan drawer tanpa `min-height: 0`** — di flex column, `min-height:auto`
   bawaan bikin badan menolak menyusut, isinya meluber, kepala & kaki kepencet.
   Kepala/kaki juga belum `flex: none`.
3. **`.rop-drawer__foot .rop-btn { flex: 1 }`** menyamakan lebar Setujui dan
   Tolak. Handoff: Setujui memuai (`flex:1`), Tolak selebar isinya.
4. **Tombol mati pakai `opacity:.42`** → hijau pudar masih terbaca "boleh" dan
   kontras teksnya jatuh. Handoff memakai warna netral `#E7DACB`/`#B0997F`.

Ikut dirapikan sekalian: label tombol berubah jadi "Pilih akun beban dulu" +
hint alasannya (sebelumnya user tak tahu kenapa tombol mati) · grid 2 kolom
(auto-fit memaksa 3 kolom di lebar 520px sehingga nama vendor pecah baris) ·
label "UNTUK APA" + pemisah · "DIBEBANKAN KE" · gulungan badan di-reset tiap
drawer dibuka · alasan cepat penolakan `flex-wrap` (dulu tersembunyi di
scroller mendatar tanpa scrollbar — dua alasan terakhir tak pernah ketahuan) ·
`focus({preventScroll:true})` sebelum `scrollIntoView` (focus bawaan
membatalkan animasi gulung sehingga blok tolak diam di tempat, terpotong).

Terverifikasi di 1440/1280/900/390px: kepala menempel atas, kaki menempel
dasar, tak ada tombol meluber, nol error JS. Di ≤900px halaman Finance
ditutup **gerbang sopan** lebih dulu — itu perilaku benar (desktop-first).

---

## 9. Keputusan: kolom Aksi dibongkar, baris jadi pintu masuk (2026-08-01, ACC Ekky)

Pertanyaan Ekky: "tombol Cek & ACC ini perlu nggak? Reservasi kan nggak pakai —
nggak konsisten." Setelah dicek: Reservasi, Pasien, dan Doctor semuanya TANPA
kolom Aksi — barisnya yang diklik. Ditemukan juga lubang: baris ROP tidak bisa
diklik sama sekali, jadi item "Disetujui" TIDAK PUNYA jalan membuka detail
(satu-satunya tombolnya langsung ke modal bayar).

Keputusan (menyimpang dari handoff desain, sengaja):
- **Baris bisa diklik** (buka drawer) di semua tab — pola yang sama dengan
  tabel lain di app. Klik pada tombol/link di dalam baris tidak ikut membuka.
- **"Cek & ACC" dan "Lihat" dihapus** — dua-duanya cuma membuka drawer,
  redundan dengan klik baris.
- **Kolom Aksi hanya hidup di tab "Antrian bayar"**, isinya satu-satunya tombol
  yang benar-benar melompati langkah: "Tandai dibayar" (langsung modal).
  Tab Menunggu ACC & Riwayat: 7 kolom, ruang balik ke kolom isi.
- **Footer drawer dapat cabang "Tandai dibayar"** untuk status disetujui —
  ini justru KEMBALI ke handoff (cabang `canBayar` yang sebelumnya tidak
  diimplementasikan). Drawer ditutup dulu sebelum modal dibuka (hindari
  rebutan lapisan dua elemen fixed). Menutup lubang di atas: Finance kini bisa
  periksa lampiran/jejak dulu sebelum menandai lunas.
- Ikutan kecil: hint "minta foto penawaran sebelum ACC" hanya muncul saat
  status masih diajukan (untuk status lain: "Tidak ada lampiran").

Susulan (usul Ekky, tepat): tiap baris diberi **chevron ›** di ujung kanan —
afordansi yang sama dengan tabel Reservasi (.st-chev-cell), supaya "baris ini
bisa dibuka" terlihat tanpa harus dikasih tahu. Di ROP pakai SVG garis
(ikon `lanjut` di rop/_ikon.blade.php), bukan FontAwesome; hover: geser 2px +
berubah burgundy, mengikuti perilaku di Reservasi.

Prinsip yang bisa dipakai ulang: kolom Aksi layak ada HANYA kalau tombolnya
melompati langkah (bukan sekadar membuka detail). Kalau isinya "buka detail",
itu tugas baris.

Terverifikasi 3 tab: menunggu (7 kolom, baris→drawer footer ACC), disetujui
(8 kolom + Tandai dibayar; baris→drawer footer bayar→modal; tombol tabel tidak
menyeret drawer), riwayat (7 kolom, baris→drawer baca-saja). Nol error JS.

---

## 10. Backlog #1 pola-interaksi: aksi tanpa reload (2026-08-01)

Melengkapi item handoff yang belum dibangun ("drawer langsung pindah ke
pengajuan berikutnya + toast Urungkan"):

- ACC / tolak / bayar dikirim via fetch (Accept: application/json).
  Controller ber-cabang wantsJson; form biasa tetap jadi fallback tanpa JS.
- Toast client-side + tombol **Urungkan** (ACC & tolak). Endpoint urungkan
  kini melayani dua arah: disetujui→diajukan dan ditolak→diajukan lewat
  service BARU `urungkanPenolakan()` — syarat ketat yang sama (status masih
  ditolak, pelakunya yang menolak, jendela DETIK_URUNG, jejak log tidak
  dihapus, pengaju dikirimi koreksi terang-terangan).
- Setelah keputusan: baris hilang, header grup menyusut (count+subtotal via
  data-total mentah), statistik & badge tab bergeser (data-n/data-rp mentah,
  tidak parsing teks rupiah), drawer langsung memuat pengajuan berikutnya;
  antrian habis → drawer menutup.
- Urungkan = location.reload() — jalur langka; resync penuh lebih jujur
  daripada akrobat DOM.
- Error (409/422/jaringan) → toast varian galat (.rop-toast--galat) dengan
  pesan servernya; tombol submit ber-state "Menyimpan…" selama fetch.
- Alasan tolak dikosongkan tiap drawer pindah item (jangan bawa alasan
  item sebelumnya).

Teruji end-to-end (net-zero di DB dev): ACC→Urungkan pulih, tolak→Urungkan
pulih, validasi alasan kosong → toast galat, nol error JS.
