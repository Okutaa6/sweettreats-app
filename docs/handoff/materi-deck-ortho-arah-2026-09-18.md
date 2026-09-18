# Materi Deck — ORTHO: Arah Fitur, Alur Informasi, dan Tahapan

> Untuk **chat build** yang akan membuat deck-nya. Disusun HUB 18 Sep 2026
> atas arahan Ikhsan (planner/PM fitur ini).
> Pembaca deck: **internal + Executive** — tulis untuk Executive; tim teknis
> akan tetap paham.
> Tiket: **IT-2026-013** · fase ANALISIS sejak 10 Sep · analisis Rahmat SELESAI ·
> desain & bangun **Reza** · pemohon **Anisa** (Sales/FO) · arah **Ikhsan**.
>
> ⚠️ Semua angka di sini **terukur dari Omnicare prod 9 Sep 2026** (sumber:
> `docs/rencana/ortho-lampiran-data-2026-09-09.md`). Jangan dibulatkan ke atas,
> jangan ditambah angka yang tidak ada di sini. Kalau butuh angka baru, minta.

---

## Nada & bentuk yang diminta

- **Sederhana.** Satu gagasan per slide. Executive harus bisa membaca 14 slide
  dalam 5 menit dan tahu: masalahnya apa, kita mau ke mana, tahapannya apa,
  apa yang diminta dari dia.
- **Mengambil sikap.** Ini deck arah, bukan deck opsi. Keputusan ditulis sebagai
  keputusan.
- Visual yang paling berguna: **peta alur informasi** (slide 8) dan **garis
  tahapan** (slide 10). Sisanya teks pendek + satu angka besar.
- Bahasa Indonesia, tanpa jargon teknis di badan slide. Nama tabel/kolom hanya
  di catatan pembicara kalau perlu.

---

## SLIDE 1 — Judul

**ORTHO: dari sheet per cabang ke pelacakan yang mengikuti pasien**
Arah fitur · alur informasi · tahapan pengembangan
IT-2026-013 · September 2026

---

## SLIDE 2 — Masalahnya dalam satu kalimat

> **Sheet-nya per cabang. Pasiennya tidak.**

Hari ini pasien behel dilacak di Google Sheets — satu berkas per cabang, dibuat
baru tiap tahun, diisi tangan "kalau lagi free".

Pasien ortho **berpindah cabang** untuk kontrol. Sheet tidak bisa melihat itu.
Akibatnya pasien yang rajin kontrol di Bintaro tercatat "hampir setahun tidak
kontrol" di Cinere — dan pasien yang benar-benar hilang tenggelam di antara
mereka.

*Catatan pembicara:* ini kegagalan **struktural**, bukan kedisiplinan petugas.
Sheet selebar 12 kolom bulan memang tidak bisa dihitung dengan mata.

---

## SLIDE 3 — Seberapa besar (diukur, bukan diperkirakan)

Kohor **Cinere 2026**, cabang paling kecil dan sheet-nya paling rapi:

| | **terukur dari Omnicare** | **ditandai sheet** |
|---|---|---|
| Pasien ortho aktif 2026 | 222 | 239 |
| Kontrol terakhir di cabang **lain** | **12** | 4 |
| **Jarang kontrol** (<2× dalam 6 bulan, definisi Sales sendiri) | **60** | ≈ 0 |

Sepanjang masa: dari 277 pasien Cinere, **76 pernah kontrol di cabang lain**,
**58 benar-benar bolak-balik** — terbanyak ke Bintaro.

Dan ini baru **satu cabang**. Lima cabang, 2026 berjalan:

| Bintaro | Sunter | Kalideres | Cinere | Pamulang |
|---|---|---|---|---|
| 542 pasien | 467 | 285 | 222 | 167 |

**1.683 pasien ortho** sedang dalam perawatan di seluruh jaringan.

---

## SLIDE 4 — Kenapa ini penting untuk bisnis

