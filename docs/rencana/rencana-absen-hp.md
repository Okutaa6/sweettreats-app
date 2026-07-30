# Rencana: Absen HP (Selfie) — Sweet Treats

Draft v1 — 2026-07-24. Hasil diskusi Ekky + Claude (3 ronde). Status: **nunggu review Ekky,
lalu sebagian keputusan nunggu meeting management (§11)**. Nol kode sampai plan di-ACC.

Posisi dokumen ini terhadap `rencana-rekonsiliasi-absensi.md`: rencana rekonsiliasi
membereskan HILIR (data mesin → reconcile → rekap → periode). Dokumen ini membereskan
HULU: dari mana scan berasal. Semua yang di hilir TIDAK berubah.

---

## 1. Ringkasan satu paragraf

Staff absen masuk/pulang lewat HP masing-masing (web DataCenter, zona pribadi), dengan
**selfie langsung dari kamera** (bukan upload galeri). Bukti lokasi = **IP jaringan WiFi
cabang** (server otomatis lihat IP publik tiap request — bukan fitur khusus, cara kerja
internet). Bukti identitas = wajah di selfie. Absen **tidak pernah ditolak** oleh sistem;
yang janggal cuma **ke-flag** dan masuk antrian review SPV (Anisa). Sistem ini menulis ke
`att_scan` dengan `source='hp'` — jadi buat engine yang sudah ada, dia cuma "mesin absen
ke-6". Reconciler, aturan per-orang, approval, rekap, periode: nol perubahan.

## 2. Kenapa dulu ditolak, dan apa yang berubah

Absen HP pernah **ditolak** (rencana rekonsiliasi §Fase E) dengan 2 alasan. Keduanya
tergarap di desain ini:

| Keberatan lama (§Fase E) | Jawaban desain ini |
|---|---|
| Fake GPS tak terdeteksi dari web app | GPS **tidak dipakai sama sekali**. Bukti lokasi = IP jaringan cabang |
| Titip-login: password bisa dipindah | Selfie live = wajah nempel di tiap absen. Password bisa dititip, muka nggak |

Kejujuran yang tetap berlaku: **sidik jari lebih kuat sebagai bukti identitas** daripada
selfie (selfie bisa dikadalin foto-dari-foto — mitigasi di §8). Yang ditukar dengan
kelemahan itu: data real-time (upload manual 5 cabang hilang), risiko edit file hilang
(rekonsiliasi §9.2), lintas cabang otomatis, nol biaya hardware (vs ganti 5 mesin ADMS).
Skala 29 karyawan + SPV yang kenal semua muka bikin review manusia feasible.

## 3. Prinsip desain (disepakati)

1. **WiFi = SAKSI, bukan GERBANG.** Jaringan lemot/mati tidak pernah menghalangi absen —
   absen via data seluler tetap diterima, cuma ke-flag. Tidak ada jalan buntu.
2. **Sinyal numpuk, masing-masing nutup lubang yang lain:**
   - Selfie live (getUserMedia, tanpa galeri) → SIAPA
   - Jam server (bukan jam HP) → KAPAN
   - IP publik request = IP terdaftar cabang → DI MANA (+ cabang ke-tag otomatis)
3. **Poster = titik absen resmi (SOP), bukan bukti kriptografis.** Selfie diambil
   membelakangi poster → semua foto satu cabang berlatar sama → foto yang salah latar
   mencolok seketika saat review. Poster nipu mata? IP tetap nyala. IP diakalin? latar
   tetap salah. Independen.
4. **Flag, bukan blokir.** Mesin ngamatin, manusia (Anisa) nafsirin. Tidak ada hukuman
   otomatis.
5. **Yang TIDAK dibangun & alasannya** (biar nggak diusulin ulang):
   - QR statis → bisa difoto sekali, dipakai selamanya. Setara nol.
   - QR dinamis → IP sudah menjawab "di mana" tanpa upacara scan-ganti-kamera. Pintu
     darurat per-cabang KALAU ada cabang yang WiFi-nya terbukti tak bisa diandalkan.
   - GPS → alasan penolakan lama tetap benar; nambah popup izin tanpa nilai.
   - Face recognition otomatis → bukan fase awal. Review manusia dulu; skala 29 orang.

## 4. Arsitektur: "mesin ke-6"

```
HP staff ──selfie+POST──▶ endpoint absen ──▶ att_scan (source='hp')  ──▶ AttendanceReconciler
                              │                                            (TIDAK DIUBAH:
                              ├─▶ att_hp_bukti (foto, ip, sinyal)           gabung lintas cabang,
                              └─▶ att_flag (kalau ada yang janggal)         dedup ≤5mnt, dawn-
                                                                            pushback, telat/alpa)
```

