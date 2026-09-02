# Design Brief — IT Request Tracker (SweetTreats)

> Untuk claude.ai/design. **Kelima berkas dilampirkan langsung** (bukan lewat repo).
> Konteks teknis lengkap:
> `docs/rencana/rencana-it-tracker.md` (alur, status, gerbang, field).
> Sistem desain yang berlaku: `docs/spec/peta-tema.md` + `docs/spec/pola-interaksi.md`.
> Ikon **FontAwesome 5** — nama gaya FA6 akan render sebagai kotak kosong.

## 1. Siapa yang memakai

Empat kelompok, dan hubungan mereka dengan layar ini **sangat berbeda**. Ini
bukan satu aplikasi untuk semua — ini dua produk yang berbagi data.

**Tim IT — dua orang.** Ekky (Head IT) dan Reza. Merekalah yang membuka layar
ini paling sering, dan merekalah yang memikul seluruh antrean. Ekky melakukan
triase; Reza hanya memindahkan status pekerjaan yang sedang dia kerjakan.

**Head departemen — "head-in-training".** Yovan (Marketing), Della (Finance),
dan Ekky sendiri. Penting dipahami: **mereka bukan head penuh** — posisi di
atas mereka kosong, dan Robi sedang melatih mereka. Mereka mengajukan
permintaan, mengisi UAT, dan ingin tahu satu hal: *punyaku sampai mana.*

**Executive & Konsultan.** Mereka **tidak** membuka layar ini tiap hari. Mereka
membukanya sesekali, sebentar, dan pertanyaannya cuma satu: **IT sedang memikul
apa, dan apakah wajar kalau aku menambah satu lagi.**

⚠️ Kelompok terakhir inilah alasan sistem ini dibangun. Kalau mereka membuka
papan dan **tidak langsung paham dalam lima detik**, seluruh proyek gagal —
sebanyak apa pun formulirnya rapi.

## 2. Yang mereka butuhkan, urut kepentingan

**① Papan Antrean** — untuk IT, Executive, Konsultan.
Kanban per fase. Yang harus terbaca **sekilas, tanpa mengklik apa pun**:
berapa banyak yang sedang berjalan, dan **mana yang macet karena menunggu
orang di luar IT**.

**② "Menunggu siapa, sejak berapa hari"** — ini bukan detail, ini **inti**.
Dihitung sistem, bukan diisi. Sebuah tiket bisa menunggu IT, menunggu pemohon
(UAT belum diisi), atau menunggu Executive (persetujuan Go-Live). Perbedaan
ketiganya **harus terlihat dari jauh**, karena itulah bukti bahwa kelambatan
bukan selalu di IT.

**③ Halaman Permintaan** — untuk Head.
Formulir pengajuan + daftar "permintaan saya" + posisinya di antrean.
Bukan kanban — mereka tak perlu melihat papan besar; mereka perlu melihat
milik mereka.

**④ Formulir per fase** — Requirement, Analisis, Uji, UAT, Go-Live, Evaluasi.

**TIDAK termasuk**: pelaporan jam kerja, penilaian kinerja, chat/komentar
panjang. Kalau butuh diskusi, itu terjadi di luar sistem.

## 3. Tantangan desain yang sebenarnya

Lima hal yang membuat ini tidak sepele:

**Beban formulirnya DISENGAJA — tapi tak boleh terasa sebagai hukuman.**
Ini paradoks utamanya. Formulir ada supaya pemohon **ikut berpikir**, bukan
menyerahkan semua ke IT. Tapi kalau ia terasa seperti birokrasi, orang akan
menghindarinya dan kembali mengirim permintaan lewat WhatsApp — dan sistemnya
mati di minggu kedua. Rancang supaya pemohon merasa **sedang berkontribusi**,
bukan sedang mengisi berkas. Pertanyaan yang bagus terasa seperti bantuan
berpikir, bukan seperti pemeriksaan.

**Satu data, dua pertanyaan yang berlawanan.** Head bertanya *"punyaku di
mana?"* — dia butuh kedalaman pada satu tiket. Executive bertanya *"IT sedang
sesibuk apa?"* — dia butuh keluasan tanpa kedalaman. Memaksakan satu tampilan
untuk keduanya akan mengecewakan dua-duanya.

**Tujuh fase itu banyak — tapi FASE tetap sumbu papannya.** Kolom = tujuh tahap
templat (`Requirement · Analysis & Design · Development · Internal Testing ·
UAT · Go Live · Evaluation`), boleh dikelompokkan jadi `Masuk / Rancang /
Kerjakan / Rilis` kalau sempit, dan fase sepi boleh terlipat — tapi **jangan
menyembunyikan yang macet**.
⛔ **JANGAN menjadikan "siapa yang memegang" sebagai kolom.** Pertanyaan utama
pengguna adalah *"modul ini sudah sampai tahap mana"*. "Menunggu siapa" tetap
ada, tapi sebagai **strip di kartu + filter Macet** — dan panel ringkasan di
atas papan sudah menjawab pertanyaan Executive, jadi kolom tak perlu
mengulanginya.

