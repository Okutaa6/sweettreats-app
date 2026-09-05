# Status Proyek — Data Center SweetTreats (app.sweettreats.id)

> **Untuk siapa file ini**: alat task-management di luar chat teknis (mis.
> Gemini) dan siapa pun yang butuh gambaran utuh tanpa akses ke memori chat.
> Teknis & coding dikerjakan di chat Claude; file ini adalah **potret proyek**:
> apa yang sudah, sedang, dan akan dikerjakan, plus utang yang belum dibayar.
>
> **Terakhir diperbarui: 2026-08-06 malam — hari besar: gabungan Reza + fitur
> absensi NAIK PROD (`e28f89b`).** Berurutan: push Reza (checklist+pettycash)
> diaudit dua penelaah → 8 temuan parah menahan prod → Reza memperbaiki
> semuanya dan lolos uji ulang → sementara itu fitur absensi dibangun terpisah
> (Tukar Hari satu-pengajuan-dua-sisi, lembur datang-awal, jadwal fleksibel
> per role — Anisa & Irma via "Fleksibel Operasional", gerbang absen hari
> libur, alasan koreksi pasca-fingerprint, jenis pengajuan disaring per
> aturan) → keduanya digabung nol konflik → naik prod, 40 halaman lintas
> peran 200. Dua ranjau keamanan ditutup: `APP_DEBUG=true` di prod (halaman
> error membocorkan isi .env) dan `APP_ENV=local` di prod (hampir menyalakan
> tombol hapus-data-uang Reza yang defaultnya "local = boleh"). Tombol Reset
> petty cash kini berdaftar-putih (mati kecuali dinyalakan) DAN empat
> lubangnya dibereskan. Pelajaran tertulis: fitur destruktif = daftar-putih;
> migrate dulu baru SQL.**
> Kalau tanggal ini sudah jauh lewat,
> minta chat teknis menyegarkan file ini sebelum dipakai mengambil keputusan.
>
>
> ⏰ **TENGGAT — 10 September 2026: push ke Accurate.** Dijeda sejak 28 Agu atas
> permintaan Finance (mapping ulang **di sisi Accurate**), dan **sengaja
> dibiarkan berhenti sampai Finance minta lanjut**. Terakhir ter-push **27 Agu**.
> ⚠️ Jendela audit `accurate:push-daily` cuma **14 hari**. Kalau dinyalakan lagi
> **sesudah 10 Sep** dengan `--days` bawaan, hari-hari yang lebih tua **tidak
> akan pernah terkirim — dan tak ada yang memberi tahu.** Bukan galat, bukan
> baris merah; cuma diam.
> **Cara aman menyalakan** (urutannya penting): ubah `ACCURATE_PUSH_ENABLED=true`
> di `.env` prod → `php artisan config:clear` → **jalankan sekali manual dengan
> `--days` yang dicetak gerbangnya** (perintahnya menghitung sendiri: mis.
> *"Terakhir ter-push 2026-08-27 (5 hari lalu) → --days=7"*) → baru biarkan cron
> 03.30 mengambil alih.
> ✅ Sisipan ini juga menandai **prod & main sudah disatukan lagi**: keduanya di
> `2400896`, tak ada lagi cabang prod terpisah. Peringatan lama "jangan deploy
> main ke prod" **sudah TIDAK berlaku** — sekarang justru `main` yang benar.
>
> ✅ **Sisipan 2026-09-01 — KONSULTAN + RESTRUKTURISASI ROLE NAIK PROD**
> (`c28b665`). Gerbong yang tertahan sejak 21 Agustus akhirnya naik: tiga role
> **Konsultan Finance/IT/Marketing**, role **Koordinator GS**, bagan pindah di
> bawah Supervisor. **`att:audit-role` berat 0** (7 sedang · 0 ringan · 4 ok).
> Nol rsync. Prod kini `prod/konsultan` = saklar Accurate + Komisi Dokter +
> Konsultan, semuanya terverifikasi hidup berdampingan.
> **Empat orang IKUT DIPINDAH oleh SQL-nya**: Haickal→Konsultan Finance,
> Rahmat→Konsultan IT, Hari→Konsultan Marketing, **Alpajri→Koordinator GS**.
> ⚠️ **Pelajaran urutan deploy**: aku menjalankan **SQL dulu baru kode**. Di
> jendela di antaranya, `isFinance()` belum mengenal `Konsultan Finance`
> sehingga Haickal sesaat **boleh mengedit petty cash**. Jendelanya pendek
> (04.46 WIB, nol user aktif) dan langsung ditutup, tapi urutan yang benar untuk
> gerbong yang memindahkan ROLE adalah **KODE DULU, BARU SQL**.
> ℹ️ Alpajri kehilangan cabang (`raw_branch_id` NULL) dan posisinya ditutup —
> **itu DISENGAJA**, tertulis alasannya di SQL: Koordinator GS peninjau lintas
> cabang seperti Anisa & Irma (cabang datang dari mesin scan, bukan dari
> `users.raw_branch_id`), dan posisinya ditutup supaya dia **tidak meninjau
> dirinya sendiri** di dashboard review. Aturan kerja `GS Pagi` tetap aktif.
> ⚠️ **Utang**: checklist Alpajri 1 draft + 3 missed **tak bisa lagi dia
> selesaikan** karena posisinya tertutup. Ekky diberi tahu sebelum deploy.
> ⚠️ **Ramdan** (dipindah Reza secara berjadwal) aktif di Bintaro per 1 Sep.
> Untuk Finance: Agustus ia bekerja di **Pamulang** tapi lembar gajinya
> mengelompokkan di **Bintaro** (penguncian gaji pakai cabang asal), padahal
> sebelumnya **Kalideres** — tiga cabang dalam satu periode.
>
> ⛔ **Sisipan 2026-08-29 dini hari — ATURAN BARU rsync, ditulis dengan darah.**
> `rsync -a laravel/public/ → public_html/` **MEMATIKAN PROD 6 menit** (500 di
> seluruh situs, 02.00 WIB, nol user aktif). Sebabnya: `public_html` prod itu
> **salinan sejajar**, jadi `index.php` di sana **sengaja berbeda** — menunjuk
> `__DIR__.'/../laravel/…`, sedangkan `laravel/public/index.php` menunjuk
> `__DIR__.'/../…`. Menyalin seluruh direktori menimpanya dan PHP mencari
> `vendor/` di tempat yang tak ada.
> ⚠️ **Gejalanya menyesatkan**: `laravel.log` **nol galat**, karena gagalnya
> sebelum Laravel hidup. "Nol galat" ≠ sehat. Yang membongkar cuma `curl /` → 500.
> **ATURAN: rsync WAJIB dibatasi ke `assets/`** —
> `rsync -a laravel/public/assets/ public_html/assets/`. **JANGAN PERNAH**
> seluruh `public/`.
> ✅ **Data AMAN dan diperiksa**: SQL malam itu murni menambah (`INSERT IGNORE` +
> `CREATE INDEX`), nol UPDATE/DELETE/DROP. Sesudah pulih: sales 145.699 ·
> komisi 87.706 · komisi Agustus **Rp 403.940.654 tak bergeser** ·
> `accurate_push_log` **3.118 tak bertambah**. Backup 48 MB dibuat sebelum apa pun.
>
> ✅ **Sisipan 2026-08-29 — KOMISI DOKTER NAIK PROD** (`86dca82`). Laporan fee
> dokter untuk Finance, **baca-saja**. Reza menghindari empat jebakan langganan
> sadar & tertulis: segmen URL sendiri (bukan `finance` yang ada di `SKIP_RBAC`),
> grant **by link bukan by id node**, ikon **FA5**, dan **tanpa `JOIN` ke
> `raw_doctor`** (doc_id kembar akan melipatgandakan uang) — dikelompokkan per
> `doc_id`, bukan nama dokter. Kedua SQL idempoten, dibuktikan 2× di dua keadaan.
> **Angkanya cocok TIGA arah**: KomisiQuery = SQL pembanding = yang sudah masuk
> Accurate sebagai "Beban fee dokter" — Rp 403.940.654, selisih Rp 0.
> 9 kasus render lulus di prod. Naik prod butuh **2 SQL + `access:rebuild` +
> rsync `assets/`**.
> ⚠️ Temuan laten: push Accurate menjumlahkan komisi **tanpa** memfilter
> `commission_role`, Reza memfilter `doctor`. Nol selisih hari ini (semua baris
> `doctor`); kalau kelak ada role lain, **jurnal Accurate yang salah**.
> ⚠️ **Prod masih sengaja di luar `main`**: berjalan dari `prod/komisi-dokter` =
> saklar Accurate + Komisi Dokter, **tanpa Konsultan**. Saat Konsultan disetujui,
> gabungkan branch prod ini ke main DULU — kalau tidak, saklar DAN Komisi Dokter
> hilang.
>
> ⚠️ **Sisipan 2026-08-28 pagi — KIRIMAN KE ACCURATE DIJEDA** (`e796d3a`, naik
> prod 09.00). Finance mapping ulang **di sisi Accurate**, jadi kita berhenti
> mengirim. Saklar `ACCURATE_PUSH_ENABLED=false` di `.env` prod; bawaannya
> `true` sehingga deploy sendiri tak mengubah perilaku apa pun.
> **Berhenti (menulis):** `accurate:push-daily` (jadwal **dan** manual) + jalur
> buku ASLI `accurate:push-sample`. **Tetap jalan (membaca):** `omnicare:sync`
> 02.00 — **cermin lokal tetap terisi, nol data hilang** — `pull-glaccount`,
> `rop:tarik-vendor`, sandbox, dan ekspor CSV ROP (itu impor manual Finance).
> ⚠️ **Jebakan**: menonaktifkan `accurate_credentials.is_active` **TIDAK**
> menghentikan push (perintahnya tak pernah membaca kolom itu) tapi **mematikan
> tarikan COA** yang Finance justru butuhkan — kebalikan dari yang diminta.
> ⚠️ **Saat menyalakan lagi**: jendela audit cuma **14 hari**. Jeda lebih lama =
> hari-hari tua **tak akan pernah terkirim**, tanpa ada yang memberi tahu.
> Gerbangnya mencetak sendiri `--days` yang dibutuhkan tiap kali menolak.
> Resep: ubah `.env` → `config:clear` → **jalankan sekali manual dengan `--days`
> itu** → baru serahkan ke cron.
> ⚠️ **Prod sengaja TIDAK di `main`.** `main` (`2853d07`) sudah memuat gerbong
> **Konsultan yang belum disetujui prod**, jadi "deploy main" bukan tindakan
> netral. Prod berjalan dari branch `prod/accurate-saklar`. **Saat Konsultan
> disetujui: gabung branch ini ke main DULU, baru kembalikan prod ke main** —
> kalau tidak, saklarnya hilang diam-diam.
> Terverifikasi dev 9/9 + prod 5/5, termasuk `schedule:run` yang terbukti tidak
> menjalankannya. Dua bug-ku sendiri ketemu saat menguji (`trans_date` bukan
> `trx_date`; baris `dry_run` ikut terhitung terkirim) — dua-duanya gagal senyap.
>
> ⚠️ **Sisipan 2026-08-27 malam — EMPAT GERBONG NAIK PROD.** Prod kini
> **`761d7a7`**, dev = prod = main. Berurutan hari ini: **Lembar Bayar ROP**
> (`0e7f26d`, 16/16) → **§M dua pagar lembur** (`26381ee`, 8/8) → **Pasien Baru
> / Cekat milik Reza** (`67cd56b`, 31 dev + 16 prod) → **§N belajar-sendiri
> IPv4** (`761d7a7`, 15/15). Nol SQL dan nol rsync untuk §N.
> **§N tidak diterima atas dasar laporan** — angkanya diulang sendiri di worktree
> terpisah di server prod dengan **vendor disalin, bukan di-symlink** (symlink
> membuat Composer memuat balik kode prod lama dan memberi hijau palsu), dan
> hasilnya cocok: **130 absen tertolong, 0 hilang, 0 pindah cabang**. Lalu
> ditunjukkan hidup: simulasi dengan kode yang kini jalan **mendaftarkan sendiri**
> `36.69.103.246` (Sunter) dan `180.251.182.171` (Pamulang), sementara
> `125.165.152.185` (Bintaro) **tetap ditolak karena saksinya kurang** — ambangnya
> bekerja, bukan meloloskan borongan. Cocok otomatis per pekan Agustus:
> **77% · 72% · 74% · 81%**, dan pekan berjalan belum memuat efek §N.
> ⛔ **DUA LARANGAN yang ikut permanen**: (1) **jangan cabut 10 baris IPv4 manual
> 23 Agu** — 7 punya pengganti otomatis, **4 tidak** (Bintaro ×3, Cinere ×1,
> saksinya orang fleksibel); (2) **jangan cabut pagar `ipv4Publik()`** — ia
> menutup CGNAT `100.64.0.0/10` yang flag bawaan PHP justru tak cakup; tanpa itu,
> di belakang proxy salah-konfigurasi kelima cabang bisa terbaca jadi satu.
> ⛔ **Yang MASIH menahan**: tujuh berkas **Konsultan + Alur Persetujuan** belum
> di-commit (ada di dev, di working copy lokal, dan di
> `wip/snapshot-konsultan-alur-2026-08-22`) — sudah diperiksa utuh sesudah dev
> pindah branch. **Chat pemiliknya yang harus commit.** Ditunda atas permintaan
> Ekky: **Ramdan & Fajri**. Jangan disentuh: **req#162 Melani**, menunggu Nissa.
> ℹ️ Perlu diawasi terpisah: **`[2002] Operation not permitted` 41 kali di prod
> hari ini**, semuanya **sebelum** deploy — penolakan koneksi MySQL hosting
> bersama, bukan akibat gerbong mana pun.
> ⚠️ **Sisipan 2026-08-27** — **Belajar-sendiri IPv4 LULUS DEV** (§N dari
> sisipan 24 Agu, branch `feat/wifi-ipv4-belajar`, **prod belum**). WiFi cabang
> ber-IPv4 kini bisa terdaftar sendiri setelah 2 orang cabang yang sama memakainya
> dalam 72 jam — **cocok-penuh, nol pelebaran**; tetangga yang beda satu angka
> tetap ditolak. `att:wifi-uji` **22/22** + audit pra-prod (2 temuan ditutup: alamat
> privat/loopback bisa terdaftar otomatis kalau proxy tak terpasang, dan docblock
> yang membantah kodenya sendiri). Putar ulang **data prod** (transaksi
> dibatalkan, aplikasi prod tak disentuh): **130 absen tertolong, 0 hilang, 0
> pindah cabang**. Mesin akan menangkap **7 dari 11** alamat yang 23 Agu
> didaftarkan manusia — semuanya **lebih awal** — plus **2 alamat baru yang
> sedang gagal cocok hari ini**, bukti daftar manual sudah basi dalam 4 hari.
> ⚠️ **Butuh keputusan**: 4 alamat manual TIDAK tergantikan karena saksinya
> orang fleksibel (cabang asal kosong) — jadi **jangan cabut 10 baris manual
> 23 Agu**. Pilihan yang tersedia: izinkan fleksibel jadi saksi PENDUKUNG
> (≥1 staf asal + total ≥2). ⚠️ Sisa risiko diterima sadar: CGNAT yang dibagi
> 2 staf bisa terdaftar — dibayar dengan label PERLU DITINJAU + notifikasi IT.
>
> ⚠️ **Sisipan 2026-08-24** — **DODO naik prod** (`2ef8a35`): lembur di hari
> libur kini terpisah dari lembur biasa di Lembar Gaji & Kehadiran. **Dua
> bayar-salah ditutup**: lembur kembar Afrina (115 menit dibayar 230) dan chip
> salah Ekky (masih pending, dibetulkan). **10 IPv4 WiFi didaftarkan** di 5
> cabang — kegagalan cocok 21 hari turun 158 → 128 tertolong. Dua utang
> disiapkan jadi chat build: **§M pagar lembur** & **§N belajar-sendiri IPv4**.
> ⛔ Konsultan + Alur Persetujuan masih menahan (kodenya belum di-commit).
>
> ⚠️ **Sisipan 2026-08-22 malam** — **DUA GERBONG NAIK PROD** (`722d164`):
> (1) **libur cabang ikut cabang BERTUGAS** — Verini dkk tak lagi salah dinilai,
> hanya `libur` yang bergerak 57→68, nol alpa baru; (2) **Branding GP** kerjaan
> Reza (diaudit dulu, 3 temuan non-penahan untuk ditindaklanjuti). Celah
> `att_user_rule` 5 Agustus (Irma & Anisa) ditutup — lembur Anisa akhirnya punya
> hari kerja. **Terjadwal**: Ramdan pindah PENUH ke Bintaro + Fajri jadi
> Koordinator GS, **setelah operasional Minggu 23 Agu**, urutan Ramdan-dulu;
> Ramdan libur Selasa 25, Pamulang sengaja kosong GS. ⛔ **Konsultan + Alur
> Persetujuan MENAHAN** — kodenya belum di-commit dan tak bisa dipisah.
>
> ⚠️ **Sisipan 2026-08-22a** — **Role Konsultan + restrukturisasi Supervisor
> JADI di dev (23/23 uji lulus), prod belum.** Tiga role per bidang (`Konsultan
> Finance`/`Marketing`/`IT`) + role struktural baru **`Koordinator GS`** untuk
> Alpajri. Bagan pindah: Coordinator Dentist & Koordinator GS di bawah
> Supervisor, General Services di bawah Koordinator GS. Wewenang ACC ikut
> dipindah di `att_approval_rule` (bukan cuma bagan): Asdent → **Irma saja**,
> GS → **Fajri + Anisa**, Irma → **Anisa** (bukan lagi Dani). Konsultan **nol
> baris dua arah** — tak meng-ACC, tak mengajukan.
> **Tiga temuan:** (1) `isFinance()` itu MEMBATASI bukan memberi — tanpa
> mendaftarkan Konsultan Finance di situ ia malah boleh **mengedit petty cash**,
> kuasa yang Finance sendiri tak punya; (2) memindahkan Fajri **menutup baris
> posisinya** lewat `syncFromUser()`, bukan sekadar menyembunyikan divisi —
> `sys_checklist_division.group_id` ada tapi **tak pernah dibaca**; absensinya
> aman karena cabang efektif jatuh balik ke `users.raw_branch_id`; (3) **id node
> menu berbeda antar lingkungan**, jadi SQL-nya mengkloning dengan membaca role
> induk, bukan daftar id.
> **Keputusan Ekky:** Fajri jadi **peninjau** checklist GS (bukan pengisi);
> Konsultan IT dapat menu Head IT **dikurangi lima alat keamanan** (User, Role,
> Akses, Setting, WiFi Cabang) — Kesehatan tetap.
> ⚠️ **Perubahan kodenya masih UNSTAGED** dan bercampur pekerjaan chat lain di
> working copy bersama; sengaja tak di-commit. Uji #1/#2/#4 (login & nol 403)
> baru diaudit statis, belum diuji hidup. Rincian: `log-update.md` entri 22 Agu a.
>
>
> ✅ **Sisipan 2026-08-21d** — **Libur cabang: SUDAH DIBANGUN, lulus 11/11 di
> dev**, branch `fix/libur-cabang-bertugas` (`9da2d5d`). **PROD BELUM** —
> menunggu izin Ekky; tenggat Selasa 25 Agu. Naik prod = deploy PENUH (ada
> class baru) + rsync `public/` + 1 SQL sapu + `att:reconcile` sesudahnya.
> Kalender libur kini **tampil di Kehadiran** (temuan kedua di bawah: beres).
> ⚠️ Temuan sampingan **belum ditambal**: `att_user_rule` prod punya 2 baris
> rentang-terbalik (Irma & Anisa) — 5 Agu mereka tak punya aturan kerja, scan
> Anisa hari itu hilang. Butuh keputusan Ekky, bukan efek samping.
> Dua ralat terhadap rencana dicatat di §10 rencananya. Rincian:
> `log-update.md` entri `2026-08-21d`. Rencana cadangan §8 (koreksi 3 baris
> `att_day` sesudah 25 Agu) tetap berlaku kalau prod tak sempat.
>
> ⚠️ **Sisipan 2026-08-21c** *(masalahnya — sudah dijawab oleh 21d di atas)* —
> **Libur cabang tak mengikuti rolling.** Libur
> per-cabang dicocokkan ke cabang ASAL orang, bukan cabang tempat dia bertugas
> hari itu — 3 orang salah dua arah pada 25 Agu (Verini berisiko alpa; Della &
> Ramdan kerjanya dicatat "libur"). Rencana + tenggat di
> `rencana/rencana-libur-cabang-bertugas.md`. Ikut ketahuan: **kalender libur
> tak pernah ditampilkan di layar mana pun**, jadi salah-catat baru terlihat
> setelah harinya lewat. Tiga chat build baru disiapkan: §J §K §L di
> `spec/prompt-chat-baru.md`.
>
> ⚠️ **Sisipan 2026-08-21b** — **Konsultan**: arah baru yang diputuskan Ekky.
> Konsultan wajib absen, tapi **tak boleh meng-ACC pengajuan orang** (cuti/
> lembur/izin). Role `Consultant` yang sudah ada ternyata **sudah** memenuhi itu
> sendiri. Akun **Achmad Haickal** (konsultan finance) dibuat di PROD 21 Agu di
> role yang sama dengan Hari & Rahmat — role khusus per bidang (`Konsultan
> Finance`/`Marketing`/`IT`) menyusul setelah rencananya jadi, dan itu **butuh
> perubahan kode**, bukan cuma config. Rincian + tiga temuan soal mesin ACC ada
> di `log-update.md` entri 21 Agu b. Rencana lengkapnya — 3 role konsultan,
> `Coordinator Dentist` pindah ke bawah Supervisor, role baru `Koordinator GS`
> untuk Alpajri — di `rencana/rencana-konsultan-struktur.md`. Dev dulu, prod
> menyusul setelah uji terima lulus.
>
> ⚠️ **Sisipan 2026-08-14** — **Lembar Bayar ROP** selesai dibangun & lolos uji
> di **dev** (branch `feature/rop-lembar-bayar`, belum prod): Finance kini bisa
> menyusun satu berkas cetak untuk bos — daftar + total + terbilang + lampiran —
> menggantikan kebiasaan mengirim 30 berkas satu per satu di WhatsApp, DAN
> menandai beberapa ROP lunas dengan satu bukti transfer. Rinciannya di
> `log-update.md` entri 14 Agu paling atas.
>
> ⚠️ **Sisipan 2026-08-13** — badan file di bawah masih potret 6 Agu dan belum
> disegarkan menyeluruh. Yang sudah berubah sejak itu ada di `log-update.md`
> (entri 11, 12, dan 13 Agu). Satu hal yang paling sering ditanyakan
> diringkas di §1a tepat di bawah.