Ortho bukan tindakan sekali datang. Ia **kontrak ±2 tahun** dengan kontrol
berulang — tiap kontrol adalah kunjungan berbayar, dan tiap kontrol yang
terlewat adalah:

- **pendapatan berulang yang tidak terjadi** (dan sering tidak pernah terkejar),
- **risiko klinis** — behel yang tak dikontrol memperpanjang perawatan,
- **pasien yang diam-diam pergi** ke klinik lain tanpa kita sadari.

Hari ini kita tidak tahu berapa dari 1.683 itu yang sedang bolong — karena
alatnya tidak bisa melihatnya.

*Catatan pembicara:* nilai rupiah per kontrol yang terlewat **bisa diukur** dari
data penjualan kalau Executive ingin angkanya; sengaja tidak ditulis di sini
karena belum diukur.

---

## SLIDE 5 — Arah yang diputuskan

Lima keputusan. Tim desain & bangun **mengikuti ini**, tidak membukanya lagi.

| # | Keputusan | Kenapa |
|---|---|---|
| 1 | **Tidak ada lagi pengisian manual.** Tanggal kontrol, jenis bracket, cabang, dan status **turun sendiri** dari data kunjungan. | Itu keluhan nomor satu Sales, dan datanya sudah ada 100%. |
| 2 | **Satu data untuk lima cabang; tampilan boleh per cabang.** | Kalau datanya dipisah per cabang, cacat sheet tertanam ulang. |
| 3 | **Pemilik pasien = cabang perawatan terakhir**, berpindah setelah **2 kunjungan berturut** di cabang lain (bukan 1). | Satu kunjungan nyasar tidak boleh memindahkan tanggung jawab bolak-balik tiap bulan. Cabang asal tetap melihat pasiennya, ditandai terpisah. |
| 4 | **"Selesai" dan "Berhenti" dipisah sejak awal.** | Sheet mencampur keduanya dalam "Done" — satu keberhasilan, satu kehilangan pelanggan. Laporan tak boleh mencampurnya. |
| 5 | **Status tidak berbasis waktu; peringatan yang berbasis waktu.** Pasien tetap Aktif sampai selesai/berhenti; lama tak kontrol memicu **peringatan**, bukan mengubah status. | Keputusan Anisa 9 Sep. "Jarang kontrol" = ukuran frekuensi, bukan kebaruan. |

---

## SLIDE 6 — Alur inti: lima langkah

```
 ┌──────────┐   ┌──────────┐   ┌───────────────┐   ┌──────────────┐   ┌──────────┐
 │ Omnicare │ → │  Mesin   │ → │ Daftar kerja  │ → │ Tindak lanjut│ → │ Laporan  │
 │ (sync    │   │  status  │   │ FO hari ini   │   │ tercatat     │   │ retensi  │
 │  harian) │   │          │   │               │   │              │   │          │
 └──────────┘   └──────────┘   └───────────────┘   └──────────────┘   └──────────┘
  tiap kontrol,   Aktif ·        siapa yang perlu    hubungi → tawarkan   per cabang ·
  bracket, lepas  Jarang ·       dihubungi, urut     jadwal → hasilnya    per dokter ·
  bracket, cabang Pindah ·       dari yang paling    dicatat              tren bulanan
                  Selesai ·      lama bolong
                  Berhenti
```

1. **Omnicare → sistem kita**, satu arah, sudah berjalan tiap hari 02.00.
   Tiap baris kontrol membawa tanggal, jenis bracket, cabang, dokter.
2. **Mesin status** menghitung sendiri: kapan kontrol terakhir, berapa kali dalam
   6 bulan, cabang mana yang memiliki, sudah lepas bracket atau belum.
3. **Daftar kerja** — bukan tabel 239 baris untuk digulir, tapi **"hubungi 7
   orang ini hari ini"**, sudah diurutkan.
4. **Tindak lanjut tercatat** — siapa dihubungi, kapan, hasilnya apa, jadwal
   ditawarkan atau tidak. Hari ini ini **tidak tercatat di mana pun**.
