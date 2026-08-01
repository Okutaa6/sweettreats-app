# SOP Absensi & Cuti — skenario dunia nyata

Dokumen ini menjawab pertanyaan "kalau X gimana?" untuk sistem absensi HP,
rekonsiliasi, pengajuan, dan jatah cuti. Format tiap skenario:
**apa yang sistem lakukan otomatis** vs **apa yang harus dilakukan manusia**
(karyawan / SPV / Finance / IT). Dokumen kerja — tambah skenario baru begitu
muncul. Disusun 2026-07-25.

Prinsip induk yang mendasari semua di bawah:
1. **Sistem menunjukkan, manusia memutuskan.** Kejanggalan jadi *flag* buat
   direview, bukan blokir otomatis.
2. **Deklarasi manusia menang atas scan.** Pengajuan yang di-ACC mengalahkan
   apa pun yang dikatakan mesin.
3. **Tidak ada kegagalan-senyap.** Kalau sistem tak bisa memproses sesuatu,
   itu muncul sebagai flag/laporan, bukan hilang diam-diam.

---

## A. Absen harian

### A1. HP karyawan mati / ketinggalan / rusak saat mau absen
- **Sistem:** tak ada scan HP hari itu. Kalau dia juga tak scan fingerprint,
  rekonsiliasi pagi mencatatnya **alpa**.
- **Manusia:** karyawan mengajukan **Koreksi Absen** (dari /akun) untuk hari
  itu dengan alasan, atau absen di mesin fingerprint cabang (masih jalan
  paralel). SPV/atasan ACC → hari itu otomatis dihitung ulang jadi hadir.
- **Catatan:** selama fingerprint masih hidup, ini jaring pengaman utama.

### A2. WiFi cabang mati / lemot / ganti provider
- **Sistem:** absen **tetap berhasil** (WiFi cuma saksi, bukan gerbang). IP
  tak cocok → lahir flag "IP asing", tapi absennya sah dan tercatat.
- **Manusia:** SPV di Pengawasan melihat kumpulan flag IP asing di hari itu →
  selesaikan borongan sebagai "wajar" (satu klik untuk semua). Kalau WiFi-nya
  ganti permanen, IT daftarkan IP baru di menu **WiFi Cabang**, atau SPV pakai
  tombol "Daftarkan IP ini ke cabang" langsung dari flag.

### A3. Absen dari rumah / pakai kuota (bukan di cabang)
- **Sistem:** tercatat + flag "IP asing" / "seharian di luar jaringan".
- **Manusia:** SPV menilai — wajar (WFH sah) atau pelanggaran. Sistem tidak
  menghakimi, cuma menyorot.

### A4. Absen pakai laptop/desktop (harusnya HP)
- **Sistem:** tercatat + flag "perangkat desktop" (perangkat_ganda / desktop).
- **Manusia:** SPV cek. Ini sinyal keras karena selfie di depan webcam laptop
  kantor = pola titip-absen klasik.

### A5. Lupa absen pulang
- **Sistem:** sweep pagi (06:20) menaikkan flag "lupa absen pulang".
- **Manusia:** SPV konfirmasi jam pulang sebenarnya; karyawan bisa ajukan
  Koreksi Absen kalau perlu jam pulang tercatat (mis. untuk klaim lembur).

### A6. Satu HP dipakai dua orang absen (titip absen)
- **Sistem:** flag "satu perangkat dua akun" (kecuali pasangan fleksibel kantor
  yang memang berbagi perangkat — itu dikecualikan).
- **Manusia:** SPV investigasi; ini indikasi kecurangan.

### A7. Orang kantor / ABG / management (fleksibel)
- **Sistem:** scan mereka tercatat sebagai **jejak saja** — tak pernah dinilai
  telat/alpa, tak pernah kena flag. Anisa & Irma pengecualian: office tapi
  tetap dinilai baku + berhak lembur.
- **Manusia:** tak perlu tindakan rutin.

---

## B. Pengajuan (cuti / izin / libur / WFH / lembur / koreksi)