## 1a. Penggelaran akun per cabang — keadaan 13 Agu 2026

Akun karyawan sengaja digelar **cabang per cabang**, bukan sekaligus. Nama yang
belum ada di prod itu **bukan data hilang**.

| Kelompok | Prod |
|---|---|
| ABG/Office + Bintaro | ✅ live 11 Agu |
| Cinere + Pamulang (5 akun) | ✅ live 13 Agu |
| Sunter (4 akun) | ✅ live 13 Agu |
| Kalideres (3 akun) | ✅ live 15 Agu — **kelima cabang lengkap** |

Perkakasnya `php artisan att:gelar-akun` (daftar orang di `const ORANG`,
`app/Console/Commands/GelarAkunCabang.php`). Idempoten; jalankan `--dry` dulu.
Untuk Kalideres nanti: **tambah baris ke `const ORANG`**, jangan tulis SQL
manual — sandi perlu di-hash dan ada tiga ekor yang gampang terlupa
(`sys_user_position`, `att_user_rule`, `att_cuti_periode`).

Akun gelombang mana pun lahir bersandi sementara seragam dan **wajib ganti saat
login pertama**. Orang yang sudah mengganti sandinya sendiri tak akan disentuh
gelombang berikutnya.

**Utang terbuka**: kontrak Alif & Reza masih dikosongkan, menunggu data.
(Kontrak Rokhmatun sudah diperpanjang HR 13 Agu → 11 Agu 2027, jatah 12.)

