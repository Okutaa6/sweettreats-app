# Rencana: Jatah Cuti (per kontrak 12 bulan)

Status: DRAFT — nunggu ACC. Disusun 2026-07-25 dari diskusi + Excel roster cuti
(sheet cabang + HQ). Belum ada koding.

## Aturan main (hasil diskusi, sudah dikonfirmasi user)

1. **Jatah = 12 hari per kontrak 12 bulan.** Ganti kontrak → langsung dapat 12
   lagi. BUKAN per tahun kalender — tidak ada prorata Januari.
2. **Cuti bersama motong jatah semua orang** (contoh nyata di Excel: 20 Maret,
   24 Maret, 28 Mei muncul di hampir semua baris) — KECUALI kasus per-orang
   (mis. ganti hari). Jadi butuh input massal + pengecualian individual.
3. **Saldo boleh minus.** Konsekuensinya karyawan "nambah hari" (mengganti
   dengan hari kerja) — sistem TIDAK memblokir pengajuan saat saldo habis,
   cukup menampilkan minusnya terang-terangan ke karyawan + SPV/Finance.
4. **Eksekutif tidak dapat jatah** (Robi, Ira — mereka juga tidak dinilai
   absen). Implementasi: user tanpa baris saldo = di luar sistem cuti, UI
   menampilkan "—". Catatan: Anisa & Irma TETAP dapat jatah (ada di Excel HQ)
   walau fleksibel absen — fleksibel absen ≠ bebas jatah cuti.
5. **Tanggal kontrak: OPSIONAL, boleh nyusul.** User masih mencari datanya.
   Sistem jalan penuh tanpa itu (lihat §Desain). Hipotesis yang perlu
   diverifikasi HR: EMP ID kelihatannya menyandikan tanggal masuk pola
   `DDMMYY + urutan` (ANGGITA 24072301 ≈ 24-07-2023, RIZKA 19061401 ≈
   19-06-2014) — kalau benar dan perpanjangan selalu di tanggal yang sama,
   tanggal kontrak bisa diturunkan otomatis lalu tinggal dikonfirmasi.

**TERJAWAB (2026-07-25):**
- Sisa cuti positif **HANGUS** saat kontrak baru (reset ke 12). ✔
- Minus **KEBAWA sebagai utang** ke kontrak berikutnya. ✔ Implementasi: saat
  reset, periode baru `saldo_awal = 12 + saldo_akhir_lama` KALAU saldo akhir
  negatif; kalau positif, `saldo_awal = 12` saja.
- Kontrak **selalu diperpanjang di tanggal yang sama** per karyawan (tanggal
  beda-beda antar orang). Data tanggalnya masih dikumpulkan user — desain
  nullable tetap berlaku.

## Desain

### Skema (SQL langsung, pola proyek — bukan migration)

```
att_cuti_periode          -- satu baris = satu periode kontrak per karyawan
  id            bigint pk
  user_id       varchar   -> users.id (ON DELETE CASCADE)
  saldo_awal    tinyint   -- 12 normal; utk periode pertama = angka "Sisa Cuti"
                          -- dari Excel (saldo pembuka, riwayat lama tak perlu)
  kontrak_mulai date NULL -- boleh kosong (data lagi dicari)
  kontrak_akhir date NULL -- = mulai + 12 bln - 1 hari; kosong kalau mulai kosong
  status        enum('berjalan','selesai')
  UNIQUE (user_id, status='berjalan')  -- 1 periode berjalan per orang

att_cuti_mutasi           -- ledger; saldo = saldo_awal - SUM(hari)
  id            bigint pk
  periode_id    bigint    -> att_cuti_periode
  user_id       varchar   -- denormal biar rekap cepat
  tanggal       date      -- tanggal cuti-nya
  hari          decimal(3,1)  -- positif = memakai jatah; negatif = koreksi
  jenis         enum('cuti','cuti_bersama','koreksi','ganti_hari')
  sumber        enum('pengajuan','massal','manual')
  ref_id        varchar NULL  -- id pengajuan absensi (kalau dari alur ACC)
  keterangan    varchar(200) NULL
  dibuat_oleh   varchar NULL
  created_at    timestamp
```

Kenapa ledger, bukan satu kolom saldo: (a) audit alami — tiap potongan ada
jejak siapa/kenapa; (b) pengecualian cuti bersama = hapus/entry `ganti_hari`
negatif, bukan utak-atik angka; (c) minus otomatis kelihatan sejarahnya.

### Alur

- **Saldo pembuka (sekali):** import manual 29 baris dari Excel — periode
  `berjalan` per orang dengan `saldo_awal` = kolom Sisa Cuti, tanpa tanggal
  kontrak. 15-30 menit kerja, bukan proyek.
- **Pengajuan cuti karyawan:** numpang alur pengajuan absensi yang SUDAH hidup
  (ACC berantai via att_approval_rule). Saat pengajuan jenis cuti di-ACC final
  → mutasi `cuti` otomatis (1 baris per hari cuti). Ditolak = tidak ada mutasi.
- **Cuti bersama:** halaman admin (Finance + admin): pilih tanggal + label →
  preview daftar semua pemegang jatah → centang pengecualian (ganti hari) →
  terapkan = mutasi massal `cuti_bersama`. Pengecualian belakangan tetap bisa:
  tambah mutasi `ganti_hari` negatif per orang.
- **Reset kontrak:** kalau `kontrak_akhir` terisi → scheduler harian menutup
  periode lewat-tanggal & membuka periode baru: saldo 12 (sisa positif
  hangus), TAPI utang kebawa (saldo akhir minus → 12 dikurangi utangnya).
  Kalau tanggal kosong → tidak ada reset otomatis; Finance bisa
  "Mulai kontrak baru" manual per orang dari halaman rekap. Begitu data
  kontrak ketemu → isi tanggal, otomatisasi nyala. INI yang bikin data
  kontrak bisa di-skip tanpa menghambat apa pun.
- **Tampilan:**
  - Karyawan (HP, halaman Akun): kartu "Sisa cuti: N hari" + riwayat potongan.
    Minus tampil merah + kalimat awam "melebihi jatah N hari — ganti hari".
  - SPV/Finance: rekap semua karyawan (saldo, minus disorot, tanggal kontrak
    kosong disorot kuning sebagai data-belum-lengkap) + halaman cuti bersama.
  - Eksekutif: tidak punya baris → "—", tidak pernah kena cuti massal.

### Akses

Node baru di bawah Absensi: `absensi/cuti` (rekap + cuti bersama) — grant
Finance, Supervisor (lihat-saja?), admin. Karyawan tidak butuh node (saldo
nempel di halaman Akun yang sudah ada). Grant pakai pola BY NAME + rebuild.

## Batas sengaja (v1)

- Tidak menghitung ulang sejarah dari Excel — mulai dari saldo pembuka.
- Tidak ada setengah hari dulu (kolom decimal sudah siap kalau nanti perlu).
- Tidak menyentuh gaji/potongan — minus cuma informasi "ganti hari".
- Sakit/izin non-cuti TIDAK motong jatah (beda jenis pengajuan).

## Urutan kerja (setelah ACC)

1. SQL skema + import saldo pembuka dari Excel (kucocokkan nama → user_id).
2. Hook alur ACC pengajuan → mutasi otomatis.
3. Halaman rekap + cuti bersama (Finance) — skin cream handoff.
4. Kartu saldo di halaman Akun karyawan.
5. Scheduler reset (dorman sampai tanggal kontrak diisi).
6. Audit pass + test.
