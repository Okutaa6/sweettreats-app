
---

# Tindak lanjut — perbaikan Reza diperiksa (2026-08-06 sore)

Branch `fixing-pettycash-checklist` sudah diperiksa & **digabung ke dev**.
Terima kasih — perbaikannya tepat sasaran, dan saya uji perilakunya (bukan
cuma baca kodenya).

**SUDAH BERES & terbukti bekerja**: backfill form mingguan lama (A1) · minggu
approved tak turun jadi draft (A2) · cabut pengecualian tak mengunci minggu
(A3) · pengecualian manual tak tertimpa (C5) · nama diketik + nama akun
ditampilkan berdua (C1) · lima temuan tombol Reset ditutup gerbang fitur.

**SATU HAL SAYA UBAH DI ATAS PERBAIKANMU** — tolong dibaca, ini penting:

Gerbangnya kamu set `env('APP_ENV') === 'local'`. Masuk akal, TAPI **produksi
kita ternyata berjalan dengan `APP_ENV=local`** (temuan 2026-08-06, belum
dibereskan karena berisiko menggeser hal lain). Artinya default itu akan
MENYALAKAN tombol hapus-permanen data uang di produksi.

Saya ubah jadi daftar-putih: `env('PETTY_CASH_RESET_ENABLED', false)` — mati
kecuali dinyalakan eksplisit. Dev sudah dinyalakan lewat `.env`, jadi alat
bersih-bersihmu tetap ada seperti biasa.

Aturan yang enak dipegang ke depan: **fitur destruktif pakai daftar-putih
(harus dinyalakan), bukan daftar-hitam (mati kalau kebetulan terdeteksi
produksi)**. Lingkungan yang salah label lalu jadi aman, bukan terbuka.

**BELUM BERES (aman karena di balik gerbang):** kalau kelak reset perlu hidup
di prod, empat ini harus dibereskan dulu — cek periode-tertutup, hitung-ulang
saldo bulan berikutnya, jejak audit siapa/kapan/berapa, dan mempersempit
"Reset Riwayat Review" yang sekarang masih menghapus seluruh cabang sepanjang
masa padahal judulnya "terakhir".
