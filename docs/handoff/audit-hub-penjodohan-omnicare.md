# Audit HUB — gerbong penjodohan Omnicare (16 Sep 2026)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/audit-hub-penjodohan-omnicare.md`"**.
> Diverifikasi terhadap **data PROD** (baca-saja) dan dengan menjalankan ujinya sendiri.

---

## Dulu yang lolos

| pemeriksaan HUB | hasil |
|---|---|
| `OmnicareIdentitasTest` | **20/20, 52 assertion** ✅ |
| `PenjodohanPidTest` | **18/18, 45 assertion** ✅ |
| `PenjodohanOmnicareTest` | **25/25, 40 assertion** ✅ |
| `uq_col_customer` benar-benar terpasang di prod? | **YA** — temuan kalian BENAR ✅ |
| `uq_col_omnicare` masih menjaga? | **YA**, unik ✅ |
| Pagar #3 di prod sebelum apa pun dijalankan | **0 pid ke >1 pasien** ✅ |
| SQL backfill mengandung `information_schema`? | **tidak** — aman untuk mysqld lokal ✅ |
| Parser `dateFromDmy` benar? | `createFromFormat('d/m/Y')` eksplisit, tak ambigu ✅ |

**Temuan pusat kalian itu yang paling berharga di seluruh PR ini.** Catatan
handoff HUB menduga skemanya sudah sanggup; kalian membuktikan sebaliknya dan
HUB sudah mengonfirmasinya langsung di prod. Tanpa itu, seluruh perbaikan kode
akan naik dan **tidak berbuat apa-apa**, diam-diam. HUB yang salah, kalian benar.

Yang juga benar dan jangan diubah: `insertOrIgnore` (bukan `insert`) supaya satu
baris ditolak tidak menjatuhkan chunk 500; potret `$sebelum` diambil **sebelum**
`--rematch`; `dobBercabang()` memeriksa kelompok, bukan pasangan; dan menolak
melebarkan `MATCH_WINDOW`.

HUB sempat curiga klaim "tidak ada UPDATE/DELETE" bertabrakan dengan `delete()`
di jalur `--rematch`. **Tidak bertabrakan** — teksnya sendiri sudah menyebut
pengecualian itu, dan `--rematch` memang sudah ada sejak versi lama. Bukan temuan.

---

## ⛔ TEMUAN 1 — DOB **pasien kita sendiri** tidak pernah diperiksa

`pilihKandidat()` memutuskan siapa berbagi rekam medis dengan siapa, tapi
**tidak pernah menerima `raw_customer.tanggal_lahir`**:

```
signature: pilihKandidat($layak, $identLama)
```

Seluruh pagar DOB bekerja di **sisi Omnicare saja** — `samaOrang()` membandingkan
kandidat dengan pid lama, `dobBercabang()` memeriksa kelompok pid. Tanggal lahir
yang kita pegang sendiri tak pernah ikut. Dan `score()` **tidak menghukum** DOB
yang bertentangan; ia cuma tidak memberi bonus. Jadi
`phone 30 + nama persis 35 = 65 ≥ 60` → tetap auto-link.

Dijalankan sungguhan (bukan dibaca):

```
DOB pasien kita      : 1990-05-05
DOB pid lama tertaut : (kosong)          <- 54,5% tautan lama memang begini
DOB kandidat baru    : 2015-03-01
samaOrang(baru,lama) : true              <- dimaafkan karena yang lama kosong
dobBercabang(grup)   : false             <- sisi Omnicare cuma punya 1 DOB
PUTUSAN              : DITAUT -> S-2026-999 (pid_kembar_nama)
```

Pid lama tanpa DOB menjadi **jembatan** — persis kelas bug yang
`dobBercabang()` dibuat untuk menutup, cuma lewat pintu yang tidak dijaga.
⚠️ Ini **baru bisa terjadi sejak jalur A ada**: selama 1:1, pasien yang sudah
tertaut tidak pernah menyerap pid tambahan.

### Radius nyata di prod — jangan dibaca sebagai 130

HUB mengukurnya di seluruh 14.191 tautan prod:

| | |
|---|---|
| Kandidat yang akan ditaut padahal DOB-nya bertentangan dgn pasien kita | **130** |
| …ternyata cuma **hari↔bulan tertukar** (kategori `dob_tertukar` kalian) | **125** |
| …selisih **>5 tahun** — patut dicurigai orang berbeda | **5** |

Pakai triase kalian sendiri di sini: **yang mendesak 5, bukan 130.**

### Usul

Tambahkan DOB pasien kita sebagai argumen dan tolak kandidat yang
`bedaDob(dobKita, kandidat) === 'bertentangan'` — **hanya** `bertentangan`,
bukan `tertukar` atau `selisih_sehari` (kalau ketiganya ditolak, 125 tautan
benar ikut hilang karena salah ketik tanggal). Kalau kalian punya alasan
menolak, katakan alasannya — yang penting jangan tanpa keputusan.

---

## ⛔ TEMUAN 2 — bukan bug kalian, tapi kerja kalian yang membongkarnya

Triase `bedaDob()` kalian dijalankan HUB terhadap **prod**, bukan salinan lokal
yang basi:

