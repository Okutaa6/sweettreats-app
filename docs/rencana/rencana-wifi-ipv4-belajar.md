# Rencana — Belajar-sendiri untuk IPv4 (cocok-penuh, tanpa pelebaran)

> **Status**: rencana, ACC Ekky 2026-08-23. Belum dibangun. Tanpa tenggat.
> Tambalan manualnya sudah jalan di prod (§2) — ini yang membuatnya berhenti
> butuh manusia. Semua angka diverifikasi ke DB **prod** 2026-08-23.

---

## 1. Lubangnya

`WifiBelajarSendiri` **sengaja melewati IPv4**. Docblock-nya menyatakan
alasannya: *"KENAPA HANYA IPv6: itulah satu-satunya yang berputar (IPv4 cabang
bertahan…) … IPv4 tak pernah lewat"* — asumsinya IPv4 cabang stabil, jadi cukup
didaftarkan manual **sekali**.

Asumsinya masuk akal. Yang tak diantisipasi: **sebuah cabang bisa tak pernah
kebagian pendaftaran manual itu sama sekali.**

Kalideres punya **nol baris IPv4** sampai 23 Agu 2026. Akibatnya **13 absen
dalam 3 minggu harus PILIH CABANG MANUAL** — belajar-sendiri tak menolong
(lewat IPv4), pencocokan /48 tak menolong (cuma IPv6). Jatuh di antara dua
jalur, dan tak ada yang berteriak.

Cinere juga nol. Sunter cuma satu.

## 2. Tambalan manual yang sudah jalan (23 Agu)

10 alamat didaftarkan manual dengan pagar: **≥2 orang berbeda**, **pilihan
cabang konsisten satu**, **tak pernah dipakai cabang lain sepanjang sejarah**.
Dari 39 kandidat: **8 lolos, 31 ditolak** (mayoritas cuma 1 orang).

Hasil putar-ulang 21 hari: **158 kegagalan → 128 kini cocok & benar**, 29 sisa
(semuanya alamat 1-orang, di bawah ambang), 1 beda cabang (kasus lama Irma).
Uji 16/16: kedelapan alamat cocok, **kedelapan tetangganya (oktet +1) tetap
ditolak**.

⚠️ **Ini stopgap.** IPv4 berotasi; daftar itu akan basi.

## 3. Yang perlu dibangun

**Ambang saksi yang SAMA dengan IPv6** — `AMBANG_ORANG = 2` dalam
`JENDELA_JAM = 72` — tapi hasilnya baris **cocok-penuh**, bukan prefix.

Bukti kelayakan yang harus dipenuhi sebelum sebuah IPv4 didaftarkan otomatis:

1. **≥ AMBANG_ORANG orang BERBEDA** memakai alamat itu dalam jendela.
2. **Semua memilih cabang yang SAMA** secara manual.
3. Alamat itu **tak pernah** dipakai memilih cabang lain sepanjang sejarah.
4. Alamat itu **belum** terdaftar untuk cabang mana pun.

Kenapa "satu alamat dipakai banyak orang" itu bukti kuat: kalau itu data
seluler pribadi, tiap orang dapat alamat sendiri. Alamat yang dipakai 4 orang
berbeda dalam 5 hari **adalah jaringan bersama**. `36.77.252.85` dipakai Anisa,
Rivanti, Cici, dan Reza — semuanya memilih Kalideres.

## 4. ⚠️ JANGAN melebarkan IPv4 ke /24 (atau apa pun)

Bukan cuma berisiko — **tak akan menolong.** Alamat satu cabang melompat antar
blok yang tak berkerabat sama sekali. Kalideres memakai **`36.77.251/252.x`
DAN `180.242.129–131.x`** dalam periode yang sama. Tak ada satu /24 pun yang
menampung keduanya, jadi melebarkan cuma membuka tetangga tanpa menutup
masalahnya.

Dan pagar tetangga itu nyata: `180.252.169.218` (Bintaro) bersebelahan dengan
`180.252.169.219` yang **harus** ditolak — sudah dikunci uji K14.

