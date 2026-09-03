# IT Request Tracker — Alur, Status, & Field

> **Tahap: SIAP DIBANGUN.** Ditulis chat HUB 2026-09-01, dilengkapi rencana
> build (§14) 2026-09-02 sesudah desain selesai.
> Dokumen ini sumber kebenaran untuk chat build. Paket desain ada di
> `Downloads\design_handoff_it_tracker\` — `it-store.js` di situ adalah
> **implementasi rujukan** mesin fasenya, bukan sekadar data dummy.

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

## 14. Rencana Build

> Ditulis chat HUB 2026-09-02 sesudah desain selesai. **Ini rencana, bukan
> koding** — chat build yang mengerjakan. Paket desain:
> `C:\Users\Ekky13\Downloads\design_handoff_it_tracker\`

### 14.1 Patokan yang sudah ada — JANGAN disusun ulang dalam prosa

**`design_handoff_it_tracker/it-store.js` adalah implementasi rujukan mesin
fasenya**, bukan sekadar data dummy. Terjemahkan ke PHP, jangan tafsirkan ulang:

| Fungsi | Isinya |
|---|---|
| `pathFor(j)` | S → `[REQ, DEV]` · M → `[REQ, ANALISIS, DEV, UAT, GOLIVE]` · L → ketujuhnya |
| `RANK` | urutan fase untuk perbandingan & posisi antrean |
| `waitOf(t)` | menunggu siapa · berapa hari · **`heat` hanya bila `luar`** |
| `queuePos(t, all)` | di belakang berapa item |
| `gateInfo(t)` | **syarat tiap transisi + daftar `missing`** — inti sistem |
| `PRA` / `PASCA` | checklist Go-Live pra-deploy & pasca |

Aturan pemanasan, tertulis satu baris dan tak boleh diubah:

```js
const heat = luar ? (days >= 7 ? 2 : days >= 3 ? 1 : 0) : 0;
```

⚠️ README paket itu menulis *"Belum termasuk: layar triase penuh, Formulir
Analisis, Checklist Go-Live"* — **baris itu BASI**. Ketiganya ada di HTML dan
di `screenshots/03` & `05`. Jangan membangunnya dari nol.

### 14.2 Skema — 6 tabel

Konvensi proyek: **SQL manual, bukan migration**. Idempoten
(`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS` lewat
`information_schema`), prefiks `it_`.

**`it_ticket`** — satu baris per tiket
`id` · `nomor` (`IT-2026-001`, unik) · `judul` · `sumber` (`permintaan`/`inisiatif`) ·
`pemohon_user_id` (NULL untuk inisiatif IT) · `raw_branch_id` ·
`jenis_permintaan` · `jenjang` (S/M/L, NULL sebelum triase) · `bobot` (1–5) ·
`prioritas_pemohon` (Rendah/Sedang/Tinggi) · `prioritas_it` (P1–P3) ·
`pic_user_id` · `fase` · `fase_since` · `induk_id` (self-FK, relasi modul→fitur) ·
`evaluator_user_id` · `alasan_parkir` · timestamps.

⚠️ **`prioritas_pemohon` dan `prioritas_it` WAJIB dua kolom.** Menyatukannya
membatalkan keputusan §11 dan menghapus seluruh gunanya.

**`it_ticket_fase`** — riwayat fase. **Tabel paling penting kedua setelah
`it_ticket`.**
`ticket_id` · `fase` · `masuk_at` · `keluar_at` (NULL = sedang berjalan) ·
`oleh_user_id` · `pihak_menunggu` (`it` / `pemohon` / `executive` / `evaluator`).

Dari tabel ini lahir tiga hal sekaligus:
1. `waitOf().days` = `NOW() − masuk_at` baris berjalan
2. **biaya menunggu** yang bisa dicetak di dokumen akhir (§13, belum diputuskan)
3. jejak audit yang dicari Konsultan

⛔ **Jangan menyimpan "berapa hari menunggu" sebagai kolom angka.** Ia harus
**dihitung** dari `masuk_at`. Angka yang disimpan akan basi diam-diam — dan
seluruh kredibilitas papan bergantung pada angka ini benar.

**`it_form`** — payload naratif per formulir
`ticket_id` · `kode` (`FRM-IT-01`..`07`) · `payload` JSON · `dikonfirmasi_at` ·
`dikonfirmasi_oleh`. Unik (`ticket_id`,`kode`).
Untuk field prosa: latar belakang, ruang lingkup, deskripsi solusi, masukan
end user, hasil evaluasi.

**`it_fr`** — baris Kebutuhan Fungsional (FRM-IT-02 §B)
`ticket_id` · `kode` (`FR-01`..) · `kebutuhan` · `prioritas` ·
**`kriteria_diterima`** · `urutan`.
⚠️ **Dinormalkan, bukan JSON — karena gerbang membacanya**
(`≥1 FR dengan Kriteria Diterima terisi`).

**`it_test_case`** — TC (FRM-IT-04 §B)
`ticket_id` · `kode` · `skenario` · `diharapkan` · `aktual` · `status`.

**`it_temuan`** — temuan uji (FRM-IT-04 §C)
`ticket_id` · `deskripsi` · `tingkat` (Minor/Major) · `pic_user_id` · `status`.
⚠️ Gerbang UJI→UAT membaca ini: **nol temuan Major terbuka**.

**Nomor tiket**: ikuti pola yang sudah ada di `RopService::nomorBaru()` —
counter per periode, bukan `MAX(id)+1` (balapan).

### 14.3 Kelas B — bagaimana FRM-IT-03 terisi dari git

⛔ **JANGAN shell `git log` dari request web.** Lambat, dan permukaan
keamanan yang tak perlu.

Pola yang cocok dengan proyek ini: **perintah artisan + tabel**, seperti
`accurate:push-daily` dan `att:reconcile`.

- `it_git_ref` — `ticket_nomor` · `branch` · `commit_sha` · `subject` · `commit_at`
- `php artisan it:sync-git` — jalankan `git log --all --grep='IT-20[0-9][0-9]-[0-9]+'`,
  urai nomor tiket dari subject, isi tabel. Idempoten (unik pada `commit_sha`).
- Jadwalkan harian; **plus tombol "segarkan" di layar** supaya IT tak menunggu cron.

Layar FRM-IT-03 lalu membaca `it_git_ref` dan menampilkannya **sudah terisi**
dengan tombol *"Benar, ini yang kukerjakan — konfirmasi"*, yang mengisi
`it_form.dikonfirmasi_at`. Gerbang DEV→UJI membaca kolom itu.

FRM-IT-04 dan 06 belum punya sumber otomatis — **isi manual dulu**, tapi
rancang layarnya dengan bentuk "sudah terisi + konfirmasi" yang sama, supaya
sumber otomatis bisa disambungkan belakangan tanpa mengubah UI.

### 14.4 Akses

Segmen URL **`it-tracker`** (bukan di bawah `finance` atau `absensi`).
⚠️ **Butuh node `access_path` ber-`link='it-tracker'`** — tanpa itu RBAC
**memantulkan SEMUA orang ke beranda, bukan 403**, dan gejalanya menipu.
Ikuti pola SQL `2026_08_28_komisi_dokter_access.sql`: `INSERT IGNORE`,
grant **by link bukan by id node**, dan induk menu ikut di-grant
(`buildTree` hanya menyusun anak di bawah induk yang dimiliki group).

| Role | Boleh |
|---|---|
| Head (Marketing/Finance) | ajukan · isi UAT & Evaluasi miliknya · lihat **miliknya + ringkasan beban** |
| Executive · Konsultan | lihat semua · ACC Go-Live · terlibat di Requirement & Analisis |
| Head IT (Ekky) | semua, termasuk triase |
| IT (Reza) | ubah status **tiket yang dia PIC**, hanya di fase DEV/UJI. Tak triase |

Gerbang tulis **di controller**, bukan sekadar menyembunyikan tombol.

### 14.5 Fase build

| | Isi | Selesai kalau |
|---|---|---|
| **Fase 1** | `it_ticket` + `it_ticket_fase` · formulir Requirement · triase · Papan Antrean · "menunggu siapa" · panel beban | Ekky & Reza memakainya untuk kerjaan nyata 2 minggu |
| **Fase 2** | ketujuh fase + `gateInfo` + `it_fr`/`it_test_case`/`it_temuan` · Kelas B + `it:sync-git` · ekspor FRM-IT-01/02/06 | backlog nyata jalan penuh dari Requirement sampai Go Live |
| **Fase 3** | sambungan Project Tracker (`sys_project`) · penjadwalan Evaluasi · nomor tiket di commit message | — |

⚠️ **Fase 1 harus benar-benar dipakai sebelum Fase 2 dimulai.** Sejarah proyek
ini: Project Tracker Fase A selesai Juli lalu berhenti; Pusat Akun ACC Juli
belum dibangun. Sistem proses yang tak dipakai lebih buruk daripada tak ada.

### 14.6 Uji terima

**Mesin fase**
1. Jenjang S melewati 2 fase, M 5, L 7 — sesuai `pathFor()`.
2. Mengubah jenjang saat triase **mengubah jumlah fase** tiket itu.
3. Gerbang ANALISIS→DEV **menolak** tanpa ≥1 `kriteria_diterima`, dan pesannya
   **menyebut field yang kurang** (bukan tombol abu-abu diam).
4. Gerbang UJI→UAT **menolak** selama ada temuan `Major` berstatus terbuka.
5. Gerbang UAT→GOLIVE **hanya** terbuka oleh pemohon, bukan IT.
6. `it_ticket_fase` bertambah **tepat satu baris** tiap perpindahan, dan baris
   sebelumnya terisi `keluar_at`.

**"Menunggu siapa"**
7. Tiket di UAT 4 hari → `luar=true`, `heat=1` (kuning).
8. Tiket di UAT 8 hari → `heat=2` (merah).
9. Tiket di DEV 30 hari → **`heat=0`**. ⚠️ **Kendali negatif paling penting** —
   kalau ini merah, aturannya salah diterjemahkan.
10. Angka hari **dihitung dari `masuk_at`**, bukan dibaca dari kolom.

**Akses**
11. Head membuka papan → melihat **ringkasan beban**, **tidak** melihat isi
    permintaan departemen lain.
12. Reza mencoba triase → **ditolak di controller**, bukan cuma tombolnya hilang.
13. Role tanpa grant membuka `/it-tracker` → terpental. ⚠️ Periksa **URL akhir**,
    bukan cuma kode HTTP — `curl -L` melaporkan 200 setelah mengikuti pentalan.

**Kelas B & ekspor**
14. `it:sync-git` idempoten — dijalankan 2× tak menggandakan `it_git_ref`.
15. FRM-IT-03 tampil **sudah terisi** branch/commit; tombol konfirmasi mengisi
    `dikonfirmasi_at`.
16. Ekspor FRM-IT-01 memuat **A. Identitas Pemohon terisi** walau layar tak
    pernah menanyakannya.

**Kendali negatif**
17. Fitur ini **tidak menyentuh** tabel lama mana pun. Cacah baris `users`,
    `att_day`, `rop_requests`, `omnicare_commission` **sama sebelum & sesudah**.
18. `access:rebuild` sesudah SQL → **mtime berkas `group_access` berubah**.

### 14.7 Jebakan yang berlaku

- ⛔ **Rute bersegmen-1 baru wajib punya node `access_path`** — kalau tidak,
  RBAC memantulkan semua orang ke beranda (302), **bukan 403**. Uji "nol 403"
  akan lulus palsu.
- ⛔ **Deploy yang menyentuh `public/assets/**` WAJIB diikuti rsync — DIBATASI
  ke `assets/`.** `rsync` seluruh `public/` **mematikan prod** (insiden 29 Agu,
  6 menit): `public_html` prod itu salinan sejajar, `index.php`-nya sengaja
  berbeda.
- ⚠️ **`laravel.log` nol galat TIDAK berarti sehat.** Gagal sebelum Laravel
  hidup tak tercatat di sana. Wajib `curl` beranda cek **200 + URL akhir**.
- ⚠️ Ikon **FontAwesome 5.8.2**; nama gaya FA6 jadi kotak kosong.
- ⚠️ **`deploy.sh` tidak menjalankan SQL.** Jalankan manual, cek keadaan
  sesudahnya (bukan exit code — pipa menyembunyikan kegagalan mysql).
- ⚠️ Kalau `git checkout -B` **MENOLAK**, itu **pengaman** — `reset --hard`
  menimpa berkas untracked tanpa peringatan. Berhenti dan periksa.

### 14.8 Lingkungan kerja

**Lokal — JALAN, TAPI DENGAN SATU RANJAU BESAR (diukur 2 Sep 21:00-21:10):**
PHP 8.2.12 · MariaDB 10.4.32 via XAMPP · DB `sweettreats` **136 tabel**
(bukan 129 — angka lama itu keliru) · Laravel boot, kueri data biasa aman.

⛔ **`php artisan db:show` MEMBUNUH MariaDB lokal. Jangan jalankan.**
Kueri yang memintanya — `information_schema.tables` dengan kolom
`data_length`/`index_length` untuk seluruh skema `sweettreats` — membuat
`mysqld.exe` **segfault dan mati**, meninggalkan `mysqld.dmp`. Terjadi
**7+ kali** dalam sepuluh menit. Bukan teori: tiap kali servernya benar-benar
tumbang dan harus dinyalakan ulang.

Yang sudah **dikesampingkan dengan pengukuran**, jadi jangan diulang:
- **Bukan satu tabel rusak.** Tersangka `sys_project_todo` sehat sendirian
  (24 baris, stats terbaca), dan kueri yang **mengecualikannya tetap crash**.
- **Bukan ambang jumlah tabel.** 126 tabel crash, tapi **130 tabel selamat**.
  Non-monoton → **flaky**, bukan deterministik. (Pencarian biner menghasilkan
  jawaban yang terdengar meyakinkan dan **salah** — kendali negatiflah yang
  menangkapnya. Kalau menduga "tabel X rusak", uji kecualikan-X dulu.)
- **Bukan statistik persisten InnoDB.** `innodb_stats_persistent=0` +
  `innodb_stats_auto_recalc=0` → tetap **crash 3 dari 3**.

**Yang aman dan sudah dibuktikan:** `SELECT` biasa · `count(*)` per tabel ·
`information_schema` **tanpa** kolom ukuran · skema `rblas_db` (66 tabel,
sama-sama InnoDB) · migrasi/DDL biasa. Jadi **membangun IT Tracker di lokal
tetap layak** — cukup jauhi perintah dan panel GUI yang menampilkan *ukuran*
tabel se-skema.

⚠️ **`mysql_error.log` TIDAK mencatat crash ini** — berkas itu terakhir ditulis
28 Agustus, karena mysqld dijalankan mode `--console`. Ini persis jebakan yang
sama dengan `laravel.log` nol galat di prod: **log sepi bukan bukti sehat.**
Bukti crash ada di `mysqld.dmp` (stempel waktunya diperbarui tiap crash).

- ⚠️ **MariaDB lokal 10.4 vs prod 11.8.8** — SQL yang lolos lokal belum tentu
  lolos prod. Uji ulang SQL-nya di dev sebelum prod.
- ⛔ **JANGAN sentuh `rblas_db`** — tablespace-nya punya galat InnoDB
  *"LSN in the future"* sejak 28 Agu. (Catatan: justru `rblas_db` yang **lolos**
  uji stats; galat lamanya beda perkara dari crash di atas.)
- ⛔ **JANGAN pakai `Downloads\sweettreats` langsung** — working copy bersama,
  masih menyimpan berkas Alur Persetujuan yang belum di-commit. Buat worktree
  sendiri, **salin** `vendor/` dan `.env` (jangan symlink).
- ⚠️ `.env` lokal ber-`APP_ENV=local`, dan sebagian fitur destruktif berperilaku
  "local = boleh". Jangan pernah menyalin `.env` itu ke lingkungan lain.

**Dev** (`dev.sweettreats.id`) untuk uji terima sebelum prod. Login `/signin`.
Dev di-install `--no-dev` → **phpunit TAK ADA**; `composer install` dulu, lalu
kembalikan `--no-dev --optimize-autoloader`. phpunit dev mati di >±17 uji
ber-DB per proses → **jalankan PER KELAS**.

**Prod** hanya kalau Ekky menyebut "prod" eksplisit.

### 14.9 Prompt chat build

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini MEMBANGUN IT Request
Tracker — Fase 1 dulu. Lokal & dev; prod hanya kalau Ekky menyebut "prod".

BACA DULU, rencananya lengkap — JANGAN menyusun ulang:
· docs/rencana/rencana-it-tracker.md         <- dokumen utama, paragraf 14 = rencana build
· docs/brief-desain/design-brief-it-tracker.md
· C:\Users\Ekky13\Downloads\design_handoff_it_tracker\   <- mockup + it-store.js + screenshot

it-store.js itu IMPLEMENTASI RUJUKAN mesin fase (pathFor, RANK, waitOf,
queuePos, gateInfo, PRA/PASCA). Terjemahkan ke PHP, jangan tafsirkan ulang.
README paket itu menulis triase/Analisis/Go-Live "belum termasuk" — BASI,
ketiganya ada di HTML dan screenshot.

Yang paling mudah salah:
1. prioritas_pemohon dan prioritas_it WAJIB dua kolom terpisah
2. "berapa hari menunggu" DIHITUNG dari it_ticket_fase.masuk_at, JANGAN
   disimpan sebagai angka — angka tersimpan akan basi diam-diam
3. heat = luar ? (days>=7?2:days>=3?1:0) : 0 — yang menunggu IT TIDAK memanas.
   Kendali negatifnya: tiket 30 hari di DEV harus heat=0
4. Kelas B (FRM-IT-03) tampil SUDAH TERISI dari git lewat perintah artisan
   it:sync-git + tabel it_git_ref. JANGAN shell git log dari request web
5. rute segmen-1 baru WAJIB punya node access_path, kalau tidak RBAC
   memantulkan semua orang ke beranda (302), bukan 403 — uji "nol 403" lulus palsu

Uji terima paragraf 14.6, 18 poin, termasuk kendali negatif: fitur ini tidak
boleh mengubah cacah baris tabel lama mana pun.

Lokal jalan (PHP 8.2.12 + MariaDB 10.4.32 XAMPP, DB sweettreats 136 tabel),
TAPI: JANGAN jalankan `php artisan db:show` - kueri ukuran tabel se-skema
membuat mysqld SEGFAULT (terjadi 7+ kali 2 Sep). Bukan satu tabel rusak, bukan
ambang jumlah, dan innodb_stats_persistent=0 tidak menolong - sudah diukur
semua, jangan diulang. SELECT biasa, count(*), dan migrasi AMAN.
mysql_error.log TIDAK mencatat crash ini (mode --console) - log sepi bukan
bukti sehat, sama seperti laravel.log di prod.
JANGAN sentuh rblas_db. JANGAN pakai
Downloads\sweettreats langsung — working copy bersama dengan berkas belum
di-commit; buat worktree sendiri, SALIN vendor/ dan .env.

Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```

### 14.10 Prompt chat build — FASE 2a (jalan sekarang, tak menunggu desain)

> Ditulis 3 Sep. **Fase 2 dipecah dua.** Pemisahnya satu pertanyaan: *siapa
> yang mengetik?*

| | Isi | Desain | Beban ketik baru |
|---|---|---|---|
| **2a** | FRM-IT-06 Checklist Go Live · ekspor FRM-IT-01 · `it:sync-git` + `it_git_ref` | ✅ sudah ada (paket ronde 1, screenshot 03 & 06, ber-badge FASE 2) | **nol** — semuanya Kelas B, terisi sendiri |
| **2b** | tabel FR (FRM-IT-02) · test case & temuan (FRM-IT-04) · skenario UAT (FRM-IT-05) | ❌ menunggu `design-brief-it-tracker-ronde2.md` | manusia mengetik |

Urutan ini **disengaja, bukan sekadar mengisi waktu**: ekspor FRM-IT-01 memaksa
infrastruktur ekspor dibangun sekali, lalu 02/04/06 tinggal menumpang. Dibalik,
mekanisme yang sama lahir tiga kali dalam bentuk berbeda. 2a juga duduk di
GOLIVE dan lintas-fase, sedangkan 2b di ANALISIS/UJI/UAT — kopelnya rendah,
jadi tak ada risiko kerja ulang saat desain 2b turun.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini melanjutkan IT Request
Tracker ke FASE 2a. Fase 1 sudah LIVE DI DEV dan dipakai Ekky & Reza.
Lokal & dev; prod HANYA kalau Ekky menyebut "prod".

BACA DULU:
· docs/rencana/rencana-it-tracker.md    <- paragraf 14, terutama 14.3 dan 14.10
· memori project_it_tracker
· Downloads\design_handoff_it_tracker\  <- screenshot 03 & 06 = acuan visual 2a

RUANG LINGKUP 2a, hanya tiga:
1. FRM-IT-06 Checklist Go Live  — acuan 03-checklist-golive.png
2. Ekspor FRM-IT-01             — acuan 06-ekspor-frm-it-01.png
3. it:sync-git + tabel it_git_ref (Kelas B)

BATAS KERAS — JANGAN dikerjakan di chat ini:
tabel FR / 02-ringkas · test case · temuan · skenario UAT.
Keempatnya SEDANG DIDESAIN (docs/brief-desain/design-brief-it-tracker-ronde2.md).
Jangan mengarang bentuknya. Fase 1 kemarin sudah terlanjur melewati batas fase
— membangun PhaseEngine tujuh fase yang menurut 14.5 milik Fase 2. Hasilnya
bagus, tapi jangan diulang di sini: yang belum didesain justru bagian dengan
risiko adopsi tertinggi, dan kalau terlanjur dikoding, versi koding yang menang.
Kalau sebuah gerbang butuh data dari formulir 2b, BIARKAN gerbang itu tertutup
dengan pesan yang jelas. Jangan bikin formulir daruratnya.

YANG PALING MUDAH SALAH:
1. Kelas B = TERISI SENDIRI, IT hanya mengonfirmasi. Kalau FRM-IT-06 jadi
   formulir kosong untuk diketik ulang, sistemnya mati dalam dua minggu — itu
   seluruh alasan fitur ini dibangun, bukan detail gaya.
2. it:sync-git itu perintah artisan. JANGAN shell `git log` dari request web.
3. Checklist pasca Go-Live WAJIB memuat "curl beranda 200 + URL akhir".
   laravel.log nol galat TIDAK berarti sehat — prod pernah mati 6 menit dengan
   log bersih, karena gagalnya sebelum Laravel boot.
4. Ekspor FRM-IT-01 menampilkan identitas pemohon TERISI dengan nama bagian
   resmi templat, walaupun layarnya tak pernah menanyakannya. Konsultan IT &
   Management harus mengenali dokumennya.

UJI TERIMA: paragraf 14.6 nomor 14-16, plus dua kendali negatif —
cacah baris tabel lama tak berubah, dan tiket 30 hari di DEV tetap heat=0.

LINGKUNGAN:
· Branch feat/it-tracker, worktree Downloads\sweettreats-it-tracker.
  Cek HEAD sendiri (git log -1); jangan percaya hash yang tertulis di dokumen,
  cepat basi.
· dev = SATU working copy bersama DAN SEDANG DIPAKAI ORANG. Di sana ada
  untracked milik chat Alur Persetujuan. Jalankan
  `git status --porcelain | grep "^??"` DULU. Pindah branch pakai checkout
  TANPA reset --hard — reset menimpa untracked tanpa peringatan, sedangkan
  checkout MENOLAK; penolakan itu pengaman, jangan diakali.
· JANGAN `php artisan db:show`, JANGAN Schema::hasTable() — keduanya membunuh
  mysqld lokal (segfault, dan mysql_error.log tak mencatatnya). Pakai
  SHOW TABLES LIKE.
· phpunit mati di lebih dari sekitar 17 uji ber-DB per proses → jalankan PER KELAS.
· Naik dev: SQL → php artisan access:rebuild → deploy penuh (ada class baru)
  → rsync CSS.

Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```

### 14.11 Dua hal yang BELUM punya rumah

> Ditemukan 3 Sep saat menyusun peta sesudah Fase 2. Keduanya bukan pekerjaan
> besar, tapi keduanya **jatuh di sela-sela** pembagian fase yang ada — jadi
> tak akan dikerjakan siapa pun kalau tidak ditulis di sini.

**1. FRM-IT-07 Evaluasi — punya kolom, tak punya layar.**

Yang sudah ada: kolom `evaluator_user_id`, nilai `evaluator` di
`pihak_menunggu`, baris akses *"isi UAT & Evaluasi miliknya"*, dan
*"penjadwalan Evaluasi"* di Fase 3. Yang **tidak** ada: **permukaannya.**
Terukur — 0 penyebutan di brief ronde 2, dan cuma 1 label di paket desain
ronde 1.

Jadi ia bukan bagian 2a maupun 2b, dan Fase 3 hanya menjadwalkan sesuatu yang
belum ada bentuknya. ⚠️ Ini Kelas A: **diisi evaluator, bukan IT.** Isinya per
§7: kesesuaian · kemudahan · stabilitas · dampak · manfaat · temuan
Bug/Enhancement · dan **Rencana Tindak Lanjut yang bisa MEMBUAT TIKET BARU** —
itulah yang menutup lingkaran sistem ini.

**Keputusan yang perlu diambil:** dititipkan ke ronde desain 2b (murah, karena
chat desainnya sedang jalan) atau jadi ronde tersendiri bersama Fase 3.
Rekomendasi: **titipkan ke 2b** — bentuknya sederhana dan menunda berarti Fase
3 akan menjadwalkan formulir yang belum dirancang.

**2. Lampiran FRM-IT-01.** §7 menyebutnya (*screenshot · contoh laporan · alur
proses · dokumen lain*), tapi **tak pernah masuk §14** dan belum dibangun.
Padahal ini satu-satunya jalan pemohon menempelkan bukti masalahnya, dan
tanpanya orang akan menempel gambar lewat WhatsApp — persis kebiasaan yang mau
digantikan sistem ini. Kecil, cocok dititipkan ke **2a** (ekspor sudah
menyentuh berkas).

### 14.12 Peta sesudah Fase 2

1. **Sekarang, paralel** — chat build 2a · chat desain 2b (+ FRM-IT-07 kalau
   keputusan di atas "titipkan").
2. **Sesudah desain 2b turun** — bangun 2b. Ini yang menutup Fase 2 sesuai
   §14.5: *backlog nyata jalan penuh dari Requirement sampai Go Live.*
3. **Fase 3** — sambungan Project Tracker (`sys_project`) · penjadwalan
   Evaluasi · **nomor tiket di commit message**. Yang terakhir berpasangan
   dengan `it:sync-git` yang dibangun di 2a: sekali nomor tiket masuk commit,
   Kelas B terisi tanpa disuruh, dan papan tersambung ke riwayat kode.
4. **Prod** — pemicunya **belum diputuskan**. Ekky condong ke "sesudah semua
   fase"; catatan tandingannya ada di §14.5 (sistem proses yang tak dipakai
   lebih buruk daripada tak ada) dan riwayat dua fitur yang berhenti persis
   karena tak pernah menyentuh prod. Apa pun keputusannya, **tulis pemicunya**
   — "nanti kalau sudah siap" bukan pemicu.

### 14.13 Keputusan desain ronde 2 — TERKUNCI

> Paket: `Downloads\design_handoff_it_tracker r2\` (3 Sep). Tujuh pertanyaan
> desain dijawab chat HUB, semuanya dipatuhi di hasil. Ditulis di sini supaya
> chat build **tidak menebak ulang**.

| # | Pertanyaan | Keputusan | Alasan singkat |
|---|---|---|---|
| 1 | kedalaman 02 | **otomatis dari jenjang + tautan "butuh detail L?" yang menaikkan lewat triase** | blok L terlipat tetap terlihat → rasa "formulirku belum lengkap". Jalan keluar disalurkan lewat triase, sesuai aturan jenjang ditentukan IT |
| 2 | layar UAT | **tetap di detail; fase UAT jadi kartu besar "Pertanyaan untukmu"** | §4 brief: tetap di detail kecuali alasan kuat. Layar kedua = dua permukaan untuk satu pekerjaan |
| 3 | tabel FR | **tabel + baris hantu, tanpa tombol Tambah** | ⚠️ syarat: **Kriteria Diterima kolom terlebar & selnya membesar saat diketik** |
| 4 | "Disetujui dengan Catatan" | **daftar tindak lanjut di tiket + tombol "jadikan tiket" per butir** | melahirkan tiket otomatis membanjiri papan (catatan UAT sering sepele); tombol memberi untungnya tanpa kebisingannya |
| 5 | lima pemeriksaan tetap | **baris TC bawaan TERKUNCI** | checklist terpisah bikin dua sistem & ringkasan pass/fail ambigu. Terkunci = tak bisa dihapus/ganti nama, **tetap diisi & ikut terhitung** |
| 6 | bentuk paraf | **baris berstempel waktu, tanpa kotak tanda tangan** | paraf itu keputusan manusia, bukan syarat terhitung — siapa & kapan adalah nilai auditnya. Lampu gerbang tetap ada; baris itulah yang menyalakannya |
| 7 | pemilih ketergantungan | **semua tiket termasuk yang DITUNDA**, kecualikan DITOLAK | bergantung pada tiket ditunda = terblokir tanpa batas waktu; justru kasus terburuk yang wajib terlihat |

**FRM-IT-07 ikut dirancang** (sebelumnya tak punya rumah, §14.11): lima aspek
1-5 + `spawnTicket()` yang melahirkan tiket dari rencana tindak lanjut —
**mekanisme yang sama dengan catatan UAT, bukan dua jalur berbeda.**

**Diverifikasi dengan pengukuran, bukan dari README:** `pathFor` identik ·
`RANK` identik · aturan panas identik byte · `gateInfo` **aditif** (`missing`
tetap ada, `checks[]` ditambahkan). ⇒ **`PhaseEngine` tidak perlu ditulis
ulang.** Ini yang paling mahal kalau meleset, dan ia tidak meleset.

#### Dua temuan yang harus ditindaklanjuti

**T1 — lima screenshot ronde 2 hilang dari paket.** README menjanjikan
`07-frm02-ringkas-M-kosong.png` … `11-frm07-evaluasi.png`; folder
`screenshots/` masih berisi enam berkas ronde 1, dan kepala README masih teks
lama ("4 layar teratas", "6 keadaan kunci"). Isinya ADA di `.dc.html` — ini
kelalaian pengemasan, minta ulang ke chat desain.

**T2 — paraf bisa jadi kosong artinya.** Boleh-edit dikendalikan
`ed = canEdit && now` — **peran dan fase, bukan status paraf**. Selama tiket
masih di ANALISIS, tabel FR tetap bisa diubah **sesudah** BO memparaf, tanpa
jejak. Jendelanya sempit (pindah ke DEV → terkunci) tapi itu justru jendela
yang penting, karena paraf BO adalah syarat gerbangnya.
**Perbaikan yang dipilih — bukan audit trail:** *mengubah baris FR sesudah
diparaf MENGHAPUS parafnya.* Menegakkan dirinya sendiri, tanpa tabel baru,
dan jujur: BO diminta menyetujui ulang apa yang berubah.

#### Dari daftar "sengaja tidak dikerjakan" — yang wajib diketahui build

- **Tab pindah antar sel tabel FR tidak dimockup** (hanya Enter di baris
  hantu). **Tetap harus dipasang** — target "tiket M < 10 menit" bergantung
  padanya untuk L yang 12 baris.
- **Ekspor cetak 02/04/05/07 belum didesain** (hanya FRM-IT-01). Tak memblokir
  Fase 2; infrastruktur ekspornya dibangun di 2a.
- **Riwayat perubahan formulir** tak ada — sebagian tertutup oleh T2.
- Saklar demo memperagakan BO oleh Robi (Executive), tapi labelnya sudah
  benar: **"Business Owner (Executive / Konsultan)"**. Jangan patok Executive.

### 14.14 Prompt chat build — FASE 2b

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini membangun IT Request
Tracker FASE 2b: empat formulir kerja. Fase 1 sudah LIVE DI DEV.
Lokal & dev; prod HANYA kalau Ekky menyebut "prod".

BACA DULU:
· docs/rencana/rencana-it-tracker.md   <- paragraf 14, terutama 14.13 (keputusan
                                          desain TERKUNCI) dan 14.14
· docs/brief-desain/design-brief-it-tracker-ronde2.md
· memori project_it_tracker
· Downloads\design_handoff_it_tracker r2\   <- ACUAN UTAMA (dc.html + it-store.js)

RUANG LINGKUP 2b:
1. FRM-IT-02 Analysis & Design  (Kelas C — Ekky + paraf 1 Business Owner)
2. FRM-IT-04 Internal Testing   (Kelas B — IT sendiri, tanpa paraf pihak lain)
3. FRM-IT-05 UAT                (Kelas A — pemohon saja)
4. FRM-IT-07 Evaluation         (Kelas A — evaluator)

KONTRAK MESIN FASE — sudah diukur, jangan diragukan dan jangan diubah:
pathFor, RANK, dan aturan panas di it-store.js r2 IDENTIK dengan r1. gateInfo
ADITIF: `missing` tetap ada, `checks[]` ditambahkan. Jadi PhaseEngine yang
sudah lulus 31 uji TIDAK perlu ditulis ulang — tambahkan checks[], jangan
mengganti bentuk lamanya.

KENDALI NEGATIF UTAMA: 31 uji Fase 1 HARUS tetap hijau sesudah 2b.
Kalau ada yang merah, itu tanda kamu mengubah kontrak — perbaiki kodemu,
JANGAN memperbarui ujinya supaya lolos.

YANG PALING MUDAH SALAH:
1. Mengubah baris FR sesudah diparaf HARUS MENGHAPUS parafnya. Desainnya
   mengunci berdasarkan peran+fase saja, jadi paraf bisa jadi kosong artinya
   (temuan T2 di 14.13). Ini pagar integritas, bukan kenyamanan.
2. 02 PENUH jangan pernah muncul untuk jenjang M. M hanya: tabel FR +
   Kriteria Diterima + komponen. Naik ke L lewat modal triase (fase TIDAK
   berubah), bukan dengan membuka field L di tiket M.
3. TC-01..05 terkunci = tak bisa DIHAPUS atau DIGANTI NAMA, tapi kolom aktual
   dan Pass/Fail diisi normal DAN ikut terhitung di ringkasan.
   TC-05 "fitur lama terkait tetap berjalan" adalah yang menangkap kerusakan
   diam-diam — jangan sampai bisa hilang.
4. Kriteria Diterima: kolom TERLEBAR, selnya membesar saat diketik, dan Tab
   berpindah antar sel (mockup TIDAK memperagakan Tab — tetap pasang; target
   tiket M < 10 menit bergantung padanya untuk L yang 12 baris).
5. Catatan dapur (estimasi & risiko) DINYATAKAN ke non-IT sebagai "ada catatan
   dapur IT — tidak ditampilkan", bukan disembunyikan diam-diam.
6. Business Owner = Executive ATAU Konsultan. Saklar demo memperagakan Robi
   (Executive) — JANGAN patok Executive di kode.
7. spawnTicket: SATU mekanisme untuk catatan UAT dan rencana tindak lanjut
   evaluasi. Jangan bikin dua jalur. Tiket lahir di TRIASE, pemohon sama,
   parent tercatat.
8. "Perlu Perbaikan" di UAT mengembalikan tiket ke DEV DAN mereset konfirmasi
   FRM-IT-03. Bukan sekadar catatan.
9. Gerbang UJI->UAT menolak selama ada temuan Major terbuka, dengan kalimat
   yang MENYEBUT JUMLAHNYA ("1 temuan Major masih terbuka - menahan gerbang").

UJI TERIMA 2b (tambahan atas paragraf 14.6):
· tiket M tidak pernah melihat satu pun field khusus L (kendali negatif)
· edit baris FR sesudah paraf -> paraf hilang, gerbang menutup lagi
· TC-01..05 tak bisa dihapus; ringkasan menghitungnya
· 1 Major terbuka -> gerbang UAT tertutup; ditutup -> gerbang terbuka
· "Perlu Perbaikan" -> fase kembali DEV dan konfirmasi 03 ter-reset
· rencana tindak lanjut evaluasi -> tiket baru di TRIASE, parent tercatat
· 31 uji Fase 1 tetap hijau
· cacah baris tabel lama tak berubah

LINGKUNGAN:
· Branch feat/it-tracker, worktree Downloads\sweettreats-it-tracker.
  Cek HEAD sendiri (git log -1); jangan percaya hash di dokumen.
· Fase 2a mungkin sedang/sudah dikerjakan chat lain di branch yang sama —
  `git log --oneline -10` dulu, jangan menimpa kerjaannya.
· dev = SATU working copy bersama DAN SEDANG DIPAKAI ORANG (untracked milik
  chat Alur Persetujuan ada di sana). `git status --porcelain | grep "^??"`
  DULU. Pindah branch pakai checkout TANPA reset --hard — reset menimpa
  untracked tanpa peringatan; checkout MENOLAK, dan penolakan itu pengaman.
· JANGAN `php artisan db:show`, JANGAN Schema::hasTable() — keduanya membunuh
  mysqld lokal (segfault, mysql_error.log tak mencatatnya). Pakai
  SHOW TABLES LIKE.
· phpunit mati di lebih dari sekitar 17 uji ber-DB per proses -> PER KELAS.
· Naik dev: SQL -> php artisan access:rebuild -> deploy penuh -> rsync CSS.

Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```

### 14.15 Keputusan Ekky pasca-2b (4 Sep)

**ACC — tiga asersi Fase 1 yang diubah sadar di 2b.** Ketiganya melacak balik
ke instruksi, bukan penyimpangan: dua konsekuensi langsung keputusan §14.13 #1
(*"02 PENUH jangan pernah muncul untuk M"* — ruang lingkup bagian dari 02
penuh), satu kalimat yang ditulis §14.14 no. 9 sendiri.

⚠️ **Pelajaran metode, supaya tak diulang:** prompt §14.14 memerintahkan
*"31 uji harus tetap hijau, jangan perbarui uji supaya lolos"* — **terlalu
mutlak, dan bertabrakan dengan keputusan §14.13 #1 di dokumen yang sama.**
Keputusan itu mustahil dijalankan tanpa mengubah gerbang ANALISIS. Chat build
menyelesaikannya dengan benar: diubah **sadar**, diberi label
`KONTRAK BERUBAH SADAR`, dan dieskalasi — bukan diam-diam. Aturan yang lebih
tepat untuk lain kali: *"uji yang merah adalah pertanyaan, bukan izin. Jawab
dengan menunjuk keputusan tertulis yang memaksanya, atau perbaiki kodenya."*

⚠️ **Ralat klaim HUB:** waktu §14.13 menulis *"`gateInfo` aditif, kontrak
utuh"*, yang diukur cuma **bentuknya** (`missing` masih ada, `checks[]`
ditambah). **Isi** `missing` — daftar syarat gerbang — memang berubah antara
r1 dan r2. Klaimnya lebih sempit daripada kedengarannya.

**ACC — tiga kebijakan:**

| Pertanyaan | Keputusan | Alasan |
|---|---|---|
| Rail Go Live tetap "Menunggu Executive" walau BO sudah ACC di 02 | **tetap** | Go Live punya persetujuan sendiri (FRM-IT-06 = IT + BO); paraf 02 bukan paraf yang sama |
| Gerbang UAT tanpa ≥1 skenario | **ditolak** | UAT tanpa skenario = menanya "ini yang kamu minta?" tanpa apa pun untuk diperiksa; sejajar aturan ≥1 FR di ANALISIS |
| Label "Dianalisis IT bersama {pemohon}" | **dipertahankan** | §5 memang menulis ANALISIS = IT + pemohon (bersama) |

**⏳ MENUNGGU — bolehkah Head IT memparaf sebagai Business Owner.**
Ekky condong **boleh**. Keberatan HUB sudah disampaikan sekali: kalau Head IT
boleh, maka orang yang menulis analisis juga yang menyetujuinya — persis yang
§7 hindari saat memangkas 3 paraf jadi 1, dan satu paraf yang bisa diteken IT
sendiri efektif sama dengan **nol paraf**. Gerbang ANALISIS→Development
kehilangan satu-satunya syarat dari luar IT.
Dua bentuk tandingan yang ditawarkan: **(a) lewati tapi tercatat** —
*"Diteruskan Head IT · BO tak merespons 5 hari"*, tampil di tiket & ekspor;
**(b) carve-out sempit** — Head IT boleh memparaf hanya untuk tiket
**inisiatif IT** yang memang tak punya pemohon.
**Belum dijawab. Jangan dianggap disetujui.**

**Utang terbuka:** lima screenshot ronde 2 (chat desain) · lampiran FRM-IT-01
(chat build, pakai ulang `RopAttachment`) · pemicu prod **masih belum ditulis**.

✅ Diperiksa 4 Sep: berkas Alur Persetujuan hilang dari working copy dev, tapi
kelimanya **utuh di snapshot `06905c4`** — tak ada yang hilang.

✅ Diperiksa 4 Sep: `public_path()` prod benar sendiri
(`AppServiceProvider::usePublicPath($publicHtml)`), lampiran ROP nyata memang
mendarat di `public_html/uploads/rop/`. Pola `RopAttachment` aman dipakai ulang.

### 14.16 Paraf: jalan pintas bertanda — dan kesiapan demo dev (4 Sep)

**KEPUTUSAN EKKY: Head IT boleh MELEWATI paraf Business Owner, tapi TERCATAT.**
Alasannya dinyatakan sendiri: menunggu Konsultan atau Robi memakan waktu,
padahal kepentingan mereka tak sedalam Head IT. Ini **bukan** "Head IT boleh
memparaf" — bentuknya berbeda dan perbedaannya penting: yang tercatat adalah
*pelewatan*, bukan *persetujuan*.

Spesifikasi yang harus dibangun:
- Tombolnya **bukan** tombol paraf. Labelnya menyatakan apa adanya, mis.
  *"Teruskan tanpa paraf BO"*.
- **Alasan satu baris WAJIB** — murah, dan membuat tindakannya disengaja.
- Yang tersimpan: siapa · kapan · alasan.
- **Penandanya ikut sampai Go Live dan tercetak di ekspor**, mis.
  *"Diteruskan Head IT tanpa paraf Business Owner · 4 Sep 14:20 · <alasan>"*.
  Jangan tampil seperti paraf biasa — kalau menyamar jadi paraf, seluruh
  gunanya hilang.
- Tidak ada masa tunggu (Ekky menolak friksi itu). Kejujuran dijaga oleh
  penanda yang tak bisa disembunyikan, bukan oleh penundaan.

⛔ **TEMUAN yang mengubah artinya — `Admin*` ADA di `ROLE_PENGAWAS`.**
Terukur 4 Sep: `ROLE_PENGAWAS` = Executive · Consultant · Konsultan
IT/Finance/Marketing · **Admin General · Admin Backup · Admin**. Dan di dev
**`Admin General` adalah akun `Admin_Ekky`** (ekky1326@gmail.com).
Artinya jalan pintas itu **sudah ada hari ini, tanpa catatan**: menulis
analisis sebagai Head IT, lalu berpindah akun dan memparaf sebagai Admin_Ekky.
Kode memeriksa **peran, bukan orang**, jadi tak ada yang menahannya dan tak
ada jejaknya.
**Rekomendasi HUB: keluarkan `Admin*` dari `ROLE_PENGAWAS`** bersamaan dengan
membangun jalan pintas bertanda. Kalau pintu sunyi dibiarkan terbuka, pintu
bertanda tak akan pernah dipakai. **Belum diputuskan Ekky.**

#### Kesiapan demo end-to-end di dev

✅ Siap: dev bersih (**0 tiket**, `it_counter` kosong → tiket pertama
`IT-2026-001`); Fase 1+2a+2b live (`22c2348`, 78 uji); pemain lengkap —
pemohon **Yovan** (creative@stdc.id) / **Yovanka**, IT **Reza**,
BO **Robi** (Executive) / **Hari** (Konsultan Marketing).

⛔ **Penghalang 1 — Ekky tak punya akun Head IT di dev.** `Admin_Ekky`
bergolongan **Admin General**, yang dibaca kode sebagai **pengawas/BO**, bukan
Head IT. Dengan akun itu **triase tidak bisa dilakukan**. Head IT di dev
tercatat atas nama **Muhamad Ikhsan**. Pilihan: beri `Admin_Ekky` peran Head IT
di dev, atau demo memakai akun Ikhsan.

⚠️ **Penghalang 2 — tembok BO muncul DUA kali**, bukan sekali: paraf 02 di
ANALISIS→Development, **dan** ACC FRM-IT-06 di Go Live yang sengaja **tak bisa
dibubuhkan Head IT sekalipun**. Robi/Hari harus siap di dua momen — atau jalan
pintas bertanda dibangun lebih dulu.

⚠️ **Penghalang 3** — jalan pintas bertanda belum dibangun (baru diputuskan
hari ini).

### 14.17 Peran terbalik untuk Ekky — dev SUDAH sama dengan prod (diukur 4 Sep)

⚠️ **Ralat §14.16.** Di sana tertulis *"Ekky tak punya akun Head IT di dev"*
seolah dev salah konfigurasi. **Salah.** Prod dan dev diukur berdampingan dan
**identik**: Admin_Ekky = `Admin General` di KEDUANYA; Head IT di keduanya
adalah **Muhamad Ikhsan**; Reza = IT; Robi = Executive; Hari = Konsultan
Marketing; Admin_Nisa = Admin Backup. Beda satu-satunya: dev punya akun
tambahan **Yovan** (`creative@stdc.id`) di samping Yovanka.
⇒ **Tak ada yang perlu "disamakan dengan prod". Dev sudah cermin prod.**

**Yang sebenarnya bengkok: perannya terbalik untuk Ekky, di KEDUA environment.**
Ekky adalah Head IT dalam kenyataan, tapi identitas sistemnya `Admin General` —
dan `Admin General` justru ada di `ROLE_PENGAWAS`, alias **Business Owner**.
Sementara kuasa Head IT di tracker menempel pada `ROLE_HEAD_IT` =
`["Head IT","Super Admin"]`, yaitu Muhamad Ikhsan dan Admin_Reza.

⛔ **Konsekuensi yang menentukan urutan kerja:** mencabut `Admin*` dari
`ROLE_PENGAWAS` **tidak boleh berdiri sendiri** — kalau begitu saja, Ekky
kehilangan pijakan sama sekali: bukan Head IT, bukan BO, penonton di sistemnya
sendiri.

**Usul HUB — satu paket, dua konstanta, NOL perubahan `group_path`:**
1. `ROLE_HEAD_IT` **+= `"Admin General"`** (itu Ekky, dan memang dia Head IT-nya)
2. `ROLE_PENGAWAS` **−= `"Admin General"`, `"Admin Backup"`, `"Admin"`**

Hasil: Ekky jadi Head IT penuh di tracker; BO menyusut jadi Executive +
Consultant + Konsultan\* — persis §7 dan persis label desainnya sendiri
(*"Menunggu paraf satu Business Owner (Executive / Konsultan)"*, screenshot 07).

✅ **Kenapa ini aman:** dua konstanta di kode, **tak ada golongan yang
berpindah**. Menu, akses, petty cash, dan fitur lain tak tersentuh — berbeda
dari gerbong role 1 Sep yang memindahkan 4 orang dan menuntut KODE DULU BARU SQL.

Sesudah paket ini, jalan pintas bertanda §14.16 baru punya arti: satu-satunya
cara Head IT melewati BO, dan cara itu meninggalkan jejak.
**Status: diusulkan HUB, Ekky bilang "bahas dulu" — belum diputuskan.**

#### Paket desain `r2 p2` — T1 DITUTUP

`Downloads\design_handoff_it_tracker r2 p2\` (4 Sep). Kelima screenshot ronde 2
(07-11) lengkap; kepala README diperbaiki (11 keadaan kunci, empat formulir
kerja disebut). **Keempat berkas desain IDENTIK BYTE dengan r2** — nol
pergeseran, jadi tak ada koding 2b yang terbatalkan.
⚠️ Cacat kecil untuk diperiksa di UI nyata: di screenshot 07 **tiga baris lampu
gerbang saling bertindihan** saat dirender. Cacat tata letak mockup, bukan
logika — jangan ikut disalin.

### 14.18 RALAT PENTING — Muhamad Ikhsan ADALAH Ekky

Ekky mengoreksi 4 Sep: **"Ekky" itu nama panggilan; akun `Muhamad Ikhsan`
adalah dia sendiri.** Jadi Ekky punya **dua akun**: `Muhamad Ikhsan` (Head IT)
dan `Admin_Ekky` (Admin General) — pola yang sama dengan
[[project_roster_karyawan]] soal *"Anisa ≠ Admin_Nisa (dua akun beda)"*.

**Yang batal dari §14.16/§14.17:** kalimat *"Ekky tak punya akun Head IT"*
**SALAH**. Dia punya. Tidak ada penghalang demo, tak perlu meminjam akun.
Kesalahan HUB: menyimpulkan dua orang berbeda dari dua nama berbeda, tanpa
bertanya.

**Yang justru MENGUAT:** lubang paraf bukan lagi hipotetis, tapi
**terkonfirmasi**. Satu manusia, dua akun: menulis analisis sebagai Ikhsan
(Head IT), lalu memparaf sebagai Admin_Ekky (`Admin General` ∈
`ROLE_PENGAWAS`). Tanpa jejak — kode memeriksa **peran, bukan orang**.

**Usulnya jadi lebih sederhana.** Batal paket dua konstanta §14.17; cukup:

> `ROLE_PENGAWAS` **−= `"Admin General"`, `"Admin Backup"`, `"Admin"`**

Satu konstanta. Tak perlu menambah apa pun ke `ROLE_HEAD_IT` karena Ekky sudah
Head IT lewat Ikhsan. Sesudah itu BO = Executive + Consultant + Konsultan\*,
persis §7 dan persis label desain (*"Business Owner (Executive / Konsultan)"*).
**Status: Ekky bilang "boleh, bahas dulu" — belum final.**

#### Catatan kesiapan demo (dev)

- Persona tersedia di dev: **Ekky** (Muhamad Ikhsan = Head IT · Admin_Ekky) ·
  **Reza** (IT) · **Robi** (Executive/BO) · **Hari** (Konsultan Marketing/BO) ·
  **Yovan** & **Yovanka** (Head Marketing/pemohon).
- ⚠️ **Rahmat (Konsultan IT) TIDAK ADA di dev** — hanya prod. Untuk demo pakai
  **Hari** sebagai persona Konsultan.
- ⛔ **Jangan membuat akun baru sekadar untuk demo.** Membuat user menyeret
  aturan kerja absensi; proyek ini sudah 2× kena **alpa palsu** karena
  `effective_from` diisi tanggal pembuatan akun, bukan tanggal mulai kerja
  ([[feedback_alpa_palsu_aturan_kerja]]).
- Password demo bersama boleh disamakan **di dev saja** (`password_verify` +
  `password_hash(PASSWORD_DEFAULT)` = bcrypt biasa). `dev.sweettreats.id`
  terbuka di internet → jangan password yang gampang ditebak, dan **jangan
  pernah di prod**.

### 14.19 Prompt chat build — jalan pintas paraf BERTANDA (keputusan §14.16)

> Keputusan Ekky 4 Sep: Head IT boleh **melewati** paraf Business Owner, **tapi
> tercatat**. Bentuknya *pelewatan*, bukan *persetujuan* — tombolnya tidak boleh
> menyamar jadi paraf. Dibangun **sesudah demo**, bukan sebelum: demo dulu akan
> memberi tahu apakah BO memang lambat; kalau tidak, fitur ini tak perlu ada.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini membangun SATU fitur kecil di
IT Request Tracker: JALAN PINTAS PARAF BERTANDA. Lokal & dev; prod HANYA kalau
Ekky menyebut "prod".

BACA DULU:
· docs/rencana/rencana-it-tracker.md  <- §14.16 (keputusan + spesifikasi),
                                        §14.18 (kenapa ini penting), §14.19 (ini)
· memori project_it_tracker

MASALAH YANG DISELESAIKAN:
Gerbang ANALISIS->Development butuh paraf satu Business Owner (Executive /
Konsultan). Kalau BO lambat merespons, tiket tersandera dan Head IT tak bisa
apa-apa. Ekky memutuskan: Head IT boleh MELEWATI, tapi TERCATAT dan TERLIHAT.

BENTUKNYA — ini yang paling mudah salah:
1. Ini PELEWATAN, bukan paraf. Tombolnya berlabel "Teruskan tanpa paraf BO",
   BUKAN "Paraf". Jangan pernah mengisi fr02.paraf dari jalur ini.
2. Simpan TERPISAH: fr02.diteruskan = {oleh, oleh_id, at, alasan}.
   gateInfo: lampu "paraf satu Business Owner" menyala kalau paraf ADA ATAU
   diteruskan ADA — tapi teks lampunya berubah jadi
   "diteruskan Head IT tanpa paraf BO" supaya tak menyamar.
3. ALASAN WAJIB, satu baris, minimal 10 karakter. Tanpa alasan -> ditolak
   dengan pesan yang menyebut kenapa (pola P3).
4. Hanya ROLE_HEAD_IT. Reza (it_dev) -> 403. BO (pengawas) -> 403 (mereka
   punya tombol paraf sendiri).
5. PENANDA IKUT SAMPAI GO LIVE dan tercetak:
   · detail tiket: banner di bagian 02 DAN di kepala tiket, mis.
     "Diteruskan Head IT tanpa paraf Business Owner · 4 Sep 14:20 · <alasan>"
   · kartu papan: chip pendek "tanpa paraf BO"
   · ekspor: baris yang sama di blok status ekspor FRM-IT-01 (satu-satunya
     ekspor yang ada), dan WAJIB ikut saat ekspor 02/06 dibangun nanti.
   Kalau penandanya bisa disembunyikan atau tampak seperti paraf biasa,
   seluruh gunanya hilang.
6. Konsisten dengan T2 (§14.13): mengubah isi 02 (FR/komponen/lingkup/deps/
   jenjang) MENGGUGURKAN diteruskan, sama seperti menggugurkan paraf.
   Simpan-ulang identik & catatan dapur TIDAK menggugurkan.
7. Kalau BO memparaf SESUDAH diteruskan: simpan keduanya. Paraf tidak
   menghapus jejak pelewatan.
8. Tidak ada masa tunggu (Ekky menolak friksi itu). Kejujuran dijaga oleh
   penanda yang tak bisa disembunyikan, bukan oleh penundaan.

GERBANG MANA: bangun SATU mekanisme teruskanTanpaBo($t, $gerbang, $alasan)
dengan konstanta GERBANG_BOLEH_DITERUSKAN = ['02']. Go Live ('06') SENGAJA
TIDAK termasuk — ACC BO Go Live tetap tak bisa dilewati Head IT. Menambahkan
'06' = satu entri, keputusan Ekky nanti; jangan ditambahkan sendiri.

⏳ HAL YANG BELUM DIPUTUSKAN — JANGAN DIKERJAKAN TANPA KATA EKSPLISIT EKKY
DI CHAT INI: mencabut "Admin General", "Admin Backup", "Admin" dari
ROLE_PENGAWAS (§14.18). Ekky bilang "boleh, bahas dulu" — itu BUKAN ya.
Kalau dia menyebutnya di chat ini: satu konstanta, nol perubahan group_path,
dan tambahkan uji "Admin_Ekky tak bisa memparaf".

UJI TERIMA:
· Head IT + alasan -> diteruskan tersimpan, gerbang terbuka, fr02.paraf TETAP
  kosong
· tanpa alasan / alasan < 10 karakter -> ditolak, pesan menyebut sebabnya
· Reza -> 403 · BO -> 403
· penanda tampil di detail (2 tempat), kartu papan, dan ekspor FRM-IT-01
· ubah FR sesudah diteruskan -> diteruskan gugur, gerbang menutup lagi
· BO memparaf sesudah diteruskan -> keduanya tersimpan, penanda tetap tampil
· teruskanTanpaBo($t, '06', ...) -> ditolak (bukan di daftar)
· KENDALI NEGATIF: 83 uji yang ada tetap hijau; cacah baris tabel lama tak
  berubah; tiket yang diparaf BO normal TIDAK menampilkan penanda apa pun

LINGKUNGAN:
· Branch feat/it-tracker, worktree Downloads\sweettreats-it-tracker. Cek HEAD
  sendiri (git log -1). Nomor tiket di BARIS PERTAMA commit.
· dev = SATU working copy bersama DAN sedang dipakai untuk demo — koordinasi
  sebelum deploy; `git status --porcelain | grep "^??"` dulu; checkout TANPA
  reset --hard.
· ⛔ JANGAN db:show / Schema::hasTable() / SHOW COLUMNS / DESCRIBE — membunuh
  mysqld lokal. Pakai SHOW TABLES LIKE + SELECT kolom LIMIT 0.
· phpunit PER KELAS.

Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```

### 14.20 Keputusan Ekky 4 Sep malam — prod, Admin*, penempatan, cabang Reza

**✅ CABUT `Admin*` dari `ROLE_PENGAWAS` — DIPUTUSKAN.** Satu konstanta:
`ROLE_PENGAWAS −= "Admin General", "Admin Backup", "Admin"`. Nol perubahan
`group_path`. Ekky tetap Head IT lewat akun Muhamad Ikhsan. Tambahkan uji
*"Admin_Ekky tak bisa memparaf"*. Boleh dikerjakan chat build tanpa tanya lagi.

**✅ PEMICU PROD — AKHIRNYA TERTULIS.** *"Setelah demo, kita pindahkan semuanya
ke prod. Dev hanya simulasi end-to-end dan realisasi."* Konsekuensinya:
- **Tiket di dev = buangan.** Sesudah demo, bersihkan (`DELETE it_*` + reset
  `it_counter`) — pola sudah ada di chat build.
- **Tiket nyata dibuat di PROD**, bukan dev. Peta antrian (§5
  `rencana-peta-antrian-it.md`) dimuat **sesudah IT Tracker naik prod**.
- Naik prod IT Tracker = gerbong sendiri: merge `feat/it-tracker` → `main`
  (uji-gabung kering: **0 konflik**) → SQL berurutan (core · access · 2b ·
  lampiran bila ada) → `access:rebuild` → deploy penuh (class baru) →
  **rsync `assets/` saja** → `curl` beranda 200 + URL akhir. Hanya saat Ekky
  menyebut "prod".
- ⚠️ "Semuanya" di kalimat Ekky dibaca HUB sebagai **IT Tracker**. Enam fitur
  lain yang antre di UAT (§3 peta) **tidak otomatis ikut** — masing-masing
  gerbong sendiri, masing-masing butuh kata "prod".

**✅ PENEMPATAN = "rolling" yang sudah ada di checklist, mau dibuat GLOBAL.**
Kodenya ditemukan: `DigitalChecklistController::rollingAssign / rollingSwap /
rollingRemove / makeRolling` + helper `cabangSesiUntuk()` (yang sudah dipakai
absensi untuk cabang-efektif). Jadi tiket 6m di peta berubah dari
*"kode belum ditemukan"* menjadi **"Rolling global — lepaskan dari checklist,
jadi satu sumber penempatan untuk absensi/kehadiran/laporan"**, jenjang **L**,
pemohon HR/Ops, fase **REQ**. Rencana lama `rencana-penempatan.md` (ACC 4 Agu,
Reza) jadi bahan FRM-IT-02-nya.

**✅ DUA CABANG REZA — diukur, ini putusannya:**
- `checklist-pettycash`: blade audit-nya **sudah ada di main** (`$ringkas` 4
  hit — penjaga nilai bersarang masuk lewat gerbong e28f89b); SQL
  `anisa_irma_fleksibel.sql` **dilarang dijalankan** (memori). → **hapus cabang.**
- `fitur/detail-pasien-…` (23 Jun): 9 berkas sudah bergerak jauh di main
  (`reservation/list.blade.php` 16 commit — redesign), **kecuali**
  `_modal_patient_detail.blade.php` yang **belum pernah ada di main**. → kalau
  fitur "detail pasien di reservasi" masih diinginkan, **cherry-pick berkas itu
  saja** sebagai tiket S; sisanya mati. → **hapus cabang sesudah cherry-pick.**

**📄 Proposal v2 DISELAMATKAN ke repo.** `docs/api/proposal-sweet-treats-api-omnicare-v2.md`
(+ PDF) ternyata hidup hanya di working copy bersama sejak Juni — tak pernah
di-commit. Sekarang di `main`. Isinya membuktikan: Fase 2 Pembayaran memang
kita tulis sebagai *"gambaran, spec menyusul"* — vendor menjawab persis yang
diminta. Spek konkretnya kini ada:
`docs/api/permintaan-omnicare-endpoint-pembayaran-2026-09.md` — siap dikirim.

### 14.21 RALAT §14.20 + prompt seeding antrian nyata ke dev (4 Sep malam)

⛔ **Ralat.** §14.20 menulis *"tiket di dev = buangan"* dan peta §8 menulis
*"jangan memuat 30 tiket ke dev"*. **Itu tafsiran HUB, bukan kata Ekky.**
Ekky dua kali bilang *"data di dev mau aku isi sama data kerjaan sekarang dan
antriannya"* — supaya demo memakai **beban nyata**. Kalimat *"dev hanya
simulasi dan realisasi"* berarti *simulasi dengan data nyata*, bukan *data
dibuang*. Papan dev kosong saat Ekky membukanya adalah akibat langsung
tafsiran itu.

**Yang benar:**
- Dev **DIISI SEKARANG** dengan antrian nyata → demo memperlihatkan beban
  sungguhan.
- Tiket dev **TIDAK dibuang.** Seeder yang sama **dijalankan ulang di prod**
  sesudah IT Tracker naik — idempoten by `nomor`, `masuk_at` dipertahankan,
  pemohon dicari by **email** (portabel antar env; id user berbeda).
- Tiga tiket **DICORET** dari peta karena ternyata **sudah di prod** (diukur
  `merge-base --is-ancestor` terhadap `2400896`): libur cabang bertugas,
  cuti/WFH tak memakan hari libur (`liburUntuk` ada di main), ketersediaan
  dokter cuti vs jadwal khusus. Memori HUB soal ketiganya **basi**.
- Sumber kebenaran: **`docs/rencana/seed-antrian-it-2026-09-04.psv`** —
  27 baris, pipe-separated, kolom: urut · judul · sumber · pemohon_email ·
  pemohon_nama · pic_email · jenjang · bobot · prioritas_pemohon ·
  prioritas_it · fase · masuk_at · status · alasan_tunda · parent_urut ·
  deps_urut · latar.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini MENGISI IT Request Tracker
di DEV dengan antrian kerjaan NYATA untuk demo 5 Sep, lewat seeder yang nanti
dijalankan ulang di prod. Lokal & dev; prod HANYA kalau Ekky menyebut "prod".

BACA DULU:
· docs/rencana/seed-antrian-it-2026-09-04.psv   <- SUMBER KEBENARAN, 27 baris
· docs/rencana/rencana-peta-antrian-it.md        <- konteks tiap baris (§3, §8, §9)
· docs/rencana/rencana-it-tracker.md §14.21      <- ini
· memori project_it_tracker

TUGAS: perintah artisan `it:seed-antrian {berkas.psv} {--env-guard=dev}`.
1. IDEMPOTEN by `urut` → simpan `urut` di kolom baru `it_ticket.seed_urut`
   (nullable, unik) supaya jalan ulang MEMPERBARUI, bukan menggandakan. Nomor
   tiket tetap dari it_counter (IT-2026-001..027) — jangan hardcode nomor.
2. Pemohon dicari by `pemohon_email`, fallback `pemohon_nama`; kalau dua-duanya
   tak ada → BERHENTI dengan pesan menyebut barisnya (jangan diam-diam
   melewati). PIC by `pic_email`, boleh kosong.
3. Fase & `masuk_at`: buat baris `it_ticket_fase` untuk fase saat ini dengan
   `masuk_at` persis dari berkas (ini yang membuat "berapa hari menunggu"
   JUJUR — jangan pakai now()). `it_ticket.created_at` = `masuk_at` juga.
   Tiket yang sudah di ANALISIS/UAT/EVALUASI: JANGAN mengisi FR/paraf/skenario
   palsu — biarkan formulirnya kosong, lampu gerbangnya mati apa adanya. Itu
   keadaan nyata: dibangun tanpa formulir karena tracker belum ada.
4. `sumber`: permintaan → pemohon wajib; inisiatif/insiden → pemohon boleh
   kosong (aturan Fase 1). Insiden masuk DEV langsung.
5. `status=ditunda` → panggil parkir yang sudah ada (`alasan_tunda` wajib),
   supaya tercatat ke audit_log seperti parkir manual.
6. `parent_urut` → `it_ticket.parent` (induk–anak, sudah ada di skema);
   `deps_urut` → deps (relasi ke tiket, fitur 2b). Selesaikan induk sebelum
   anak; dua lintasan bila perlu.
7. FRM-IT-01 payload minimal: `latar` dari berkas + `jenis` "Perubahan fitur"
   (untuk permintaan). Jangan mengarang field lain.
8. Baris 01 (Absensi stabilisasi): fase EVALUASI, evaluator = pemohon (HR).
   Anak 02/03/26 di DEV di bawahnya.
9. `--env-guard`: perintah MENOLAK jalan kalau APP_ENV/host tidak cocok dengan
   argumen. Di dev jalankan dengan `--env-guard=dev`. Untuk prod nanti Ekky
   sendiri yang memanggil dengan `--env-guard=prod`.

UJI TERIMA:
· jalan 2× di lokal → 27 tiket, bukan 54; counter 027
· pemohon tak ditemukan → berhenti, sebut baris
· "berapa hari menunggu" tiket 07 (13 Agu) ≈ 22 hari; tiket 04 (7 Jul) ≈ 59 hari
· heat: tiket di DEV tetap 0 walau 59 hari; tiket 08 di UAT menunggu Robi memanas
· 3 tiket ditunda tampil dengan alasan; tidak dihitung "item aktif"
· panel beban: item aktif = 24, macet di luar IT ≥ 3
· cacah baris tabel lama tak berubah; 83 uji lama hijau
· DEV: buka /it-tracker sebagai Ikhsan → papan terisi; sebagai Yovan →
  /it-tracker/permintaan menampilkan tiket 11 & 13; sebagai Robi → 08 & 17

LINGKUNGAN:
· Branch feat/it-tracker, worktree Downloads\sweettreats-it-tracker. Cek HEAD.
· Nomor tiket di baris pertama commit (pakai IT-2026-005: tracker sendiri).
· dev = working copy bersama, sedang dipakai demo. `git status --porcelain |
  grep "^??"` dulu; checkout TANPA reset --hard.
· ⛔ JANGAN db:show / Schema::hasTable() / SHOW COLUMNS / DESCRIBE — membunuh
  mysqld lokal. `ADD COLUMN IF NOT EXISTS` + `SELECT kolom LIMIT 0`.
· phpunit PER KELAS.
· Naik dev: SQL (kolom seed_urut) → deploy → jalankan `it:seed-antrian
  docs/rencana/seed-antrian-it-2026-09-04.psv --env-guard=dev` → curl
  /it-tracker sebagai tiga persona, periksa URL AKHIR.

Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```

### 14.22 Tambahan seed baris 28–30: COGS · Inventory · Slip gaji sebagai DRAF IT

Ekky (4 Sep malam): *"apa bisa kita buatkan requirement-nya, biar tampil dulu,
biar antriannya jelas?"* — **bisa, dengan satu penanda kejujuran**: ketiganya
masuk sebagai **permintaan** atas nama pemilik departemennya, tapi latarnya
diawali **`[DRAF IT — belum dikonfirmasi pemohon]`**. Papan memperlihatkan
antriannya; teksnya jujur soal siapa yang sudah bicara.

Draf lengkap: `docs/rencana/draf-frm01-cogs-inventory-slipgaji.md`.

| Urut | Tiket | Pemohon | Masuk | Catatan |
|---|---|---|---|---|
| 28 | COGS | Della (Finance) | **Triase** | Ekky triase langsung saat demo |
| 29 | Inventory | **Anisa (Supervisor) — SEMENTARA** | **Triase** | pemilik dept belum disebut Ekky; ganti saat triase |
| 30 | Slip gaji | Admin_Nisa (HR) | **Antre → Ditunda** | alasan = kata Ekky sendiri: IT fokus Tracker & Finance |

**Untuk seeder (menambah §14.21):** baris 28–30 diperlakukan sama; penanda
`[DRAF IT …]` **harus tetap tampil** di FRM-IT-01 dan ekspor — jangan
dibersihkan. Gerbang Triase→Antre untuk 28/29 tetap milik Head IT saat demo.
Baris 30 sudah bertriase (L·4·P3) lalu diparkir lewat mekanisme parkir.

⚠️ Ini **mengoreksi** peta §9 yang menulis *"sengaja TIDAK ada di seed: COGS,
Inventory, Slip gaji"*. Prinsip "pemohon yang benar" tetap dijaga lewat
penanda draf + kewajiban konfirmasi sebelum lolos triase.
