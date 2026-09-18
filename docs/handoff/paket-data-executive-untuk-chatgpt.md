# Executive minta struktur + data laporannya untuk dikirim ke ChatGPT

> Disusun HUB 18 Sep 2026. **Prioritas di atas paket konsultan.**
> Jawaban singkat: **BISA, dan jauh lebih aman daripada permintaan konsultan** —
> asal yang dikirim keluarannya, bukan tabel sumbernya.

---

## 1. Kenapa permintaan ini justru yang paling aman

Laporan Executive **sudah berupa agregat**. Ia tidak pernah menampilkan satu pun
baris pasien. Diperiksa di kode (`ExecutiveReportController`, 1.038 baris):

| | Isi | Aman ke ChatGPT? |
|---|---|---|
| **Keluaran laporan** — 24 kumpulan agregat | omzet per cabang/bulan, target vs capaian, YoY, top perawatan, top dokter, bauran perawatan, diskon, promo, retensi, lintas-cabang, tren | ✅ **Ya** |
| **Tabel sumber** `omnicare_sales_detail` | **148.163** baris; kolom `p_name`, `p_email`, `p_id`, `sales_notes`, `raw_payload` | ⛔ **Tidak, sama sekali** |

**Ini garis pemisahnya, dan ini yang paling penting dari seluruh dokumen ini.**
Laporan Executive **tidak pernah menyentuh** kolom-kolom identitas itu — sudah
diperiksa, nol rujukan ke `p_name`/`p_email`/`phone`/`dob` di kedua controller.
Jadi mengirim **keluarannya** aman; mengirim **tabelnya** membocorkan nama dan
email 148 ribu transaksi pasien sekaligus.

Yang perlu Ikhsan pastikan cuma satu: Executive mengirim **hasil ekspor**, bukan
minta Reza men-dump tabelnya "biar lengkap".

## 2. Sumber laporannya cuma 4 tabel

Ini yang dia maksud "struktur data", dan ternyata kecil sekali — bukan 156 tabel:

| Tabel | Perannya di laporan Executive |
|---|---|
| `omnicare_sales_detail` | **23 rujukan** — tulang punggung: omzet, diskon, perawatan, dokter, cabang |
| `raw_branch_target` | target bulanan per cabang (pembanding capaian) |
| `raw_cus_reservation` | sisi reservasi (booking vs realisasi) |
| `raw_branch` | 5 cabang + pemetaan ke `omnicare_place_id` |

Dashboard Executive (`ExecutiveDashboardController`) menambah `omnicare_sync_log`,
`raw_cus_res_service`, `raw_cus_journey`, `raw_commission`.

**Untuk ChatGPT, struktur 4 tabel ini saja sudah cukup** — dan strukturnya boleh
dikirim apa adanya (nama kolom + tipe, tanpa isi). Ambil dari
`03-kamus-kolom.csv` di paket konsultan, saring 4 tabel itu.

⚠️ Satu catatan waktu mengirim struktur `omnicare_sales_detail`: kolom `p_name`,
`p_email`, `sales_notes`, `raw_payload` **ada di struktur** tapi **tidak dipakai
laporan**. Sebut itu ke ChatGPT, supaya ia tidak merancang metrik di atas kolom
yang memang sengaja tidak disentuh.

## 3. 24 kumpulan agregat yang dihasilkan laporan

Ini "data-data di report dia" yang dimaksud. Semuanya sudah dihitung di server
tiap kali halaman dibuka:

`rows` · `totals` · `patients` · `daily` · `monthly` · `yoy` · `hero` ·
`topTreatments` · `topDoctors` · `treatmentMix` · `discount` · `topPromos` ·
`retention` · `crossBranch` · `treatmentTrends` · `doctorTrends` ·
`patientByBranch` · `patientTrend` · `topDoctorsByPasien` · `branchComparison` ·
`branches` · `presets` · `spansMonth` · `filterId`

