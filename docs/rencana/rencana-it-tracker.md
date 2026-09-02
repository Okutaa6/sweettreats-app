# IT Request Tracker — Alur, Status, & Field

> **Tahap: pemetaan.** Ditulis chat HUB 2026-09-01 dari hasil pembahasan Ekky.
> Dokumen ini jadi bahan untuk **dua** hal berikutnya: brief desain
> (claude.ai/design) dan rencana build penuh. Belum rencana koding.

## 1. Masalah yang diselesaikan

IT = **dua orang** (Ekky + Reza) dengan ±**5 permintaan modul besar per bulan**,
masing-masing berisi fitur di dalamnya. Keluhannya bukan kecepatan, tapi:

- permintaan datang tanpa pemohon ikut berpikir — semua diserahkan ke IT
- eksekutif tak melihat antrean, jadi menambah kerjaan begitu satu selesai
- tak ada tempat melihat **IT sedang memikul apa**

⚠️ **Beban proses di sini DISENGAJA, bukan ongkos yang harus ditekan.** Formulir
memaksa pemohon terlibat; antrean yang terlihat jadi alat tawar. Jangan
"disederhanakan" tanpa memahami itu.

## 2. Pengguna

| Peran | Boleh |
|---|---|
| **Head** (head-in-training) | mengajukan, mengisi UAT miliknya, melihat antreannya |
| **Executive** | melihat semua, ikut di Requirement & Design, menyetujui |
| **Konsultan** | sama seperti Executive — sengaja, mereka memegang akses lintas-departemen supaya head-in-training tak dibebani (keputusan Robi) |
| **IT — Ekky** | triase, bobot, prioritas, semua fase |
| **IT — Reza** | **hanya mengubah status item yang dia kerjakan sendiri, di fase Development.** Tak triase, tak menentukan prioritas |

⚠️ Reza tidak sekadar lapor lisan ke Ekky — kalau begitu, Ekky jadi juru ketik
status dan itu menambah beban yang justru mau dikurangi.

## 3. Status & transisi

```
                    ┌─────────── INSIDEN / HOTFIX ───────────┐
                    │  masuk langsung ke DEVELOPMENT,         │
                    │  formulir diisi menyusul                │
                    └────────────────────┬───────────────────┘
                                         │
       ┌──────── INISIATIF IT ───────────┤
       │  mulai dari ANALISIS,           │
       │  tanpa pemohon                  │
       └────────────────┬────────────────┘
                        │
[Head/Exec/Konsultan]   │
   isi Requirement      │
        ↓               │
      BARU → TRIASE → ANTRE → ANALISIS → DEVELOPMENT → UJI → UAT → GO-LIVE → EVALUASI → SELESAI
                │                                                   │
                ├→ DITOLAK                                          └→ (gagal) ROLLBACK → DEVELOPMENT
                └→ DITUNDA ──(dibuka lagi)──> ANTRE
```

### Jenjang menentukan fase yang dilewati

| Jenjang | Lewat fase |
|---|---|
| **S** — bug, tweak, <1 hari | ANTRE → DEVELOPMENT → SELESAI |
| **M** — fitur | + ANALISIS, UAT, GO-LIVE |
| **L** — modul / integrasi | ketujuhnya penuh |

**Jenjang ditentukan IT saat triase, bukan pemohon.** Pemohon mengisi
"prioritas menurut saya"; IT menetapkan prioritas nyata. **Dua kolom terpisah,
jangan disatukan.**

## 4. Gerbang — apa yang membukanya

Kesepakatan: **campur.** Fase awal dibuka kelengkapan + terima IT; fase akhir
wajib persetujuan manusia.

