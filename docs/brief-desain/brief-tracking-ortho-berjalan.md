# Brief Desain — Tracking Pasien Ortho (versi BERJALAN)

**3 Oktober 2026 · tiket IT-2026-013 · audiens: Sales, IT, HUB**

> Dokumen ini menggambarkan modul **sebagaimana ia berdiri sekarang**, bukan
> sebagaimana pernah dirancang. Ia menggantikan `revisi-3-tracking-ortho.md`
> untuk hal-hal yang disebut di §12; berkas itu tetap disimpan karena ia
> menjelaskan KENAPA rancangannya pernah begitu.
>
> Teman berkas ini:
> - `design-system-tracking-ortho.md` — token, komponen, dan kelas CSS
> - `alur-fungsi-tracking-ortho.md` — tiap rute, aksi, service, perintah, dan
> - `peta-ui-css-fungsi-ortho.md` — peta elemen ⇄ CSS ⇄ fungsi, satu-banding-satu
>   blok JavaScript beserta validasi, pagar, dan kolom yang ditulisnya
> - `../deploy/deploy-ortho.md` — langkah naik prod
> - `../handoff/balasan-ortho-hub-2026-10-02.md` — apa yang berubah sesudah audit HUB

Halaman kerja harian Sales untuk menagih pasien behel supaya kontrol.
Menggantikan Google Sheets per cabang yang angkanya saling berbeda.
Lima cabang · 1.639 pasien.

---

## 0. Yang berubah sejak 2 Okt

Sembilan hal, dan **empat di antaranya membatalkan keputusan saya sendiri** —
dicatat apa adanya karena alasan pembatalannya yang berguna, bukan hasil
akhirnya saja.

| Yang berubah | Kenapa |
|---|---|
| Kolom Jadwal **kembali jadi tanggal** | Perkiraan memberi masa depan empat tingkat tapi masa lalu satu ember; semua yang terlambat menumpuk di atas dan kehilangan pembeda — §4 |
| Tab "Tanggal reservasi" **berhenti mengaku tahu** | Klaim "tanpa reservasi" hidup di lima tempat, padahal sistem memang tidak tahu — §3 |
| `ort:tautkan-reservasi` **menyapu tiga jenis baris** | Versi lama melewati persis baris yang dilihat Sales — §10 |
| **Siklus terlewat** menggantikan ambang hari untuk eskalasi | "Telat 45 hari" berarti dua hal berbeda untuk pasien berinterval 21 dan 60 — §5 |
| **Hasil follow-up** dicatat, dan **ditampilkan** | Versi pertamanya menyimpan tanpa menampilkan — form meminta tiga keputusan lalu membuang ketiganya — §6 |
| Dropdown **kanal dibuang** | Ia meminta satu keputusan lagi lalu tidak pernah dipakai di layar mana pun |
| **Jumlah baris per halaman bisa diatur** | 10 · 25 · 50 · 100, bawaan tetap 50 — §3 |
| Kelompok **"di cabang lain" dibungkus** | Pil cabangnya terbaca setara dengan cabang utama — §2 |
| Teks baris riwayat **ditebalkan** | Yang membuatnya pudar ternyata kontras bobot (400 di antara 700 dan 800), bukan warnanya |

---

## 1. Dasar yang mengikat

Empat kalimat ini menentukan seluruh sisanya. Tiga di antaranya pernah
dipahami terbalik, dan tiap kali salah pahamnya melahirkan fitur yang harus
dibongkar lagi.

### 1.1 Kolom Jadwal itu JATUH TEMPO, bukan janji

Kamus brief asli sudah menuliskannya:

> `SCH` · **Jadwal** · tanggal pasien **seharusnya** datang kontrol
> `RSV` · **Reservasi** · reservasi nyata. **Kosong = pasien belum booking**

Janji yang sesungguhnya hidup di modul Reservasi, lengkap dengan jam, dokter,
dan aturan anti-bentrok. `ort_control.sch_date` tidak punya satu pun dari itu.

### 1.2 SALES yang menentukan tanggal, bukan dokter

