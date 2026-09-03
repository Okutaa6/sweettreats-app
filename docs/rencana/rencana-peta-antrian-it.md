# Peta Antrian IT → Tiket Tracker

> Ditulis chat HUB **4 Sep 2026** atas permintaan Ekky: *"aku mau Executive
> melihat antrian kerjaan yang sudah ada di tanganku sebelum tracker ini ada."*
> Semua status di sini **diukur** (git, prod, dev), bukan dari ingatan. Aturan
> mainnya disepakati Ekky: **hanya pekerjaan yang BELUM SELESAI yang masuk**;
> yang sudah hidup di prod tidak di-backfill — tanggal palsu merusak angka
> "berapa hari menunggu", dan itu satu-satunya angka yang membuat papan
> dipercaya.

## 1. Koreksi hasil pengukuran — klaim vs kenyataan

| Klaim | Kenyataan (diukur 4 Sep) |
|---|---|
| "Omnicare sudah memberi API push, terutama pembayaran" | Spek vendor 10 Agu (`docs/api/omnicare-open-api-2026-08-10.md`): **6 WRITE, tak satu pun pembayaran**. Yang ada: pasien, janji temu, jadwal dokter (preview). Pembayaran hanya READ |
| "Petty cash 0 transaksi nyata" (klaim HUB) | **SALAH — 355 transaksi di prod, terbaru 3 Sep 19:44, 23 periode.** Angka lama dari `status-proyek.md` 6 Agu, basi sebulan. Ralat |
| "Integrasi Accurate selesai" | Push **dijeda** sejak 28 Agu; terakhir ter-push 27 Agu. Saklar **ada** di main & prod (gerbang + `--force` + `Kernel->when`). Finance ACC lanjut **Sabtu 5 Sep** |
| Hotfix `sweet-mates-auth` (23 Jul) belum ter-merge | Diuji tanpa login: `/sweet-mates` → dipantulkan ke `/signin`. **Prod tidak bocor**, perbaikannya sudah masuk lewat jalan lain. Cabang = kedaluwarsa |
| WiFi "cegat baris kembar" (13 Agu) | **BELUM di prod** — `PengawasanController` main memanggil `create` tanpa `matchBranch`. Perbaikan kecil tersangkut |
| Zona akun dokter | **18 commit 13 Agu** di `integrasi/dev-2026-08-06`, **tak pernah merge**. Tersangkut utuh |

## 2. Rekonsiliasi 19 cabang yang belum masuk `main` (= belum prod)

Prod = `2400896` (1 Sep) = main minus docs + 1 SQL Alpajri. Jadi *"belum di
main"* ≈ *"belum di prod"*.

