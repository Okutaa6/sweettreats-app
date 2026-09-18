# Materi Deck — ORTHO: Peta Arah & Alur Informasi

> Untuk **chat build**. Disusun HUB 18 Sep 2026 atas arahan Ikhsan (planner fitur),
> **direvisi sesudah pengukuran 18 Sep** (pasien mengikuti dokter — lihat slide 1).
> Pembaca: **internal + Executive**, yang **sudah membaca brief** — deck ini **tidak
> mengulang masalah, angka, atau keputusan Sales**. Hanya tiga hal: alur fitur,
> ke mana informasinya mengalir, tahap pengembangannya. **4 slide** (+ judul).
> Satu gagasan per slide. Slide 2 dan 3 **wajib diagram**, bukan bullet.
> Tiket IT-2026-013 · analisis Rahmat selesai · desain & bangun Reza · arah Ikhsan.

---

## SLIDE 0 — Judul

**ORTHO — peta arah**
Alur fitur · alur informasi · tahapan
IT-2026-013 · September 2026

---

## SLIDE 1 — Alur fitur: lima langkah, satu rel

```
 ┌──────────┐   ┌──────────┐   ┌───────────────┐   ┌──────────────┐   ┌──────────┐
 │ Omnicare │ → │  Mesin   │ → │ Daftar kerja  │ → │ Tindak lanjut│ → │ Laporan  │
 │ sync     │   │  status  │   │ Sales hari ini│   │ tercatat     │   │ retensi  │
 │ harian   │   │          │   │               │   │              │   │          │
 └──────────┘   └──────────┘   └───────────────┘   └──────────────┘   └──────────┘
  kontrol ·       Aktif ·        "hubungi 7 orang   hubungi → tawarkan   per dokter ·
  bracket ·       perlu          ini hari ini";     jadwal dokternya     per cabang ·
  lepas ·         dihubungi ·    yang SUDAH punya   → hasilnya dicatat,  tren
  cabang ·        Pindah ·       reservasi TIDAK    terlihat semua
  dokter          Selesai ·      masuk daftar       cabang
                  Berhenti
```

**Satu temuan baru yang menentukan relnya** (diukur 18 Sep, 2.034 pasien):
dari pasien yang kontrol di lebih dari satu cabang, **89% bertemu dokter yang sama**
di kedua cabang (Cinere: **95%**). Pasien tidak pindah cabang — **pasien mengikuti
dokternya**, dan empat dokter yang berkeliling mengerjakan 46% seluruh kontrol.

**Rel yang dipakai** (keputusan arah — tim mengikuti, tidak membuka ulang):
- nol pengisian manual — kecuali tiga: *Berhenti*, *Endorser*, dan tanggal pemasangan yang ditebak dari transaksi ortho pertama
- **satu data untuk lima cabang**, tampilan per cabang
- **pasien milik dokternya** (dokter kontrol terakhir); **yang menghubungi = Sales cabang perawatan terakhir**. Cabang = informasi, bukan pemilik. 11% pasien yang ganti dokter ditangani manual dulu.
- **Selesai ≠ Berhenti**
- status tak berbasis waktu — **peringatan** yang berbasis waktu

---

## SLIDE 2 — Peta alur informasi: ORTHO bukan pulau

```
              MASUK                                    KELUAR
 ┌──────────────────────┐                  ┌──────────────────────────────┐
 │ Omnicare             │ kontrol, bracket,│ Reservasi                    │
 │ rekam medis & kasir  │ dokter           │ "tawarkan jadwal" → booking  │
 └──────────────────────┘ ───────────────▶ │  ke dokternya, cabang mana   │
 ┌──────────────────────┐                  │  pun dia praktik             │
 │ Jadwal Dokter        │ dokter X ada di  └──────────────────────────────┘
 │ (lintas cabang)      │ cabang mana,     ┌──────────────────────────────┐
 └──────────────────────┘ hari apa        │ Cekat (WhatsApp)             │
                          ───────▶ ┌──────┐│ pengingat otomatis; balasan  │
 ┌──────────────────────┐          │      ││ masuk ke jejak yang sama     │
 │ Reservasi            │ reservasi│ORTHO │└──────────────────────────────┘
 │ aktif → keluar dari  │ aktif &  │      │┌──────────────────────────────┐
 │ daftar; no-show →    │ no-show  │      ││ Akun Dokter — INTI           │
 │ peringatan           │ ───────▶ │      ││ "pasien ortho saya" = daftar │
 └──────────────────────┘          └──────┘│ kerja dokter, karena pasien  │
 ┌──────────────────────┐            │ │   │ milik dokter                 │
 │ Cekat (WhatsApp)     │ balasan    │ │   └──────────────────────────────┘
 │ pasien membalas      │ ─────────▶ │ │   ┌──────────────────────────────┐
 └──────────────────────┘            │ └──▶│ Penjualan                    │
                                     │     │ ortho sebagai LINI PRODUK:   │
                                     │     │ pemasangan baru · kontrol    │
                                     │     │ (pendapatan berulang) ·      │
                                     │     │ lepas bracket · target cabang│
                                     │     └──────────────────────────────┘
                                     │     ┌──────────────────────────────┐
                                     ├────▶│ Laporan Executive            │
                                     │     │ retensi · bolong · tuntas ·  │
                                     │     │ pendapatan berulang terlewat │
                                     │     └──────────────────────────────┘
                                     │     ┌──────────────────────────────┐
                                     └────▶│ Marketing / Afiliasi         │
                                           │ Endorser = program afiliasi  │
                                           │ yang sudah ada; lead promo   │
                                           └──────────────────────────────┘
```