Dikonfirmasi Ikhsan 1 Okt 2026. Dokter hanya menyarankan, dan sarannya
**lisan** — tidak pernah tercatat di mana pun. Terbukti di data: dari 2.531
baris kontrol ortho Jul–Sep prod, `sales_notes` terisi 0,6% dan isinya catatan
tagihan; `redeem_rx` 0%; `item_extra_name` 0%.

⚠ Premis brief Revisi 3 — *"tanggal kontrol berikutnya adalah kesepakatan
antara dokter dan pasien di kursi perawatan"* — **salah**, dan dari premis itu
lahir tombol "Konfirmasi jadwal" yang kini sudah dibuang.

### 1.3 Tidak ada aturan otomatis yang layak dikonfirmasi massal

Diukur di prod terhadap kunjungan yang benar-benar terjadi:

| Tebakan | Meleset median |
|---|---|
| Jarak baku 30 hari | 22 hari |
| Irama pasien sendiri | **14 hari** |

Irama menang di 86% kasus. **Tapi hanya 24% pasien yang iramanya rapat**
(`(max − min) / median ≤ 0,5`). Tebakan terbaik yang bisa dihitung pun masih
tebakan dua minggu.

### 1.4 Kolom Datang diisi MESIN; Sales tidak punya tombolnya

Kehadiran datang dari cermin rekam medis Omnicare, bukan dari status reservasi.
Diukur di DB pengembangan: **133 dari 133** kontrol tertutup bersumber
`visit_source = 'rekam_medis'`, nol dari reservasi, nol dari tombol Sales.

Tidak ada rute `ortho.hadir`. Secara struktural Sales memang tidak bisa
mengarang kehadiran.

---

## 2. Tiga tab, tiga kartu

| Tab | Isinya |
|---|---|
| **List** | Daftar kerja harian. Satu baris = satu siklus kontrol yang masih terbuka |
| **Kalender** | Grid bulanan jatuh tempo. Papan **beban kerja**, bukan agenda janji |
| **Pasien Ortho** | Daftar pasien, bukan daftar jadwal. Punya saringan Aktif / Selesai |

Tiga kartu ringkas di atas tabel. Mengkliknya **mengganti** isi tabel, bukan
menyaringnya.

| Kartu | Menjawab |
|---|---|
| **Jadwal minggu ini** | Berapa jatuh tempo pekan ini, dan berapa yang sudah punya reservasi |
| **Terlewat** | Jatuh tempo yang sudah lewat tanpa kunjungan |
| **Belum punya jadwal** | Pasien aktif tanpa jatuh tempo sama sekali — antrean penjadwalan |

Sub-tab kegentingan di dalam tab List: `semua` · `terlewat` · `hari-ini` ·
`minggu-ini` · `mendatang`. Urutan bawaan menaruh yang paling tertinggal di atas.

Tab cabang lain muncul untuk pasien kita yang sedang dipegang cabang lain —
**lihat-saja**, tanpa tombol tulis.

Tombol pembukanya dan pil cabang yang muncul saat dibuka **dibungkus jadi satu
kelompok** (3 Okt 2026). Sebelumnya ketiganya pil sejajar di baris yang sama
dengan pil cabang utama, jadi "Sunter / Cinere / Pamulang" terbaca setara
dengan "Bintaro" — padahal mereka milik tombol di kirinya dan isinya
lihat-saja. Pil cabang **utama sengaja tetap di luar** kelompok itu.

⚠ Wadahnya hanya terlihat saat terbuka, dan **tinggi barisnya tidak pernah
berubah** saat dibuka-tutup — padding-nya tetap ada di kedua keadaan lalu
dikompensasi margin negatif. Versi pertama memasang padding hanya saat
terbuka, dan seisi halaman di bawahnya tersentak turun 12px tiap kali.

---

## 3. Tabel daftar kerja

Tujuh kolom dan satu tombol buka. **50 baris per halaman** — bisa diubah ke
10 / 25 / 100 lewat pemilih di kaki tabel, dan pemilihnya berlaku di kelima
daftar, bukan cuma tab ini. Bawaannya dulu 10, dan 959 kasus Bintaro berarti
96 halaman.

