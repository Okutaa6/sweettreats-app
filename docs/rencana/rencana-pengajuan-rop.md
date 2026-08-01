# Rencana: Pengajuan / ROP (Request of Payment)

> Status: DRAFT — planning selesai dibahas 2026-07-31, belum coding.
> Pemilik keputusan: Ekky (bisa ubah SOP). Finance mengikuti sistem dulu.
> Launch: BARENG adopsi Petty Cash (dua-duanya = sisi "pengeluaran" Data Center).

## 1. Konteks & masalah

Data Center sudah pegang sisi **pemasukan** (Omnicare, terbukti match Accurate
sampai rupiah). Sisi **pengeluaran** dibagi dua:

| Jalur | Untuk apa | Status |
|---|---|---|
| **Petty cash** | operasional kecil/tunai di cabang | fitur sudah ada, adopsi belum (prod 0 trx aktif, dev 2 trx tes) |
| **Pengajuan (ROP)** | kebutuhan yang dibayar transfer bank oleh Finance | masih KERTAS (Formulir A "Request of Payment") — fitur ini |

Masalah proses kertas:
- Harus print + isi manual, tanda tangan keliling.
- Tidak bisa di-track "sudah di-ACC belum" → orang lupa, pengajuan hilang.
- Finance sulit menentukan prioritas mana yang harus dibayar duluan.
- Praktik nyata: dari 4 kolom tanda tangan, **yang kepakai cuma "Made by (User)"**
  sebagai pertanggungjawaban — sisanya formalitas yang tidak jalan.

Visi jangka panjang (fase 2+): Finance **tidak input manual ke Accurate lagi** —
pengeluaran lahir di Data Center → di-push ke Accurate (push engine sudah proven
di proyek accurate-integration) → di-pull balik sebagai pembanding. Muter dari
sini, balik ke sini.

## 2. Siapa boleh mengajukan (v1)

Hanya pemegang departemen — **bukan** semua karyawan:

| Pengaju | Role | Cost centre |
|---|---|---|
| Anisa (Nissa) | Supervisor | cabang (5) — titipan FO/GS/Sales lewat dia |
| Irma | Coordinator Dentist | cabang (kebutuhan asdent/dokter) |
| Yovanka | Head Marketing | MKT |
| Ekky / Muhamad Ikhsan | Head IT | IT |
| Reza | IT *(opsional — atau lewat Head IT)* | IT |
| Della | Finance | FIN |

Karyawan biasa → bilang ke atasannya (mis. FO ke Nissa), atasan yang membuat
pengajuan. Gate pakai mekanisme akses menu existing (`hasMenuAccess`), bukan
hardcode nama.

**Yang meng-ACC: Finance.** Executive + Finance dapat laporan.
- Threshold "> 2 jt ikut ACC Executive" = **FASE 2** (disiapkan kolomnya saja,
  logika belum). Belum ada SOP-nya — sistem dulu, SOP menyusul.
- Catatan four-eyes (fase 2 juga): pengajuan yang dibuat Finance sendiri
  idealnya di-ACC Executive, bukan dirinya.

## 3. Lifecycle status

```
diajukan ──▶ disetujui ──▶ dibayar ──▶ selesai
    │            (ACC Finance)  (bukti transfer)  (barang/dana diterima, oleh pengaju)
    └──▶ ditolak (wajib alasan) — pengaju bisa ajukan ulang (record baru, link ke lama)
```

- **dibayar ≠ disetujui**: ROP itu "request of payment", tracking harus sampai
  uangnya keluar. Upload bukti transfer di tahap ini (opsional v1, dianjurkan).
- **selesai** = pengganti kolom "Received by" di kertas — pengaju konfirmasi
  barang/dana diterima. Boleh di-skip Finance (tombol "tandai selesai").
- Edit setelah diajukan: tidak boleh (biar audit bersih) — tolak + ajukan ulang.

## 4. Prioritas untuk Finance

Dua sinyal, dua-duanya diisi pengaju:
- **`needed_by` (butuh kapan)** — tanggal. Antrian Finance di-sort dari sini.
- **`is_urgent`** — flag darurat (dipakai hemat; kelihatan mencolok di antrian).