5. **Laporan** — yang selama ini "maunya ada tapi susah kalau manual".

---

## SLIDE 7 — Status pasien, satu bahasa untuk lima cabang

| Status | Artinya | Dari mana |
|---|---|---|
| **Aktif** | dalam perawatan, kontrol wajar | otomatis |
| **Aktif — perlu dihubungi** | Aktif, tapi <2 kontrol dalam 6 bulan | otomatis (peringatan, bukan status baru) |
| **Pindah kontrol** | terakhir dirawat di cabang lain | otomatis |
| **Selesai** | bracket dilepas, perawatan tuntas | otomatis dari `Lepas Bracket` |
| **Berhenti** | pindah klinik / pindah kota / tidak lanjut | **ditandai FO** — satu-satunya yang manual, karena sistem tak bisa tahu |

Plus satu **penanda** (bukan status): **Endorser** — pasien yang memasang lewat
endorse Sweet Treats. Bisa berbarengan dengan status apa pun.

*Catatan pembicara:* jenis bracket **bisa berubah** di tengah perawatan; sheet
hanya menyimpan yang terbaru, sistem menyimpan seluruh riwayatnya. Itu satu
keunggulan gratis dari otomatisasi.

---

## SLIDE 8 — Peta alur informasi: ke mana ORTHO tersambung

Ini slide yang paling penting untuk **tim**, dan yang paling cepat menjelaskan
ke **Executive** kenapa fitur ini bukan pulau.

```
                MASUK                                  KELUAR
 ┌──────────────────────┐                  ┌─────────────────────────────┐
 │ Omnicare             │ kontrol, bracket │ Reservasi                   │
 │ (rekam medis, kasir) │ ───────────────▶ │ "tawarkan jadwal" →         │
 └──────────────────────┘                  │  langsung jadi booking      │
                                 ┌──────┐  └─────────────────────────────┘
 ┌──────────────────────┐        │      │  ┌─────────────────────────────┐
 │ Reservasi            │ no-show│ORTHO │  │ Cekat (WhatsApp)            │
 │ pasien ortho tidak   │ ──────▶│      │─▶│ pengingat otomatis untuk    │
 │ datang               │        │      │  │ yang telat kontrol          │
 └──────────────────────┘        └──────┘  └─────────────────────────────┘
                                   │ │     ┌─────────────────────────────┐
 ┌──────────────────────┐          │ └────▶│ Akun Dokter                 │
 │ Cekat (WhatsApp)     │ balasan  │       │ "pasien ortho saya" per     │
 │ pasien membalas      │ ────────▶│       │  dokter                     │
 └──────────────────────┘          │       └─────────────────────────────┘
                                   │       ┌─────────────────────────────┐
                                   ├──────▶│ Laporan Executive           │
                                   │       │ retensi · bolong · selesai  │
                                   │       │ per cabang & dokter         │
                                   │       └─────────────────────────────┘
                                   │       ┌─────────────────────────────┐
                                   └──────▶│ Marketing                   │
                                           │ Endorser · lead pemasangan  │
                                           │ dari promo                  │
                                           └─────────────────────────────┘
```

**Yang masuk ke ORTHO** (3): kunjungan dari Omnicare · no-show dari Reservasi ·
balasan pasien dari Cekat.

**Yang keluar dari ORTHO** (5): tawaran jadwal → **Reservasi** · pengingat →
**Cekat** · daftar pasien per dokter → **Akun Dokter** · angka retensi →
**Laporan Executive** · kategori Endorser & lead → **Marketing**.

**Yang TIDAK berubah:** Omnicare tetap satu arah. Kita **membaca**, tidak menulis
balik. Itu prinsip yang sudah berlaku dan tetap berlaku.

---

## SLIDE 9 — Cekat menggantikan MaxChat untuk pengingat otomatis

Keputusan yang sudah dikonfirmasi dan masuk rencana: kanal pengingat ke pasien
adalah **Cekat**, bukan MaxChat. Webhook diganti.

