# Handoff — Reward KOC: pintu `used`, surplus terbawa, dan baris jumlah kontrol

> Untuk **chat build**. Disusun HUB 26 September 2026 dari pengukuran prod.
> Keputusan arah sudah diambil **Ikhsan** (tercantum per bagian). Empat pekerjaan
> dalam satu wilayah — urutannya penting, §1 adalah prasyarat §2.

---

## 0. Peta keadaan sekarang (terukur prod 26 Sep)

**Tier:** Bronze **3** konten · Silver **12** · Gold **15**
(`affiliate_reward_tier`; peta kode `RawAffiliate::rewardTiers()` = `3x`/`12x`/`above_12x`).

**Populasi — jangan pakai angka 130.**

| | |
|---|---|
| `raw_affiliate` seluruhnya | 130 |
| Halaman **KOC** (`type='koc'`) | **32** |
| **SweetMates** (`reward_program` NOT NULL + `is_active=1` + type koc/kol) | **5** |
| Punya `reward_program` tanpa syarat lain | 6 — yang ke-6 **Rohayu** (`type=NULL`, `is_active=0`) |
| Pernah mengunggah konten | **11** orang / 72 baris |

**Siklus klaim:** `eligible` → `claimed` → `used`.

---

## 1. ⛔ PRASYARAT — pintu `claimed → used` TIDAK PERNAH DIBUAT

**Tidak ada satu baris pun di seluruh kode yang menulis status `used`.** Yang menulis status:

```
AffiliatePortalController:597,645  → 'eligible'
AffiliatePortalController:655      → 'claimed'
ContentReviewController:232        → 'eligible'
```

`SweetMatesController:168` dan `rewards.blade.php:317,365` hanya **membaca**.
Rute `sweet-mates` cuma **GET** (`routes/web.php:887`) — tak ada POST/PATCH.
Se-jaringan: **0 klaim** berstatus `used`.

**Akibatnya nyata dan sedang berjalan.** Linaily Mardhatillah klaim Bronze
**23 Sep 19:37**, status berhenti di `claimed`. `activeRewardClaim()` memasukkan
`claimed` ke daftar pengunci → `canSwitchProgram()` = **false**. Layarnya bilang:

> *"Tidak bisa pindah program — selesaikan dulu klaim untuk program saat ini
> (tunggu admin proses sampai reward 'sudah dipakai')."*

**Tak ada admin yang bisa melakukannya.** Dia terkunci di Bronze selamanya.

➜ **Bangun pintunya** di SweetMates (atau halaman KOC): aksi menandai klaim
**"Reward sudah diberikan"** → status `used`, isi `processed_by` + `claimed_at`/
waktu proses, dan catat di `notes`.

⚠️ **Ini aksi yang menyentuh UANG** (Free Scaling / Retainer / Whitening sungguhan).
Rekam siapa & kapan. Jangan sediakan pembatalan senyap; kalau perlu diurungkan,
buat aksi terpisah yang meninggalkan jejak — jangan tombol toggle.

⚠️ **Pola berulang keempat kalinya**: sistemnya bisa, manusianya tak punya pintu —
sama seperti ROP "tolak setelah disetujui" dan layar `/reconcile`. Tiap menambah
status baru, tanya: **siapa yang memencetnya, dari layar mana.**

---

## 2. Surplus konten TERBAWA, bukan hangus

**Keputusan Ikhsan 26 Sep.** Aturan sekarang: saat pindah program sesudah reward
`used`, `reward_program_started_at = now()` → **semua** konten lama hangus.

**Yang diminta:** tier memakan **tepat sejumlah syaratnya**, sisanya ikut ke
program berikutnya. Linaily: 7 konten, Bronze makan **3**, **4 terbawa** ke
Silver → dia di 4/12, bukan 0/12.

**Alasannya aritmetika.** Jalur berurutan (3 lalu 12) = **15** konten untuk dua
hadiah. Dengan aturan hangus, Linaily bayar **19** untuk dua hadiah yang sama —
denda 4 semata karena berproduksi sebelum memilih. Tak ada dasar bisnisnya.
Dan aturan hangus **mengajari KOC berhenti berproduksi** begitu menyentuh ambang.

**Aturan turunan yang sudah diputuskan:**
- Surplus terbawa **juga saat turun kelas** (Silver → Bronze). Logikanya sama.
- Surplus **TIDAK kedaluwarsa**. Jangan tambahkan masa berlaku.

