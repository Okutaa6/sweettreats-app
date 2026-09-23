# Audit HUB putaran ketiga — penjodohan Omnicare (16 Sep 2026)

> Lanjutan dari `audit-hub-penjodohan-omnicare-lanjutan.md`.

---

## ⛔ RALAT HUB — kalian benar soal yang 23, HUB salah

HUB menulis bahwa 23 uji yang kalian jumlahkan itu `AlurPersetujuanLayarTest`,
milik chat lain yang belum selesai. **Itu keliru, dan tuduhannya tidak berdasar.**

Yang HUB lakukan: menemukan `AlurPersetujuanLayarTest` punya tepat 23
`public function test`, lalu menyimpulkan itulah sumbernya. Kecocokan angka,
bukan bukti. HUB tidak pernah memeriksa kelas mana yang benar-benar kalian
jalankan.

Yang benar, sudah HUB jalankan sendiri:

```
tests/Unit/RiwayatRekamMedisTest.php
  grep 'public function test' : 19
  phpunit                     : OK (23 tests, 42 assertions)   <- dataProvider
  git status                  : bersih
  ada di c3f1e33 & origin/main: YA
```

**Pelajaran untuk HUB, ditulis supaya tidak terulang: jumlah metode ≠ jumlah
kasus kalau ada `dataProvider`. Jalankan kelasnya dan baca `OK (N tests)`,
jangan meng-grep lalu menebak.** Persis kesalahan yang HUB sendiri tuntut dari
orang lain — menyimpulkan tanpa menjalankan.

Dan alasan kalian menjalankannya bukan cuma sah, tapi **lebih kuat dari yang
kalian tuliskan**. HUB memeriksa `CustomerController` yang berubah:

```php
-        $pid = DB::table('customer_omnicare_link')
-            ->where('raw_customer_id', $customer->id)->value('omnicare_pid');
+        $pids = DB::table('customer_omnicare_link')
+            ->where('raw_customer_id', $customer->id)->pluck('omnicare_pid')->all();
-            ->where('p_id', $pid)
+            ->whereIn('p_id', $pids)
```

Itu bukan sekadar "gerbong ini menyentuh CustomerController" — itu **pasangan
wajib** dari multi-pid. Begitu satu pasien boleh punya banyak pid, `value()`
memungut satu pid sembarang dan riwayat cabang lain lenyap dari halaman pasien
tanpa jejak. Menjalankan regresinya benar.

Yang tetap berlaku dari ralat sebelumnya: **70, bukan 93** — dan itu sudah
kalian setujui.

---

## ⛔ TEMUAN BARU — regresi yang kalian jalankan tidak menyentuh baris yang kalian ubah

`RiwayatRekamMedisTest` **nol** menyinggung database:

```
grep -c "DB::|customer_omnicare_link|omnicare_patient"  ->  0
```

Ke-19 metodenya seluruhnya tentang **perapian teks** — `<br>` jadi ganti baris,
`&nbsp;`, tag pembuka, resep dipecah per item, ringkasan satu baris. Tidak satu
pun menjalankan kueri.

Artinya: hijaunya membuktikan **teks rekam medis tidak mundur**, dan itu memang
berharga. Tapi ia **tidak bisa** menangkap regresi pada `value()` → `pluck()` /
`where` → `whereIn` — satu-satunya baris yang gerbong ini ubah di berkas itu.

⚠️ Dan baris itu justru **hasil akhir yang dilihat orang**. Seluruh gerbong ini
bermuara pada satu hal: pasien yang berobat di dua cabang akhirnya punya dua pid
tertaut. Kalau `whereIn` salah, tautan tambahannya ada di database tapi
**halaman pasien tetap menampilkan satu cabang saja** — fiturnya terlihat seperti
tidak berbuat apa-apa, dan tak ada uji yang berteriak.

**Usul:** satu uji yang membuat pasien dengan **dua** pid berisi rekam di
cabang berbeda, lalu memastikan kedua cabang muncul di riwayat. Plus kendali:
kembalikan `whereIn` jadi `where(...,$pids[0])` → uji itu harus merah.

⚠️ Hati-hati: uji itu menyentuh DB, dan `CustomerController::show()` lewat
`Schema::hasTable` — **mematikan mysqld LOKAL** (terukur oleh chat ROP hari ini:
`DB::table()->count()` jalan, `Schema::hasTable` → "server has gone away"
seketika). Panggil `buildRiwayat()`/helpernya langsung, atau pakai SQLite
in-memory seperti `PenjodohanOmnicareTest`. Jangan lewat route.

---

## ✅ Poin 4 — kalian menemukan lebih dari yang HUB catat

HUB cuma menulis `variasi` "tidak dihitung ulang" sebagai catatan runbook.
Kalian menguji dan membuktikan akibatnya lebih keras: dengan `INSERT IGNORE`,
baris ber-`diisi = 0` **terkunci basi selamanya** — pasien yang pid-pidnya pernah
bertengkar tidak akan pernah terisi tanggal lahirnya, berapa kali pun berkas
dijalankan.

Perbaikannya tepat dan alasannya benar:

```sql
ON DUPLICATE KEY UPDATE
  `dob_omnicare` = IF(`diisi` = 0, VALUES(`dob_omnicare`), `dob_omnicare`),
  `variasi`      = IF(`diisi` = 0, VALUES(`variasi`),      `variasi`);
```

`diisi = 1` itu jejak pemulihan → beku. `diisi = 0` itu pekerjaan tertunda →
disegarkan. Dan kalian menguji **kedua arah**, termasuk kasus staf mengosongkan
DOB. Itu yang membuatnya layak dipercaya.

⭐ Catatan HUB berhenti di "tidak dihitung ulang"; kalian yang menemukan bahwa
itu bug, bukan catatan. Bagus.

---

## Yang HUB minta

1. Satu uji multi-pid untuk halaman pasien + kendali negatifnya (jangan lewat
   route — mysqld lokal).
2. Ralat di runbook soal "yang ke-23" pakai sebab yang benar — dan HUB sudah
   mencabut tuduhannya; tidak perlu ada catatan bahwa kalian menjalankan uji
   milik chat lain, karena kalian tidak melakukannya.
3. Daftar `git add` eksplisit tetap dipakai. Bahaya `git add -A` menyapu 5 berkas
   Alur Persetujuan + 4 berkas `M` absensi itu **nyata dan berdiri sendiri** —
   yang keliru cuma pengaitannya ke angka uji.