| Transisi | Syarat | Jenis |
|---|---|---|
| BARU → TRIASE | otomatis saat dikirim | — |
| TRIASE → ANTRE | jenjang + bobot + prioritas IT terisi | IT (manusia) |
| ANTRE → ANALISIS | IT menarik kerjaannya | IT |
| **ANALISIS → DEVELOPMENT** | **≥1 FR dengan `Kriteria Diterima` terisi** + ruang lingkup + komponen terdampak | **kelengkapan field** + terima IT |
| DEVELOPMENT → UJI | IT menandai siap | IT |
| UJI → UAT | semua test case berstatus, **nol temuan Major terbuka** | kelengkapan field |
| **UAT → GO-LIVE** | **pemohon/Head menyetujui** | **persetujuan manusia** |
| **GO-LIVE → EVALUASI** | checklist pra-deploy semua Ya/N-A + pemeriksaan pasca Go-Live Pass | **persetujuan manusia** |
| EVALUASI → SELESAI | evaluator mengisi, **atau tutup otomatis setelah 14 hari** | campur |

⚠️ **`Kriteria Diterima` adalah field terpenting di seluruh sistem.** Ia memaksa
"selesai" didefinisikan sebelum koding. Jangan dibuat opsional.

⚠️ **UAT yang didiamkan TIDAK boleh otomatis dianggap diterima.** Ia harus
**menyala di papan** — itu justru bukti yang dicari (kemacetan ada di pemohon,
bukan di IT).

## 5. "Menunggu siapa, sejak kapan" — jantung sistemnya

Field ini **tidak ada di template PDF**, dan justru inilah yang menjawab tujuan
utama. Dihitung, bukan diisi manual:

```
penanggung fase saat ini  +  lama sejak fase itu dimasuki
```

| Fase | Menunggu |
|---|---|
| BARU / TRIASE | IT |
| ANTRE | (tak menunggu siapa — memang antre) |
| ANALISIS | IT + pemohon (bersama) |
| DEVELOPMENT / UJI | IT |
| **UAT** | **pemohon / Head** |
| **GO-LIVE** | **Executive / Business Owner** |
| EVALUASI | evaluator |

Tampilkan di kartu, dan **beri warna memanas** setelah ambang (usul: 3 hari
kuning, 7 hari merah). Ini satu-satunya angka yang membuat "IT lambat" bisa
dibantah dengan data.

## 6. Nomor tiket

Format **`IT-2026-001`**, berurut, dipakai di ketujuh tahap **dan di commit
message**. Itu menutup jarak antara papan dan kode — riwayat git bisa dilacak
balik ke tiketnya, dan sebaliknya.

## 7. Ketujuh formulir — TIGA KELAS, jangan diperlakukan sama

Isinya diambil dari templat FRM-IT-01..07 (`Template_Development_Sweet_Treat.pdf`,
pemilik: Konsultan IT & Management). **Kata-kata boleh dimanusiakan; ISI tidak
boleh berkurang.**

Pembagian kelas ini menentukan sistemnya hidup atau mati:

| Kelas | Formulir | Sifat |
|---|---|---|
| **A** | 01 · 05 · 07 | **diisi pemohon** — sedikit field, bahasa manusia |
| **B** | 03 · 04 · 06 | **TERISI SENDIRI**, IT tinggal mengonfirmasi |
| **C** | 02 | **harus benar-benar dipikirkan** — tak bisa diotomatiskan |

⛔ **Kelas B adalah pembeda sistem dari PDF.** Ketiganya mendokumentasikan
pekerjaan yang **sudah** dilakukan IT, bukan menambah kerjaan. Kalau dirancang
sebagai formulir kosong untuk diketik ulang, **di situlah sistemnya mati.**

Bukti dari kerja nyata 1 Sep: FRM-IT-03 minta *branch/commit/script* — sudah ada
(`c28b665`, `2026_09_01_konsultan_dashboard_sales.sql`). FRM-IT-04 minta hasil
uji — sudah ada (`9 lulus 1 gagal`, `att:audit-role berat 0`). FRM-IT-06 minta
backup/rollback/pemeriksaan pasca — **checklist itu dijalankan manual hari itu**
(backup 48 MB, titik balik `761d7a7`, `curl` beranda).

