# Materi Deck — ORTHO: Peta Arah & Alur Informasi

> Untuk **chat build**. Disusun HUB 18 Sep 2026 atas arahan Ikhsan (planner fitur).
> Pembaca: **internal + Executive**, yang **sudah membaca brief** — jadi deck ini
> **tidak mengulang masalah, angka, atau keputusan Sales**. Isinya hanya tiga hal:
> alur fitur, ke mana informasinya mengalir, dan tahap pengembangannya.
> **4 slide** (+ judul). Satu gagasan per slide. Visual > teks.
> Tiket IT-2026-013 · analisis Rahmat selesai · desain & bangun Reza · arah Ikhsan.
> Versi 14 slide yang lebih panjang ada di riwayat git (`5ea57ec`) kalau perlu.

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
 │ sync     │   │  status  │   │ FO hari ini   │   │ tercatat     │   │ retensi  │
 │ harian   │   │          │   │               │   │              │   │          │
 └──────────┘   └──────────┘   └───────────────┘   └──────────────┘   └──────────┘
  kontrol ·       Aktif ·        "hubungi 7 orang   hubungi → tawarkan   per cabang ·
  bracket ·       perlu          ini hari ini",     jadwal → hasilnya    per dokter ·
  lepas ·         dihubungi ·    sudah diurutkan    dicatat              tren
  cabang          Pindah ·
                  Selesai ·
                  Berhenti
```

**Rel yang dipakai** (keputusan arah — tim mengikuti, tidak membuka ulang):
nol pengisian manual · **satu data untuk lima cabang**, tampilan per cabang ·
pemilik = cabang perawatan terakhir, pindah setelah **2 kunjungan berturut** ·
**Selesai ≠ Berhenti** · status tak berbasis waktu, **peringatan** yang berbasis waktu.

*Catatan pembicara:* "Berhenti" satu-satunya yang ditandai tangan — sistem tak bisa
tahu pasien pindah kota. Sisanya turun sendiri dari data kunjungan.

---

## SLIDE 2 — Peta alur informasi: ORTHO bukan pulau

```
              MASUK                                    KELUAR
 ┌──────────────────────┐                  ┌──────────────────────────────┐
 │ Omnicare             │ kontrol, bracket │ Reservasi                    │
 │ rekam medis & kasir  │ ───────────────▶ │ "tawarkan jadwal" → langsung │
 └──────────────────────┘                  │  jadi booking                │
                                 ┌──────┐  └──────────────────────────────┘
 ┌──────────────────────┐        │      │  ┌──────────────────────────────┐
 │ Reservasi            │ no-show│ORTHO │─▶│ Cekat (WhatsApp)             │
 │ pasien ortho         │ ──────▶│      │  │ pengingat otomatis, telat    │
 │ tak datang           │        │      │  │ kontrol                      │
 └──────────────────────┘        └──────┘  └──────────────────────────────┘
                                   │ │     ┌──────────────────────────────┐
 ┌──────────────────────┐          │ └────▶│ Akun Dokter                  │
 │ Cekat (WhatsApp)     │ balasan  │       │ "pasien ortho saya"          │
 │ pasien membalas      │ ────────▶│       └──────────────────────────────┘
 └──────────────────────┘          │       ┌──────────────────────────────┐
                                   ├──────▶│ Laporan Executive            │
                                   │       │ retensi · bolong · selesai   │
                                   │       └──────────────────────────────┘
                                   │       ┌──────────────────────────────┐
                                   └──────▶│ Marketing                    │
                                           │ Endorser · lead pemasangan   │
                                           └──────────────────────────────┘
```

**3 masuk · 5 keluar.** Omnicare tetap **satu arah** — kita membaca, tidak menulis balik.

**Cekat, bukan MaxChat**, untuk pengingat: satu jejak percakapan per pasien —
pengingat keluar dan balasan masuk lewat kanal yang sama yang sudah dipakai FO.

---

## SLIDE 3 — Tahapan: lihat → bertindak → otomatis → ukur

```
 FASE 1           FASE 2             FASE 3             FASE 4
 LIHAT            BERTINDAK          OTOMATIS           UKUR
 ────────────     ────────────       ────────────       ────────────
 tracking         daftar "hubungi    pengingat Cekat    laporan retensi
 otomatis;        hari ini";         ke yang telat;     per cabang &
 5 status;        tindak lanjut      balasan masuk      dokter → Laporan
 Cinere = alat    tercatat;          jejak yang sama    Executive;
 ukur → 5 cabang  tawarkan jadwal                       Endorser & lead
                  → reservasi
 ────────────     ────────────       ────────────       ────────────
 sheet berhenti   FO berhenti        FO berhenti        Executive tahu
 diisi            menggulir &        mengetik           berapa bolong,
                  WA antar cabang    satu-satu          berapa kembali
```

**Syarat lulus Fase 1** (keras, terukur): mereproduksi **222** pasien Cinere +
memunculkan **12** pindah-cabang & **60** jarang-kontrol yang sheet tak lihat.

**Dua hal dimulai paralel dengan Fase 1, bukan sesudahnya:**
- **template WhatsApp** diajukan ke Meta lewat Cekat (persetujuan berminggu-minggu)
- **webhook Cekat terautentikasi sejak hari pertama** — jangan mewarisi webhook lama yang berjalan tanpa token

*Catatan pembicara:* empat utang teknis sengaja menunggu fitur ini masuk
Development (ganti webhook → Cekat, keamanan §6, tanggal lahir tertukar, layar
rekonsiliasi). Reza tahu keempatnya.

---

## SLIDE 4 — Sesudah ortho: pintu yang sengaja dibiarkan terbuka

Fokus sekarang **ortho saja**. Tapi polanya —
*kunjungan berulang → status otomatis → daftar hubungi → tindak lanjut tercatat* —
**bukan pola khusus behel.**

| Perawatan | "kontrol"-nya | jeda wajar |
|---|---|---|
| Implant | pasca-pasang | bulanan → tahunan |
| Veneer / crown | berkala | 6 bulan |
| Scaling rutin | kunjungan ulang | 6 bulan |
| Saluran akar | lanjutan | mingguan |

Arahan ke tim desain: **jangan kunci model ke kata "ortho"** di tempat yang tak
perlu. Aturan jeda boleh beda per perawatan; mesinnya satu. Bukan tugas sekarang —
supaya tidak dibongkar nanti.

**Yang diminta dari Executive:** restu rel di slide 1 · Cinere sebagai cabang
pengukur · pengajuan template WhatsApp **sekarang**.

---

## Lampiran untuk chat build (tidak masuk slide)

- Semua angka (222/12/60, 5 cabang) dari `docs/rencana/ortho-lampiran-data-2026-09-09.md`.
  **Jangan tambah angka**, jangan tulis rupiah (belum diukur).
- Slide 2 dan 3 adalah **diagram**, bukan bullet — itu inti deck ini.
- Kalau harus 3 slide: gabung slide 4 ke slide 3 sebagai baris "Kemudian".