| Kolom | Isi |
|---|---|
| **Pasien** | Nama tebal, lalu telepon yang bisa diklik ke WhatsApp. Badge transfer / "Tanpa jadwal tercatat" kalau ada — yang kedua berarti kunjungan tidak cocok dengan jadwal mana pun di sistem, **bukan** pasien tidak memesan (sistem tidak tahu itu). **Tanpa nomor RM** — itu hanya di panel yang dibuka |
| **Dokter** | Nama dokter ortho pasien ini |
| **Jadwal** | Tanggal jatuh tempo. Bergaris putus selama masih hitungan mesin — lihat §4 |
| **Follow-up** | Tanggal Sales menghubungi. Belum ada → `X` |
| **Reservasi** | Tanggal janji. Dibatalkan → tanggal dicoret + teks merah. Waitlist tanpa tanggal → tulis "Waitlist", jangan `X` |
| **Status** | Chip, lihat §5 |
| **Tindakan berikutnya** | Tepat satu tombol, lihat §6 |

### Kenapa `X` dipertahankan

Sel kosong terasa seperti data hilang; `X` terasa seperti fakta. Tapi saat
**seluruh** kolom berisi X — keadaan hari pertama — ia berhenti terbaca "belum
terjadi" dan mulai terbaca "Sales tidak bekerja". Jadi X tetap per baris, dan
keadaannya dijelaskan sekali lewat spanduk di atas tabel yang **hilang sendiri**
begitu kolomnya mulai terisi.

### Yang TIDAK boleh ada di baris List

Kode cabang (BIN/SUN), nomor RM, tombol "Catat Kunjungan", chip "Aktif", filter
cabang, teks instruksi "klik baris untuk…", emoji, dan dialog konfirmasi.

---

## 4. Kolom Jadwal — tanggal jatuh tempo

Kolom ini menampilkan **tanggal**: `31 Agt '26`. Perkiraannya pindah ke tooltip.

### Sempat jadi perkiraan, lalu dibatalkan — dan ini alasannya

Antara 2–5 Okt 2026 kolom ini berbunyi `Sudah waktunya` · `Hari ini` ·
`Pekan ini` · `± 3 pekan lagi` · `± Nov`, supaya tidak memberi ketepatan yang
memang tidak kita punya. Niatnya benar, efeknya di layar kerja tidak:

> masa depan dapat **empat tingkat**, tapi masa lalu cuma **satu ember**.

Daftar diurutkan dari jatuh tempo terdekat, jadi semua yang terlambat menumpuk
di paling atas — tepat di situ mereka kehilangan pembeda. Terukur di DB
pengembangan:

| Pasien | Kolom berbunyi | Sebenarnya telat |
|---|---|---|
| ANDIKA ISMONO | Sudah waktunya | **32 hari** |
| Agies Aulia | Sudah waktunya | 3 hari |
| LIA FITRIANI | Sudah waktunya | 2 hari |
| tiga lainnya | Sudah waktunya | 2 hari |

Enam belas kali lebih genting, tampil sama persis. **Itu penyakit yang sama
dengan lantai `MIN_MAJU_HARI` yang sudah dicabut** — yang satu meratakan
*datanya*, yang satu lagi meratakan *tampilannya*.

Tanggal membedakan sendiri tanpa perlu tingkatan: `31 Agt` di atas `30 Sep`
langsung terbaca jauh lebih tertinggal.

### Kejujurannya dipindah, bukan dibuang

| Penanda | Artinya |
|---|---|
| Garis putus amber (`.ort-proposal`) | tanggalnya masih hitungan mesin, belum disentuh Sales. Hilang begitu Sales menggesernya |
| Tooltip | kalimat perkiraannya — "Sudah waktunya", "± 3 pekan lagi" — tetap terbaca tanpa memakan lebar kolom 112px |
| Merah (`.ort-late`) | sudah lewat jatuh tempo |

Seberapa telatnya tetap dibawa chip Status, bukan diulang di kolom ini.