### Formulir mana untuk jenjang mana

| Jenjang | Formulir |
|---|---|
| **S** — bug, tweak | 01 → catatan singkat (03 ringkas) → selesai |
| **M** — fitur | 01 → **02 RINGKAS** (hanya tabel FR + Kriteria Diterima + komponen terdampak) → 03 → 05 → 06 |
| **L** — modul, integrasi | ketujuhnya penuh |

⚠️ **02 penuh JANGAN diminta untuk jenjang M.** Tim dua orang akan tenggelam.

### Tanda tangan minimum (kesepakatan 1–2, bukan 3 seperti templat)

Templat meminta 3 paraf di hampir semua tahap. Di tim 2 orang itu berarti
**orang yang sama meneken kotak berbeda** — jadi dipangkas:

| Formulir | Persetujuan |
|---|---|
| 01 | pemohon (otomatis saat kirim) + **IT** terima/tolak |
| 02 | IT + **satu** Business Owner (Konsultan atau Executive) |
| **03, 04** | **IT sendiri — tanpa paraf pihak lain** |
| 05 | **pemohon saja** (jangan ditambah) |
| 06 | IT + Business Owner |
| 07 | evaluator |

---

### FRM-IT-01 — Requirement Request · **Kelas A** · diisi **pemohon**

**A. Identitas Pemohon** — ⚙️ **SEMUANYA OTOMATIS**, jangan ditanyakan:
nama · departemen/jabatan · cabang (dari `raw_branch`, bisa diubah kalau lintas
cabang) · kontak · tanggal pengajuan.
⚠️ Tapi **ekspor/cetak FRM-IT-01 tetap wajib menampilkannya terisi** — Konsultan
IT & Management harus mengenali dokumennya.

**B. Detail Kebutuhan** — ✍️ ini yang ditanyakan:
- **Jenis Permintaan** — Modul baru / Fitur baru / Perubahan fitur / Integrasi /
  Lainnya ← **memberi sinyal jenjang S/M/L saat triase**
- Nama Modul/Fitur
- **Prioritas Pemohon** — Rendah/Sedang/Tinggi ← **terpisah dari prioritas IT.
  Dua kolom berbeda, JANGAN disatukan**
- Target Waktu / tenggat dari luar
- Latar Belakang / Masalah Saat Ini
- Kebutuhan dan Hasil yang Diharapkan
- Pengguna dan Proses yang Terdampak
- **Lampiran** — screenshot / contoh laporan / alur proses / dokumen lain

**C. Review Awal** — diisi **IT**: Diterima untuk dianalisis · Perlu dilengkapi ·
Ditunda/Ditolak + catatan.

### FRM-IT-02 — Analysis & Design · **Kelas C** · diisi **IT** + Business Owner

**A. Ringkasan** — ref FRM-IT-01 (⚙️ otomatis) · nama modul (⚙️) · Business Owner ·
prioritas · target implementasi · tujuan pengembangan · **ruang lingkup
termasuk / TIDAK termasuk** · gambaran proses setelah pengembangan.

**B. Kebutuhan Fungsional** — tabel `FR-01..n`: kebutuhan/fungsi · prioritas ·
**KRITERIA DITERIMA**.
⚠️ **Field terpenting di seluruh sistem.** Memaksa "selesai" didefinisikan
sebelum koding. **Jangan opsional** — minimal 1 baris terisi untuk membuka
gerbang ke Development.

**C. Desain Teknis Ringkas** — komponen diubah (Aplikasi/Database/API/Laporan/
Hak akses) · modul/tabel/API terkait · hak akses pengguna · dampak ke cabang ·
**ketergantungan → RELASI ke tiket lain, bukan teks bebas** · deskripsi solusi.

**D. Estimasi & Risiko** — **estimasi hari kerja (INTERNAL IT SAJA, tak tampil ke
Executive)** · PIC developer · risiko utama · rencana mitigasi.