- Dedup ≤5 menit yang sudah ada otomatis menangani orang yang selfie DAN tap fingerprint
  (masa paralel) — tidak dobel.
- Cabang pada scan diisi dari IP yang cocok. IP tak dikenal → staff pilih cabang manual
  + flag.
- Aturan per-orang tetap dari `att_user_rule` — fleksibel vs baku (lihat §9).

## 5. Skema data baru (4 tabel, `att_scan` tidak berubah strukturnya)

Nama final boleh digeser pas build; ini bentuknya:

**`att_branch_ip`** — daftar IP terdaftar per cabang (data, bukan kode — §7 halaman admin)
- `id`, `raw_branch_id`, `ip`, `label` ("jalur utama Cinere", "ruang dokter"), `status`
  (aktif/nonaktif), `last_seen_at` (di-update tiap ada absen yang cocok), `created_by`, ts.
- Satu cabang BOLEH banyak IP (multi-jalur ISP). Banyak SSID ≠ banyak IP — yang dihitung
  jumlah langganan internet.

**`att_hp_bukti`** — bukti tiap absen HP (1 baris per selfie)
- `id`, `user_id`, `att_scan_id` (FK ke scan yang ditulis), `jenis` (masuk/pulang),
  `photo_path`, `ip`, `branch_id_cocok` (nullable — hasil pencocokan IP),
  `device_id` (dari cookie perangkat "Ingat perangkat ini", nullable),
  `taken_at` (JAM SERVER), ts.

**`att_flag`** — antrian review Anisa
- `id`, `user_id`, `work_date`, `jenis` (lihat §8), `ref_type`/`ref_id` (umumnya ke
  att_hp_bukti), `status` (**baru → dicek → selesai**), `hasil` (**wajar /
  perlu_koreksi / pelanggaran** — wajib diisi saat selesai), `catatan`,
  `ditindak_oleh`, ts.
- Kaitan periode: **`att_period` tidak bisa di-close selama masih ada flag terbuka di
  rentangnya** (atau minimal warning keras — diputuskan pas build). Flag di periode
  closed ikut beku. Ini garis finis alami antrian Anisa, nunut ritme cut-off 25.

**`user_device`** — remember me ("Ingat perangkat ini")
- `id`, `user_id`, `token_hash` (token acak, yang disimpan HASH-nya), `label` (dari
  user-agent), `last_used_at`, `revoked_at`, ts.
- Mekanisme: checkbox di login → cookie 90 hari → sesi expired tapi cookie valid → masuk
  otomatis. **Token diputar tiap dipakai.** Dicabut saat: logout manual, ganti password,
  akun nonaktif. UI kelola perangkat (lihat daftar + cabut paksa) = jodohnya fitur
  **user management IT** — fase 1 cukup mekanismenya jalan.
- Konteks masalah: sesi sekarang idle-timeout 1 jam (AuthController `expires_in` +
  AuthGuard refresh) + SESSION_LIFETIME 120 — staff yang buka cuma 2×/hari PASTI login
  ulang tiap absen. Ini akarnya, bukan takdir.
- SOP: centang hanya di HP PRIBADI. Komputer FO rame-rame jangan (di sana juga nggak
  perlu — dipakai terus, nggak pernah idle 1 jam).

## 6. Foto: ambil, kompres, simpan, hapus

- **Ambil**: getUserMedia (kamera depan) di halaman — BUKAN `<input type=file>`, jadi
  tidak ada jalur galeri. Butuh HTTPS (dev & prod sudah).
- **Kompres di HP sebelum kirim** (canvas → JPEG): ~640px sisi panjang, kualitas ~70% →
  **40–80 KB/foto**. Kekirim di WiFi megap-megap; retry otomatis kalau gagal.
- **Volume**: 29 orang × 2 × 26 hari ≈ 1.500 foto ≈ 60–120 MB/bulan. Bukan masalah.
- **Retensi (proposal)**: purge (atau kecilkan ekstrem) setelah periode gaji closed
  + 2 bulan. Final angka → keputusan management (§11).

## 7. Layar (fundamental di sini; VISUAL via claude.ai/design — lihat §12)