`sch_date` juga tetap mengurutkan daftar, memilah sub-tab, mengisi kartu, dan
jadi default saat lembar Buat Reservasi dibuka.

Rumus perkiraannya: `OrtControl::perkiraanJatuhTempo()` — fungsi statis murni,
satu salinan, sekarang dipakai enam tooltip.

---

## 5. Chip status

| Chip | Artinya |
|---|---|
| **Menunggu** | Belum di-FU, belum ada reservasi, belum terlewat |
| **Sudah di-FU** | Sudah dihubungi, reservasinya belum dibuat |
| **Ada reservasi** | Punya janji yang belum lewat. Hari-H berganti jadi "Reservasi hari ini" |
| **Reservasi batal** | Janjinya dibatalkan — harus langsung ketahuan |
| **Terlewat N hari** | Dihitung dari umur baris |
| **Terlewat N hari** (parah) | Lewat dari 14 hari; barisnya diberi garis merah di tepi kiri |
| **Terlewat N hari · K siklus** | *(4 Okt 2026)* Ditambahkan kalau sudah ≥ 2 **siklus** kontrol lewat sejak kunjungan terakhir — bukan ambang hari. "Terlewat 45 hari" terbaca sama untuk pasien berinterval 21 (sudah 2 siklus) dan 60 (belum satu); siklus yang membedakannya. Panel baris memberi fakta "Siklus terlewat · eskalasi", dan saringan status punya pilihan "Terlewat 2× atau lebih" |

⚠ **Chip "Usulan jadwal" sudah dibuang.** Ia dulu berdiri paling atas, jadi
jatuh tempo yang sudah lewat tetap berbunyi "Usulan jadwal" alih-alih
"Terlewat". Bersama jepitan tanggal, chip itu lapis kedua yang menyembunyikan
26 dari 39 pasien terlambat di DB pengembangan.

---

## 6. Tangga tindakan

Tepat **satu** tombol per baris, selalu anak tangga berikutnya. Lima tombol
untuk Sales yang sedang menelepon justru memperlambat.

```
Catat Follow-up  →  Buat Reservasi  →  (tidak ada tombol)
```

| Anak tangga | Perilaku |
|---|---|
| **Catat Follow-up** | *(sejak 4 Okt 2026)* Membuka lembar: **hasil** (pilihan tetap — dihubungi · tidak respon · minta jadwal ulang · menolak · salah nomor), tanggal, catatan bebas. Dulu sekali klik tanpa dialog, dan hasilnya tidak pernah tercatat. Tetap **tanpa muat ulang** — tabel ditukar, lembar ditutup sendiri — dan bisa diurungkan 90 detik (hasil + catatan ikut dipulihkan) |
| **Buat Reservasi** | Membuka lembar berisi hari praktik dokter pasien ini, lengkap jam dan seberapa penuh |
| **Menunggu pasien** | Tidak ada tombol. Kolom Datang terisi sendiri dari rekam medis tiap malam |

⚠ **"Konfirmasi jadwal" sudah dihapus** beserta rutenya. Tidak ada yang perlu
dikonfirmasi dari sebuah pengingat: menyetujui tanggal jatuh tempo tidak
mengubah apa pun dan tidak memberi tahu pasien. Panggilan untuk *menanyakan*
kapan pasien bisa datang justru mendahului penentuan tanggalnya.

Pengganti dialog "Anda yakin?": **toast + Urungkan 90 detik**. Lebih cepat
untuk pekerjaan yang diulang puluhan kali sehari, dan tetap bisa dibatalkan.

---

## 7. Antrean penjadwalan

Kartu "Belum punya jadwal" membuka antrean bertingkat kegentingan. Tiga
kelompok, terlama-dulu, satu tindakan saja: **Jadwalkan kontrol**.

| Kelompok | Syarat |
|---|---|
| **Hilang lebih dari 6 bulan** | Kontrol terakhir lebih dari 180 hari lalu |
| **Jarang kontrol** | Kurang dari 2 kali per 6 bulan — **definisi Sales sendiri**, ukuran frekuensi bukan kebaruan |
| **Baru selesai kontrol** | Baru saja datang, belum punya jatuh tempo berikutnya |

