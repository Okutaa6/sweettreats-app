# Rencana: Pusat Akun — Sweet Treats

Draft v1 — 2026-07-24. Hasil diskusi Ekky + Claude. Status: **keputusan inti sudah
di-ACC user** (lihat §3), siap dibangun. Dikerjakan SAMBIL nunggu Reza selesai testing
dev (absen HP); semua di LOCAL dulu.

Nama resmi: **Pusat Akun** (pengganti section "User Access"). Sebutan pendek: "PA".

---

## 1. Tujuan

1. **IT bisa tahu sendiri** keadaan akun & akses tanpa bertanya ke AI/orang — "biar ga
   ngandelin AI terus" (kutipan user).
2. **Kegagalan-senyap ketahuan DULUAN** — satu jenis masalah sudah kejadian 8× di
   proyek ini (user terkunci, scan lenyap, menu hilang), semuanya tanpa error,
   semuanya ketemu karena digali manual. Halaman Kesehatan membalik itu.
3. **Monitoring user** — 36 akun dan bertambah; siapa aktif, siapa mati, perangkat apa.
4. **Jalur lupa-password yang realistis** — email karyawan (@sweettreats.id/.local)
   kemungkinan besar TIDAK punya mailbox → tombol "Lupa password?" via email itu
   pajangan buat staf internal. Jalur nyata: hubungi IT → IT reset dari Pusat Akun.

## 2. Kondisi sekarang (fakta kode, 2026-07-24)

| Halaman | Route | Kondisi |
|---|---|---|
| User | `setusermanagement` | CRUD lengkap; kolom Nama/Email/Role/Approved/Status/Last Login |
| Role | `setgroup` | CRUD + pohon hierarki (`_orgnode`) — wilayah Reza (user sudah kabari) |
| Akses | `setaccess` | CRUD access_path — RAWAN: salah edit = menu hilang senyap |
| Setting | `sysprofile` | Profil sistem, kecil |
| Audit log | `auditlog` | Route ada; HANYA rekam login/logout — filter CRUD-nya pajangan |

Akses section ini sekarang: 4 role admin saja. **Role IT & Head IT (akun karyawan)
belum punya** — padahal merekalah targetnya. (Catatan: admin & IT = Ekky+Reza juga,
akses penuh ini sekalian eksperimen "seberapa jauh akses role IT".)

## 3. Keputusan TERKUNCI (ACC user 2026-07-24)

1. Nama **Pusat Akun**; menu: **Kesehatan · User · Role · Akses · Setting**.
2. **IT & Head IT dapat akses PENUH** (lihat audit log + semua aksi) — setara admin
   di section ini. Grant via SQL by-name + access:rebuild (pola absen HP).
3. **Reset password oleh IT**: generate **password sementara ACAK** (IT tidak pernah
   mengetik/tahu password pilihan orang) → kasih ke orangnya → login berikutnya
   **WAJIB ganti** (`must_change_password`). Reset juga mencabut semua perangkat
   "diingat" (sudah terpasang dari fitur absen HP).
4. **`last_seen_at`** ditambah (update dari AuthGuard, di-throttle ±5 menit) —
   monitoring "aktif barusan / hari ini / lama tak terlihat". `last_login` saja tak cukup.
5. **Aksi IT di halaman User**: reset password · aktif/nonaktifkan akun · cabut
   perangkat diingat · **paksa logout** (invalidasi sesi via stempel, cek di AuthGuard).
   SEMUA aksi tercatat di audit log.
6. **Audit log diperluas — FASE 2, dibatasi yang sensitif keamanan**: perubahan akun,
   role & grant akses, pencabutan perangkat, penyelesaian flag absen. Data bisnis
   (reservasi/pasien) = fase 3 kalau kepakai. + kebijakan retensi (arsip >6 bulan).
7. **Testing di LOCAL dulu**; deploy nyusul bareng ritme dev/prod absensi.
8. **Ticketing = fitur TERPISAH**, diparkir — bahas sendiri nanti, jangan numpang di sini.
9. Re-skin = re-skin: **CRUD yang jalan TIDAK ditulis ulang**, cuma ganti kulit + insight.
10. Visual via claude.ai/design — brief: `docs/design-brief-pusat-akun.md`.

## 4. Halaman

### 4a. Kesehatan (BARU — bintangnya)

Kartu detektor, tiap kartu: angka + daftar terdampak + "cara benerin". Sumber query:
`docs/deploy-absensi.md` §9 (sudah ditulis, tinggal dijadikan halaman):