### FRM-IT-03 — Development Record · **Kelas B** · ⚙️ terisi sendiri

**A. Informasi** — ⚙️ ref 02 · nama modul · developer · tanggal mulai (saat fase
dimasuki) · tanggal selesai · versi/build.

**B. Catatan Implementasi** — 5 komponen: Aplikasi/Source Code · Database ·
API/Integrasi · Hak Akses/Konfigurasi · Dokumentasi. IT menambah keterangan.

**Detail Teknis** — ⚙️ **branch/commit/tag** dan **script/migration file**
**diambil dari git lewat nomor tiket di commit message**. IT tinggal
mengonfirmasi.

⚠️ Bagian **C. Developer Testing** templat **DILEBUR ke FRM-IT-04** — di tim 2
orang itu formulir yang sama diisi dua kali.

### FRM-IT-04 — Internal Testing · **Kelas B** · ⚙️ sebagian terisi

**A. Informasi** — ⚙️ ref 03 · nama · tester · tanggal · environment/build.

**B. Test Case** — `TC-01..n`: skenario · hasil diharapkan · aktual · Pass/Fail.
**C. Temuan** — temuan · **Minor/Major** · PIC · status.
**Ringkasan** — ⚙️ dihitung: jumlah · pass · fail · temuan terbuka.

Ditambah 5 pemeriksaan dari 03C: fungsi utama · validasi & error handling ·
database/API · hak akses · **fitur lama terkait tetap berjalan**.

⛔ **Gerbang ke UAT: nol temuan Major terbuka.**

### FRM-IT-05 — UAT · **Kelas A** · diisi **pemohon / End User**

**A. Informasi** — ⚙️ ref 04 · nama · departemen/cabang · penguji · tanggal · build.
**B. Skenario UAT** — proses/skenario bisnis · hasil diharapkan · **Sesuai/Tidak**
· catatan. **Masukan End User.**
**Keputusan** — Disetujui Go Live · Disetujui dengan Catatan · Perlu Perbaikan.

⚠️ **Ini gerbang manusia.** Didiamkan = **menyala di papan**, bukan otomatis
diterima.

### FRM-IT-06 — Go Live Checklist · **Kelas B** · ⚙️ terisi sendiri

⭐ **Formulir paling matang di seluruh templat** — cocok dengan praktik nyata
kita. **Pertahankan apa adanya.**

**A. Informasi Release** — ⚙️ ref 05 · nama · versi/release · tanggal & waktu Go
Live · PIC deployment · target cabang.

**B. Checklist Pra-Deploy** — UAT disetujui (⚙️) · **backup tersedia** ·
file/script siap (⚙️ dari git) · akses & konfigurasi siap · panduan pengguna ·
**rencana rollback siap**.

**C. Catatan Deployment** — waktu · aktivitas · PIC · hasil.

**D. Pemeriksaan Pasca Go-Live** — aplikasi dapat diakses · fungsi utama ·
data/integrasi · hak akses · cabang target terverifikasi.
⚠️ Tambahkan pelajaran nyata: **`curl` beranda cek 200 + URL akhir** —
`laravel.log` nol galat TIDAK berarti sehat (insiden rsync 29 Agu).

**Kendala / Tindakan Pemulihan** · **Hasil Monitoring Awal** ·
keputusan: Berhasil · dengan Monitoring · **Rollback**.

### FRM-IT-07 — Evaluation & Enhancement · **Kelas A** · diisi **evaluator**

**A. Informasi** — ⚙️ ref 06 · nama · periode evaluasi · departemen/cabang ·
evaluator.
**B. Hasil** — kesesuaian · kemudahan · stabilitas · dampak ke proses kerja ·
manfaat yang dirasakan.
**C. Temuan & Usulan** — **Bug / Enhancement** · deskripsi · prioritas R/S/T ·
tindak lanjut.
**Rencana Tindak Lanjut** — Tidak ada · Perbaikan Teknis ·
**Masuk Requirement Request Baru** ← ⚙️ ini **membuat tiket baru otomatis**,
menutup lingkarannya.