✅ **Penjadwal harian prod: TERBUKTI JALAN** (15 Agu). Baris `missed` checklist
bercap 00:10 tiap hari selama sepuluh hari berturut — itu cron. Dugaan 14 Agu
yang menyimpulkan sebaliknya sudah **ditarik**; inferensinya cacat karena
memakai `att_day.computed_at`, yang ditimpa tiap kali `att:reconcile` dijalankan
manual. Tak perlu lagi cek hPanel.

## 1. Apa aplikasi ini

CMS internal klinik gigi **Sweet Treats** (5 cabang: Bintaro/pusat, Sunter,
Kalideres, Pamulang, Cinere; ±29 karyawan + 7 staf kantor). Laravel + MariaDB,
Bootstrap 4, FontAwesome 5.8.2. Fungsinya berkembang dari CRM/reservasi menjadi
"Data Center": satu pintu untuk reservasi, pasien, dokter, penjualan
(tarik-otomatis dari vendor Omnicare), pengeluaran, absensi, cuti, gaji,
checklist operasional, dan integrasi akunting (Accurate).

**Lingkungan:**
| Env | URL | Branch git | Keadaan |
|---|---|---|---|
| Prod | app.sweettreats.id | `main` (`e28f89b`, 6 Agu malam) | SEMUA live: gerbong + rapi-rapi + checklist/pettycash Reza (sudah diperbaiki pasca-audit) + Tukar Hari + lembur datang-awal + jadwal fleksibel per role. `APP_ENV=production`, debug mati, tombol Reset mati (daftar-putih) |
| Dev | dev.sweettreats.id | `integrasi/dev-2026-08-06` (= main + docs) | = prod + data demo; tombol Reset MENYALA (alat bersih-bersih Reza) |
| Lokal | worktree `sweettreats-rapi` | `integrasi/dev-2026-08-06` | branch kerja aktif |