Pasien yang sengaja tidak diberi jatuh tempo mendarat di sini. Mereka **tidak
hilang** dari layar — mereka berpindah dari daftar kerja ke antrean.

---

## 8. Lima lembar

Semua aksi yang butuh masukan dibuka sebagai **side sheet**, bukan halaman baru.

| Lembar | Isinya |
|---|---|
| **Buat Reservasi** | Hari praktik dokter pasien itu — tanggal, jam kerja, berapa slot terisi — lalu dioper ke form Reservasi yang sudah terisi pasien, cabang, dokter, dan tanggal |
| **Geser pengingat** | Mengubah kapan pasien dianggap jatuh tempo. Dibuka dari panel baris, bukan dari kolom Jadwal |
| **Jadwalkan kontrol** | Memberi jatuh tempo pertama kepada pasien dari antrean |
| **Pindahkan ke cabang lain** | Transfer, dengan **alasan wajib** |
| **Catat Follow-up** *(4 Okt 2026)* | Hasil (pilihan tetap), tanggal (maks hari ini), catatan bebas. Hasilnya **ditampilkan** di teks baris panel dan tooltip tabel/matriks — tapi **chip tetap seragam "Sudah di-FU"**: chip menjawab satu pertanyaan saja, sudah ditelepon atau belum, dan chip panjang seperti "Sudah dihubungi, bersedia" mendorong baris jadi dua baris. Dropdown **kanal dibuang 5 Okt** — ia meminta satu keputusan lagi lalu tidak pernah ditampilkan; panel baris malah menulis "WhatsApp" di-hardcode apa pun pilihannya. Form-nya mengarah ke `/follow-up`, jadi tercegat mesin submit realtime: tabel ditukar, toast muncul, lembar ditutup — tanpa muat ulang |

### Pemilih hari memakai mesin modul Reservasi

Jam praktik, jam istirahat, cuti, dan pengecualian per tanggal semuanya
dihitung trait `ResolvesDoctorAvailability` yang sama dengan yang dipakai form
Reservasi. **Tidak ada rumus kedua** — repo ini sudah punya tiga rumus
kapasitas yang saling berbeda, dan yang keempat akan membuat angka di dua layar
berbeda untuk hari yang sama.

**Hari penuh tetap ditampilkan**, ditandai dan tidak bisa diklik. Daftar yang
diam-diam melewati hari penuh membuat pasien bertanya "kenapa tidak Rabu?" dan
Sales tidak punya jawaban.

⚠ Dua dari tiga kasus di dev dokternya **tidak terpetakan**. Lembarnya wajib
tetap berguna tanpa dokter: kotak tanggal bebas selalu ada, dan alasannya
dikatakan apa adanya. Empat sebab dibedakan karena perbaikannya berbeda —
"belum dipetakan" urusan IT, "belum punya jadwal praktik" urusan operasional.

---

## 9. Mesin jatuh tempo

Jatuh tempo = **kunjungan terakhir + irama pasien**. Satu pagar, dan ia soal
beban kerja.

| Aturan | Nilai | Kenapa |
|---|---|---|
| Irama | median selang | Rata-rata tertarik pencilan: satu kali hilang 8 bulan lalu kembali sudah cukup melarkan angkanya puluhan hari |
| Selang minimum | 2 | Satu selang bukan irama, itu kebetulan. Di bawahnya dipakai jarak baku 30 hari |
| Jepitan irama | 21–60 hari | Di bawah 21 biasanya perbaikan bracket lepas; di atas 60 itu kelalaian |
| Irama rapat | sebaran ≤ 50% | `(max − min) ÷ median`. Hanya 24% pasien memenuhinya |
| Pasien hilang | > 180 hari | **Tidak diberi jatuh tempo.** 495 dari 927 pasien Bintaro hilang lebih dari itu |

### Jatuh tempo BOLEH jatuh di masa lalu

Itu bukan cacat, itu jawabannya. Pasien yang kontrol terakhirnya 200 hari lalu
memang sudah telat 170 hari, dan itulah yang perlu dilihat Sales.