| Cabang | Isi | Beda kode vs main | Status nyata | Ke tracker? |
|---|---|---|---|---|
| `feat/it-tracker` | IT Tracker 1+2a+2b+lampiran+pintas | 30 berkas | **live di dev**, prod belum | ya — tiket induk sendiri, **UAT/Go Live** |
| `integrasi/dev-2026-08-06` | **zona akun dokter** (Hari Ini · Jadwal · Pasien Saya · Absensi Saya), 18 commit | 15 | dibangun 13 Agu, **tersangkut** | ya — **L, UAT** |
| `feat/target-vs-pencapaian` | laporan Target vs Pencapaian + brief Executive Report | 7 | lulus dev, prod belum | ya — **M, UAT** |
| `feature/project-tracker` | Project Tracker Fase A | 16 | lokal Juli, diparkir | ya — **L, DITUNDA** ("prioritas IT Tracker dulu") |
| `feature/accurate-integration` | integrasi Accurate penuh (pull 6 buku + 5 laporan) | 38 | pause sejak 21 Jun | ya — **L, DITUNDA** ("Finance mapping ulang") |
| `feature/wifi-belajar-sendiri` | cegat pendaftaran IP kembar | 2 | **tersangkut**, belum prod | ya — **S, DEV** |
| `fix/branch-target-step-nominal` | input target `step=1000`→`1` | 1 | tersangkut sejak 7 Jul | ya — **S, DEV** |
| `fix/accurate-push-saklar` | + pesan penasihat `--days` | 4 | gerbangnya **sudah** di main; sisa cuma pesan | tidak — merge kecil saja |
| `wip/snapshot-konsultan-alur-2026-08-22` | potret working copy (Alur Persetujuan ada di sini) | 15 | **sumber ambil per-berkas**, bukan merge | tiket Alur Persetujuan — **L, UAT** |
| `wip/snapshot-dodo-2026-08-23` | potret DODO | 6 | DODO **sudah prod** 24 Agu | tidak — arsip |
| `checklist-pettycash` | audit push Reza + `anisa_irma_fleksibel.sql` | 2 | ⛔ SQL-nya **dilarang dijalankan** (memori); blade audit — tanya Reza | tidak, kecuali Reza bilang blade-nya perlu |
| `fitur/detail-pasien-…` (Reza, 23 Jun) | tombol detail pasien, warna perawatan | 10 | kemungkinan **tergantikan** redesign reservasi (11 Agu) | tanya Reza; kalau mati → hapus |
| `test/komisi-dokter-merged` | cabang uji merge | 73 | komisi dokter **sudah prod** 29 Agu; ini basis lama | tidak — hapus |
| `feature/accurate-commands` | WIP perintah Accurate Jul | 3 | tergantikan push harian yang hidup | tidak — hapus |
| `hotfix/sweet-mates-auth` | pagar rute KOL/KOC | 2 | **tergantikan** (prod sudah aman, diuji) | tidak — hapus |
| `chore/deploy-branch-switch` · `docs/notes` · `docs/git-workflow-reza` · `wip/docs-rescue` | docs / skrip | 0 | tak ada kode | tidak |

**Yang perlu dibersihkan git (bukan tiket):** hapus `test/komisi-dokter-merged`,
`feature/accurate-commands`, `hotfix/sweet-mates-auth`; merge sisa kecil
`fix/accurate-push-saklar`; tanya Reza soal dua cabangnya.

## 3. Modul → tiket — siapa mengajukan, jenjang, fase masuk

Prinsip yang disepakati: **pemohonnya orang yang benar.** IT (Ekky + HUB)
boleh **membantu menulis** isi requirement, tapi yang *mengajukan* dan
*menyetujui UAT* tetap pemiliknya. Kalau pemilik tak mau mengisi FRM-IT-01,
**itu temuan**, bukan halangan.