Satu-satunya yang memuat nama orang: `topDoctors` dan `topDoctorsByPasien` —
**nama dokter**, bukan pasien. Itu informasi profesional yang sudah tercetak di
poster jadwal di dinding tiap cabang. Kalau Executive mau ekstra hati-hati,
namanya bisa diganti "Dokter A/B/C" tanpa mengurangi nilai analisanya sedikit pun.

## 4. Cara mengambilnya — tiga jalan

Layar `report/executive` **belum punya tombol ekspor** (diperiksa: nol rujukan
export/unduh/csv di 1.905 baris blade-nya). Jadi:

| | Cara | Cocok untuk |
|---|---|---|
| **(a)** | Skrip sekali-jalan (§5) dijalankan Reza di prod → 24 berkas JSON | **butuh hari ini** |
| **(b)** | Executive salin-tempel angka dari layar ke ChatGPT | cepat, tapi capek & rawan salah ketik |
| **(c)** | **Bangun tombol "Ekspor Agregat"** di layar laporan | **jawaban sebenarnya** — lihat §6 |

HUB sendiri **tidak bisa menjalankan (a)** — pengaman otomatis menolak tujuh kali
(pemindaian kolom PII, tabel `users`, skrip penyamaran, pemindahan berkas,
penyimpanan skrip, penyalinan dump, dan terakhir ekspor agregat ini dengan alasan
"data exfiltration"). Izin di chat tidak mengangkatnya; perlu aturan di
`settings.json`, atau dijalankan orang lain.

## 5. Skrip untuk Reza

Inti caranya: **panggil controller aslinya**, jangan tulis ulang kuerinya — supaya
angka yang dikirim ke ChatGPT dijamin identik dengan yang dilihat Executive di
layar. Kalau ditulis ulang, dua angka akan berselisih dan tidak ada yang tahu mana
yang benar.

```php
<?php
// ekspor-agregat-executive.php — HANYA MEMBACA. Jalankan: php ekspor-agregat-executive.php
$root = '/home/u695655413/domains/app.sweettreats.id/laravel';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;

$out = '/home/u695655413/paket_executive';
@mkdir($out, 0755, true);

$req = Request::create('/report/executive', 'GET');
$req->setLaravelSession(app('session.store'));
app()->instance('request', $req);
app('session.store')->flush();
session(['group_name' => 'Super Admin']);  // hanya melewati gerbang menu; tak menyentuh tabel users

$data = app('App\Http\Controllers\ExecutiveReportController')->index()->getData();

// Pagar PII: TOLAK, jangan samarkan — kalau ada kunci identitas, berarti asumsinya salah.
$LARANG = '/p_name|patient_name|p_email|email|phone|telp|dob|birth|lahir|nik|ktp|alamat|address|sales_notes|raw_payload|password|token/i';
$temuan = [];
$periksa = function ($node, $jalur) use (&$periksa, $LARANG, &$temuan) {
    if (is_object($node)) $node = (array) $node;
    if (! is_array($node)) return;
    foreach ($node as $k => $v) {
        if (is_string($k) && preg_match($LARANG, $k)) $temuan[] = "$jalur.$k";
        $periksa($v, "$jalur.$k");
    }
};
foreach ($data as $k => $v) $periksa($v, $k);

if ($temuan) {
    echo "⛔ BATAL — ditemukan kunci beridentitas:\n  " . implode("\n  ", array_slice($temuan, 0, 20)) . "\n";
    echo "Lapor ke IT sebelum mengirim apa pun.\n";
    exit(1);
}

$isi = "# Paket agregat laporan Executive — " . date('d M Y H:i') . "\n\n";
foreach ($data as $k => $v) {
    if ($k === 'errors') continue;
    $json = json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents("$out/$k.json", $json);
    $isi .= sprintf("- `%s.json` — %d KB\n", $k, max(1, round(strlen($json) / 1024)));
}
file_put_contents("$out/00-ISI-PAKET.md", $isi);
echo $isi . "\n✅ selesai — " . $out . "\n";
```