Kenapa ini masuk akal:
- Cekat sudah menjadi **gerbang FO** untuk semua percakapan pasien. Pengingat
  ortho lewat kanal yang sama = **satu jejak percakapan per pasien**, bukan dua.
- Balasan pasien ("bisa Sabtu?") kembali ke sistem yang sama yang mengirim
  pengingatnya.

Dua **prasyarat** yang harus dimulai **bersamaan** dengan pembangunan, bukan
sesudahnya:

1. **Template WhatsApp harus disetujui Meta lebih dulu.** Pengingat ke pasien
   yang tidak sedang chat wajib memakai template resmi. Persetujuannya makan
   waktu berminggu-minggu — mulai ajukan sekarang, paralel.
2. **Webhook Cekat harus terautentikasi sejak hari pertama.** Webhook lama
   (MaxChat) sampai hari ini berjalan **tanpa token** — lubang yang sudah
   tercatat. Ganti kanal adalah kesempatan menutupnya, bukan mewarisinya.

---

## SLIDE 10 — Tahapan: lihat → bertindak → otomatis → ukur

```
 FASE 1            FASE 2              FASE 3              FASE 4
 LIHAT             BERTINDAK           OTOMATIS            UKUR
 ─────────────     ─────────────       ─────────────       ─────────────
 tracking          daftar "hubungi     pengingat Cekat     laporan retensi
 otomatis dari     hari ini" +         ke pasien telat     per cabang/dokter,
 Omnicare;         tindak lanjut       kontrol; balasan    Endorser & lead,
 status 5 macam;   tercatat;           masuk ke jejak      masuk Laporan
 Cinere = alat     "tawarkan jadwal"   yang sama           Executive
 ukur, lalu        → jadi reservasi
 5 cabang
 ─────────────     ─────────────       ─────────────       ─────────────
 sheet berhenti    FO berhenti         FO berhenti         Executive tahu
 diisi             menggulir & WA      mengetik pengingat  berapa yang bolong
                   antar cabang        satu-satu           dan berapa kembali
```

**Fase 1 punya alat ukur yang keras:** sistem harus **mereproduksi 222 pasien
Cinere** dan **memunculkan 12 pindah-cabang serta 60 jarang-kontrol** yang
sheet tidak lihat. Kalau tidak lulus itu, belum ke fase berikutnya.

Fase 2 dan 3 bisa **tumpang tindih** — pengajuan template WA (Fase 3) dimulai
saat Fase 1 dibangun.

---

## SLIDE 11 — Sesudah ortho: pintu yang sengaja dibiarkan terbuka

Fokus **sekarang: ortho saja.** Tapi pola yang dibangun — *kunjungan berulang
→ status otomatis → daftar hubungi → tindak lanjut tercatat* — **bukan pola
khusus behel.**

Perawatan lain yang bentuknya sama:

| Perawatan | "kontrol"-nya | jeda wajar |
|---|---|---|
| Implant | kontrol pasca-pasang | bulanan → tahunan |
| Veneer / crown | kontrol berkala | 6 bulan |
| Scaling rutin | kunjungan ulang | 6 bulan |
| Perawatan saluran akar | kunjungan lanjutan | mingguan |

Arahan ke tim desain: **jangan mengunci model ke kata "ortho"** di tempat yang
tak perlu. Aturan status dan jeda boleh berbeda per jenis perawatan; mesinnya
satu. Ini **bukan** tugas sekarang — ini supaya tidak harus dibongkar nanti.

---

## SLIDE 12 — Yang harus dijaga (risiko yang sudah kelihatan)