⚠ **Jepitan yang dulu ada, dan akibatnya.** Versi sebelumnya mendorong setiap
jatuh tempo yang sudah lewat ke `hari ini + 3`. Saat menarik riwayat
berbulan-bulan, semua pasien yang tertinggal menumpuk di tanggal dekat yang
sama: **651 usulan di satu hari**, 56% dari seluruhnya. Tanpa jepitan dan
**tanpa pagar kapasitas apa pun**, 34 pasien tersebar di 28 tanggal, paling
padat 2 orang.

Irama dihitung **segar** saat mengusulkan, bukan dibaca dari kolom: saat
tarikan malam berjalan, kunjungan yang baru ditulis sudah masuk hitungan
sementara kolomnya baru disegarkan sesudah semua kasus selesai.

---

## 10. Dari mana datanya

Satu arah: Omnicare → Data Center. **Tidak pernah menulis balik.**

| Jam | Perintah | Hasilnya |
|---|---|---|
| 02:00 | `omnicare:sync` | Tarik transaksi ke cermin rekam medis |
| 02:45 | `omnicare:match-patients` | Jodohkan identitas pasien |
| 03:00 | `omnicare:reconcile` | Cocokkan reservasi dengan kunjungan |
| **04:00** | **`ort:pull-visits`** | Isi kolom Datang, tutup kontrol, lahirkan jatuh tempo berikutnya |

⚠ **Jeda 18 jam, dan itu disengaja.** Pasien yang datang pukul 10:00 hari ini
baru terlihat besok subuh. Status `completed` di modul Reservasi tidak
mempercepatnya: ia bisa diisi Front Office tanpa pasien benar-benar dilayani,
sementara rekam medis adalah bukti transaksi yang terjadi.

### Perintah yang dipakai manusia

| Perintah | Guna |
|---|---|
| `ort:seed-cases` | Semai pasien behel dari rekam medis, sekali jalan. Butuh `--commit` |
| `ort:pull-visits` | Tarikan malam. `--tanpa-usulan` untuk tarikan mundur pertama |
| `ort:rollup` | Hitung ulang ringkasan. `--verify` memeriksa tanpa menulis |
| `ort:rapikan-usulan` | Hitung ulang jatuh tempo yang sudah tersimpan |
| `ort:tautkan-reservasi` | Jodohkan reservasi lama. Dicocokkan ke tanggal **janji**, bukan tanggal pesan. Menyapu **tiga jenis baris** — lihat di bawah |
| `ort:seed-demo` | Data peraga. **Lokal saja** |

#### Kenapa penjodohan reservasi menyapu tiga jenis baris

Kolom Reservasi sempat berbunyi "tidak ada" untuk hampir semua orang, dan
sebabnya bukan tanggal yang rusak: **`reservation_id` terisi 0 dari 35.317
baris di prod.** Reservasi hidup di tabel Sweet Treats sendiri
(`raw_cus_reservation`) — **bukan dari Omnicare** — dan belum pernah
dijodohkan ke kontrol ortho.

Alat penjodohnya ada, tapi versi pertamanya hanya menyentuh baris yang sudah
punya kunjungan — melewati persis baris yang dilihat Sales tiap hari:

| Jenis baris | Dijodohkan lewat |
|---|---|
| **Riwayat** (sudah ada kunjungan) | tanggal datang ± 3 hari |
| **Tidak hadir** (ditutup, tanpa kunjungan) | tanggal jadwal ± 3 hari — termasuk reservasi yang **dibatalkan**, karena justru di situ ceritanya |
| **Kontrol terbuka** | reservasi **mendatang** milik pasien itu, sampai 120 hari ke depan. Yang sudah batal dibuang; layanan behel diutamakan |

⚠ Angka **"Cakupan: N%"** di laporannya hanya mengukur jenis **riwayat** —
itulah yang dibandingkan ke 91% prod. Dua jenis lain dilaporkan terpisah supaya
tidak mengencerkannya.

---

## 11. Yang SENGAJA tidak ada

Tiap butir pernah ada atau pernah diminta, dan dibuang dengan alasan.

