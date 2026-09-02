# IT Request Tracker — Alur, Status, & Field

> **Tahap: pemetaan.** Ditulis chat HUB 2026-09-01 dari hasil pembahasan Ekky.
> Dokumen ini jadi bahan untuk **dua** hal berikutnya: brief desain
> (claude.ai/design) dan rencana build penuh. Belum rencana koding.

## 1. Masalah yang diselesaikan

IT = **dua orang** (Ekky + Reza) dengan ±**5 permintaan modul besar per bulan**,
masing-masing berisi fitur di dalamnya. Keluhannya bukan kecepatan, tapi:

- permintaan datang tanpa pemohon ikut berpikir — semua diserahkan ke IT
- eksekutif tak melihat antrean, jadi menambah kerjaan begitu satu selesai
- tak ada tempat melihat **IT sedang memikul apa**

⚠️ **Beban proses di sini DISENGAJA, bukan ongkos yang harus ditekan.** Formulir
memaksa pemohon terlibat; antrean yang terlihat jadi alat tawar. Jangan
"disederhanakan" tanpa memahami itu.

## 2. Pengguna

| Peran | Boleh |
|---|---|
| **Head** (head-in-training) | mengajukan, mengisi UAT miliknya, melihat antreannya |
| **Executive** | melihat semua, ikut di Requirement & Design, menyetujui |
| **Konsultan** | sama seperti Executive — sengaja, mereka memegang akses lintas-departemen supaya head-in-training tak dibebani (keputusan Robi) |
| **IT — Ekky** | triase, bobot, prioritas, semua fase |
| **IT — Reza** | **hanya mengubah status item yang dia kerjakan sendiri, di fase Development.** Tak triase, tak menentukan prioritas |

⚠️ Reza tidak sekadar lapor lisan ke Ekky — kalau begitu, Ekky jadi juru ketik
status dan itu menambah beban yang justru mau dikurangi.

## 3. Status & transisi

```
                    ┌─────────── INSIDEN / HOTFIX ───────────┐
                    │  masuk langsung ke DEVELOPMENT,         │
                    │  formulir diisi menyusul                │
                    └────────────────────┬───────────────────┘
                                         │
       ┌──────── INISIATIF IT ───────────┤
       │  mulai dari ANALISIS,           │
       │  tanpa pemohon                  │
       └────────────────┬────────────────┘
                        │
[Head/Exec/Konsultan]   │
   isi Requirement      │
        ↓               │
      BARU → TRIASE → ANTRE → ANALISIS → DEVELOPMENT → UJI → UAT → GO-LIVE → EVALUASI → SELESAI
                │                                                   │
                ├→ DITOLAK                                          └→ (gagal) ROLLBACK → DEVELOPMENT
                └→ DITUNDA ──(dibuka lagi)──> ANTRE
```

### Jenjang menentukan fase yang dilewati

| Jenjang | Lewat fase |
|---|---|
| **S** — bug, tweak, <1 hari | ANTRE → DEVELOPMENT → SELESAI |
| **M** — fitur | + ANALISIS, UAT, GO-LIVE |
| **L** — modul / integrasi | ketujuhnya penuh |

**Jenjang ditentukan IT saat triase, bukan pemohon.** Pemohon mengisi
"prioritas menurut saya"; IT menetapkan prioritas nyata. **Dua kolom terpisah,
jangan disatukan.**

## 4. Gerbang — apa yang membukanya

Kesepakatan: **campur.** Fase awal dibuka kelengkapan + terima IT; fase akhir
wajib persetujuan manusia.