| Risiko | Sikap |
|---|---|
| **Tanggal pemasangan tak punya nama item baku** — 132 nama promo berbeda untuk 1.237 pemasangan | Dihitung sebagai *transaksi ortho pertama yang bukan kontrol*. Diuji pada 2 pasien, tepat. Desain Reza yang mengunci aturannya. |
| **Nomor RM di sheet tidak bisa dipercaya** (disalin tangan, ada yang salah) | Migrasi data lama **tidak** memakai sheet sebagai kunci. Sumbernya Omnicare. |
| **Sheet 239 vs terukur 222** | Selisih 17 = dobel & terlewat (kata Anisa). Tidak dikejar satu-satu; sistem baru yang jadi kebenaran. |
| **Kepemilikan berbalik** untuk pasien bolak-balik | Ambang 2 kunjungan berturut (slide 5). |
| **Pengingat otomatis butuh template WA** | Diajukan paralel dengan Fase 1. |
| **Webhook tanpa token** | Ditutup saat ganti ke Cekat, bukan diwarisi. |
| **Empat utang teknis yang sengaja menunggu fitur ini** | Dibuka saat ORTHO masuk Development: token/webhook (→ Cekat), keamanan §6, 598 tanggal lahir tertukar, layar tinjauan rekonsiliasi. Reza perlu tahu keempatnya ada. |

---

## SLIDE 13 — Ukuran keberhasilan

Bukan "fitur selesai dibangun". Ini yang dihitung:

| Ukuran | Sekarang | Target |
|---|---|---|
| Pasien pindah-cabang yang terdeteksi (Cinere) | 4 dari 12 | **12 dari 12** |
| Pasien jarang-kontrol yang terdeteksi (Cinere) | ≈ 0 dari 60 | **60 dari 60** |
| Sheet ortho masih diisi | 5 berkas | **0** |
| Tindak lanjut yang tercatat | tidak ada | **setiap kontak tercatat** |
| Jeda dari "telat" sampai "dihubungi" | tidak diketahui | **terukur, lalu diturunkan** |
| % pasien aktif yang bolong >6 bulan | tidak diketahui | **terukur per cabang, turun tiap kuartal** |

Tiga baris pertama = **Fase 1 lulus**. Tiga berikutnya = fitur ini **hidup**.

---

## SLIDE 14 — Peran & yang diminta dari Executive

| Siapa | Peran |
|---|---|
| **Ikhsan** | arah fitur (deck ini), keputusan lintas cabang |
| **Rahmat** (Konsultan IT) | analisis — **selesai** |
| **Reza** | desain & bangun |
| **Anisa** (Sales/FO) | pemohon, penguji di Cinere, suara pengguna |
| **HUB** | audit, verifikasi angka, deploy |

**Yang diminta dari Executive:**
1. Restu atas **lima keputusan arah** (slide 5) — terutama satu data global
   dan pemisahan Selesai/Berhenti.
2. Restu **Cinere sebagai cabang pengukur** sebelum lima cabang.
3. Dukungan untuk **pengajuan template WhatsApp** lewat Cekat sekarang, supaya
   Fase 3 tidak menunggu.

---

## Lampiran untuk chat build (jangan masuk slide)

- Sumber angka: `docs/rencana/ortho-lampiran-data-2026-09-09.md` (kueri
  tercantum, bisa diulang) · brief: `docs/brief-desain/brief-ortho-tracking-untuk-rahmat-2026-09-09.md`
  · jawaban Sales: `docs/rencana/ortho-pertanyaan-sales-2026-09-09.md`.
- Tangkapan layar sheet Cinere ada di
  `C:\Users\Ekky13\Downloads\Brief-Ortho-Rahmat-2026-09-09\screenshoot\` —
  boleh dipakai di slide 2 sebagai "keadaan sekarang", **buramkan nama pasien**.
- Angka 1.683 = 542+467+285+222+167 (pasien kontrol ortho 2026 per cabang).
  Angka 5.639 baris kontrol 2026 kalau perlu volume kunjungan.
- Jangan tulis nilai rupiah. Belum diukur.
- Kalau deck dibuat 2 versi (Executive 8 slide / tim 14 slide): versi Executive
  = slide 1, 2, 3, 4, 5, 8, 10, 14.