Antrian Finance menampilkan **aging** ("diajukan X hari lalu") — pengajuan tua
naik sendiri, tidak ada yang tenggelam. Ini jawaban langsung buat pain point
"finance sulit prioritisasi".

## 5. Field formulir (mapping dari kertas Formulir A)

| Kertas | Digital | Catatan |
|---|---|---|
| No. | `number` auto | lihat §6 |
| Date | `created_at` | otomatis |
| Pay To | `vendor_id` ATAU `pay_to_text` | master vendor, seed dari 58 vendor Accurate; boleh free-text |
| Description | `description` | + `attachment_path` (foto penawaran/invoice vendor) |
| Amount | `amount` | rupiah |
| BUDGET NUMBER | `budget_ref` nullable | **disiapkan saja** — budgeting sisi Finance belum selesai; kolom hidup nanti |
| COST CENTRE | `cost_centre` | kode cabang/dept (§6) |
| Made by (User) | `requester_id` | satu-satunya "tanda tangan" yang beneran dipakai |
| Checked/Approved 4 kolom | `reviewed_by/at` (Finance) + `approved_by/at` (fase 2 Executive) | dikolaps sesuai praktik nyata |
| No. Acc / Debet / Credit | `coa_no` + `coa_name` | **diisi Finance saat ACC** (bukan pengaju) — dropdown akun beban dari `accurate_staging_glaccount`, **per buku cost centre** (lihat §6a) → output langsung Accurate-ready |
| Received by | status `selesai` | konfirmasi pengaju |
| BANK TRANSFER (cap) | status `dibayar` + `paid_proof_path` | bukti transfer |

## 6. Penomoran (usulan, per cost centre)

```
ROP/{CC}/{YYMM}/{NNN}   →  contoh: ROP/BIN/2608/001, ROP/IT/2608/003
```

- `CC` = cost centre: **BIN / SUN / KAL / PAM / CIN** (cabang) + **IT / MKT /
  FIN / HQ** (office). Sekuens reset per cost-centre per bulan.
- Enak dibaca, enak difilter per cabang, dan kolom cost centre kertas otomatis
  terwakili di nomor. Format kertas lama (`001/FA/I/2017`) tidak dilanjutkan.

## 6a. Akun beban diambil PER BUKU Accurate (temuan implementasi 2026-07-31)

Rencana awal menulis "dropdown 53 akun beban 6000xx". Saat dibangun, datanya
ternyata tidak sesederhana itu:

- `accurate_staging_glaccount` berisi gabungan **7 buku** (5 cabang + holding +
  testing) — 1.006 baris di lokal, 1.098 di dev. Jadi daftarnya **wajib**
  disaring, tidak bisa dipakai apa adanya.
- Angka 53 itu jumlah akun beban di **satu** buku. Kalau digabung lintas buku,
  lokal jadi 65 dan dev 78 — berbeda karena tiap cabang punya akun sendiri.
- **Yang berbahaya**: nomor akun yang sama BERARTI LAIN di buku berbeda.
  Contoh nyata `600010` — "Beban Tunjangan Karyawan" (bintaro), "Beban THR"
  (cinere), "Beban Training" (holding). 33 nomor akun punya nama berbeda
  antar buku.

**Keputusan:** daftar akun mengikuti buku Accurate milik cost centre-nya —
kolom `rop_cost_centre.accurate_slug` (cabang → buku cabang, IT/MKT/FIN/HQ →
`holding`). Menggabungkan daftar akan membuat Finance memilih arti yang salah
tanpa sadar, dan angkanya mendarat di pos keliru saat diekspor ke Accurate.

Nama akun ikut di-**snapshot** ke `rop_requests.coa_name` saat ACC, supaya
riwayat pengajuan tidak berubah arti kalau staging di-resync.

## 6b. Master vendor (temuan implementasi 2026-07-31)

- Tarikan `/api/vendor/list.do` berhasil: 57–62 vendor per buku, 6 buku
  terbaca (buku `testing` kredensialnya invalid — dilewati). Setelah dedup:
  **73 vendor unik**.
- Dedup memakai **NAMA**, bukan `accurate_id`. Sebabnya: id vendor Accurate
  hanya unik **di dalam satu buku** — id `300` dipakai vendor berbeda di
  sunter dan pamulang. Dedup per id membuat satu supplier muncul enam kali di
  combobox (dan sempat menabrak unique constraint saat pertama dijalankan).