Kalau skrip ini **berhenti dengan ⛔**, jangan dikirim — artinya ada kunci
beridentitas yang HUB tidak duga, dan asumsi di §1 perlu diperiksa ulang.
Sengaja menolak, bukan menyamarkan: pada tahap ini lebih baik gagal berisik.

Rentang tanggalnya mengikuti default layar. Untuk rentang lain, tambahkan
`$req = Request::create('/report/executive?place=<raw_branch.id>', 'GET')` atau
parameter rentang yang dipakai `resolveRange()`.

## 6. Yang sebenarnya perlu dibangun — dan kenapa

Executive tidak akan minta ini sekali. Begitu ChatGPT memberinya rancangan, dia
akan mau data bulan depan, lalu per cabang, lalu rentang lain. Skrip sekali-jalan
menyelesaikan hari ini; **tombol menyelesaikan selamanya.**

Usul tiket untuk chat build — kecil, dan pagarnya sudah jelas:

> **"Ekspor Agregat" di layar Laporan Executive.** Tombol yang mengunduh 24
> kumpulan agregat yang SUDAH dihitung halaman itu (JSON + CSV), mengikuti filter
> cabang & rentang yang sedang aktif di layar. **Wajib memakai data yang sama
> dengan yang dirender** — jangan tulis ulang kuerinya, kalau tidak angka ekspor
> dan angka layar akan berselisih. Nol kolom identitas pasien; kalau kelak ada
> agregat baru yang memuatnya, ekspor harus MENOLAK, bukan menyamarkan.
> Pagar akses: sama dengan halamannya (`hasMenuAccess('report/executive')`).

Dan sekalian pertimbangkan hal yang sama untuk Sales & Marketing dashboard —
begitu Executive punya, mereka akan minta juga.

## 7. Yang perlu Ikhsan putuskan (bukan HUB)

1. **Angka omzet ke ChatGPT itu rahasia dagang**, bukan data pribadi — jadi ini
   murni keputusan bisnis, bukan kepatuhan. Kalau dipakai akun ChatGPT pribadi,
   percakapannya bisa terpakai untuk pelatihan model kecuali disetel mati. Kalau
   memang rutin, akun berbayar dengan riwayat dimatikan lebih pas.
2. **Nama dokter di `topDoctors`** — biarkan, atau ganti "Dokter A/B/C"?
3. **Siapa yang menjalankan ekspornya** — Reza, atau dibuatkan tombol dulu?

## 8. Ringkas untuk diteruskan ke Executive

> Bisa, Pak. Laporan Bapak isinya sudah berupa **rangkuman** — tidak ada nama,
> nomor, atau data pribadi pasien di dalamnya — jadi aman dianalisa di ChatGPT.
>
> Yang kami siapkan: **struktur 4 tabel** yang menyusun laporan Bapak, plus
> **24 kumpulan angka** yang sekarang tampil di layar (omzet per cabang & bulan,
> target vs capaian, pertumbuhan tahunan, top perawatan, top dokter, diskon,
> promo, retensi pasien, perbandingan antar cabang).
>
> Satu permintaan: kalau ChatGPT minta "kirim saja tabel mentahnya biar lengkap",
> **mohon jangan** — tabel sumbernya memuat nama dan email 148 ribu transaksi
> pasien. Rangkumannya sudah cukup untuk merancang dashboard apa pun; kalau ada
> angka yang kurang, sebutkan ke kami dan kami tambahkan ke paketnya.

---

## ⚠️ Ralat angka baris (18 Sep 2026)

Angka jumlah baris yang ditulis lebih awal di dokumen ini sebagian berasal dari
`information_schema.TABLE_ROWS`. Untuk InnoDB itu **perkiraan**, bukan hitungan —
di sistem ini meleset sampai ~9% (contoh: `omnicare_sales_detail` diperkirakan
135.501, `COUNT(*)` sebenarnya **148.163**).

**Yang sahih adalah `00-PROFIL-DATA.md` di paket**, karena dibuat dengan
`COUNT(*)`. Kalau ada dua angka berbeda untuk tabel yang sama, pakai yang di
profil.
