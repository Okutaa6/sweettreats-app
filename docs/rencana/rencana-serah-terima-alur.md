# Serah-Terima — Layar Alur Persetujuan + Delegasi

> Ditulis chat HUB **2026-09-01**. Ini **bukan** rencana membangun — fiturnya
> sudah jadi dan lulus uji di dev 22 Agustus (77 hijau, 4 temuan ditutup).
> Ini peta untuk **chat build** yang akan mendamaikannya dengan `main` yang
> sudah bergerak jauh, lalu menaikkannya.

## 1. Kabar baik dulu — ini tidak lagi memblokir apa pun

Dulu Konsultan disebut tak bisa berangkat tanpa Alur. **Itu sudah tidak
berlaku**: Konsultan naik prod 1 Sep (`c28b665`) karena chat pembangunnya
sengaja meng-commit hanya +11 baris miliknya sendiri, bukan berkas campuran.

Dan **`att_approver` di prod masih 0 baris** (terukur). Selama tak disentuh,
fitur ini **nol risiko** — tak ada delegasi yang hidup, tak ada rantai yang
bergantung padanya.

Jadi ini boleh dikerjakan kapan pun, tanpa tekanan.

## 2. Di mana barangnya sekarang

**Tidak ada yang terancam hilang.** Kelima berkas baru identik byte dengan yang
sudah di-commit di `wip/snapshot-konsultan-alur-2026-08-22` (`06905c4`) —
sudah diverifikasi dua sesi secara terpisah.

⚠️ Tapi **branch itu tak terpakai sebagai sumber merge**: ia potret working
copy bersama, jadi memuat Accurate, WiFi, dan belasan controller chat lain.
Ambil per-berkas (`git checkout 06905c4 -- <path>`), jangan di-merge.

### Berkas BARU (belum ada di git mana pun selain snapshot)

| Berkas | Baris |
|---|---|
| `app/Http/Controllers/Absensi/AlurPersetujuanController.php` | 572 |
| `app/Services/AlurPersetujuanService.php` | 503 |
| `tests/Feature/AlurPersetujuanLayarTest.php` | 699 |
| `tests/Unit/AlurPersetujuanTest.php` | 182 |
| `database/sql/2026_08_21_alur_persetujuan_delegasi.sql` | 94 |

### Berkas TERLACAK yang harus didamaikan dengan `main`

| Berkas | Baris beda |
|---|---|
| `resources/views/absensi/aturan.blade.php` | 469 |
| `app/Services/AttendanceRequestWorkflow.php` | **235** |
| `routes/web.php` | 67 |
| `app/Http/Controllers/Absensi/AturanKerjaController.php` | 40 |

Total **700 sisipan, 103 hapusan** di 4 berkas.

## 3. Yang sebenarnya sulit: `AttendanceRequestWorkflow`

Berkas ini memutuskan **siapa menyetujui cuti dan lembur siapa**. Tapi
mendamaikannya **lebih ringan dari kesannya**, dan ini sudah diperiksa:

✅ **Pagar Konsultan SUDAH ada di versi Alur** — `isPengamat` 3 rujukan,
`pastikanBolehMengajukan` 3, `rolePengamatIds` 2. Versinya dibangun **di atas**
`1213f19`, jadi tidak membatalkan gerbong Konsultan yang sudah di prod.
Di `git diff` mentah ada pasangan `-pastikanBolehMengajukan` /
`+pastikanBolehMengajukan` yang **terlihat seperti penghapusan** — itu cuma
pindah baris (400 → 299). Jangan panik membacanya.

✅ **Konstruktornya beropsional**: `__construct(?AlurPersetujuanService $graf = null)`
dengan `$this->graf = $graf ?? new AlurPersetujuanService()`. Pemanggil lama
tetap jalan tanpa diubah — tidak ada perubahan yang memecah.

**Yang baru dan perlu ditelaah** (lima metode privat + satu jadi publik):
`kumpulkanLapis()` · `terapkanKetidakhadiran()` · `penyetujuTakHadir()` ·
`delegasiAktif()` · `pastikanBolehMengajukan()` (pindah) ·
`indexLapis()` jadi `public static`.

Ditambah **pagar ketiga** yang dipasang chat Alur di sisi delegasi: delegat
yang jabatannya **pengamat DILEWATI** (`rolePengamatIds()` di `delegasiAktif()`).

## 4. SQL — lebih jinak dari dugaan

`2026_08_21_alur_persetujuan_delegasi.sql` **tidak membuat tabel baru**. Ia
hanya `ALTER TABLE att_approver` menambah `mulai`, `selesai`, `dibuat_oleh`,
lalu menukar index `uq_approver` → `uq_delegasi (user_id, approver_user_id,
level, mulai)` + `idx_delegasi_rentang`.

Semuanya **bersyarat lewat `information_schema`** → idempoten, aman diulang,
dan skip bersih di env yang belum punya kolomnya.

⚠️ **Arti kolom `att_approver` TERBALIK dari dugaan biasa**: `user_id` adalah
**penyetuju yang berhalangan**, bukan pengaju. Salah membacanya akan membalik
seluruh logika delegasi. Ini jebakan nomor satu di fitur ini.

## 5. Jebakan yang berlaku