- Konsekuensi fase 2: saat push ke Accurate dinyalakan, tiap buku butuh id
  vendor versinya sendiri → perlu **tabel pemetaan terpisah**, jangan mengubah
  tabel `vendors`. Di v1 vendor hanya bantuan pengetikan nama penerima.

## 7. Batas Petty Cash vs ROP (aturan yang ditulis di UI)

- **Petty cash**: pengeluaran tunai kecil di cabang, < Rp 300.000 (angka bisa
  digeser), langsung dicatat kasir cabang dari kas kecil.
- **ROP**: selain itu — semua yang butuh transfer bank / pembelian vendor /
  nominal besar.
- Dua fitur dirilis bareng sebagai paket "Pengeluaran"; halaman Finance kasih
  link silang keduanya.

## 8. Data model (SQL manual, konvensi `database/sql/`)

- `rop_requests` — number, requester_id (uuid), cost_centre, vendor_id nullable,
  pay_to_text, description, amount, needed_by, is_urgent, budget_ref nullable,
  status enum(diajukan/disetujui/ditolak/dibayar/selesai), reject_reason,
  coa_no nullable, attachment_path, paid_proof_path, reviewed_by/reviewed_at,
  paid_by/paid_at, resubmit_of nullable, timestamps + soft delete.
- `rop_status_log` — audit trail tiap transisi (siapa, kapan, dari→ke, catatan).
- `vendors` — master vendor (name, akun bank opsional), seed dari Accurate
  `vendor/list.do` (58 vendor). Dipakai juga nanti oleh push Accurate.
- Notifikasi pakai `AdminNotification` existing (pola `topup_requests` yang
  sudah jalan: pending → approved/rejected + notify).

## 9. Laporan

- **Antrian Finance** (burgundy, desktop): sort needed_by + aging + urgent,
  aksi ACC/tolak inline, isi `coa_no` saat ACC.
- **Rekap bulanan** untuk Executive + Finance: total per cost centre, per akun
  CoA, status breakdown, export CSV.
- **Riwayat pengaju** (HP-friendly): daftar pengajuanku + statusnya — ini yang
  membunuh "lupa & ga bisa tracking".

## 10. Fase

| Fase | Isi |
|---|---|
| **1 (build)** | form + lifecycle + antrian Finance + rekap + vendor master + notifikasi + 6 improvement di bawah. Tanpa threshold, tanpa budget, tanpa push Accurate |
| **2** | threshold ACC Executive (> 2 jt), four-eyes Finance, budget_ref hidup (nunggu Finance), push otomatis ke Accurate (other-payment/purchase) + pull pembanding, multi-item per pengajuan, rekening vendor tersimpan, dashboard spending Executive |

### Improvement v1 (di-ACC user 2026-07-31)

1. **"Ajukan lagi"** — duplikat pengajuan lama sebagai template (pengeluaran
   repetitif: Shopee 45×, supplier dental bulanan, galon/listrik).
2. **Deteksi dobel** — saat submit, kalau ada pengajuan pending vendor sama +
   nominal mirip → peringatan "sudah ada pengajuan serupa dari X". Anti double-pay.
3. **Nota pertanggungjawaban** — tahap `selesai` minta upload nota/kwitansi
   pembelian (beda dari bukti transfer Finance). Loop akuntabilitas tertutup.
4. **Reminder & auto-expire** — notif Finance kalau pengajuan nunggu > 3 hari
   (cron existing); 30 hari tak disentuh → kadaluarsa otomatis + notif pengaju.
5. **Export format impor Accurate** — dari daftar ROP `dibayar`, export
   Excel/CSV format impor resmi Accurate (blueprint template sudah ada di
   proyek accurate-integration). Jembatan sebelum push API fase 2 — manfaat
   "Finance gak ngetik ulang" terasa sejak fase 1.
6. **Foto lampiran via kamera HP + kompresi** — pakai ulang pola upload
   terkompresi dari absen HP selfie.

## 11. Dependensi & catatan deploy

- **Role**: Supervisor, Coordinator Dentist, Finance, Head IT, IT **belum ada di
  prod** — ROP prod satu gerbong dengan migrasi 8 role absensi
  (`docs/deploy/deploy-absensi.md`). Di dev/lokal role sudah lengkap.