| Transisi | Syarat | Jenis |
|---|---|---|
| BARU → TRIASE | otomatis saat dikirim | — |
| TRIASE → ANTRE | jenjang + bobot + prioritas IT terisi | IT (manusia) |
| ANTRE → ANALISIS | IT menarik kerjaannya | IT |
| **ANALISIS → DEVELOPMENT** | **≥1 FR dengan `Kriteria Diterima` terisi** + ruang lingkup + komponen terdampak | **kelengkapan field** + terima IT |
| DEVELOPMENT → UJI | IT menandai siap | IT |
| UJI → UAT | semua test case berstatus, **nol temuan Major terbuka** | kelengkapan field |
| **UAT → GO-LIVE** | **pemohon/Head menyetujui** | **persetujuan manusia** |
| **GO-LIVE → EVALUASI** | checklist pra-deploy semua Ya/N-A + pemeriksaan pasca Go-Live Pass | **persetujuan manusia** |
| EVALUASI → SELESAI | evaluator mengisi, **atau tutup otomatis setelah 14 hari** | campur |

⚠️ **`Kriteria Diterima` adalah field terpenting di seluruh sistem.** Ia memaksa
"selesai" didefinisikan sebelum koding. Jangan dibuat opsional.

⚠️ **UAT yang didiamkan TIDAK boleh otomatis dianggap diterima.** Ia harus
**menyala di papan** — itu justru bukti yang dicari (kemacetan ada di pemohon,
bukan di IT).

## 5. "Menunggu siapa, sejak kapan" — jantung sistemnya

Field ini **tidak ada di template PDF**, dan justru inilah yang menjawab tujuan
utama. Dihitung, bukan diisi manual:

```
penanggung fase saat ini  +  lama sejak fase itu dimasuki
```

| Fase | Menunggu |
|---|---|
| BARU / TRIASE | IT |
| ANTRE | (tak menunggu siapa — memang antre) |
| ANALISIS | IT + pemohon (bersama) |
| DEVELOPMENT / UJI | IT |
| **UAT** | **pemohon / Head** |
| **GO-LIVE** | **Executive / Business Owner** |
| EVALUASI | evaluator |

Tampilkan di kartu, dan **beri warna memanas** setelah ambang (usul: 3 hari
kuning, 7 hari merah). Ini satu-satunya angka yang membuat "IT lambat" bisa
dibantah dengan data.

## 6. Nomor tiket

Format **`IT-2026-001`**, berurut, dipakai di ketujuh tahap **dan di commit
message**. Itu menutup jarak antara papan dan kode — riwayat git bisa dilacak
balik ke tiketnya, dan sebaliknya.

## 7. Field per fase

Diturunkan dari FRM-IT-01..07, **dengan perubahan** yang disepakati:

**Intake (FRM-IT-01)** — pemohon · departemen · **cabang dari `raw_branch`**
(bukan "Cabang 1..5") · jenis permintaan · nama modul/fitur · **prioritas
pemohon** · latar belakang · hasil yang diharapkan · pengguna terdampak ·
lampiran.

**Triase (baru, tak ada di PDF)** — **jenjang S/M/L** · **prioritas IT** ·
**bobot** · PIC · induk (kalau ini fitur di dalam modul lain).

**Analisis (FRM-IT-02)** — tujuan · ruang lingkup (termasuk/tidak) · **tabel FR
dengan `Kriteria Diterima`** · komponen terdampak · hak akses · dampak cabang ·
**ketergantungan sebagai relasi ke tiket lain, bukan teks bebas** · risiko ·
mitigasi · **estimasi (internal IT saja)**.

**Development (FRM-IT-03)** — catatan implementasi per komponen · **branch /
commit / SQL** · keterbatasan untuk tester.
→ **Bagian 3C "Developer Testing" DILEBUR ke fase UJI** (untuk tim 2 orang, itu
formulir yang sama diisi dua kali).

**Uji (FRM-IT-04)** — test case (skenario · diharapkan · aktual · status) ·
temuan (Minor/Major · PIC · status) · ringkasan.

**UAT (FRM-IT-05)** — skenario bisnis · sesuai/tidak · masukan end user ·
keputusan.

**Go-Live (FRM-IT-06)** — checklist pra-deploy (**backup · script siap ·
rencana rollback**) · catatan deployment · pemeriksaan pasca Go-Live · hasil.
⚠️ Formulir ini **paling matang di seluruh set** dan cocok dengan praktik nyata
kita — pertahankan apa adanya.

