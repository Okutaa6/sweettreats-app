# Dua pagar kecil — WiFi dedup (IT-2026-002) & cabut Admin\* dari ROLE_PENGAWAS (IT-2026-026)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/prompt-build-wifi-dedup-role-pengawas.md`"**.
> Disusun chat HUB 17 Sep 2026. Basis: `origin/main` = `23fb5b1` (= prod).
> ⚠️ **PIC kedua tiket = Muhamad Ikhsan** (diperiksa di `it_ticket.pic_user_id`
> 17 Sep). Bukan Reza — jangan menunggu siapa pun.

---

## Kenapa dua-duanya di satu gerbong

Sama-sama kecil, sama-sama **belum ada di prod**, sama-sama harus **ditulis di
atas `main` sekarang** — bukan di-merge dari branch lama. Keduanya juga di
wilayah pagar/akses, jadi enak diuji bersama.

⛔ **Yang TIDAK boleh dilakukan: merge `origin/feature/wifi-belajar-sendiri`.**
Branch itu **303 commit di belakang main**, dan HUB sudah mencoba
`git cherry-pick 9fac952` ke main hari ini → **konflik di kedua berkas**.
Ditulis ulang lebih bersih daripada menyelesaikan konflik itu.

---

## BAGIAN 1 — WiFi: cegat pendaftaran jaringan yang sudah tercakup

### ⛔ BACA INI DULU: prod SUDAH punya pagar di titik yang sama

Ini sumber konfliknya, dan sumber salah paham kalau tidak dibaca.

`AbsensiBranchIpController::store()` (≈baris 119) dan
`PengawasanController` (≈baris 424) **sudah** memasang pagar:

```php
$bentrok48 = AttBranchIp::pemilik48Lain($data['ip'], $data['raw_branch_id']);
if ($bentrok48) {
    return back()->with('error', 'Blok /48 milik … sudah dipakai cabang …');
}
```

**Itu pagar yang BERBEDA, dan jangan disentuh:**

| | menolak apa | bahaya yang ditutup |
|---|---|---|
| **sudah ada di prod** `pemilik48Lain()` | /48 yang dipegang **cabang LAIN** | absen dua cabang **tertukar diam-diam** |
| **yang mau ditambah** `matchBranch()` | jaringan yang **sudah tercakup**, cabang mana pun | baris **kembar** menumpuk |

⛔ Yang di prod **lebih berbahaya kalau bocor** — ia menyangkut absen tercatat
di cabang yang salah. **Pertahankan, jangan diganti.** Yang diminta di sini
adalah **menambah pagar kedua SESUDAHNYA**, bukan memilih salah satu.

### Yang diminta

Di **kedua** controller, sesudah cek `pemilik48Lain` yang sudah ada:

```php
// Pencocokan IPv6 hanya membaca blok jaringannya, bukan alamat tiap HP — jadi
// jaringan yang sama bisa ditulis dengan tak terhingga alamat berbeda.
// Membandingkan TEKS saja membuat siapa pun bisa menambah baris untuk jaringan
// yang SUDAH tercakup, tanpa peringatan apa pun.
$cakupan = AttBranchIp::matchBranch($data['ip']);
if ($cakupan) {
    return back()->with('error', '…');
}
```

Kata-katanya beda per pintu, dan itu disengaja:

- **Halaman WiFi Cabang (IT)** — pembacanya paham peta jaringan:
  *"Jaringan ini SUDAH dikenali lewat baris `<ip>` (cabang `<nama>`). Menambah
  baris lagi tidak mengubah apa pun. Kalau jaringan ini seharusnya milik cabang
  lain, nonaktifkan dulu baris itu."*
- **Layar Pengawasan (SPV)** — pembacanya sedang menutup flag, bukan mengelola
  jaringan:
  *"Jaringan ini sudah dikenali lewat baris `<ip>` (cabang `<nama>`), jadi tak
  perlu didaftarkan lagi — flag ini tinggal diselesaikan."*

### Bukti bahwa ini nyata, bukan kehati-hatian

Diukur HUB di prod 17 Sep — jumlah baris IP per cabang:

```
Bintaro 18 · Sunter 11 · Kalideres 10 · Cinere 8 · Pamulang 7
```

Bintaro **18**. Catatan aslinya ditulis waktu daftarnya membengkak jadi **10**
dan dirapikan tangan 5 Agustus. Ia tumbuh lagi hampir dua kali lipat sejak itu,
karena pagar /48 tidak menahan duplikat **di dalam cabang yang sama**.

### ⚠️ Pagar yang salah di sini menolak orang yang benar