**Branch mati (jangan dilanjutkan)**: `checklist-pettycash` (digantikan
`fixing-pettycash-checklist` yang di-squash Reza; berisi juga draf SQL
`anisa_irma_fleksibel` yang SUDAH USANG — digantikan jadwal per role),
`feature/rapi-pasca-launch` & `feature/rop` (isinya sudah di main).

**Orang-orang:**
- **Ekky** (= Muhamad Ikhsan, Head IT) — pemilik keputusan, bisa mengubah SOP.
- **Reza** (IT) — developer kedua; kerjaannya masuk lewat branch sendiri lalu
  di-merge ke branch integrasi.
- **Dani** (Executive), **Della** (Finance), **Anisa/Nissa** (Supervisor),
  **Irma** (Coordinator Dentist), **Yovanka** (Head Marketing).

**Aturan kerja repo (disepakati 2026-08-01):**
- Semua kerjaan bermuara ke **`feature/rop`** (branch integrasi). Tidak ada
  cabang yang lompat langsung ke `main`.
- `release/2026-08-pengeluaran` = kandidat rilis, hanya dimajukan saat mau
  deploy. PR ke `main` dibuka **saat isinya beku**, bukan di awal.
- Prod tidak pernah disentuh tanpa perintah eksplisit; dev bebas.
- DB pakai SQL manual di `database/sql/` (bukan migration), KECUALI kerjaan
  Reza yang pakai migration — dua-duanya harus dijalankan saat deploy.