### ⛔ Mekanisme sekarang TIDAK BISA menyatakan ini

`approvedContentCount()` memfilter `created_at >= reward_program_started_at` —
sebuah **stempel waktu**. Itu hanya bisa bilang "semua sebelum X hangus", tak
bisa bilang "bawa 4".

Menggeser cutoff ke sesudah konten ke-3 **rapuh**: ketujuh konten Linaily lahir
dalam **dua menit** (8 Agu 10:25:14–10:27:10) dan bisa berbagi detik yang sama.

➜ **Pakai hitungan, bukan waktu.** Simpan di baris klaim berapa konten yang
dimakannya — mis. kolom baru `affiliate_reward_claim.consumed_content_count`
(diisi = `required_content_count` tier saat status jadi `used`). Lalu:

```
konten terpakai = SUM(consumed_content_count) dari klaim ber-status 'used'
approvedContentCount() = total approved − konten terpakai
```

Bisa diaudit (kelihatan konten seseorang lari ke mana), tahan seri waktu, dan
tak bergantung pada urutan tanggal.

⚠️ **`reward_program_started_at` jangan langsung dibuang** — 0 baris memakainya
sekarang (semua NULL), tapi ia masih dibaca di beberapa tempat. Matikan
pemakaiannya dengan sadar, jangan disisakan setengah hidup.

### ⚠️ Yang akan terjadi di hari pertama — sudah disampaikan & diterima Ikhsan

**Lima orang punya konten tapi belum memilih program.** Begitu aturan ini hidup:

| Nama | Konten approved | Kalau pilih Silver (12) |
|---|---|---|
| **Ni Wayan Ananda** | **13** | **langsung memenuhi syarat, hari itu juga** |
| Annisa Nidya | 9 | 9/12 |
| Tri Ayuni | 9 | 9/12 |
| Audya Citra | 4 | 4/12 |
| Mahya | 1 | 1/12 |

Aturan ini **melahirkan klaim seketika**, bukan pelan-pelan. Ikhsan sudah tahu
dan menerimanya ("data yang berbicara") — aritmetikanya adil, mereka memang
sudah bekerja. Dicatat di sini supaya tak dikira efek samping.

---

## 3. Tautan KOC → pasien: SIMPAN, jangan tebak tiap kali

Prasyarat §4. `raw_affiliate` **tidak punya** kolom penghubung ke pasien
(diperiksa: tak ada `customer`/`patient`/`pid` apa pun di kolomnya).

**Penjodohan lewat nomor HP TIDAK deterministik — terbukti.** HUB menjalankan
kueri yang sama dua kali dan Dewi Santi terbaca **23 kontrol** lalu **0**:

```
HP 087880405047 cocok ke DUA pasien:
  DEWI SANTI SIMATUPANG        lahir 1991-08-26  pid C-2012-0059  23 kontrol
  DEWI SANTI SARTIKA AGUSTIN   lahir 1991-08-26  pid S-2011-0892   0 kontrol
```

Nomor sama, **tanggal lahir sama**, nama mirip → hampir pasti **satu orang dengan
dua rekam pasien**. `first()` tanpa `orderBy` memilih acak.
(Ikhsan sudah akan mengabarkan FO untuk digabung — di luar lingkup gerbong ini.)

➜ **Tambah kolom `raw_affiliate.raw_customer_id`**, diisi **sekali**:
- cocok otomatis bila **tunggal** — terukur **10 dari 11** peserta berkonten;
- bila **ganda**, layar meminta **manusia memilih**, jangan tebak.

⛔ Jangan pernah menjodohkan saat layar dibuka. Angka yang berubah-ubah sendiri
lebih buruk daripada tidak ada angka.
⛔ Jangan pakai `first()` tanpa `orderBy` di mana pun di gerbong ini.

---

## 4. Baris "jumlah kontrol" di layar

**Keputusan Ikhsan:** tampilkan berapa kali KOC itu kontrol, di samping jumlah
kontennya. Alasannya: begitu surplus bisa ditabung, **penjaga sesungguhnya
bergeser ke review konten** — orang yang menyetujui perlu melihat gambaran utuh.

**Sumber angkanya** (diverifikasi ujung ke ujung):
```
raw_affiliate.raw_customer_id  (§3)
  → customer_omnicare_link.omnicare_pid        (15.176 baris di prod)
  → omnicare_sales_detail.p_id
     WHERE item_name LIKE '%ontrol%'           (30.495 baris di prod)
```
Tampilkan **jumlah** + **tanggal kontrol terakhir**.