**Tujuh formulir, TIGA kelas — ini yang paling mudah salah dirancang.**
Rinciannya di `rencana-it-tracker.md` §7, tapi intinya:
- **Kelas A** (01 Requirement · 05 UAT · 07 Evaluation) — diisi **pemohon**.
  Sedikit field, bahasa manusia.
- **Kelas B** (03 Development Record · 04 Internal Testing · 06 Go Live) —
  **TERISI SENDIRI**, IT tinggal mengonfirmasi. Branch/commit diambil dari git,
  hasil uji dari catatan uji, checklist deploy dari catatan deploy.
  ⛔ **Kalau ketiganya dirancang sebagai formulir kosong untuk diketik ulang, di
  situlah sistemnya mati** — itu pekerjaan yang sudah dilakukan IT, bukan
  kerjaan baru.
- **Kelas C** (02 Analysis & Design) — satu-satunya yang benar-benar dipikirkan.

**Identitas pemohon jangan ditanyakan.** Nama, departemen, cabang, kontak,
tanggal — sistem sudah tahu semuanya dari akun yang login. Tapi **ekspor
FRM-IT-01 tetap harus menampilkannya terisi**, dengan nama bagian resmi templat,
supaya Konsultan IT & Management mengenali dokumennya. Kertas bertanya karena
kertas tak tahu; sistem sudah tahu.

**Tiga jenjang, satu papan.** Permintaan **S** (bug kecil) melewati 3 fase;
**L** (modul) melewati 7. Keduanya hidup di papan yang sama. Kartu harus
menunjukkan jenjangnya tanpa membuat yang kecil terlihat sepele — karena yang
kecil justru sering yang paling mendesak.

**Mockup ini merangkap alat komunikasi.** Ekky akan memakainya untuk
menjelaskan proses ini ke Executive **sebelum** dibangun. Jadi tiap layar butuh
**badge Fase 1 / Fase 2 / Fase 3** yang menandai kapan ia benar-benar datang.
Tanpa badge, menunjukkan mockup penuh sama dengan menjanjikan sistem penuh.
Badge-nya harus jelas terbaca tapi tidak ikut jadi tokoh utama.

## 4. Yang sudah ada dan sebaiknya diikuti

- **`docs/spec/peta-tema.md`** — cokelat sebagai default, tiga zona spesial.
  Tracker ini kandidat zona sendiri; tentukan di desain.
- **`docs/spec/pola-interaksi.md`** — kamus P1–P6 yang sudah dipakai di
  seluruh aplikasi (chevron, toast-urungkan, tombol-mati-yang-bilang-kenapa).
  **Pola "tombol mati harus menjelaskan kenapa" sangat relevan di sini**:
  tiket yang tak bisa maju karena gerbangnya belum terpenuhi harus mengatakan
  *field mana* yang kurang, bukan sekadar tak bisa diklik.
- **Project Tracker** (`resources/views/crud/project-tracker/`) — sudah punya
  skin krem dan pola kartu. Produk ini **terpisah** darinya (audiensnya beda),
  tapi kalau ada bahasa visual yang bisa dipinjam, pinjam.
- Bootstrap 4, FontAwesome **5.8.2** lokal.

## 5. Yang diharapkan dari handoff

Layar, urut kepentingan:

1. **Papan Antrean** — tampilan utama. Termasuk keadaan "menunggu" yang
   memanas, dan keadaan kosong (belum ada tiket).
2. **Kartu tiket** — anatominya. Muat: nomor `IT-2026-001`, judul, pemohon,
   jenjang S/M/L, fase, **menunggu siapa + berapa hari**, prioritas IT.
3. **Halaman Permintaan (Head)** — formulir pengajuan + daftar milik saya.
4. **Detail tiket** — ketujuh fase dalam satu halaman, fase yang sudah lewat
   terlipat, fase berjalan terbuka.
5. **Layar triase IT** — menetapkan jenjang, bobot, prioritas.
6. **Formulir Analisis** — yang terberat, karena memuat tabel FR dengan
   **`Kriteria Diterima`** (field terpenting di seluruh sistem).
7. **Checklist Go-Live** — backup, script, rollback, pemeriksaan pasca-deploy.

Keadaan yang jangan dilupakan: **tiket ditolak**, **tiket ditunda**,
**jalur Insiden/Hotfix** (masuk langsung ke Development, formulir menyusul),
dan **Inisiatif IT** (tanpa pemohon).