**4 masuk · 6 keluar.** Omnicare tetap **satu arah** — kita membaca, tidak menulis balik.

**Fitur yang ikut berubah, bukan sekadar dibaca:**
- **Pasien** — riwayat ortho hidup di **halaman pasien**, bukan layar terpisah
- **Dokter** — peta 37 dokter kita ↔ 53 dokter Omnicare **wajib dibuat** (nama tak bisa dicocokkan)
- **Reservasi** — form tahu pasien ini ortho aktif: layanan & dokter terisi sendiri
- **Penjualan** — ortho jadi lini produk dengan tiga angkanya sendiri

**Cekat, bukan MaxChat**, untuk pengingat: satu jejak percakapan per pasien.

---

## SLIDE 3 — Tahapan: lihat → bertindak → otomatis → ukur

```
 FASE 1            FASE 2              FASE 3             FASE 4
 LIHAT             BERTINDAK           OTOMATIS           UKUR
 ─────────────     ─────────────       ─────────────      ─────────────
 tracking          daftar "hubungi     pengingat Cekat    ortho di dashboard
 otomatis per      hari ini" per       ke yang telat;     Penjualan & Laporan
 DOKTER; 5 status; cabang; tindak      balasan masuk      Executive: retensi,
 riwayat ortho di  lanjut tercatat,    jejak yang sama    tuntas, pendapatan
 halaman pasien;   terlihat lintas                        berulang terlewat;
 Cinere = alat     cabang; tawarkan                       target pemasangan
 ukur → 5 cabang   jadwal dokternya                       per cabang
                   → reservasi
 ─────────────     ─────────────       ─────────────      ─────────────
 PRASYARAT:        PRASYARAT:          PRASYARAT:         —
 peta dokter       jadwal dokter       template WA
 kita ↔ Omnicare   rapi (IT-042)       disetujui Meta;
                                       webhook Cekat
                                       terautentikasi
 ─────────────     ─────────────       ─────────────      ─────────────
 sheet berhenti    Sales berhenti      Sales berhenti     Executive tahu
 diisi             menggulir & WA      mengetik           berapa bolong,
                   antar cabang        satu-satu          berapa kembali,
                                                          berapa rupiahnya
```

**Syarat lulus Fase 1** (keras, terukur): mereproduksi **222** pasien Cinere +
memunculkan **12** pindah-cabang & **60** jarang-kontrol yang sheet tak lihat.

**Dimulai sekarang, paralel dengan Fase 1** — karena jamnya bukan kita yang pegang:
pengajuan **template WhatsApp** ke Meta lewat Cekat, dan **pembersihan jadwal dokter**
(IT-042) yang sudah direncanakan.

*Catatan pembicara:* empat utang teknis sengaja menunggu fitur ini masuk
Development (webhook → Cekat + autentikasi, keamanan §6, tanggal lahir tertukar,
layar rekonsiliasi). Reza tahu keempatnya.

---

## SLIDE 4 — Sesudah ortho: pintu yang sengaja dibiarkan terbuka

Fokus sekarang **ortho saja**. Tiga improvement yang sudah kelihatan tapi
**sengaja tidak dikerjakan sekarang**:

| Improvement | Kenapa nanti |
|---|---|
| **Aturan otomatis untuk 11% pasien yang ganti dokter** | Butuh 6 bulan tindak lanjut tercatat dulu untuk tahu polanya; sekarang manual |
| **Belajar kenapa pasien berhenti** — dari catatan tindak lanjut jadi pola: dokter, cabang, jenis bracket, jarak rumah | Datanya baru lahir di Fase 2 |
| **Pola yang sama untuk perawatan lain** — implant, veneer, scaling rutin: *kunjungan berulang → status → daftar hubungi → tindak lanjut* | Mesinnya satu; aturan jeda per perawatan |

Arahan ke tim desain: **jangan kunci model ke kata "ortho"** di tempat yang tak
perlu, dan **jangan kunci ke cabang** — sumbunya dokter & pasien.

**Yang diminta dari Executive:** restu rel di slide 1 · Cinere sebagai cabang
pengukur · pengajuan template WhatsApp **sekarang** · pembersihan jadwal dokter
dianggap bagian dari ORTHO, bukan tiket sampingan.

---

## Lampiran untuk chat build (tidak masuk slide)

- Angka 222/12/60 dan volume cabang: `docs/rencana/ortho-lampiran-data-2026-09-09.md`.
  Angka 89% / 95% / 46%: pengukuran HUB 18 Sep (kontrol 2025–2026, 11.975 kunjungan,
  2.034 pasien, 83 di >1 cabang; 74 ikut dokter; Cinere 54/57). Empat dokter
  berkeliling: Dito, Komalawaty Sp.Ort, Nugroho Sp.Ort, Ryan.
- **Jangan tambah angka**, jangan tulis rupiah (belum diukur — "pendapatan berulang
  terlewat" di slide 3 adalah *yang akan diukur*, bukan angka).
- Slide 2 dan 3 adalah **diagram**. Kalau 4 slide masih terlalu banyak: gabung
  slide 4 ke slide 3 sebagai baris "Kemudian".