⚠️ **Wajib punya pemicu terjadwal** (usul: 14 hari sesudah Go Live). Tanpa itu
tahap ini tak akan pernah terjadi dan cuma jadi beban mati.

## 8. Hubungan dengan yang sudah ada

- **Project Tracker** (`feature/project-tracker`, `sys_project → task → todo`,
  Fase A selesai Juli, belum naik). **Tetap terpisah** — audiensnya seluruh
  office/ABG, ini khusus Head/Exec/Konsultan/IT.
  **Sambungannya**: tiket yang lolos gerbang ANALISIS **melahirkan project** di
  tracker. Modul → project, fitur di dalamnya → task. Fase tetap di sini,
  eksekusi harian di sana. *(Fase 3 build.)*
- **Papan Gemini** (`log-update.md` + `status-proyek.md` di Google Drive) —
  keputusan Ekky: **dipensiunkan** setelah tracker jalan. Jangan merawat dua
  papan; itu yang membuat catatan basi.

## 9. Fase build

Desain di claude.ai/design mencakup **ketujuh fase** (mockup penuh = alat
komunikasi ke eksekutif). **Build-nya dipecah**, dan tiap layar di mockup diberi
**badge Fase 1/2/3** supaya mockup jadi peta jalan, bukan janji.

| | Isi | Kenapa |
|---|---|---|
| **Fase 1** | formulir permintaan · triase · papan antrean · "menunggu siapa" | **sudah memenuhi tujuan utama**: pemohon berpikir, eksekutif melihat antrean |
| **Fase 2** | ketujuh fase + gerbang + form per tahap | tata kelola |
| **Fase 3** | sambungan Project Tracker · penjadwalan Evaluasi · nomor tiket di commit | penyempurnaan |

Fase 1 saja sudah menghentikan "semena-mena menambah kerjaan" — dan kalau
ternyata IT sendiri malas mengisinya, itu ketahuan **sebelum** tujuh fase
dibangun.

## 10. Yang harus masuk sistem, bukan cuma permintaan luar