⚠️ **Indikator gerbang bukan to-do.** Di layar detail, kelengkapan tiap fase
ditampilkan sebagai daftar syarat. Itu **lampu status yang menyala sendiri**
kalau field-nya terisi — **bukan** checkbox yang dicentang manual. Kalau
bentuknya seperti checkbox, orang akan mencoba mengkliknya dan bingung; ini
sudah terbukti membingungkan bahkan pada orang yang merancang sistemnya.

⚠️ **Badge Fase butuh legenda.** "● Fase 2" tanpa keterangan tak berarti apa-apa
bagi Executive. Sertakan legenda singkat sekali per halaman: Fase 1 = intake +
papan · Fase 2 = tujuh fase + gerbang · Fase 3 = sambungan Project Tracker +
penjadwalan Evaluasi.

## 5b. Sembilan hal yang WAJIB ada — bentuknya bebas

Ditulis sebagai **persyaratan, bukan gambar**, supaya idenya ikut tanpa
mengunci bentuknya. Tujuh di antaranya ditemukan pada percobaan desain pertama
dan terbukti menjawab tujuan brief ini — sayang kalau hilang, tapi rancang
ulang bentuknya kalau menemukan yang lebih baik.

1. **Panel ringkas untuk Executive, terbaca dalam 5 detik** — berapa item
   aktif, dipikul berapa orang, berapa yang macet **dan karena siapa**.
2. **Panel itu memisahkan sumber beban** — berapa dari formulir permintaan,
   berapa inisiatif IT sendiri. Ini yang membantah "IT senggang".
3. **Konsekuensi menambah permintaan ditulis eksplisit** — mis. *"tambah satu
   sekarang → antre di belakang 7 yang sudah di tangan IT"*. Kalimat inilah
   yang mengubah refleks jadi keputusan sadar.
4. **Tiap permintaan menampilkan posisinya di antrean** — *"antre di belakang
   N item"*.
5. **Posisi fase terlihat sebagai kemajuan, bukan sekadar label** — mis.
   *"UAT · 4/5"*, *"Uji Internal · 4/7"*. Head ingin tahu *sampai mana*, bukan
   cuma *sedang apa*.
6. **Relasi induk–anak terlihat di kartu** — mis. *"2 fitur di dalam"*. Modul
   besar berisi fitur; contoh nyata: Omnicare push/write berisi fitur
   pembayaran.
7. **Halaman Head menegaskan kapan bola ada di tangan pemohon** — bukan di IT.
   Termasuk: selama belum diisi, **hitungan hari berjalan di kolom pemohon**.
8. **Kerjaan yang lahir dari IT tampil berdampingan** dengan permintaan luar di
   papan yang sama, dengan penanda pembeda. ⛔ **Jangan dipisah ke tab lain** —
   Executive akan membuka tab permintaan saja lalu menyimpulkan IT senggang.
9. **Tombol mati menyebut apa yang kurang** — mis. *"Tetapkan jenjang dulu"*,
   bukan tombol abu-abu tanpa penjelasan. Ini pola P yang sudah berlaku di
   seluruh aplikasi.

Plus satu keadaan yang mudah terlupa: **tiket `ditunda` tetap tercatat dan
menjelaskan alasannya** (*"ditunda sampai komisi 70% selesai. Tidak sedang
dikerjakan, tetap tercatat."*) — ditunda bukan berarti hilang dari papan.

## 6. Angka nyata untuk mengukur rancangan

- **±5 permintaan modul besar per bulan**, masing-masing berisi beberapa fitur
  di dalamnya. Contoh nyata: modul *Omnicare push/write* berisi fitur
  *pembayaran*. Jadi papan harus menangani **induk–anak**, bukan daftar rata.
- **2 orang IT**, 5 cabang, ±20 role di aplikasi.
- **Backlog nyata saat ini ±10 item** — Alur Persetujuan, penyesuaian komisi
  70%, jatah cuti, Pusat Akun, integrasi Accurate, dan seterusnya. Papan hari
  pertama akan berisi sekitar segitu, bukan kosong.
- **Kerjaan IT sendiri ikut masuk papan.** Bulan ini saja: saklar Accurate,
  rekonsiliasi fee dokter, insiden rsync yang mematikan prod 6 menit, security
  hardening. **Tak satu pun datang dari formulir permintaan** — dan kalau
  mereka tak tampil, papan akan menunjukkan 5 item padahal IT memikul 15.
  Desain harus membedakan **"permintaan"** dan **"inisiatif IT"** tanpa
  membuat yang kedua terlihat kurang penting.

## 7. Satu kalimat yang menjelaskan produk ini

> Bukan alat mempercepat IT — alat **membuat beban IT terlihat**, supaya
> penambahan kerjaan jadi keputusan sadar, bukan refleks.

Kalau sebuah pilihan desain membuat beban itu **lebih sulit dilihat**, pilihan
itu salah, sebagus apa pun tampilannya.