- Data pembanding: April 2026 (bulan lengkap di Accurate) = 736 other_payment
  ±1,38 M + 55 purchase ±191 jt → volume ROP realistis diperkirakan puluhan/bulan.
- Tema (ACC 2026-07-31, tercatat juga di `docs/spec/peta-tema.md`): halaman
  **pengaju = COKELAT default** (dipakai lintas role, bukan orang Finance);
  halaman **Finance (antrian + rekap) = BURGUNDY** `#8A2E43`
  (`body.zona-finance` sudah ada di `public/assets/css/zona.css`).
  Device-intent: form + riwayat pengaju = HP-first; antrian + rekap = desktop.
- Desain UI lewat **claude.ai/design**: brief di
  `docs/brief-desain/design-brief-rop.md` (4 sesi, satu layar per sesi).
- Prompt chat build: `docs/spec/prompt-chat-baru.md` §D.
- Coding di **branch terpisah / chat terpisah** — bukan chat HUB.

## 12. Status implementasi (2026-07-31)

Backend fase 1 **selesai & diuji lokal** di branch `feature/rop` (worktree
`../sweettreats-rop`, base `feature/absensi` df8b6cb). Rincian & cara
melanjutkan: `docs/handoff/handoff-rop.md`.

| Bagian | Status |
|---|---|
| SQL skema (5 tabel) + akses menu | selesai, dijalankan di lokal |
| Lifecycle + audit log + notifikasi | selesai, diuji end-to-end lewat HTTP |
| Penomoran per cost centre per bulan | selesai (atomik, diuji balapan nomor) |
| Deteksi dobel, reminder, auto-expire | selesai, diuji |
| Puller vendor Accurate | selesai, 73 vendor tertarik |
| UI 4 layar | **selesai** — dibangun dari design handoff (lihat §13) |
| Export impor Accurate | CSV jalan, **header belum dicocokkan template resmi** |

**Yang masih perlu keputusan/berkas dari user:**
1. Berkas template impor resmi Accurate — **DIPAUSE 2026-08-01** (perlu dibahas
   dengan Finance dulu). Tidak memblokir apa pun. Selama pause tombolnya
   berlabel "Draf untuk Accurate" dan halaman rekap menyatakan berkasnya belum
   siap diimpor. Cara mencabut pause: `docs/handoff/handoff-rop.md` §6.
2. ~~Konfirmasi role antrian Finance~~ — **di-ACC user 2026-07-31**: tetap
   Finance + admin lintas-cabang + Head IT/IT (`RopService::ROLE_ANTRIAN`).
3. **Kode cost centre Kalideres/Pamulang/Cinere**: rencana ini (dan kode yang
   sudah dibangun) memakai `KAL` / `PAM` / `CIN`; design handoff memakai
   `KDL` / `PML` / `CNR`. Kode ini masuk ke nomor ROP yang jadi No Bukti di
   Accurate, jadi sebaiknya diputuskan sebelum dipakai produksi. Mengubahnya
   nanti berarti ada dua gaya nomor di riwayat.

## 13. UI (2026-07-31)

Dibangun ulang di Blade dari `design_handoff_rop` (README high-fidelity +
7 screenshot HP + 3 prototipe HTML). Yang diterapkan: 3 kartu angka yang
sekaligus filter, kartu pengajuan dengan baris *nudge*, master–detail di layar
lebar, pintasan "Ajukan lagi", input rupiah besar + chip cepat, peringatan
dobel dua-klik, layar sukses, antrian Finance berkelompok per tingkat mendesak
dengan subtotal, badge umur bertingkat, drawer ACC dengan saran akun beban
(dari pemakaian terakhir untuk penerima yang sama) dan riwayat vendor.

CSS-nya di-scope ke `.rop-surface` / `.rop-fin`, **bukan** body-class, supaya
tidak menyentuh `layouts/app.blade.php` yang sedang dipakai reskin Kehadiran.

Ambang deteksi dobel mengikuti handoff: selisih ≤ 15% ATAU ≤ Rp 50.000 —
dua-duanya, karena persentase saja terlalu ketat untuk nominal kecil dan
terlalu longgar untuk nominal besar.