- ✅ **RBAC segmen-1 TIDAK berlaku di sini.** Rutenya di bawah `absensi/`
  (`absensi/aturan/persetujuan`, `absensi/aturan/delegasi`) — segmen itu sudah
  punya node `access_path`. Tak perlu SQL akses baru.
- ⚠️ **Gerbang halamannya SATU konstanta**: `AturanKerjaController::BOLEH`.
  Menyalinnya ke tempat lain pernah menyebabkan **403 senyap**. Pakai konstanta
  itu lewat refleksi, jangan duplikasi daftarnya.
- ⚠️ **DUA jalan menuju lubang auto-approve**: mencabut baris rantai DAN
  menambahkannya. Ketidakhadiran penyetuju **tidak boleh mengosongkan rantai** —
  kalau kosong, `pastikanApexBukanSalahKonfigurasi()` menolak keras dan orang
  tak bisa mengajukan sama sekali.
- ⚠️ **`att_approval_rule.role` teks, butuh `COLLATE` saat di-JOIN.** Nol baris
  hasil JOIN bisa berarti "JOIN gagal", bukan "belum masuk rantai".
  **Cara membedakannya**: hitung LANGSUNG tanpa JOIN. Setelah gerbong Konsultan,
  patokan prod hari ini: Konsultan = **0** (disengaja, dua arah),
  Koordinator GS = **1 sebagai `role_id` + 1 sebagai `approver_role_id`**.
- ⚠️ Entri log `2026-08-21d` **bentrok** — ada dua entri berbeda dengan label
  itu (satu punya Alur, satu punya HUB yang sudah di main). Saat menggabungkan
  catatan, patok **judul penuh**, bukan tanggal; ganti label jadi `21e`.

## 6. Utang catatan yang ikut serah-terima

Tiga entri Alur **aman di snapshot tapi belum di `main`**:
`2026-08-22c`, `2026-08-22b`, `2026-08-21d`. Ikut disisipkan saat merge, dengan
hitung-header sebelum-sesudah (`grep -c '^## 2026'` harus bertambah persis 3,
dan `sort | uniq -d` tetap **kosong** — terukur nol hari ini).

## 7. Urutan yang disarankan

1. Branch bersih dari `origin/main`.
2. Ambil 5 berkas baru **per-berkas** dari `06905c4` — jangan merge branch-nya.
3. Damaikan 4 berkas terlacak; mulai dari yang mudah (`routes`,
   `AturanKerjaController`), simpan `AttendanceRequestWorkflow` terakhir.
4. Jalankan SQL di dev, lalu **uji terima §7 rencana induk**
   (`docs/rencana/rencana-alur-persetujuan.md`, 77 poin).
5. Kendali negatif wajib: **`att_approval_rule` prod tak berubah**, dan orang
   yang hari ini bisa mengajukan **tetap bisa** sesudahnya.
6. Prod hanya kalau Ekky menyebut "prod".

## 8. Prompt chat build

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini MENDAMAIKAN fitur Layar Alur
Persetujuan + delegasi dengan main yang sudah bergerak, lalu menaikkannya.
Fiturnya SUDAH JADI dan lulus 77 uji di dev 22 Agu — ini bukan membangun ulang.

BACA DULU:
· docs/rencana/rencana-serah-terima-alur.md   <- peta serah-terima (utama)
· docs/rencana/rencana-alur-persetujuan.md    <- rencana induk + uji terima
· memori project_alur_persetujuan
· memori project_konsultan_role               <- sudah naik prod, JANGAN dibatalkan

Barangnya ada di branch snapshot wip/snapshot-konsultan-alur-2026-08-22
(06905c4). Ambil PER-BERKAS (git checkout 06905c4 -- <path>), JANGAN merge
branch-nya: itu potret working copy bersama, memuat kerjaan chat lain.

Yang tak boleh salah:
· att_approver: user_id = PENYETUJU YANG BERHALANGAN, bukan pengaju. Terbalik
  dari dugaan biasa; salah baca membalik seluruh logika delegasi.
· Ketidakhadiran penyetuju TIDAK BOLEH mengosongkan rantai. Rantai kosong =
  pastikanApexBukanSalahKonfigurasi menolak keras, orang tak bisa mengajukan.
· Gerbang halaman = SATU konstanta AturanKerjaController::BOLEH. Menyalinnya
  pernah menyebabkan 403 senyap.
· Pagar Konsultan SUDAH ADA di versi Alur (isPengamat dkk). Di diff mentah
  pastikanBolehMengajukan terlihat dihapus - itu cuma pindah baris. Jangan
  membatalkan gerbong Konsultan yang sudah di prod.

Uji terima 77 poin di rencana induk, plus kendali negatif: att_approval_rule
prod tak berubah, dan orang yang hari ini bisa mengajukan tetap bisa sesudahnya.

Aturan kerja: dev = SATU working copy bersama, cek `git status --porcelain |
grep "^??"` dulu. STOP kalau `git checkout -B` MENOLAK - itu pengaman, JANGAN
diakali dengan `git reset --hard` (reset menimpa untracked tanpa peringatan).
Dev --no-dev jadi phpunit TAK ADA; kembalikan sesudahnya. phpunit dev mati di
lebih dari sekitar 17 uji ber-DB per proses -> jalankan PER KELAS.
Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```
