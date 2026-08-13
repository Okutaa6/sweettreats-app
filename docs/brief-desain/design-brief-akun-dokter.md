# Design Brief — Akun Dokter (SweetTreats)

> Untuk claude.ai/design. Konteks teknis lengkap:
> `docs/rencana/rencana-akun-dokter.md`.
> Sistem desain yang berlaku: `docs/spec/peta-tema.md` + `docs/spec/pola-interaksi.md`.

## 1. Siapa yang memakai

**Enam dokter gigi** di klinik SweetTreats, gelombang pertama. Mereka bukan
karyawan kantor — mereka datang untuk praktik, mengerjakan pasien, lalu pulang.

Yang membedakan mereka dari pengguna aplikasi ini yang sudah ada:

- **Praktik di lebih dari satu cabang.** Empat dari enam pindah-pindah antara
  Pamulang, Bintaro, atau Cinere dalam minggu yang sama. "Hari ini saya di mana"
  adalah pertanyaan nyata bagi mereka, bukan basa-basi.
- **Membuka dari HP, di sela pasien.** Bukan duduk di depan komputer.
  Kemungkinan besar sambil berdiri, beberapa detik, di antara dua tindakan.
- **Bukan pengguna harian aplikasi.** Mereka takkan belajar navigasi. Kalau
  jawabannya tak terlihat di layar pertama, mereka berhenti memakai.

## 2. Yang mereka butuhkan, urut kepentingan

**① Hari ini** — jadwal praktik hari ini (jam berapa, cabang mana) dan **siapa
saja pasien yang sudah booking**. Dugaan kami inilah yang paling sering dibuka,
lebih sering daripada jadwal mingguan: dokter yang baru sampai ingin tahu apa
yang menunggunya.

**② Absen** — masuk & pulang, lewat selfie. Ini alasan awal akun dibuat.

**③ Jadwal saya** — minggu/bulan ini, lintas cabang, termasuk jadwal khusus
(pengganti/tambahan yang menimpa pola mingguan).

**TIDAK termasuk**: cuti (dilewati untuk sekarang), gaji, checklist.

## 3. Tantangan desain yang sebenarnya

Ini bukan sekadar "halaman profil". Tiga hal yang membuatnya tak sepele:

**Jadwal lintas cabang harus terbaca sekilas.** Seorang dokter bisa praktik
Senin di Pamulang, Rabu di Bintaro, Sabtu di Pamulang lagi. Menampilkan itu
sebagai daftar biasa membuat cabangnya tenggelam — padahal justru cabang yang
paling gampang salah diingat. Bagaimana membuat "di mana" sekuat "kapan"?

**Jadwal khusus menimpa jadwal rutin.** Ada 57 baris pengecualian di sistem —
hari yang jamnya berubah atau dipindah. Layar harus menunjukkan yang BERLAKU,
bukan dua lapis yang saling bertentangan, tapi tetap jujur bahwa hari itu
berbeda dari biasanya.

**Daftar pasien hari ini: seberapa banyak yang ditampilkan?** Nama dan jam
sudah pasti. Tapi layanan yang dipesan? Catatan? Terlalu sedikit jadi tak
berguna, terlalu banyak jadi tak terbaca di HP sambil berdiri — dan ini data
pasien, jadi menampilkan berlebihan di layar yang dibuka di ruang praktik
punya risikonya sendiri.

## 4. Yang sudah ada dan sebaiknya diikuti

Aplikasi ini punya sistem desain yang matang; layar dokter harus terasa
sekeluarga, bukan tempelan.

- **Palet**: cokelat `#461500` terkunci sebagai warna utama, krem sebagai latar.
- **Zona pribadi sudah punya bentuknya** — halaman `Absensi Saya` dan
  `Absen HP` (selfie) sudah dipakai harian oleh ±16 karyawan. Layar absen dokter
  sebaiknya **memakai ulang** alur itu, bukan bikin baru.
- **Kamus pola interaksi P1–P6** (chevron, toast + urungkan, tombol mati yang
  bilang kenapa) — ada di `docs/spec/pola-interaksi.md`.
- **Ikon**: FontAwesome 5.8.2 (bukan 6) + SVG garis minimalis. **Hindari emoji**
  — pernah dicoba dan hasilnya meleset dari maksud desainnya.

## 5. Yang diharapkan dari handoff

Layar untuk **HP lebih dulu** (itu perangkat sebenarnya), desktop menyusul.

1. **Beranda dokter** — "hari ini" sebagai fokus utama
2. **Jadwal saya** — mingguan/bulanan lintas cabang, termasuk jadwal khusus
3. **Absen** — kalau memang perlu berbeda dari layar absen karyawan yang sudah
   ada; kalau tidak, katakan saja bahwa yang lama sudah cukup

Keadaan yang mudah terlupa tapi akan sering terjadi: **hari libur praktik**
(dokter tidak dijadwalkan hari itu), **jadwal khusus**, dan **belum ada pasien
yang booking**. Ketiganya perlu punya tampilan yang tidak terasa seperti error.

## 6. Angka nyata untuk mengukur rancangan

Bukan data karangan — ini isi produksi per 12 Agu 2026:

- 6 dokter gelombang pertama; **4 di antaranya lintas cabang**
- Jadwal per dokter: **1 sampai 5** baris (jadi tak ada yang padat berlebihan)
- 103 jadwal praktik · 57 jadwal khusus · 261 layanan per dokter
- Nama dokter **panjang-panjang** — "drg. Maharetta Ditaprilia, Sp. Ort" dan
  "drg. Putri Moortiningsih Hasheila". Rancangan harus tahan nama sepanjang itu
  tanpa terpotong di HP.