| # | Layar | Pemakai / perangkat | Isi inti |
|---|---|---|---|
| 1 | **Absen** (dari Absensi Saya / FAB) | semua karyawan / HP | kamera live, tombol Absen Masuk / Absen Pulang, state: izin kamera → preview → kirim → sukses (jam server + cabang terdeteksi) / gagal-retry. Kalau IP tak dikenal: pilih cabang manual + tetap lanjut |
| 2 | **Review SPV** | Anisa / desktop | (a) **strip foto harian per cabang** — semua selfie hari itu dijejer, latar poster seragam bikin anomali nongol sendiri; (b) **antrian flag** = daftar kerja yang dikosongin, flag serentak satu cabang DIGRUPING ("8 flag Sunter pagi ini" = WiFi mati → beres sekali klik); (c) riwayat per orang sekali klik (flag bulan ini, % single-scan); (d) tombol pintas **"daftarkan IP ini ke cabang X"** dari flag |
| 3 | **Admin IP cabang** | IT | CRUD `att_branch_ip`. Ganti WiFi / tambah cabang = urusan data, BUKAN deploy |
| 4 | **Login** | semua | + checkbox "Ingat perangkat ini" |
| 5 | **Koreksi Absensi** | semua / HP | tipe `att_request` ke-6 (desain SUDAH ada di handoff lama) — jalur resmi kalau absen gagal total. TERSERAP ke build ini |

Zona: layar 1/5 = **drop-up akun (zona pribadi, HP)** — sesuai prinsip shell. Layar 2/3 =
menu role (desktop).

## 8. Flag: jenis, siklus, celah yang diakui

**Jenis flag (fase 1):**

| Jenis | Nangkep apa |
|---|---|
| `ip_asing` | IP bukan IP terdaftar cabang mana pun — sinyal utama "absen dari luar" |
| `perangkat_ganda` | satu device dipakai absen ≥2 akun di hari sama (titip absen versi baru; kebaca dari cookie perangkat) |
| `pola_ip` | orang sama ke-flag ip_asing ≥3× sebulan (kebiasaan, bukan insiden) |
| `luar_seharian` | masuk DAN pulang dua-duanya dari IP asing di hari sama |
| `jam_janggal` | absen masuk sebelum 05:00 (nyambung aturan dawn-pushback engine) |
| `single_scan` | lupa absen pulang (BAKAL jadi flag tersibuk minggu-minggu awal — normal, mendidik kebiasaan; jangan auto-hukum) |

**BUKAN flag:** absen di cabang lain dari cabang asal — sah & sering (Rokhmatun dkk.),
cukup tercatat cabangnya (`cabang_terlibat` di rekap sudah ada).

**Siklus:** baru → dicek → selesai (hasil wajib: wajar / perlu_koreksi / pelanggaran +
catatan). Pelanggaran terekap per orang per periode → Finance & Dani. Lingkaran belajar
IP: flag → "jalur sah belum terdaftar" → tombol daftarkan → besok nggak ke-flag.

**Celah yang DIAKUI + mitigasi (bukan teknologi, kebiasaan murah):**
foto-dari-foto (A di cabang pegang HP si B, nunjukin foto muka B) — semua sinyal hijau.
Penutup: strip foto harian (cabang 5–7 orang, ngelirik 20–30 detik; foto-dari-foto
kelihatan aneh: bingkai HP, pantulan, muka datar) + sampling acak sesekali (deterrence) +
selama paralel, selfie-ada-tapi-fingerprint-nggak = anomali yang kelihatan sendiri di
perbandingan periode. Batas sistem yang disepakati jujur: absen = bukti DATANG & PULANG,
bukan bukti kerja seharian — itu wilayah **checklist harian** (9 kategori 24 task + foto
bukti) + SPV. Absen + checklist + approval = satu paket bukti, tiga pertanyaan beda.

## 9. Orang fleksibel (7 office): jejak iya, penilaian tidak

Keputusan: mereka **ikut absen** (perlu jejak hadir) lewat halaman yang sama persis.
Bedanya: **semua flag di-suppress** dan engine tetap tidak menilai (tak ada telat/alpa —
`att_user_rule` tipe `fleksibel` sudah menjaga; WFH tetap dari form approved). IP mereka
nggak cocok cabang = bukan anomali, mereka memang boleh dari mana saja. Satu sistem, dua
arti; artinya diturunkan dari aturan per-ORANG yang sudah ada — **nol logika khusus nama
orang** (pelajaran Anisa/Irma).

## 10. SOP v1 (draft — dibawa ke management)

**Titik Absen:** satu poster resmi per cabang (desain unik per cabang — nama gede +
warna beda), permanen di area FO / dekat pintu staff, **dalam jangkauan WiFi, cahaya
cukup**. Poster nyantumin 3 langkah absen + kontak SPV kalau gagal.