⚠️ **Kerjaan yang lahir dari IT sendiri WAJIB punya tiket** (penanda "Inisiatif
IT"). Contoh nyata bulan ini yang tak satu pun datang dari formulir: saklar
Accurate, rekonsiliasi fee dokter, insiden rsync yang mematikan prod, security
hardening, koreksi checklist.

Kalau papan hanya menampilkan permintaan luar, eksekutif melihat **5 item**
padahal IT memikul **15** — dan menyimpulkan IT senggang. Itu **kebalikan** dari
tujuan sistem ini.

Sama berlaku untuk kerjaan yang Reza mulai sendiri.

## 11. Keputusan yang sudah diambil (jangan dibuka ulang)

1. Beban proses disengaja — **bukan** untuk ditekan.
2. Tanda tangan **1–2 saja** per tahap, kecuali procurement.
3. Jenjang S/M/L, ditentukan **IT**.
4. Gerbang **campur**: awal = kelengkapan field, akhir (UAT & Go-Live) = manusia.
5. UAT didiamkan → **menyala**, bukan otomatis diterima.
6. Estimasi hari **internal IT saja**; publik lihat ukuran S/M/L.
7. Nomor tiket `IT-2026-001` dipakai sampai ke commit message.
8. Reza mengubah status miliknya sendiri di fase Development.
9. Terpisah dari Project Tracker, tapi bersambung di fase Development.
10. Papan Gemini dipensiunkan.
11. Backlog berjalan diisi manual setelah sistem jadi.

## 12. Yang tadinya terbuka — SUDAH DIPUTUSKAN 2026-09-02 saat melihat desain

Ketiganya sengaja dibiarkan terbuka sampai ada layar untuk dilihat. Sekarang
terkunci — **jangan dibuka ulang**.

**Ambang "menunggu" memanas: 3 hari kuning · 7 hari merah — TAPI hanya untuk
yang menunggu DI LUAR IT** (pemohon / Executive). Yang menunggu IT tidak ikut
memanas keras: modul jenjang L wajar makan berminggu-minggu, dan kalau semua
merah sinyalnya mati. ⚠️ Ini yang membuat warna berarti sesuatu.

**Evaluator default fase 7: pemohon, bisa dialihkan.** Pertanyaan tahap 7 adalah
*"masalah yang kamu tulis di awal masih ada?"* — itu milik orang yang menulisnya.
Bisa dialihkan karena orang pindah posisi dan modul besar melayani banyak pihak.

**Head melihat: miliknya + RINGKASAN beban IT** — bukan semua tiket.
Dua pilihan lain ditolak dengan alasan:
- *hanya miliknya* → Head tahu posisinya tapi tak melihat beban, jadi tetap
  bisa mendesak tanpa gambaran;
- *semua tiket read-only* → Head melihat isi permintaan departemen lain, dan itu
  **bertentangan dengan keputusan Robi** (konsultan yang memegang pandangan
  lintas-departemen justru supaya head-in-training tak dibebani).

Yang dipilih memberi seluruh manfaatnya — Head melihat *"IT sedang memikul 11
item (8 permintaan + 3 inisiatif IT)"* dan *"antre di belakang 8"* — tanpa
melihat isi permintaan orang lain.

### Dua keputusan tambahan dari ronde desain

**Zona warna: Grafit** (zona yang sudah ada di `peta-tema.md`), tanpa aksen
khusus. Bukan menambah zona ke-4 — menambah dialek warna untuk satu halaman
adalah awal sistem warna yang pecah.

**Saklar peran = ALAT DEMO, bukan fitur produk.** Berguna saat Ekky presentasi
ke Executive (berpindah sudut pandang Ekky/Reza/Head/Executive). ⛔ **Jangan
dibangun sebagai fitur** — di produk, "lihat sebagai" menyiratkan menyamar dan
soal hak akses. Di mockup ia alat navigasi; bedakan visualnya dari chrome
aplikasi supaya tak ada yang mengira harus dibangun.

## 13. Yang masih terbuka

- Tampilan **Ekspor FRM-IT-01** belum pernah dilihat. Tombolnya sudah ada di
  layar detail, tapi isinya belum. Ini satu-satunya layar yang membuktikan
  cerita tata kelola ke Konsultan & Management — tanpa itu mereka melihat
  kanban yang tak mereka kenali.
- Ide yang **ditampung, belum diputuskan** (usul chat HUB, ditunda Ekky):
  - **estimasi tanpa satuan hari** — ganti dengan ukuran + throughput historis,
    karena estimasi hari mengandaikan hari itu milik IT (padahal ada meeting &
    tech support cabang). Tampilkan *"belum cukup data"* sebelum 3 bulan.
  - **fase 7 diganti satu pertanyaan** — *"masalah yang kamu tulis di awal masih
    ada?"* ditampilkan berdampingan dengan tulisan pemohon sendiri di FRM-IT-01,
    menggantikan penilaian kepuasan yang kemungkinan besar diisi asal-asalan.
  - **`Jenis Permintaan` mengusulkan jenjang otomatis** (Integrasi/Modul → L,
    Fitur → M, Perubahan → S), IT tinggal mengoreksi.
  - **deteksi permintaan mirip** saat pemohon mengetik judul.
  - **biaya menunggu ikut tercetak di dokumen akhir** — templat mencatat
    keputusan tapi tak pernah mencatat berapa lama tertahan dan pada siapa.
  - **FRM-IT-02 sebagian sebagai dokumen (teks bebas)**, bukan formulir —
    hanya tabel FR + Kriteria Diterima yang tetap terstruktur. Formulir buruk
    untuk berpikir. ⚠️ Ini menyimpang paling jauh dari templat; Konsultan
    mungkin menginginkan keseragaman.