**Evaluasi (FRM-IT-07)** — kesesuaian · kemudahan · stabilitas · dampak ·
temuan & usulan · tindak lanjut.
⚠️ **Wajib punya pemicu terjadwal.** Tanpa itu tahap ini tak akan pernah
terjadi dan cuma jadi beban mati.

## 8. Hubungan dengan yang sudah ada

- **Project Tracker** (`feature/project-tracker`, `sys_project → task → todo`,
  Fase A selesai Juli, belum naik). **Tetap terpisah** — audiensnya seluruh
  office/ABG, ini khusus Head/Exec/Konsultan/IT.
  **Sambungannya**: tiket yang lolos gerbang ANALISIS **melahirkan project** di
  tracker. Modul → project, fitur di dalamnya → task. Fase tetap di sini,
  eksekusi harian di sana. *(Fase 3 build.)*
- **Papan Gemini** (`log-update.md` + `status-proyek.md` di Google Drive) —
  keputusan Ekky: **dipensiunkan** setelah tracker jalan. Jangan merawat dua
  papan; itu yang membuat catatan basi.

## 9. Fase build

Desain di claude.ai/design mencakup **ketujuh fase** (mockup penuh = alat
komunikasi ke eksekutif). **Build-nya dipecah**, dan tiap layar di mockup diberi
**badge Fase 1/2/3** supaya mockup jadi peta jalan, bukan janji.

| | Isi | Kenapa |
|---|---|---|
| **Fase 1** | formulir permintaan · triase · papan antrean · "menunggu siapa" | **sudah memenuhi tujuan utama**: pemohon berpikir, eksekutif melihat antrean |
| **Fase 2** | ketujuh fase + gerbang + form per tahap | tata kelola |
| **Fase 3** | sambungan Project Tracker · penjadwalan Evaluasi · nomor tiket di commit | penyempurnaan |

Fase 1 saja sudah menghentikan "semena-mena menambah kerjaan" — dan kalau
ternyata IT sendiri malas mengisinya, itu ketahuan **sebelum** tujuh fase
dibangun.

## 10. Yang harus masuk sistem, bukan cuma permintaan luar

⚠️ **Kerjaan yang lahir dari IT sendiri WAJIB punya tiket** (penanda "Inisiatif
IT"). Contoh nyata bulan ini yang tak satu pun datang dari formulir: saklar
Accurate, rekonsiliasi fee dokter, insiden rsync yang mematikan prod, security
hardening, koreksi checklist.

Kalau papan hanya menampilkan permintaan luar, eksekutif melihat **5 item**
padahal IT memikul **15** — dan menyimpulkan IT senggang. Itu **kebalikan** dari
tujuan sistem ini.

Sama berlaku untuk kerjaan yang Reza mulai sendiri.

## 11. Keputusan yang sudah diambil (jangan dibuka ulang)

1. Beban proses disengaja — **bukan** untuk ditekan.
2. Tanda tangan **1–2 saja** per tahap, kecuali procurement.
3. Jenjang S/M/L, ditentukan **IT**.
4. Gerbang **campur**: awal = kelengkapan field, akhir (UAT & Go-Live) = manusia.
5. UAT didiamkan → **menyala**, bukan otomatis diterima.
6. Estimasi hari **internal IT saja**; publik lihat ukuran S/M/L.
7. Nomor tiket `IT-2026-001` dipakai sampai ke commit message.
8. Reza mengubah status miliknya sendiri di fase Development.
9. Terpisah dari Project Tracker, tapi bersambung di fase Development.
10. Papan Gemini dipensiunkan.
11. Backlog berjalan diisi manual setelah sistem jadi.

## 12. Yang masih terbuka

- Ambang warna "menunggu" — 3 hari kuning / 7 merah, atau lain?
- Siapa **evaluator** default di fase 7 — pemohon, atau Executive?
- Apakah **Head boleh melihat antrean penuh** (semua tiket) atau hanya miliknya
  + posisi nomor? Ini menentukan seberapa "telanjang" beban IT terlihat.