**Cara absen:** (1) sampai cabang → konek WiFi cabang; (2) buka Absensi Saya → Absen
Masuk → selfie membelakangi poster, muka jelas; (3) pulang: Absen Pulang sebelum
meninggalkan cabang.

**Kalau gagal** (HP mati / kuota habis / WiFi mati): lapor SPV **hari itu juga** +
ajukan Koreksi Absensi begitu bisa.

**Larangan:** nitip akun/HP (pelanggaran serius, setara nitip absen lama); absen di
luar titik absen tanpa alasan (ke-flag, SPV nanya).

**Aturan perangkat:** "Ingat perangkat ini" hanya di HP pribadi.

## 11. Fase build & rollout

| Fase | Isi | Keluar dengan |
|---|---|---|
| **0. Design** | design brief (§12) → claude.ai/design → handoff balik | mockup layar 1–3 |
| **1. Inti** | endpoint absen + att_hp_bukti + att_branch_ip (+admin) + flag engine + remember me + layar Absen | staff bisa absen di local/dev, scan masuk reconciler |
| **2. Pengawasan** | layar review Anisa (strip + antrian + gruping) + Koreksi Absensi + guard flag-vs-close-periode | Anisa bisa kerja by-exception |
| **3. Pilot** | SATU cabang (usul: Bintaro — kantor pusat, Ekky & Reza di lokasi), **paralel fingerprint**, 1 periode gaji penuh, bandingkan hasil reconcile mesin vs selfie orang-per-orang | dua kolom angka buat stakeholder |
| **4. Rollout** | 4 cabang lain, tetap paralel | — |
| **5. Green light** | SEMUA stakeholder setuju → baru bicara pensiun mesin (dan keputusan ADMS kemungkinan gugur sendiri: nol rupiah vs ganti 5 mesin) | — |

Catatan build: trusted proxies dipastikan (IP asli klien, bukan proxy) · jam SELALU
server · route ikut pola segment-1 + access path (pelajaran AuthGuard) · bahasa layar =
awam.

## 12. Design brief (buat claude.ai/design — alur yang sudah terbukti di shell/absensi)

Fundamental & logika dikunci dokumen ini; visual dilempar ke claude.ai/design (keputusan
Ekky, sama seperti shell + Absensi Saya + approver desktop). Bagian Claude Code: nulis
brief per layar (isi, state, batasan teknis: getUserMedia, kompresi canvas, mobile-first
layar 1/5, desktop layar 2/3) + konsep poster per cabang (atau poster ke tim kreatif
Yovanka — belum diputus). Hasil design balik ke Claude Code buat implementasi.

## 13. Pertanyaan terbuka

**Agenda SATU meeting management (sekali duduk, banyak keputusan):**
1. Dokter ikut absen atau di luar scope? (17 dokter ada di mapping mesin)
2. Jatah cuti — 4 pertanyaan yang sudah tergali (jatah & basis? reset kapan? carry-over?
   siapa boleh adjust saldo?) — fitur berikutnya, nunggu Finance & Dani
3. Kriteria green light pilot (angka apa yang bikin stakeholder bilang "lulus"?)
4. Retensi foto final (usul: purge setelah periode closed + 2 bulan)
5. ACC SOP v1 (§10) — termasuk aturan "wajib WiFi cabang" sebagai SOP baru

**PR lapangan (santai, nggak ngeblokir):**
- Catat kasar per cabang: langganan internet berapa jalur? (SSID banyak ≠ jalur banyak;
  Cinere 4 SSID kemungkinan 1 jalur = 1 IP). Sisanya kebentuk sendiri via lingkaran
  belajar flag di minggu pertama pilot.

**Keputusan kecil pas build:** close periode di-BLOK vs warning keras kalau ada flag
terbuka · nama-nama tabel final.

## 14. Hubungan dengan pekerjaan lain (biar nggak ketinggalan)

- **Koreksi Absensi** (utang lama, tipe ke-6, desain ada) → terserap fase 2.
- **User management IT** → dapat 2 penghuni baru dari sini: kelola perangkat remember-me
  + monitor flag. Katalog kegagalan-senyap tetap antre di fitur itu.
- **Jatah cuti** → numpang agenda meeting §13, build-nya setelah ini.
- **Hotfix sweet-mates** → tetap utang prod, nunggu Reza. TIDAK terkait build ini.
- **Checklist harian** → pasangan resmi absen (bukti kerja vs bukti datang) — §8.
- Utang lama yang tetap kecatat: data scan dev kosong, 3 node menu garis-miring dev,
  7 temuan audit (`deploy-absensi.md` §10), warning jadwal khusus dokter.