| kategori | lokal (kalian) | **PROD (HUB)** |
|---|---|---|
| tautan ber-DOB di kedua sisi | 5.702 | **7.005** |
| `sama` | — | 6.464 (92,3%) |
| **`tertukar`** | — | **474 (6,8%)** |
| `selisih_sehari` | — | 6 |
| `bertentangan` | 16 | **61** |

**474, bukan 16.** Dan yang membuatnya penting: itu **tidak tersebar**.

| bulan `raw_customer` dibuat | swap terdeteksi | tertukar | |
|---|---|---|---|
| 2026-04 | 1.599 | **2** | 0,1% |
| **2026-05** | 741 | **472** | **63,7%** |
| 2026-06 … 09 | 136 | **0** | 0% |

Dipersempit per hari, seluruhnya jatuh di **satu hari: 12 Mei 2026** (700
terdeteksi, 472 tertukar). Hari lain nol. Dan pada hari itu **2.164 pasien
dibuat dalam satu menit — 11:18** — jelas impor massal.

> Kendali sudah dipasang: April punya 2x lebih banyak pasangan yang bisa
> diperiksa dan hampir nol swap. Jadi ini bukan artefak populasi, dan bukan
> salah ketik FO. **Satu batch impor 12 Mei 2026 menulis ~472 tanggal lahir
> dengan hari & bulan tertukar.** Omnicare-nya benar (`createFromFormat('d/m/Y')`);
> yang salah sisi kita.

⚠️ Angka 472 itu **batas bawah**. Swap hanya bisa TERDETEKSI kalau hari dan
bulan dua-duanya ≤12. Dari 2.123 pasien ber-DOB di batch itu, cuma 700 yang
bisa diperiksa silang. Sisanya tidak terbukti benar — cuma tak terbukti salah.

**Ini di luar cakupan PR kalian dan jangan dipaksa masuk.** Tapi ia harus
dicatat, karena: (a) `--dob --tulis` kalian akan menandainya, dan angkanya akan
jauh lebih besar dari dugaan siapa pun; (b) DOB yang salah membuat matcher
kehilangan bonus 35 poin, jadi ia **ikut menyebabkan** kegagalan penjodohan yang
sedang kalian perbaiki.

ℹ️ Di antara 61 `bertentangan` ada yang mustahil, bukan cuma beda:
`Ekhsan Rohmawan` lahir **2026-05-11**, `Charen Audy Paulus` lahir
**2026-02-28** — tahun ini. Itu tanggal pendaftaran yang masuk ke kolom tanggal
lahir.

---

## ⚠️ TEMUAN 3 — jaring pengaman backfill hilang diam-diam kalau dijalankan dua kali

`2026_09_16_omnicare_dob_backfill.sql` membuka dengan:

```sql
DROP TABLE IF EXISTS `omnicare_dob_backfill_log`;
```

lalu mengisi ulang log itu **hanya dari pasien yang `tanggal_lahir`-nya masih
kosong**. Jalan kedua: yang sudah terisi di jalan pertama tidak lagi kosong →
tidak masuk log → **log jalan pertama lenyap, dan resep pemulihan di kaki berkas
tidak lagi cocok dengan baris mana pun.**

Kepala berkas menulis *"Idempoten: aman dijalankan berulang."* Untuk **data** itu
benar. Untuk **pemulihan** tidak — dan justru pemulihan yang kalian bangun
tabel itu untuk menjaminnya. Kalau 5.786 tanggal lahir terisi lalu seseorang
menjalankan ulang berkasnya "untuk memastikan", kemampuan membatalkan hilang
tanpa pesan apa pun.

**Usul (kecil):** `CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`, atau berhenti
dengan pesan kalau log lama masih punya baris `diisi = 1`.

---

## ℹ️ Dua ralat angka

1. **Cakupan DOB prod hari ini = 7.597 / 15.732 (48,3%)**, bukan 56% seperti di
   kepala SQL (dari `data-reservasi-2026-09-11.md` §10). Angka 56% itu mungkin
   memakai penyebut lain. Pakai 48,3% sebagai patokan "SEBELUM".
2. Tabel hasil uji kalian sudah kalian beri label "BUKAN angka prod" — benar,
   dan terima kasih sudah menuliskannya. HUB cuma menegaskan: **selisihnya besar**
   (mis. `bertentangan` 16 lokal vs 61 prod). Jangan ada yang memakai tabel itu
   untuk memperkirakan hasil prod.

---

## Yang HUB minta

1. Putuskan Temuan 1 — perbaiki, atau tuliskan alasan menolak.
2. Tambah uji penjaga untuk bentuk "pid lama tanpa DOB jadi jembatan ke orang
   yang DOB-nya bertentangan dengan pasien kita".
3. Perbaiki Temuan 3 (satu-dua baris).
4. Masukkan angka prod Temuan 2 ke runbook sebagai ekspektasi, supaya yang
   menjalankan `--dob --tulis` di prod tidak kaget melihat 474.
5. Temuan 2 sendiri → tiket terpisah, bukan PR ini.

## Catatan deploy

- Urutan kalian benar dan HUB tidak mengubahnya: **SQL backfill → SQL multi-pid →
  deploy kode**. `deploy.sh` TIDAK menjalankan SQL.
- Gerbong ini **tidak menyentuh** `public/assets/` → tidak perlu rsync.
- Prod saat ini `23fb5b1`; branch ini masih duduk di working copy bersama
  (belum ada commit). Ingat `git status` dulu — folder ini dipakai banyak chat.