| # | Tiket | Pemohon | Sumber | Jenjang | Fase masuk | Bergantung pada | Catatan |
|---|---|---|---|---|---|---|---|
| 1a | **Reservasi & pasien dua arah ke Omnicare** | Head Marketing / FO | permintaan | **L** | REQ → siap Analisis | spek ✅ di repo | inti proposal v2 fase 1: hilangkan input ganda staf |
| 1b | **Pembayaran → Omnicare** | Finance | permintaan | L | **DITUNDA** | endpoint vendor **belum ada** | supaya Executive melihat kita menunggu vendor, bukan diam |
| 2 | **ORTHO — tracking & retensi pasien** | **Yovan** | permintaan | **M** | REQ | 1a (pemetaan `doc_id`) | data tindakan cukup; diagnosa tidak; HUB bantu draf FR |
| 3 | **COGS — kendali harga & promo** | **Finance** | permintaan | L | *belum tiket* | data Finance | Finance mengajukan; HPP mentah tersedia di `sales_detail`/`product_sales` |
| 4 | **Inventory / stok** | dept. operasional | permintaan | L | *belum tiket* | #3, kebersihan data Omnicare | asumsi "data clean" **tidak terbukti** — itu risikonya |
| 5a | **Penyesuaian komisi 70% + baris manual** | Finance | permintaan | M | **ANALISIS** (rencana = 02) | — | ACC Ekky 4 Sep; butuh paraf BO / pintas bertanda |
| 5b | **Jatah cuti** | HR (Nissa) | permintaan | M | **ANALISIS** | — | ACC Ekky 4 Sep |
| 5c | **Aturan kerja per role per cabang** | HR / Ops | permintaan | M | REQ | absensi stabil | ini yang "baru" dari absensi, bukan Evaluasi |
| 5d | **Slip gaji & insentif** | **HR** | permintaan | L | *belum tiket* | 5a | HR mengajukan; nol dokumen hari ini |
| 6a | **Alur Persetujuan + delegasi** | HR / Ops | permintaan | L | **UAT** (`masuk_at` 22 Agu) | — | 77 uji; ambil per-berkas dari snapshot |
| 6b | **Zona akun dokter** | Ekky (inisiatif) | inisiatif | L | **UAT** (13 Agu) | prasyarat SQL akun dokter | 18 commit tersangkut |
| 6c | **Target vs Pencapaian** | Executive | permintaan | M | **UAT** | — | lulus dev |
| 6d | **Libur cabang bertugas** | HR | permintaan | M | **UAT** | — | 11/11 |
| 6e | **Cuti/WFH tak memakan hari libur** | HR | permintaan | M | **UAT** | — | 109 uji; **menentukan uang** |
| 6f | **Ketersediaan dokter: cuti vs jadwal khusus** | FO | permintaan | S | DEV | — | fix lokal, belum deploy |
| 6g | **WiFi: cegat IP kembar** | Ekky | insiden | S | DEV | — | tersangkut |
| 6h | **Target: step nominal** | Ekky | insiden | S | DEV | — | 1 baris |
| 6i | **Alpajri: koreksi checklist 27 Agu** | Ekky | insiden | S | DEV | — | SQL committed, belum dijalankan |
| 6j | **Pusat Akun** | Ekky (inisiatif) | inisiatif | L | ANTRE | — | ACC Juli, belum dibangun |
| 6k | **Security hardening** | Ekky (inisiatif) | inisiatif | L | ANALISIS | — | brief ada |
| 6l | **Project Tracker** | Ekky (inisiatif) | inisiatif | L | **DITUNDA** | IT Tracker Fase 3 | alasan tertulis |
| 6m | **Penempatan** (Reza) | HR | permintaan | M | ? | — | ACC 4 Agu; **kode belum ditemukan** — tanya Reza |
| 6n | **Rekonsiliasi absensi P207** | HR | permintaan | M | REQ | CSV 5 cabang | draf menunggu ACC |
| 6o | **IT Tracker Fase 3** | Ekky (inisiatif) | inisiatif | M | ANTRE | 6l | penjadwalan Evaluasi dulu, sambungan PT nanti |
| 6p | **Integrasi Accurate penuh** | Finance | permintaan | L | **DITUNDA** | mapping Finance | pause 21 Jun |
| 7a | **Notifikasi WA reservasi** | Ekky (inisiatif) | inisiatif | M | ANTRE | Cekat (masuk sudah ada) | pengirim belum ada |
| 7b | **Hub notifikasi internal** | Ekky (inisiatif) | inisiatif | L | ANTRE | 7a | terpisah dari 7a |
| 8 | **Redesign dashboard & laporan** | Executive (Robi) | permintaan | L | REQ | — | **satu tiket** (keputusan Ekky); **satu FR per laporan** supaya tabel FR-nya berguna |

**Yang sengaja BUKAN tiket:** Accurate lanjut Sabtu (operasional) · Rp 4,36
juta / 17 baris fee dokter (pertanyaan ke Finance) · cabut `Admin*` dari
`ROLE_PENGAWAS` (satu konstanta, menunggu kata Ekky) · fitur yang sudah prod.

## 4. Absensi — jawaban untuk "fasenya di mana?"