| Tidak ada | Kenapa |
|---|---|
| Tombol Catat Kunjungan | Kolom Datang diisi mesin. Tombol manual = dua sumber kebenaran |
| Tombol Konfirmasi jadwal | Tidak ada yang perlu dikonfirmasi dari pengingat |
| Pagar kapasitas per cabang-tanggal | Penumpukan 651 lahir dari jepitan, bukan dari ketiadaan pagar |
| Pagar hari praktik dokter di mesin jatuh tempo | Dipindah ke saat **memesan**, tempat yang benar |
| Pagar klinik tutup Senin | Pengingat boleh jatuh Senin; Sales meneleponnya Selasa |
| Filter cabang | Cabang dikunci akun. Dropdown menyiratkan bisa diganti |
| Kode cabang BIN / SUN | Di layar pakai nama panjang |
| Dialog "Anda yakin?" | Diganti toast + Urungkan 90 detik |
| Emoji | Ikon garis saja (Font Awesome 5) |

---

## 12. Berubah dari Revisi 3

Kalau Anda sudah membaca `revisi-3-tracking-ortho.md`, tujuh hal ini yang
berbeda. Premis Revisi 3 (§1.2) itulah yang dikoreksi, dan hampir semua
perubahan turun dari sana.

| Dulu | Sekarang |
|---|---|
| Tanggal "12 Jan '27" di kolom Jadwal | Tetap tanggal — tapi bergaris putus selama masih hitungan mesin, dan perkiraannya di tooltip |
| Konfirmasi jadwal → Follow-up → Buat Reservasi | Catat Follow-up → Buat Reservasi |
| Jatuh tempo tidak boleh di masa lalu | Boleh, dan itu arti "Terlewat" |
| Jarak baku 30 hari untuk semua | Irama pasien sendiri, dijepit 21–60 hari |
| Tanggal pasang tampil sebagai fakta | Yang diperkirakan diberi tanda `±` — 45,7% kasus |
| 10 baris per halaman | 50 baris |
| Antrean dua kelompok | Tiga kelompok — yang ketiga kembali |

---

## 13. Deploy

Rinciannya di `../deploy/deploy-ortho.md`. Dua kegagalan di sana **sunyi** —
tidak ada pesan galat, halamannya terbuka normal.

1. **Prasyarat** — audit collation prod. SELECT saja. Kalau mengembalikan
   baris, DDL tidak boleh jalan apa adanya.
2. **Skema** — 8 berkas SQL, lalu `php artisan access:rebuild`. Lupa berarti
   **404 di semua halaman**.
3. **Isi data** — 6 perintah berurutan. Lupa `ort:rollup` berarti irama kosong
   untuk semua pasien.
4. **Aset** — rsync **dibatasi** ke `public/assets/`. `public_html` prod salinan
   manual, bukan symlink.

---

## 14. Masih terbuka

| Hal | Status |
|---|---|
| Impor follow-up lama dari Google Sheets | Menunggu keputusan Ikhsan |
| Pemetaan dokter ke `raw_doctor` | Bolong. Di dev hanya 12 dari 42 terpetakan |
| Sebaran jatuh tempo di volume prod | **Belum pernah diukur.** Semua angka sebaran dari 39 pasien lokal; prod punya 1.639 |
| Pagar kapasitas | Dibuang. Dipasang kembali kalau pengukuran prod menunjukkan penumpukan |
| Merekam saran lisan dokter | Satu-satunya sumber yang bisa mengalahkan tebakan 14 hari. Keputusan Ikhsan |

### Satu permintaan konkret saat deploy

Sesudah tarikan pertama di prod, hitung berapa pasien jatuh tempo per tanggal
per cabang:

```sql
SELECT sch_date, COUNT(*) n FROM ort_control
 WHERE is_proposal = 1 AND closed_at IS NULL
 GROUP BY sch_date ORDER BY n DESC LIMIT 5;
```

Di lokal paling padat **2 orang**. Kalau di prod ada tanggal berisi puluhan
pasien dalam satu cabang, laporkan — pagarnya mudah dipasang kembali karena
mesinnya masih utuh.