## 2. FOKUS SEKARANG: pemantauan pasca-rilis besar 2026-08-06

> **Semua yang direncanakan minggu ini sudah LIVE di prod** (`e28f89b`):
> gerbong absensi, batch rapi-rapi, checklist+pettycash Reza (pasca-audit +
> perbaikan), Tukar Hari, lembur datang-awal, jadwal fleksibel per role.
> Verifikasi terakhir: 40 halaman lintas peran 200, log nol error, absensi
> hari berjalan tetap jalan (11 bukti HP = 11 baris harian).
>
> **Pantau beberapa hari ke depan**: (1) sapuan pagi 06:10 menghasilkan baris
> yang benar utk 3 kelompok fleksibel baru (tanpa jadwal / Kantor / Operasional
> — Anisa & Irma kini dinilai "masuk Minggu, libur Senin"); (2) pengajuan
> Tukar Hari pertama dari orang sungguhan lolos ACC & kalendernya benar;
> (3) form mingguan checklist lama yang di-backfill bisa di-ACC supervisor.
>
> **Utang lama yang masih hidup**: cek cron hPanel `schedule:run` · 14 akun
> karyawan sisanya via Pusat Akun · node menu kembar lintas-seksi (analisis
> ada, menunggu keputusan konsolidasi) · menu accurate-* di dev menunjuk route
> yang tak ada (proyek Accurate pause) · `APP_DEBUG` dev masih true (wajar
> untuk dev) · tombol Reset petty cash: kalau kelak mau dinyalakan di prod,
> empat perbaikannya SUDAH terpasang — tinggal set `PETTY_CASH_RESET_ENABLED`.

### Arsip rencana (sudah terlaksana):

> **REVISI 2026-08-04 malam**: deploy prod DITUNDA sampai fitur **Aturan
> Kerja** selesai (dikerjakan Ekky sekarang, masuk gerbong). Besok
> **2026-08-05: demo di office/ABG + cabang Bintaro** memakai dev — data
> demo sudah terisi (absensi Juli–Agustus, cuti/lembur, 14 ROP).
> Hotfix sweet-mates (kiriman 1) TIDAK ikut ditunda — tetap bisa naik
> duluan kapan pun Ekky bilang "prod".

## 2a. Rencana deploy prod (setelah Aturan Kerja selesai)

Rujukan wajib: **`docs/deploy/manifest-prod.md`** (daftar centang lengkap:
32 SQL urut, 19 migration, 8 role baru + 29 akun, rsync aset, verifikasi
per peran). Jangan deploy dari ingatan.

Dua kiriman, urut:
1. **Hotfix sweet-mates** (branch `hotfix/sweet-mates-auth`, 2 berkas) —
   duluan & sendirian. Celah: halaman member KOL/KOC + komisi bisa terbuka
   tanpa login; sekarang cuma tertutup karena opcache lama, dan deploy besar
   justru me-reset opcache. **Status: siap, belum dieksekusi.**
2. **Gerbong besar** `release/2026-08-pengeluaran` → `main` (±95 commit,
   ±300 berkas): absensi lengkap + absen HP selfie + Pusat Akun + jatah cuti +
   lembar gaji + checklist & cuci AC (Reza) + dashboard marketing + login baru +
   reskin tema/zona + ROP + fix jadwal dokter. **Status: lengkap di dev,
   sedang masa uji.**

Sisa pekerjaan sebelum gas: selesaikan masa uji dev (Ekky), buka PR, ikuti
manifest langkah demi langkah. Gotcha yang sering kelupaan: SQL checklist Reza
(`2026_08_01_checklist_item_exception.sql`) **harus lewat mysql CLI** (pakai
DELIMITER, gagal via tinker); DDL Accurate harus jalan **sebelum**
`2026_07_31_rop_access`; aset publik wajib **rsync** (public_html prod = copy,
bukan symlink).

## 3. Peta status per modul

### Sudah LIVE di prod (jalan sehari-hari)
- **Reservasi + drawer** (dgn hotfix anti-tertimpa 25 Jul), pasien, dokter,
  cabang, keluhan, waiting list.
- **Sales/penjualan dari Omnicare** — tarik otomatis harian, 5 cabang,
  dashboard sales + executive report. Angka terbukti cocok dengan Accurate
  sampai rupiah.
- **Marketing arm**: editorial plan, kode referral, komisi, affiliate (Behel),
  sweet mates, KOL/KOC.
- **Checklist harian/mingguan** versi awal (8 tabel sudah ada di prod).

### Selesai & teruji di DEV — ikut gerbong minggu ini
- **Absensi**: mesin rekonsiliasi scan→hari kerja, lintas cabang, aturan per
  orang, persetujuan, periode gaji, rekap. (Data scan bulanan tetap di-upload
  manual dari mesin sidik jari — arah ADMS masih rencana.)
- **Absen HP selfie**: WiFi cabang sebagai saksi + IP cabang, pencocokan IPv6
  per blok **/48**, remember-me, paksa ganti password.
- **WiFi Cabang — rotasi Telkom** *(LIVE PROD 21 Agu `618ca57`, termasuk
  pelebaran /48)*: nomor IPv6 Telkom berputar
  kira-kira tiap dua hari. Dengan pencocokan /64, tiap rotasi membuat seisi
  cabang kena "WiFi tidak dikenali" sampai ada yang mendaftarkan nomor
  barunya — **28,1% absen HP sepanjang Agustus gagal cocok** karenanya.
  Sejak 21 Agu pencocokan memakai **blok /48** cabang, jadi nomor baru di blok
  yang sama langsung dikenali tanpa didaftarkan siapa pun. Belajar-sendiri
  tetap ada tapi perannya menyempit: **melaporkan ke IT** kalau ada blok yang
  asing sama sekali (2 orang dalam 72 jam) — bukan lagi mendaftarkan sendiri.
  Veto IT menang atas kekerabatan /48, dan pendaftaran menolak /48 yang sudah
  dimiliki cabang lain. Saklar mati tetap ada, tapi ia mematikan PELAPORAN,
  bukan pencocokan /48.