Sebaran alamat IPv4 unik per cabang, 21 hari (bukti lompatannya):
Kalideres **9** dari 17 absen · Sunter **8**/16 · Bintaro **7**/16 ·
Cinere **5**/8 · Pamulang **2**/5.

## 5. ⚠️ Pelajaran yang harus dibawa: jangan tebak dari bentuk alamat

Penulis dokumen ini sempat menyimpulkan absen ber-IPv4 itu "orang pakai data
seluler". **Ekky bertanya langsung ke Cici, dan dia memakai WiFi klinik.**
Datanya kemudian membenarkan Cici.

Ini pengulangan pelajaran peta WiFi Bintaro (*"satu WiFi dua wajah IPv4+IPv6 —
jangan tebak 'beda alamat = beda WiFi'"*). Bukti yang sah itu **perilaku**
(berapa orang berbeda memakainya, dan cabang apa yang mereka pilih), bukan
bentuk atau blok alamatnya.

## 6. Tinjauan: hanya untuk /48 yang BENAR-BENAR baru

Temuan sampingan 23 Agu: kelima baris `sumber = otomatis`
(id 20, 21, 22, 25, 26) semuanya **se-/48 dengan baris yang sudah ada** — sejak
perbaikan /48 (21 Agu) mereka **sudah cocok sendiri tanpa didaftarkan**.
Menandainya "perlu ditinjau" tak menambah apa-apa.

Tinjauan sebaiknya dinyalakan **hanya untuk /48 yang tak dikenal sama sekali** —
di situlah WiFi rumah seseorang bisa terdaftar sebagai jaringan cabang. Yang
se-/48 langsung disahkan saja.

(id 25 sudah ditandai diverifikasi Ekky di lokasi Kalideres, 23 Agu.)

## 7. Uji terima

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | 2 orang berbeda, 1 cabang konsisten, dalam 72 jam | alamat **didaftarkan** cocok-penuh |
| 2 | 1 orang saja, berkali-kali | **TIDAK** didaftarkan |
| 3 | 2 orang tapi memilih cabang **berbeda** | **TIDAK** didaftarkan |
| 4 | Alamat pernah dipakai cabang lain di masa lalu | **TIDAK** didaftarkan |
| 5 | Tetangga oktet +1 dari alamat terdaftar | **ditolak** (nol pelebaran) |
| 6 | `180.252.169.219` vs `…218` terdaftar | **ditolak** — K14 tetap hijau |
| 7 | `::ffff:` IPv4 menyamar sebagai IPv6 | tetap lewat jalur IPv4, bukan prefix |
| 8 | IPv6 | perilaku **tak berubah** sedikit pun |
| 9 | `att:wifi-uji` 15 kasus lama | tetap hijau |
| 10 | Putar ulang absen prod yang gagal | jumlah yang cocok **naik**, nol yang hilang, nol pindah cabang |
| 11 | /48 baru sama sekali | **TAWARKAN** ke IT, bukan didaftarkan diam-diam |
| 12 | /64 se-/48 dengan yang sudah ada | disahkan tanpa minta tinjauan (§6) |

Uji 10 wajib memakai **data prod yang diputar ulang**, di dalam transaksi yang
dibatalkan — pola yang sama dengan perbaikan /48 (21 Agu) dan libur-cabang
(22 Agu).

## 8. Yang sengaja TIDAK dikerjakan

1. **Jangan melebarkan IPv4** ke /24, /16, atau apa pun (§4).
2. **Jangan mengubah jalur IPv6** — sudah 87% cocok dan baru saja diperbaiki.
3. **Jangan mencabut** 10 baris IPv4 yang didaftarkan manual 23 Agu; biarkan
   sampai penggantinya terbukti jalan.
4. `180.242.129–131.x` (jalur kedua Kalideres, berotasi lintas /24) tetap tak
   bisa ditangani cocok-penuh maupun pelebaran. **Pantau**, jangan dipaksa.
5. Prod menunggu dev lulus §7.