Absensi *bukan* satu tiket; ia ±15 fitur yang naik prod berbulan-bulan dan
**masih terus ditambal**. Memaksanya jadi satu tiket dengan tanggal palsu =
merusak angka papan. Usul yang jujur:

**Satu tiket induk "Absensi — stabilisasi pasca-rilis Agustus"**, jenjang L,
fase **EVALUASI**, `masuk_at` = **27 Agu** (gerbong terakhir `761d7a7`, tanggal
sungguhan), evaluator **HR (Nissa)**. Evaluasi jatuh tempo ±10 Sep — dan itu
memang saat yang tepat untuk menanyakan *"bagaimana rasanya memakai ini?"*.
Tiap bug baru masuk sebagai **anak S (insiden)** di bawahnya. Executive
melihat: absensi *hidup*, *dievaluasi*, dan berapa tambalan yang masih terjadi.
Pengembangan baru (5c) tetap tiket sendiri.

## 5. Urutan memuat ke dev

1. **Tiket yang sudah dibangun** (6a–6e, IT Tracker) — masuk dengan `masuk_at`
   dari git, langsung di UAT. Ini yang paling cepat memberi Executive gambaran
   "berapa yang menunggu ACC saya".
2. **Yang sudah di-ACC** (5a, 5b) — ANALISIS, isi FR dari rencananya.
3. **Insiden kecil** (6g–6i) — DEV, selesaikan minggu ini; ini juga latihan
   nyata `it:sync-git`.
4. **Ditunda beralasan** (1b, 6l, 6p) — supaya penantian *terlihat*.
5. **Inisiatif IT** (6j, 6k, 6o, 7a, 7b) — ANTRE.
6. **Yang menunggu pemohon** (1a, 2, 8, 5c, 6n) — Ekky/HUB drafkan isinya,
   **pemohon yang menekan Kirim**. Kalau tak mau: catat sebagai temuan.
7. **Belum tiket** (3, 4, 5d) — jangan dibuat atas nama orang lain.

## 6. Menunggu Ekky

- **Modul #1**: setuju dipecah 1a/1b? Dan **adakah versi PDF lain** yang memuat
  endpoint pembayaran? Kalau tidak, peta alur pembayaranmu perlu ditulis ulang
  untuk sasaran 1a.
- **Penempatan (6m)**: tanya Reza kodenya di mana.
- **Dua cabang Reza** (`fitur/detail-pasien-…`, `checklist-pettycash` blade):
  hidup atau hapus.
- **Cabut `Admin*`**: satu kata.
- **Pemicu prod IT Tracker**: masih belum ditulis.

## 7. Accurate — resep Sabtu 5 Sep (Finance sudah ACC)

Terakhir ter-push **27 Agu** → jarak 9 hari → `--days=12` (lebih lebar aman;
perintahnya menghapus-lalu-menulis ulang hari yang berubah, jadi tak
menggandakan). Urutan **wajib**: `.env` prod `ACCURATE_PUSH_ENABLED=true` →
`php artisan config:clear` → **jalankan sekali manual**
`php artisan accurate:push-daily --days=12` dan **baca ringkasannya** → baru
biarkan cron 03.30. ⚠️ Main/prod belum punya pesan penasihat `--days` (sisa
cabang `fix/accurate-push-saklar`), jadi angkanya dari sini, bukan dari
perintahnya. ⚠️ `is_active` **tidak** memagari push — jangan memakainya.

## 8. Pembaruan 4 Sep malam — empat pertanyaan §6 terjawab