Ini jalur absen HP yang dipakai harian di lima cabang. Kalau `matchBranch`
terlalu galak, gejalanya bukan "daftar jadi rapi" melainkan **jaringan sah tidak
bisa didaftarkan, lalu orang tidak bisa absen**. Karena itu:

- Pesannya **wajib menyebut baris mana** yang sudah mencakupnya — supaya yang
  ditolak tahu harus melihat ke mana, bukan cuma ditolak.
- **Jangan** melebarkan atau mengubah `matchBranch()` sendiri. Ia sudah dipakai
  jalur absen; menyentuhnya mengubah siapa yang bisa absen. Panggil apa adanya.
- ⛔ **JANGAN cabut 10 baris manual 23 Agustus** dan **JANGAN cabut
  `ipv4Publik()`** (menutup CGNAT 100.64/10) — dua pagar itu sudah tercatat dan
  tidak tergantikan.

---

## BAGIAN 2 — cabut `Admin*` dari `ROLE_PENGAWAS`

Keadaan prod hari ini, `app/Services/ItTracker/ItTrackerService.php:29`:

```php
public const ROLE_PENGAWAS = ['Executive', 'Consultant', 'Konsultan IT',
    'Konsultan Finance', 'Konsultan Marketing',
    'Admin General', 'Admin Backup', 'Admin'];   // <- tiga ini dicabut
```

`ROLE_PENGAWAS` dipakai sebagai **daftar yang boleh memberi paraf Business
Owner** di IT Tracker (lihat `ItTrackerController` ≈843 & ≈1251 — docblock-nya
berbunyi *"Paraf satu Business Owner — Executive ATAU Konsultan (ROLE_PENGAWAS),
bukan IT"*). Akun `Admin*` bukan Business Owner, jadi kehadirannya di daftar itu
membuat paraf bisa diberikan pihak yang bukan sasarannya.

### ⚠️ Periksa dulu: siapa yang akan kehilangan akses

Sebelum mencabut, **hitung penghuninya di prod** dan laporkan angkanya:

```sql
SELECT g.nama, COUNT(u.id) AS orang
FROM <tabel_role> g LEFT JOIN users u ON u.group_id = g.id
WHERE g.nama IN ('Admin General','Admin Backup','Admin')
GROUP BY g.nama;
```

Kalau ada tiket yang **sedang menunggu paraf** dan satu-satunya calon parafnya
akun `Admin*`, mencabut sekarang membuat tiket itu **menggantung tanpa jalan**.
Cek itu dan katakan hasilnya sebelum mencabut — kalau ada, sebut nomor tiketnya.

⛔ Dan ingat pola yang sudah dua kali menggigit di repo ini: **gerbong yang
memindahkan hak akses = KODE DULU, BARU SQL.** Di sini tidak ada SQL sama
sekali (`ROLE_PENGAWAS` cuma konstanta PHP), jadi tidak ada urutan yang bisa
terbalik — tapi jangan tergoda "sekalian" mengubah role di DB.

---

## Uji yang HUB harapkan

**WiFi** (belum ada uji sama sekali untuk jalur ini):

1. Daftar IP yang jaringannya **sudah tercakup** baris lain → **ditolak**, dan
   pesannya menyebut baris yang mencakupnya.
2. Daftar IP jaringan **baru** → **diterima** (kendali positif — ini yang
   memastikan pagarnya tidak terlalu galak).
3. Pagar `/48` lama **masih menolak** /48 milik cabang lain (kendali regresi —
   membuktikan pagar yang sudah ada tidak tergeser).
4. Ketiganya di **kedua** pintu: `AbsensiBranchIpController` dan `Pengawasan`.

**ROLE_PENGAWAS:**

5. Akun `Admin General` **tidak lagi** bisa memberi paraf BO.
6. `Executive` dan `Konsultan *` **masih bisa** (kendali positif).

⚠️ `phpunit` di dev tumbang di >±17 uji ber-DB per proses — **jalankan per kelas**.

⚠️ Uji apa pun yang menyentuh `Schema::hasTable()` **mematikan mysqld lokal**.
Kalau ujinya lewat route yang memanggil itu, pakai SQLite in-memory atau panggil
helpernya langsung.

## Catatan deploy

- **Nol SQL.** Nol `public/assets/**` → **tidak perlu rsync**.
- Gerbong kecil; HUB sarankan naik **sendiri**, bukan menempel pada gerbong lain
  — supaya kalau ada yang tidak bisa mendaftarkan WiFi besoknya, penyebabnya
  tidak tercampur.

## Kalau ada yang ditolak

Kalau kalian tidak setuju — terutama soal **menambah** pagar alih-alih
menggantinya — katakan alasannya. Bukan tidak mungkin ada sisi yang HUB tak
lihat. Yang penting jangan dibiarkan tanpa keputusan.