- **Pusat Akun**: Kesehatan (detektor kegagalan-senyap), User, Akses, Role,
  Setting, audit log login.
- **Jatah cuti** per kontrak 12 bulan (saldo boleh minus, cuti bersama) +
  kartu SISA CUTI; **Lembar Gaji** + export CSV.
- **ROP/Pengajuan** (pengganti Formulir A kertas): form HP, antrian Finance
  burgundy, ACC + pilih akun beban Accurate per buku, tahap dibayar + bukti,
  nota pertanggungjawaban, "Ajukan lagi", deteksi dobel, toast Urungkan 2
  menit, auto-expire 30 hari, master 73 vendor tarik-otomatis, rekap + export.
- **Kerjaan Reza**: login redesign, dashboard marketing, monitoring cuci AC,
  foto before/after checklist, export Excel checklist, pengecualian
  per-pertanyaan, grup promo.
- **Reskin tema/zona**: cokelat default + 3 zona (Marketing teal, IT grafit,
  Finance burgundy), Kehadiran gaya kalender Google, sapuan emoji→ikon SVG.
- **Fix dokter**: jadwal khusus menang atas cuti di resolusi slot reservasi.

### Lulus di DEV — menunggu izin prod (2026-09-05)
- **Koreksi absen berjejak audit · ambang DODO · ekspor rentang bebas** —
  branch `feat/absensi-koreksi` (dari `main` `13c49cd`); serah-terima
  `handoff/handoff-absensi-koreksi-2026-09-05.md`. **59 uji hijau, 161 assertion**
  (per kelas), dev dipinjam lalu dikembalikan persis ke chat IT Tracker.
  1. **Koreksi absen `/absensi/koreksi`** — ubah status satu orang satu tanggal
     dengan alasan wajib, nama pelaku, dan waktu. Sebelumnya `alpa` hari lampau
     **hanya bisa dibetulkan dengan menembak basis data**. Disimpan sebagai
     deklarasi di tabel baru `att_day_koreksi`, BUKAN di `att_day` — `att_day`
     itu turunan dan reconciler menimpanya tiap pagi, jadi koreksi yang ditulis
     langsung ke sana akan **hilang diam-diam besok paginya**. Periode gaji
     tertutup **ditolak** dengan menyebut nama periodenya. Buku besar: mencabut,
     bukan menghapus.
  2. **Ambang DODO bisa diatur** (`sys_setting`, bawaan **7 jam**, diubah Finance
     dari layar Lembar Gaji, berjejak audit). `>=` ambang → DODO sebesar ambang,
     sisanya lembur biasa; `<` ambang → bukan DODO sama sekali. **Per HARI**,
     bukan per periode. Invarian `biasa + DODO = total` diuji di lima ambang
     berbeda; `LemburDodo::dasar()` nol baris disentuh.
  3. **Ekspor CSV di layar Kehadiran, rentang bebas** — tak lagi memaksa orang
     membuat periode gaji hanya untuk bisa membaca "1–15 Agustus". Ikut penyaring
     cabang yang sedang aktif.
  - Bonus: **`att:periksa-kejanggalan`** harian 06:30, BACA-SAJA — potongan cuti
    di hari libur, potongan kembar, orang tanpa aturan kerja. Tak membetulkan
    apa pun (Ramdan membuktikan kenapa: pembetulan otomatis bisa melahirkan
    kelebihan bayar). Stempel waktu ditulis walau nol temuan.
  - **Diaudit ulang sesudah selesai — 7 temuan, semua ditutup.** Yang terpenting:
    ekspor menghilangkan orang tanpa aturan kerja · kolom "Hadir" berarti dua
    hal berbeda dari layar di atasnya · dan pemeriksa harian akan melaporkan
    Yovanka & Ramdan **selamanya** sesudah koreksi §A, sebab buku besar tak
    pernah menghapus baris (ditutup lewat konvensi `ref_id = batal-<id>` yang
    diikat dua arah antara SQL dan `AttPeriksaKejanggalan::PREFIX_BATAL`).
    **67 uji hijau, 176 assertion.**
  - **Pagar buku besar cuti** (temuan ke-8, sudah ditutup): status `cuti` tak
    bisa disetel dari layar koreksi — jatahnya hidup di `att_cuti_mutasi` dan
    hanya berkurang lewat pengajuan di-ACC. Tanpa pagar, kolom **Cuti** naik
    sementara **Sisa Cuti** diam: dua angka di satu baris Lembar Gaji yang
    saling membantah. **72 uji hijau, 197 assertion.**
  - ⚠️ **Ambang DODO RETROAKTIF** — Lembar Gaji menghitung ulang bulan lampau,
    jadi 7 jam mengubah bulan yang mungkin sudah dibayar. **Deploy dengan
    `dodo_ambang_menit = 0`** (perilaku persis hari ini), Finance yang
    menaikkannya kemudian lewat layar. Urutan lengkap:
    `handoff/checklist-deploy-absensi-koreksi.md`.
  - ⚠️ Dilaporkan, sengaja belum dikerjakan: matriks Kehadiran **tak menandai**
    sel yang sudah dikoreksi (jejaknya cuma di `/absensi/koreksi`).
  - Deploy: **3 SQL + deploy PENUH** (empat kelas baru), **nol rsync**.
  - ⛔ **Prasyarat yang belum ada**: perbaikan "cuti tak memakan hari libur"
    **TIDAK hidup di prod** meski serah-terima menyatakan sebaliknya — lihat
    catatan di §4 Utang.