### B1. Ngajuin cuti untuk KEMARIN (backdate) — HP mati, lupa, dsb.
- **Bisa.** Form tidak melarang tanggal lampau.
- **Sistem:** setelah di-ACC final, hari itu **dihitung ulang otomatis** —
  kalau tadinya tercap alpa, berubah jadi cuti; saldo cuti terpotong. (Fix
  2026-07-25: hook re-reconcile saat approval.)
- **Batas:** kalau tanggal itu ada di **periode gaji yang sudah ditutup**,
  sistem TIDAK mengubah angka beku (dilindungi). Finance harus buka periode
  dulu bila memang perlu koreksi retroaktif.

### B2. Lembur kemarin lupa diajukan (backdate lembur)
- **Bisa**, sama seperti B1. **Lebih aman** dari cuti-backdate: bukti scan
  masih ada, jadi klaim lembur tetap bisa disandingkan dengan scan (jam masuk/
  pulang asli) saat approver menilai. Setelah di-ACC, jam lembur masuk att_day
  dan muncul di Lembar Gaji.
- **Manusia:** approver melihat "dukungan scan: N menit" sebagai info, lalu
  memutuskan angka yang di-ACC.

### B3. Lembur KHUSUS untuk orang kantor / management (yang normalnya tak dapat lembur)
- **Bisa.** Form tidak memblokir berdasarkan role.
- **Sistem:** karena mereka fleksibel (tak punya baseline jam kerja), klaim
  **tak bisa disandingkan otomatis dengan scan** — verifikasinya "tak bisa
  dicek dari scan" (bukan ditolak). Jadi ini murni **keputusan manual approver**.
- **Manusia:** atasan yang menyetujui memasukkan jumlah jam/menit lembur
  khusus itu manual dan meng-ACC. Cocok untuk lembur eksepsional yang memang
  tak punya pola scan.

### B4. Cuti seminggu penuh tapi karyawan tak buka aplikasi
- **Sistem (saat ini):** kalau tak ada pengajuan, hari-harinya bisa tercatat
  alpa. Modul checklist punya kasus serupa (excuse per hari) — **keputusan
  produk terbuka** (dibawa ke Reza/management).
- **Manusia:** SPV/Finance bisa entri koreksi. Idealnya karyawan ajukan cuti
  sebelum berangkat.

### B5. Pengajuan ditolak atasan
- **Sistem:** status rejected, tidak ada efek ke att_day maupun saldo cuti.
- **Manusia:** karyawan dapat notifikasi + alasan; bisa ajukan ulang.

### B6. Sakit / izin
- **Sistem:** tipe *izin* menutup status alpa untuk hari itu **tapi TIDAK
  memotong jatah cuti** (hanya tipe *cuti* yang memotong).

---

## C. Jatah cuti

### C1. Karyawan cuti tapi saldo sudah habis / minus
- **Sistem:** pengajuan **tidak diblokir**. Saldo boleh minus → tampil merah
  "melebihi jatah N hari — ganti hari" di halaman Akun-nya dan di rekap Finance.
- **Manusia:** ini artinya karyawan mengganti dengan hari kerja. Finance
  memantau; saat karyawan masuk di hari liburnya, Finance catat **Koreksi →
  Kembalikan (ganti hari)** untuk mengembalikan saldo.

### C2. Cuti bersama (mis. Idul Fitri, 17 Agustus)
- **Sistem:** Finance buka menu Cuti → **Cuti bersama** (wizard 3 langkah):
  pilih tanggal + label + berapa hari → semua pemegang jatah tercentang →
  hilangkan centang yang dikecualikan → terapkan. Semua terpotong sekaligus.
- **Undo:** ada tombol **Urungkan** di notifikasi kalau salah — membatalkan
  tepat batch itu saja.
- **Pengecualian belakangan:** kalau ada satu orang yang ternyata ganti hari,
  Finance pakai **Koreksi → Kembalikan** di drawer orang itu.

### C3. Ganti kontrak (perpanjangan 12 bulan)
- **Sistem:** kalau tanggal kontrak sudah diisi → reset otomatis di tanggalnya
  (scheduler harian 01:20): saldo kembali **12**, sisa positif **hangus**,
  utang (minus) **kebawa** (saldo awal baru = 12 dikurangi utang).
- **Kalau tanggal kontrak belum diisi:** tak ada reset otomatis (badge kuning
  "Tanggal belum ada"). Finance bisa **Mulai kontrak baru** manual per orang.
  Begitu tanggalnya ketemu dan diisi, otomatisasi nyala sendiri.