| Pertanyaan §6 | Jawaban |
|---|---|
| Versi PDF lain dengan endpoint pembayaran? | **Tidak ada.** Proposal v2 (diselamatkan ke `docs/api/`) menulis Fase 2 sebagai *"gambaran, spec menyusul"* — vendor menjawab persis yang diminta. Spek konkret kini ada: `docs/api/permintaan-omnicare-endpoint-pembayaran-2026-09.md`, siap dikirim. Tiket **1b tetap DITUNDA** sampai vendor menjawab |
| Penempatan (6m) kodenya di mana? | **Ditemukan**: `DigitalChecklistController::rolling*` + `cabangSesiUntuk()`. Ekky: mau dibuat **global**. Tiket 6m → *"Rolling global"*, **L**, pemohon HR/Ops, **REQ**; `rencana-penempatan.md` jadi bahan 02 |
| Dua cabang Reza | `checklist-pettycash` → **hapus** (blade sudah di main, SQL dilarang). `fitur/detail-pasien-…` → **cherry-pick `_modal_patient_detail.blade.php` saja** sebagai tiket S bila masih diinginkan, lalu hapus |
| Cabut `Admin*` | **Ya.** Boleh dikerjakan chat build |
| Pemicu prod | **Sesudah demo.** Dev = simulasi; **tiket dev dibuang**; tiket nyata dibuat di **prod** |

**⚠️ Konsekuensi untuk §5 (urutan pemuatan):** seluruh urutan itu dijalankan
**di PROD sesudah IT Tracker naik**, bukan di dev. Dev cuma untuk demo dan
latihan. Jangan memuat 30 tiket ke dev — semuanya akan dibuang.

**Gerbong prod IT Tracker** (hanya saat Ekky menyebut "prod"): merge
`feat/it-tracker` → `main` (**0 konflik**, diuji kering 4 Sep) → SQL berurutan
→ `access:rebuild` → deploy penuh → **rsync `assets/` saja** → `curl` beranda
200 + URL akhir. Enam fitur lain di UAT **tidak ikut otomatis**.

## 9. RALAT 4 Sep malam — §5 & §8 salah tafsir; dev DIISI sekarang

⛔ §8 menulis *"jangan memuat 30 tiket ke dev — semuanya akan dibuang"*. **Itu
tafsiran HUB atas kalimat Ekky, bukan keputusannya.** Ekky minta dev **diisi
antrian nyata untuk demo**. Yang berlaku sekarang:

- **Dev diisi malam ini** lewat seeder (prompt di `rencana-it-tracker.md`
  §14.21). Sumber kebenaran: **`seed-antrian-it-2026-09-04.psv`** (27 baris).
- **Tidak ada tiket yang "dipindahkan" ke prod dengan tangan.** Seeder yang
  sama dijalankan ulang di prod sesudah IT Tracker naik — idempoten by
  `seed_urut`, `masuk_at` dipertahankan, pemohon by email.
- Urutan §5 tetap berlaku sebagai **urutan baris di berkas**, bukan urutan
  pemuatan manual.

**Tiga tiket DICORET dari §3 — ternyata sudah di prod** (diukur
`merge-base --is-ancestor … 2400896`): **6d** libur cabang bertugas, **6e**
cuti/WFH tak memakan hari libur (`liburUntuk` ada di main), **6f**
ketersediaan dokter cuti vs jadwal khusus. Memori HUB tentang ketiganya basi;
sudah diralat.

**Yang sengaja TIDAK ada di berkas seed** (bukan lupa): COGS, Inventory, Slip
gaji — pemohonnya (Finance, Ops, HR) belum mengajukan; membuatnya atas nama
mereka melanggar prinsip §3. Kalau saat demo Executive bertanya *"COGS mana?"*,
jawabannya jujur: *"Finance belum mengajukan — dan itu yang papan ini
perlihatkan."*

**Angka yang akan tampil di panel beban dev sesudah seeding** (untuk dicocokkan
saat demo): **24 item aktif** (27 − 3 ditunda) · 3 ditunda beralasan · yang
memanas di luar IT: tiket 06 & 08 (UAT, menunggu HR/Robi), 11 (Antre >3 hari
sejak 10 Agu tidak memanas — Antre tak menunggu siapa pun) · tiket 04 di DEV
59 hari **tetap dingin** — tunjukkan itu saat demo.
