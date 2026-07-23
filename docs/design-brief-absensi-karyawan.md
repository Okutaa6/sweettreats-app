# Design Brief — Surface Karyawan (Absensi Saya) · Desktop + Header

Brief untuk **claude.ai/design**. Yang didesain: **surface self-service karyawan** untuk
absensi — versi **desktop** (yang belum ada) + **satu pola header konsisten** yang dipakai
lintas halaman personal (Absensi Saya, Checklist Saya, Persetujuan).

Versi **mobile-nya sudah jadi & disukai** (lihat "Acuan mobile" di bawah). Yang kurang:
1. Di **desktop** tampilannya masih kolom mobile ngambang di tengah — belum manfaatin lebar layar.
2. **Header nggak konsisten**: halaman Absensi pakai kartu-coklat-sebagai-header (tanpa top-bar),
   tapi halaman Checklist pakai top-bar standar + kartu sapaan. Perlu **satu pola**.

---

## 0. Konteks & aturan

- Ini aplikasi admin klinik gigi multi-cabang (5 cabang), multi-role. Halaman ini dipakai
  **karyawan operasional** (General Services / Front Office / Assistant Dentist) — **bukan orang
  yang tech-savvy**. Bahasa & UI harus **sederhana, jelas, ramah**.
- **Mobile-first** (karyawan buka di HP sela kerja) TAPI **harus nyaman juga di desktop/PC**
  (sebagian ngajuin dari komputer).
- **Warna netral/themeable** (ikut shell): basis cream + satu aksen coklat hangat (default).
  Jangan hardcode warna lain. Font **Plus Jakarta Sans**.
- Halaman ini **duduk di dalam shell** yang sudah ada (sidebar kiri + drop-up akun). Yang didesain
  = **isi + header halaman**, bukan sidebar.

---

## 1. Isi surface "Absensi Saya"

Elemen (semua sudah ada datanya):
- **Identitas + navigasi bulan**: avatar + nama + jabatan·cabang + lonceng notif; tombol ‹ Bulan Tahun ›.
- **Ringkasan bulan**: 4 angka (Hadir / Telat / Alpa / Izin-Cuti) + 2 tile (Total Telat menit,
  Lembur Disetujui jam).
- **Kartu "Hari Ini"**: jam Masuk → Pulang + status (hadir/telat/…), muncul kalau lihat bulan berjalan.
- **Toggle Harian / Kalender**.
- **Daftar hari (Harian)**: per tanggal — tanggal+hari, status (pill warna), jam masuk–pulang,
  menit telat. (Kalender = grid bulanan dot warna — opsional, boleh didesain.)
- **Riwayat Permohonan**: kartu pengajuan (jenis, tanggal, alasan, status Menunggu/Disetujui/Ditolak).
- **Tombol "＋ Ajukan Permohonan"** → buka **drawer kanan** berisi form (ini sudah jadi, jangan didesain ulang).

---

## 2. Yang diminta

### A. Layout DESKTOP (>1024px) — utama
Manfaatin lebar layar, jangan cuma kolom sempit di tengah. Saran arah (bebas dikembangkan):
- Header identitas full-width di atas.
- Lalu 2 area: **kiri** ringkasan bulan (angka + tile + Hari Ini + toggle), **kanan** daftar hari
  (list panjang) + riwayat. Atau tata letak lain yang lebih rapi & seimbang.
- Tetap terasa satu kesatuan, bukan dua pulau terpisah. Kasih breathing room (jangan mentok tepi).

### B. HEADER konsisten (desktop + mobile) — penting
Tentuin **SATU pola header** untuk semua halaman personal karyawan (Absensi, Checklist, Persetujuan),
supaya seragam. Pertimbangan:
- Di **mobile**: enaknya kartu-identitas jadi header (nempel atas, full-bleed) — app-like.
- Di **desktop**: ada shell top-bar; header halaman jangan dobel/berantakan sama itu.
- Butuh: identitas (nama·jabatan·cabang), konteks (mis. bulan / tanggal), aksi (notif).
- Kasih **satu jawaban** yang jelas: mobile begini, desktop begitu — biar bisa dipakai seragam.

---

## 3. Acuan mobile (sudah jadi — pertahankan rasanya)
- Header: kartu coklat gradient full-bleed nempel atas (avatar + nama + jabatan·cabang + lonceng),
  baris kedua nav bulan (‹ Juni 2026 ›).
- Konten: kartu putih rounded, nge-inset ~14px dari tepi, di atas kanvas cream.
- Pill status: Hadir hijau, Telat kuning, Alpa merah, Izin/Cuti biru, Libur abu.
- Aksen tombol utama: coklat brand.

---

## 4. Yang TIDAK didesain
- Sidebar / drop-up akun (shell — sudah ada).
- Drawer form "Ajukan Permohonan" (sudah jadi).
- Warna spesifik non-default (netral + aksen).

## 5. Deliverable
1. Desktop — Absensi Saya penuh (header + ringkasan + daftar hari + riwayat).
2. Header pattern — contoh dipakai di 2 halaman berbeda (mis. Absensi vs Checklist) biar keliatan konsisten.
3. Mobile — referensi singkat (biar developer nyambungin).
4. (Opsional) tampilan Kalender bulanan.

> Catatan developer (dari kami): tokens & shell sudah ada; mohon aksen diekspos sebagai satu
> variabel, dan header dibuat 1 pola reusable. Bahasa Indonesia yang awam.