### C4. Eksekutif / direksi
- **Sistem:** tak punya jatah, tak muncul di halaman Cuti, tak pernah kena
  cuti bersama. Mereka juga tak dinilai absen.

### C5. Karyawan baru masuk
- **Manusia:** IT buat akun (Pusat Akun). Untuk cuti, Finance **Mulai kontrak
  baru** untuk orang itu (saldo 12), isi tanggal mulai kontrak bila ada.
- **Catatan:** saldo pembuka 23 orang lama sudah di-import dari Excel; orang
  baru ditambahkan manual.

### C6. Karyawan resign / dinonaktifkan
- **Sistem:** saat akun dinonaktifkan (Pusat Akun), posisi checklist-nya
  ditutup dan perangkat "diingat" dicabut. Dia tak lagi muncul di rekap aktif.
- **Manusia:** Finance selesaikan sisa administrasi cuti/gaji manual sebelum
  akun dinonaktifkan.

---

## D. Finance — tutup buku & bayar gaji

### D1. Alur bulanan Finance
1. **Beresin flag** di Pengawasan (sistem memblokir tutup periode selama masih
   ada flag terbuka).
2. **Kehadiran** — cek matriks bulan, klik sel untuk detail + foto.
3. **Rekap Persetujuan** — semua pengajuan di-ACC, klaim lembur vs scan.
4. **Lembar Gaji** — satu tabel per karyawan: hari hadir, telat (hari &
   menit), lembur (jam, sudah di-ACC), alpa, cuti/izin/libur/wfh, sisa cuti.
   **Ekspor CSV** → buka di Excel, kalikan tarif masing-masing (sistem tak
   tahu nominal rupiah — penggajian masih manual).
5. **Cuti** — cek saldo & potongan bila perlu.
6. **Periode Gaji** — tutup periode → semua angka bulan itu **beku**, aman
   jadi dasar hitung gaji.

### D2. Kenapa harus beresin flag & tutup periode dulu
- Angka yang belum beku masih bisa berubah (mis. ada ACC backdate masuk).
- Tutup periode = pagar: setelah itu, ACC backdate untuk tanggal di periode
  itu tak lagi mengubah angka (dilindungi). Jadi gaji yang sudah dihitung
  tak bergeser diam-diam.

### D3. Ada koreksi masuk setelah periode ditutup
- **Sistem:** menolak menyentuh angka beku.
- **Manusia:** Finance **buka periode** dulu (Periode Gaji) bila memang perlu
  koreksi retroaktif, proses, lalu tutup lagi. Keputusan sadar, bukan otomatis.

---

## E. Batas yang diketahui (v1) — perlu keputusan/penyempurnaan

1. **Potongan cuti menghitung hari kalender** dalam rentang pengajuan, belum
   melihat jadwal kerja/libur. Cuti Jumat–Senin = 4 hari termasuk Minggu.
   Kalau kebijakannya "hanya hari kerja", ini penyempurnaan berikutnya.
2. **Cuti seminggu penuh tanpa pengajuan** (B4) bisa jadi alpa — keputusan
   produk (selaras dengan kasus checklist day-excuse).
3. **Sistem penggajian (rupiah) belum ada** — Lembar Gaji sengaja berhenti di
   jam/hari; perkalian tarif manual oleh Finance.
4. **Absen HP masih paralel dengan fingerprint** sampai lampu hijau
   stakeholder. Importer CSV tetap sebagai cadangan.
5. **Setengah hari cuti** belum didukung penuh di UI (kolom desimal sudah siap).

---

## F. Peran singkat

| Peran | Tugas absensi/cuti |
|---|---|
| Karyawan | Absen selfie HP · ajukan cuti/izin/lembur/koreksi · lihat sisa cuti |
| SPV (Anisa) | Review flag di Pengawasan · ACC pengajuan bawahan |
| Finance | Tutup periode · Kehadiran · Rekap Persetujuan · Lembar Gaji · kelola Cuti |
| IT (Ekky/Reza) | WiFi Cabang · Pusat Akun · akses penuh semua halaman |
| Eksekutif | Di luar sistem absensi & cuti |