**Taruh di mana** (populasi tiap layar sudah diperiksa):

| Layar | Populasi | Perlu baris ini? |
|---|---|---|
| **KOC** (`type='koc'`) | 32 | ✅ **paling berguna** — di sinilah yang belum pilih program terlihat |
| **Review Konten** | semua yang punya konten (11) | ✅ di sinilah keputusan approve diambil |
| **SweetMates** | 5 yang sudah pilih tier | ✅ |

⚠️ Ni Wayan Ananda **tidak muncul di SweetMates** (belum pilih program) tapi
muncul di KOC & Review Konten. Kalau barisnya hanya dipasang di SweetMates,
justru orang yang paling perlu dilihat tidak terlihat.

**Seperti apa angkanya (data prod sungguhan):**

| Nama | Program | Konten | Kontrol | Kontrol terakhir |
|---|---|---|---|---|
| Ni Wayan Ananda | — | 13 | 19 | 1 Sep |
| Zivi | Gold | 10 | **9** | 29 Agu |
| Annisa Nidya | — | 9 | 17 | 22 Agu |
| Tri Ayuni | — | 9 | 9 | 26 Agu |
| Linaily Mardhatillah | Bronze | 7 | **6** | 23 Sep |
| Maulidia Mardatilla | Silver | 6 | 9 | 12 Sep |
| Audya Citra | — | 4 | 15 | 19 Sep |
| Asyifa Nur Rahmah | Silver | 3 | 9 | 4 Sep |
| Dewi Santi | Silver | 1 | *ambigu — lihat §3* | — |

⚠️ **Tampilkan angkanya, JANGAN jadikan pagar.** Program berbunyi
*"N× Kontrol + N Video"*, tapi kelayakan di kode **murni dari jumlah video** —
kata "Kontrol" cuma ada sebagai `content_desc` (teks tampilan) di
`RawAffiliate::rewardTiers()`, tak pernah dihitung. Menjadikannya syarat itu
**perubahan kebijakan**, bukan perbaikan — dan tiga orang (Zivi, Linaily,
Tri Ayuni) kontennya ≥ kontrolnya, jadi pagar mendadak akan menolak mereka.
Kalau Marketing mau itu jadi syarat, minta keputusan tersendiri.

---

## 5. Uji yang diharapkan

1. klaim ditandai `used` → `canSwitchProgram()` jadi **true**
2. ⭐ klaim masih `claimed` → **tetap terkunci** (kendali negatif §1)
3. pindah program sesudah Bronze `used` → hitungan **4**, bukan 0 dan bukan 7
4. ⭐ Bronze makan **tepat 3** — 15 konten tidak boleh memuaskan Bronze **dan**
   Silver sekaligus dari kolam yang sama
5. turun kelas (Silver → Bronze) → surplus **tetap terbawa**
6. ⭐ surplus **tidak** kedaluwarsa — konten lama tetap terhitung
7. KOC ber-`raw_customer_id` ganda/kosong → baris kontrol berbunyi **"perlu
   dipilih"**, bukan angka tebakan
8. ⭐ jumlah kontrol **deterministik**: jalankan dua kali, hasilnya sama
   (uji khusus untuk kasus Dewi Santi)
9. ⭐ kelayakan **tetap** dari jumlah video — KOC dengan kontrol 0 tapi video
   cukup **tetap** bisa klaim (kendali negatif: baris kontrol bukan pagar)

---

## 6. Catatan deploy

- **Ada SQL**: kolom `affiliate_reward_claim.consumed_content_count` dan
  `raw_affiliate.raw_customer_id` (+ backfill tautan yang tunggal).
  SQL langsung, bukan migration. `deploy.sh` **tidak** menjalankan SQL.
- Urutan aman: **SQL dulu**, lalu deploy — kolom baru tanpa kode tidak merusak
  apa pun, kode tanpa kolom merusak.
- Kalau menyentuh `public/assets/**` → rsync **dibatasi ke `assets/`**.
- Sebelum deploy prod, ukur `git log <HEAD-prod>..main` — prod bisa tertinggal.
- ⚠️ Backfill `raw_customer_id` **hanya untuk yang cocok tunggal**. Yang ganda
  dibiarkan NULL supaya manusia memilih. Jangan "menyelesaikan" dengan `first()`.