### Lulus di DEV — menunggu izin prod (2026-08-27)
- **Dua pagar pengajuan lembur** — branch `feat/pagar-lembur` (`c363de9`, dari
  `main` `0e7f26d`); rencana `rencana/rencana-pagar-lembur.md`; dev ditinggal di
  branch ini.
  1. **Lembur kembar**: jam persis sama → **ditolak** saat mengajukan (pesannya
     menyebut nomor pengajuan yang sudah ada); bertindih sebagian → **tetap
     boleh**, tapi penyetuju diberi tahu di kartu. Dipasang di titik simpan
     **dan** titik ACC — tombol tersembunyi bukan pagar. Nol penggabungan
     otomatis: dua sesi terpisah di hari yang sama itu sah.
  2. **Peringatan chip DODO maju ke titik keputusan**: `pending` ikut diperiksa,
     dan peringatannya tampil di **layar persetujuan**, bukan cuma Lembar Gaji.
     "Hari libur" tetap dari `AttendanceReconciler::liburUntuk()` — tak ada
     definisi kedua.
  - **28 uji hijau** (termasuk fixture nyata prod: dua giliran sah Alpajri
    11 Agu, 08:00–10:00 + 18:00–23:31, wajib lewat) · **sapuan prod baca-saja:
    0 pasang bertindih**, jadi pagar ini pencegahan bukan pemadam ·
    invarian `biasa + dodo = total` pegang di 51 pasangan
    orang-bulan dev · cacah baris 5 tabel tak bergeser · menemukan **kembar
    sungguhan yang masih hidup di dev** (Fajri 15 Agu, req#34 vs #35).
  - Deploy: **nol SQL, nol rsync**, tapi ada kelas baru → **deploy PENUH**.
  - ⚠️ Jalankan phpunit **per kelas**: >±17 uji ber-DB dalam satu proses membuat
    MySQL dev menolak koneksi (batas hosting bersama, bukan cacat kode — uji
    lama pun ikut gagal).

### Berjalan PARALEL di chat lain (belum selesai)
- **Pola interaksi (UX)** — kamus P1–P6 (chevron, toast-urungkan, tombol mati
  yang bilang kenapa, dll) sedang disebar bertahap; spec:
  `docs/spec/pola-interaksi.md`. Ada WIP belum commit di worktree rop.
- **Security hardening** — chat terpisah; brief:
  `docs/handoff/handoff-security-hardening.md`. P0: throttle `/signin` &
  `/affiliate/login` (PIN 6 digit tanpa throttle). Deploy-nya hotfix dari
  `main`, TERPISAH dari gerbong. Lintas-isu: TrustProxies kosong memengaruhi
  throttle DAN premis WiFi-saksi absen HP.

### Selesai di LOKAL — menunggu review Ekky (2026-08-04 malam)
- **Aturan Kerja** (`/absensi/aturan`, branch `feature/aturan-kerja` dari
  `feature/rop`, BELUM commit): halaman 3 tab — (1) Aturan: CRUD + editor jam
  per hari + konfirmasi "berlaku utk N karyawan mulai besok"; (2) Penugasan:
  ganti aturan = baris baru bertanggal (riwayat gaji utuh) + default per role
  utk akun baru Pusat Akun; (3) Kalender Libur: exception pola Lebaran, rambu
  libur ≠ cuti bersama. Gate Supervisor/Head IT/Finance/Super Admin di
  controller (Supervisor ditambah saat review); semua
  perubahan forward-only; mesin rekonsiliasi tak disentuh; log `att_rule_log`.
  Uji 28/28 lulus + smoke HTTP (termasuk audit putaran-2: fix section JS,
  default-role menggantung, self-XSS confirm, old() lintas-form).
  Deploy: SQL `2026_08_04_aturan_kerja.sql` +
  `access:rebuild`. Rencana: `rencana/rencana-aturan-kerja.md`.

### Rencana yang SUDAH di-ACC, belum dibangun
- **Paket "Pengaturan Operasional"** (ACC 2026-08-04, dibagi dua pemilik,
  keduanya basis `feature/rop`):
  - ~~Aturan Kerja (Ekky)~~ → **SELESAI di lokal**, lihat blok di atas.
  - **Penempatan — dikerjakan REZA** (`rencana/rencana-penempatan.md`):
    staf rolling/backup ke cabang lain tercatat resmi; checklist ikut cabang
    penempatan; Nissa & Irma boleh menempatkan staf mana pun di bawah bagan
    (lintas domain); tanggal akhir wajib; cabang asal tak pernah disentuh.
- **ROP fase 2**: threshold ACC Executive > 2 jt, four-eyes untuk pengajuan
  Finance, budget per departemen, multi-item, rekening vendor, push otomatis
  ke Accurate. Template impor Accurate **di-pause** (tunggu bahas dengan
  Finance; tombol sementara berlabel "Draf untuk Accurate").
- **Launch paket "Pengeluaran"**: petty cash sudah ada tapi **belum diadopsi
  siapa pun** (⚠️ RALAT 4 Sep: klaim "0 transaksi" BASI — prod **355 transaksi**, terbaru 3 Sep; sudah dipakai) — diluncurkan bareng ROP ke Finance +
  para head sebagai satu paket, butuh sosialisasi/demo.
- **IT Request Tracker** (dipetakan + didesain 2026-09-02, `rencana-it-tracker.md`
  §14 = rencana build): tiket permintaan IT dengan fase S/M/L dan formulir
  FRM-IT-01..07. Kunci desainnya: **formulir Kelas B (03/04/06) TERISI SENDIRI**
  dari git dan hasil uji — kalau dibuat formulir kosong untuk diketik ulang,
  sistemnya mati dalam dua minggu. Papan hanya memanas untuk tunggu **di luar
  IT** (tiket 30 hari di DEV tetap dingin). Belum ada kode; chat build terpisah,
  Fase 1 = papan + FRM-IT-01.

### Di-PAUSE (sengaja, jangan dikira hilang)
- **Integrasi Accurate penuh** (pull 6 buku + push engine + 5 halaman laporan
  Finance) — branch `feature/accurate-integration`, pause sejak 21 Jun, menu
  disembunyikan. Push engine terbukti (25 faktur, verifikasi 25/25 cocok).
  Sebagian kecil (DDL + pull-glaccount) sudah dipetik ke gerbong untuk ROP.
  ⚠️ Utang saat dilanjutkan: **rotate semua token API** (token testing pernah
  terpapar di chat) + password SSH.
- **Project Tracker** (Project>Tugas>To-do multi-user) — Fase A selesai lokal,
  branch `feature/project-tracker`, jangan merge dulu.
- **Redesign Reservasi / Pasien / Doctor** — re-skin bergaya baru; Doctor
  sampai Fase A (list). Diparkir sejak fokus pindah ke absensi.
- **Marketing dashboard** (akuisisi pasien) — plan matang, belum dibangun.
- **Cekat Open API / Omnicare push v2** — proposal sudah dikirim ke vendor
  (`docs/api/proposal-sweet-treats-api-omnicare-v2.md`), menunggu vendor.

## 4. Utang & pekerjaan tertinggal (di luar gerbong)

| # | Utang | Beratnya | Catatan |
|---|---|---|---|
| 1 | **Hotfix sweet-mates ke prod** | P0 keamanan | lihat §2 kiriman 1 |
| 2 | **Security hardening P0** (throttle signin/affiliate) | P0 keamanan | chat terpisah, hotfix dari main |
| 3 | Repo men-track foto upload (`expense-receipts`, `checklist-photos`, `affiliate`) — hanya `doctors/` & `rop/` yang diabaikan | sedang | bahan chat security; bukti petty cash ada di git history |
| 4 | Setelah gerbong naik: **retire `feature/absensi`** + arahkan semua orang cabang dari `feature/rop` | kecil | sudah disepakati, tinggal eksekusi |
| 5 | Prod penuh berkas cruft lama (`Helpers20260413.php`, `.env20260525`, dll) | kecil | bersihkan saat jendela deploy |
| 6 | Warning di form Jadwal Khusus kalau dokter cuti di tanggal itu | kecil | pasangan fix/doctor-availability yang ikut gerbong |
| 7 | Drawer Tambah Reservasi belum konsisten di halaman Pasien & Reservasi Cabang | kecil | masih link polos |
| 8 | 3 node menu ber-awalan `/` di dev (`/my-checklist`, dll) | kecil | prod bersih |
| 9 | `audit_log` baru merekam login/logout, belum perubahan data | besar (fase lanjut Pusat Akun) | "siapa mengubah apa" belum terjawab |
| 10 | Akun Yanwar Fauzi & Madalena belum ada (cuti:saldo-awal 21/23) | kecil | tunggu tanggal kontrak dari Finance |
| 11 | `Ela #58` (mesin Bintaro) belum di-mapping ke akun | kecil | |
| 12 | Konversi `public_html` prod jadi symlink (hapus gotcha rsync selamanya) | kecil | perbaikan permanen |
| 13 | **Perbaikan "cuti tak memakan hari libur" MENGGANTUNG TANPA COMMIT** | **besar — menentukan uang** | ±830 baris `app/` + 6 kelas uji di worktree `sweettreats-dodo`, **hidup di NOL environment**. Lihat kotak di bawah. |

> ### ⛔ Utang #13 — kenapa ini paling gawat di daftar
>
> Catatan sebelumnya menyatakan perbaikan ini **sudah di prod**, lengkap dengan
> pengukuran `git merge-base --is-ancestor` yang **lulus**. Pengukurannya benar;
> **bendanya yang salah**: branch `fix/cuti-hari-libur` **nol commit**
> (`reflog`: "Created from origin/main"), jadi tentu saja ia leluhur prod —
> yang lulus branch **kosong**.
>
> Yang bisa diperiksa siapa pun dalam 30 detik:
> ```
> git rev-parse main:app/Services/CutiService.php     # sama di SEMUA branch
> git reflog show fix/cuti-hari-libur                  # satu baris: dibuat, lalu diam
> ```
> `potongDariPengajuan()` masih memotong tiap hari kalender — komentarnya
> sendiri menyebut "belum melihat jadwal kerja/hari libur".
>
> **Akibat nyata:** koreksi data Yovanka & Ramdan boleh dijalankan (pembalik
> tak menghapus baris asli, jadi potongannya tak lahir lagi), tapi **pengajuan
> cuti berikutnya akan tetap memotong hari libur**. Membersihkan data tanpa
> menaikkan kodenya = menyapu di bawah keran yang masih menetes.
>
> **Langkah berikutnya**: selamatkan 16 berkas itu jadi commit sungguhan di
> branch bernama benar, uji per kelas di dev, baru koreksi datanya.
>
> **Pelajaran yang sudah tercatat dan berulang lagi**: *nama branch bukan bukti
> isi branch*, dan bentuk hasil palsu yang paling mahal bukan yang kacau —
> melainkan yang **rapi dan salah**.

## 5. Menunggu keputusan/aksi ORANG (bukan kerjaan koding)

| Siapa | Apa yang ditunggu |
|---|---|
| **Head sales** | CSV 150 kandidat reservasi yang mungkin tertimpa selama regresi 14–25 Jul (Sunter 60/Bintaro 46/Cinere 23/Kalideres 17/Pamulang 4) — hanya untuk cross-check FO; identitas lama tak bisa dipulihkan (reservasi tak punya audit-trail) |
| **Finance/Dani** | (a) cuti potong hari kalender vs hari kerja; (b) cuti seminggu penuh tanpa pengajuan = alpa?; (c) tanggal kontrak 29 karyawan; (d) pembahasan template impor Accurate utk ROP; (e) kapan siap adopsi paket Pengeluaran |
| **Reza** | (a) keputusan produk day-excuse checklist; (b) izin reskin halaman Role/setgroup + header tabel Reservasi (2 item terakhir §8 peta-tema) |
| **Ekky sendiri** | (a) selesaikan masa uji dev → bilang "gas prod"; (b) `gh auth login` sekali di mesin ini kalau mau PR dibuat otomatis; (c) demo/sosialisasi absensi+ROP ke tim saat naik prod (migrasi manual bertahap, tidak ada yang dipensiun seketika) |

## 6. Rencana SETELAH prod naik (urutan kasar prioritas)

1. **Stabilisasi prod** — pantau 1–2 minggu, angkat 8 role + 29 akun, demo ke
   tim, adopsi petty cash + ROP oleh Finance & para head.
2. **Security hardening** — habiskan P0/P1 dari chat security.
3. **ROP fase 2** (threshold Executive, budget, push Accurate) — sekalian
   membuka kembali proyek **Accurate** dari pause (ingat: rotate token dulu).
4. **Pusat Akun lanjutan** — perluas audit_log ke perubahan data; halaman
   Kesehatan jadi detektor kegagalan-senyap penuh.
5. **Jatah cuti penyempurnaan** — setelah jawaban Finance (kalender vs hari
   kerja, dst) + isi tanggal kontrak.
6. **Kalender Kehadiran lanjutan / arah ADMS** — mesin absen push otomatis
   (P207 sekarang USB-only), supaya upload manual hilang.
7. **Project Tracker Fase B/C** — lanjutkan dari branch parkiran.
8. **Redesign yang diparkir** — Reservasi drawer/shell, Pasien, Doctor B/C/D,
   Marketing dashboard akuisisi.
9. **Omnicare v2 / Cekat** — lanjut saat vendor merespons proposal.

## 7. Rujukan dokumen (semua di folder `docs/`)

| Butuh apa | Buka |
|---|---|
| Indeks semua docs | `README.md` |
| Daftar centang deploy prod | `deploy/manifest-prod.md` (+ `deploy/deploy-absensi.md`) |
| Cara kerja mesin absensi | `rencana/rencana-rekonsiliasi-absensi.md` |
| Absen HP (WiFi saksi) | `rencana/rencana-absen-hp.md` |
| WiFi cabang belajar sendiri | `rencana/rencana-wifi-belajar-sendiri.md` |
| Jatah cuti | `rencana/rencana-jatah-cuti.md` |
| ROP lengkap | `rencana/rencana-pengajuan-rop.md` + `handoff/handoff-rop.md` |
| Aturan tema/zona warna | `spec/peta-tema.md` |
| Kamus pola interaksi | `spec/pola-interaksi.md` |
| Skenario absensi dunia-nyata | `spec/sop-absensi-cuti.md` |
| Brief security | `handoff/handoff-security-hardening.md` |
| Prompt buka chat baru per alur | `spec/prompt-chat-baru.md` |

## 8. Cara merawat file ini

Setiap ada kejadian besar (deploy, fitur selesai, keputusan produk, pause/
lanjut proyek), minta chat teknis memperbarui file ini + tanggal di atas.
Sumber kebenaran teknis tetap: git history, `docs/`, dan memori chat Claude —
file ini ringkasannya, bukan penggantinya.