| Detektor | Menangkap | Sudah kejadian |
|---|---|---|
| Landing terkunci | `landing_page` role menunjuk halaman yang tak di-grant → user 403 selamanya | 3× |
| Scan yatim | user punya att_scan tapi tanpa `att_user_rule` → kehadiran lenyap senyap | 2× (538 scan) |
| Grant belum rebuild | group_access DB ≠ file cache `storage/app/group_access` | 1× |
| Node menu dobel | link sama id beda → pencabutan akses gagal senyap | 1× |
| Approval nyasar | att_approval_rule menunjuk role/UUID yang tak ada → auto-approve | 1× |
| Akun tak bertuan | aktif tapi tak pernah login ≥30 hari / tak pernah sama sekali | — |
| Perangkat menggantung | user_device hidup milik akun nonaktif | — |

Badge angka total masalah di sidebar (pola badge flag Pengawasan).

### 4b. User (redesign + insight)

- List: filter role/cabang/status, kolom indikator **aktif** (🟢 barusan · 🟡 hari ini ·
  ⚪ ≥X hari, dari last_seen_at), jumlah perangkat diingat, badge ⚠ kalau kena detektor.
- **Detail user** (baru, satu layar): identitas+role+cabang · perangkat diingat (label,
  terakhir dipakai, tombol cabut) · jejak login (dari audit_log) · tombol aksi (§3.5)
  dengan dialog konfirmasi · hasil reset = kartu password sementara (sekali lihat).

### 4c. Role — re-skin pohon hierarki + jumlah anggota per role + tanda ⚠ kalau
landing role tak di-grant (nyambung detektor).

### 4d. Akses — re-skin + GUARDRAIL: peringatan saat hapus/ubah node yang masih
di-grant ke role; tombol pintas "jalankan access:rebuild" setelah ubah (penyebab
kegagalan-senyap #3); penanda node yatim.

### 4e. Setting — re-skin ringan, ikut keluarga.

## 5. Perubahan data

- `users` + `last_seen_at` datetime NULL · `must_change_password` tinyint default 0 ·
  `force_logout_at` datetime NULL (paksa logout: AuthGuard menolak sesi yang login
  sebelum stempel ini).
- Fase 2 audit: `audit_log` + kolom `target_type`/`target_id`/`changes` (json) — mekanisme
  observer di model sensitif (Users, GroupPath, GroupAccess, AccessPath, UserDevice,
  AttFlag) supaya tak menyentuh puluhan controller.
- SQL deployable satu file `2026_07_xx_pusat_akun.sql` (idempotent, by-name grants,
  rename node "User Access"→"Pusat Akun" + node Kesehatan + grant Head IT/IT).

## 6. Usul tambahan (user "open banget" — status: OPSI, belum diputuskan)

- **Lihat-sebagai (view-as)**: IT membuka ringkasan menu/akses yang DILIHAT role
  tertentu tanpa login jadi dia — senjata paling ampuh buat debug "menu gak muncul".
  (Versi aman: render daftar menu dari cache akses role, BUKAN impersonation penuh.)
- **Export CSV** daftar user (buat audit/roster).
- **Onboarding cepat**: buat akun → kartu info siap-salin (email + pw sementara +
  wajib ganti) — cocok dengan gelombang akun baru pas migrasi prod.
- Ticketing (parkir, bahasan terpisah).

## 7. Fase build

| Fase | Isi | Catatan |
|---|---|---|
| **A. Kesehatan** | halaman detektor + badge sidebar + node akses/grant | nilai terbesar, nol risiko nabrak; MULAI DI SINI |
| **B. User** | last_seen_at + list baru + detail + 4 aksi + reset flow + must_change_password + paksa logout | inti monitoring |
| **C. Re-skin sisanya** | Role + Akses (+guardrail) + Setting + rename section | koordinasi Reza (sudah dikabari user) |
| **D. Audit fase 2** | observer model sensitif + kolom baru + UI riwayat + retensi | setelah A–C stabil |
| **E. Opsi §6** | kalau di-ACC | — |

Design handoff bisa nyusul kapan pun — backend A & B tidak menunggunya (pola absen HP:
logika duluan pakai tampilan polos, kulit dipasang saat handoff datang).

## 8. Batasan & pengaman

- CRUD lama tidak ditulis ulang; route lama tidak pindah (setusermanagement dkk tetap).
- Semua aksi IT tercatat audit (termasuk SIAPA me-reset password siapa).
- Password sementara: acak, tampil SEKALI, tidak disimpan plaintext, wajib diganti.
- Halaman Kesehatan read-only terhadap data (mendeteksi, tidak mengubah) — aksi
  perbaikan tetap lewat halaman masing-masing, biar tak ada tombol ajaib merusak.
- Local dulu; deploy ikut gerbong absensi (nunggu Reza, merge kita yang pegang).

## 9. Kaitan fitur lain

- **UI kelola perangkat remember-me** yang dijanjikan di rencana-absen-hp → rumahnya
  DI SINI (detail user §4b).
- Monitor flag absen TETAP di Pengawasan (wilayah Anisa) — Kesehatan tak mencaploknya.
- Jatah cuti: menunggu 4 jawaban Finance/Dani, digarap setelah ini.
