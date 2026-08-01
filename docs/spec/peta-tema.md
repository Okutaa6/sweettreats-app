# Peta Tema & Zona — SweetTreats

Spec hidup untuk konsistensi visual. Dipakai **setiap kali** bikin/reskin
halaman: tentukan zona → ambil token zona itu → ikuti aturan lapis di bawah.
Diputuskan 2026-07-25 (Ekky). Dasar riset di §9.

---

## 1. Prinsip induk

> **Konsisten DI DALAM zona, sengaja BEDA antar zona.**
> Warna bukan dekorasi — dia penanda arah ("aku sedang di area mana").

**Cokelat = default/rumah.** Hanya **TIGA** zona yang keluar dari cokelat:
Marketing (teal), IT/Sistem (grafit), Finance (burgundy). Sisanya cokelat.

---

## 2. Tiga lapis warna

### Lapis 1 — SHELL (KUNCI, tak pernah berubah)
Sidebar, brand/logo, frame atas. Selalu **cokelat** `#461500` (sidebar gradient
`#2B201A → #1C130D`). Ini identitas Sweet Treats + wayfinding: shell yang tetap
membuat aplikasi terasa satu walau isi zona beda warna. **Jangan pernah**
mewarnai sidebar per zona.

### Lapis 2 — AKSEN ZONA (beda per domain)
Aksen zona **HANYA** boleh dipasang di elemen berikut:

- header tabel (`thead tr`)
- tombol primer
- tab aktif / underline nav-dalam-halaman
- ikon di stat-card / kartu KPI
- header drawer / panel
- link dalam konten, focus ring, teks-terpilih

**HARAM:** mewarnai permukaan besar (background halaman, seluruh kartu, area
konten). Brand/aksen di permukaan luas melemahkan hirarki & menyulitkan
navigasi (Fluent 2). Aksen itu **bumbu, bukan cat tembok** — zona tetap
didominasi netral.

### Lapis 3 — TOKEN SEMANTIK (universal, SUCI)
Sama di **semua** zona; **tak boleh** ditabrak/diganti aksen zona:

| Makna | Teks | Background |
|---|---|---|
| Error / minus / alpa | `#C0392B` | `#FBE3DE` |
| Sukses / hadir / disetujui | `#2F8E55` | `#E3F4E8` |
| Warning / telat / perlu dicek | `#B5701A` | `#FBEFD9` |
| Info / cuti / netral-biru | `#3F6BAF` | `#E4ECFB` |
| Nol / mati / muted | `#A08A76` | `#F1E9DF` |

⚠️ **Aksen zona jangan sekeluarga warna dengan token semantik.** Contoh nyata:
Marketing teal ≈ hijau-sukses → jaga shade-nya jelas beda. Finance burgundy
dipilih justru supaya beda dari merah-error terang.

---

## 3. Token per zona

Netral (sama semua zona): cream `#F4ECE2` · ink `#3B1500` · sekunder `#5A3A22`
`#7A5B43` `#9A7256` · muted `#A9866A` `#B79A82` · kartu `#fff` · border kartu
`#EFE0CE` · border kontrol `#E7D3BE` · hover baris `#FBF4EC` · footer wash
`#FCF7F0`.
Font: **Plus Jakarta Sans** (400–800). Angka: `font-variant-numeric: tabular-nums`.
Radius: kartu-tabel **18** · kartu KPI/stat **16** · kontrol/tombol **10–11** ·
badge/pill **7–8** · chip **20**.
Shadow kartu: `0 1px 2px rgba(70,21,0,.04)` (+ `0 10px 26px rgba(70,21,0,.05)`
untuk kartu tabel). Tombol primer: `0 4px 12px rgba(20,20,18,.22)`.
Avatar palette: `#8A3A12 #A8481B #2F8E55 #3C5FA8 #B5701A #7A3415 #5A6B2E #8E2419`.

| Zona | Header tabel / panel | Teks di header | Tombol primer |
|---|---|---|---|
| **Cokelat** (default) | `linear-gradient(120deg,#5A2410,#3A1100)` | `#FBEADB` | `linear-gradient(135deg,#6B2400,#461500)` |
| **Grafit** (IT) | `linear-gradient(120deg,#2C2C29,#3A3A36)` | `#E3D8C9` | `linear-gradient(135deg,#3A3A36,#242422)` |
| **Burgundy** (Finance) | `linear-gradient(120deg,#7A2739,#5A1B29)` | `#F6E3E7` | `linear-gradient(135deg,#8A2E43,#6B2333)` |
| **Teal** (Marketing) | `#13928e` (solid) | `#FFFFFF` | `#13928e` · hover `#1aaaa5` |

**Token teal Marketing** (dinormalkan 2026-07-31 dari `theme-marketing.css` —
sumber kebenaran tetap file CSS itu, tabel ini catatannya):
deep `#0e6e6b` (teks aksen/brand panel) · main `#13928e` (tombol, header tabel,
link) · bright `#1aaaa5` (hover) · light `#c4ebe9` (pill/divider) · pale
`#e6f5f4` · page bg `#f4faf9` · edge `#0a4f4d` · lime sekunder `#89c541`
(hover `#9bd456`, deep `#6ea433`, pale `#eaf5da`). Mekanisme: override var
`--brown*` di `body[data-theme="marketing"]` — pola yang sama dipakai
`zona.css` untuk burgundy Finance (`body.zona-finance`) & grafit IT
(`body.zona-it`), bedanya zona di-scope ke `.page-content` saja (shell tetap
cokelat).

Aksen Finance = **`#8A2E43`** (burgundy/wine tua) — kalem di mata, kesan
ledger/uang, jelas beda dari merah-error `#C0392B` **dan** dari cokelat
operasional. Disiplin: burgundy hanya untuk chrome; merah terang tetap **hanya**
untuk status error.

**⚠️ Aturan teknis header tabel:** gradient dipasang di **`thead tr`**, `th`
dibuat `background: transparent`, tabel `border-collapse: separate;
border-spacing: 0`. Kalau dipasang per-`th`, gradient me-restart tiap sel →
**belang** di tabel kolom-banyak (pernah kejadian di Kehadiran ~30 kolom &
Lembar Gaji 13 kolom).

---

## 4. Mapping per halaman (zona)

> Basis: struktur `access_path` per 2026-07-25. **"Ringkasan" (dashboard)
> di-drop** dari peta — tak dipakai di prod.

### 🟩 TEAL — Marketing
Dashboard Marketing (`marketing-dashboard`) · Konten Pillar (`konten-pillar`) ·
EP Konten (`ep-konten`) · Breakdown Content (`ep-breakdown`) · Campaign
Framework (`ep-campaign`) · Kode Referral (`rawpromo`) · Klaim Komisi
(`commission-claim`) · Review Konten (`content-review`) · Report Referral
(`report-referral`) · Affiliate (`rawaffiliate`) · Promo (`marketing-promo`) ·
Keyword (`rawkeyword`) · Sweet Mates (`sweet-mates`) · KOL/KOC

### ⬛ GRAFIT — IT / Sistem
Pusat Akun: Kesehatan (`setkesehatan`) · User (`setusermanagement`) · Akses
(`setaccess`) · Role (`setgroup`) · Setting (`setsystemprofile`) ·
Audit Log (`auditlog`) · **WiFi Cabang (`absensi/wifi`)** ·
Integrasi Accurate: Monitor (`accurate-monitor`) · Peta Data (`accurate-datamap`) ·
Rekonsiliasi Accurate (`accurate-recon`)

### 🟥 BURGUNDY — Finance
Petty Cash (`finance/pettycash`) · Executive Report (`report/executive`) ·
Laporan Keuangan (`accurate-keuangan`) · Laporan Accurate (`accurate-laporan`) ·
Laporan Penjualan (`report`) · Rekonsiliasi (`reconcile`) ·
**Antrian & Rekap ROP (`finance/rop`)** — sisi Finance dari fitur Pengajuan/ROP
(keputusan 2026-07-31: pengaju cokelat, Finance burgundy; lihat
`docs/rencana/rencana-pengajuan-rop.md`)

### 🟫 COKELAT — Operasional (default) + HR/Absensi + personal + lintas-zona
Reservasi (`rawreservation`) · Reservasi Cabang (`reservasicabang`) · Pasien
(`rawcustomer`) · Doctor (`rawdoctor`) · Doctor Service (`rawdocservice`) ·
Branch (`rawbranch`) · Dashboard Sales (`sales-dashboard`) · Keluhan
(`complaint`) ·
**Absensi/HR:** Kehadiran (`absensi/kehadiran`) · Upload Data (`absensi`) ·
Mapping Karyawan (`absensi/mapping`) · Pengawasan Absen (`pengawasan`) · Rekap
Persetujuan (`absensi/recap`) ·
**Payroll (tetap cokelat, ikut lokasi menu):** Periode Gaji (`absensi/periode`) ·
Cuti (`absensi/cuti`) · Lembar Gaji (`absensi/lembar-gaji`) ·
**Checklist (cokelat):** Dashboard (`checklist`) · Setup Tugas
(`checklist/setup`) · Review & Approval (`checklist/review`) · Posisi Karyawan
(`checklist/positions`) · Audit Trail (`checklist/audit`) · Checklist Saya
(`my-checklist`) ·
**Personal & lintas-zona:** Akun Saya / Absensi Saya (`akun`) · Absen HP
(`akun/absen`) · Pengajuan · Inbox Persetujuan (`persetujuan`) · Project Tracker
(`project-tracker`) · Ganti Password · **Pengajuan ROP sisi pengaju (`rop`)** —
dipakai lintas role (SPV/koordinator/head), bukan orang Finance → cokelat

### Keputusan yang mendasari (jangan diperdebatkan ulang tanpa alasan baru)
- **Operasional = SATU zona.** Sales/FO/GS/Asdent/HR **tidak** dipisah warna —
  pemisahan mereka lewat **akses (RBAC)** dan **device-intent**, bukan warna.
  Alasan: aksen menjawab "aku di AREA mana", bukan "aku SIAPA"; halaman yang
  sama harus terlihat sama untuk semua penontonnya.
- **Payroll & Checklist cokelat** — ikut lokasi navigasi, bukan domain murni,
  supaya dalam satu menu tak campur dua warna.
- **WiFi Cabang → grafit + menu-nya DIPINDAH** ke seksi IT/Pusat Akun (bukan
  hanya diubah warnanya), supaya "lokasi menu = domain" konsisten.
  → *Aksi: ubah `pid` node `abs-wifi-0000000000000` via SQL access_path + rebuild.*
- **Data Center bukan zona.** Isinya campur (Reservasi/Pasien = operasional,
  Affiliate/Promo = marketing) → tiap halaman ikut domainnya sendiri.

---

## 5. Lensa DEVICE-INTENT (mobile vs desktop)

Dimensi terpisah dari warna. **Akses TIDAK pernah dihilangkan** — halaman
desktop-only tetap bisa dibuka dari HP.

**Pola: gerbang sopan, BUKAN hide.** Halaman desktop-only dibuka di HP →
tampilkan pesan ramah: *"Halaman ini paling nyaman dibuka di komputer."* +
tombol **"Lanjut lihat"** (tampilkan apa adanya, scroll horizontal). Boleh
disembunyikan dari sidebar HP agar rapi, **asal tetap bisa diakses** via link/
gerbang. Alasan menolak hard-hide: menu kosong di HP = kegagalan-senyap ("fitur
rusak/hilang"), padahal kadang orang cuma mau ngintip satu angka dari luar.

### Mobile-ready (WAJIB nyaman di HP — dipakai staf lapangan)
Absen HP (`akun/absen`) · Akun Saya (`akun`) · Pengajuan + Detail Pengajuan ·
Koreksi Absen · Checklist Saya (`my-checklist`) + weekform · Ganti Password ·
Login · Landing + Reservasi publik (`/reservasi`) · Portal Afiliasi

### Desktop-first, gerbang sopan di HP
Semua dashboard (Marketing/Sales/Executive) · Editorial Plan & seluruh Marketing ·
Pusat Akun (semua) · Audit Log · Integrasi Accurate · Petty Cash & Laporan ·
Kehadiran · Upload Data · Mapping · Rekap Persetujuan · Periode Gaji · Cuti ·
Lembar Gaji · Pengawasan (*panel + foto; layak dibuat tablet-friendly nanti*) ·
CRUD (Reservasi/Pasien/Doctor/Branch) · Checklist Setup/Review/Posisi/Audit ·
Project Tracker

*Catatan:* Pengawasan Absen dipakai SPV yang mungkin mobile — kandidat
prioritas berikutnya untuk dibuat benar-benar responsif.

---

## 6. Ikon & emoji

**Tanpa emoji di UI.** Semua ganti **SVG line-icon** (`stroke: currentColor`,
`fill: none`, stroke-width ~2.2, ukuran 14–16px inline). Alasan: emoji
render-nya beda per OS/browser, tak bisa diwarnai token, dan terasa kurang
kredibel untuk sistem internal.

Glyph yang **BOLEH** tetap (bukan emoji, murni tipografi UI): `‹ › ← → ✕ · • —`
dan tanda minus asli `−` (U+2212).

Emoji yang sudah teridentifikasi untuk disapu: `⚠️` (banyak file — ganti ikon
triangle-alert), `🎉` (petty cash pending queue, report/sales, inbox
persetujuan), `🎯` (EP Konten/Breakdown Marketing), `📞 🎂` (crud/customer/list),
`🔗` (inbox), `📢 🙂` (checklist setup), `✅ ❌` (reservation-form publik),
`⚑` (akun/absensi). Sekitar 10–18 file. **FontAwesome yang dipakai = 5.8.2**
(bukan 6) — jangan pakai nama ikon FA6-only.

---

## 7. Checklist saat bikin/reskin halaman

1. Tentukan **zona** (§4) → ambil token header/tombol zona itu (§3).
2. Shell tetap cokelat — jangan disentuh.
3. Aksen hanya di elemen yang diizinkan (§2 lapis 2). Jangan cat permukaan besar.
4. Status pakai **token semantik** (§2 lapis 3) — bukan aksen zona, bukan hex baru.
5. Header tabel: gradient di `thead tr`, `th` transparan.
6. Netral, font, radius, shadow: dari §3.
7. Tentukan **device-intent** (§5); kalau desktop-only → siapkan gerbang sopan.
8. Ikon SVG line, tanpa emoji (§6).

---

## 8. Utang / TODO peta ini
*(dieksekusi 2026-07-31, chat "eksekusi visual")*
- [x] Normalkan & catat **hex teal Marketing** → tabel & catatan token di §3.
- [x] Terapkan **burgundy** ke halaman Finance → `zona.css` + body-class
      `zona-finance` (segment finance/report/reconcile), override var `--brown*`
      di-scope `.page-content` (shell tetap cokelat). Chart data-viz di
      Executive tidak disentuh (palet data ≠ chrome).
- [x] **Pindahkan node menu WiFi Cabang** ke seksi IT (Pusat Akun) —
      SQL `database/sql/2026_07_31_wifi_ke_pusat_akun.sql` + `access:rebuild`
      (SUDAH jalan di lokal/dev; prod ikut deploy berikutnya). Warna grafit via
      body-class `zona-it`. Bonus: sorot sidebar & judul header kini ikut node
      paling spesifik (fix dobel-sorot Upload Data + WiFi).
- [x] Sweep **emoji → ikon** (§6) — 14 file. Sisa yang SENGAJA dibiarkan:
      `crud/reservation/list` (ikut redesign reservasi/Reza), file backup
      v1.5.2, dan glyph mono tipografi (✓ ✗ ★ ✎ ⚠ polos di executive/komentar).
- [x] Implementasi **gerbang sopan** desktop-only — komponen
      `layouts/_gerbang_sopan.blade.php`, dipasang otomatis dari layout untuk
      segment desktop-first ("Lanjut lihat" diingat per sesi browser).
      Kehadiran TIDAK digerbang (sudah responsif betulan — auto view Hari).
- [ ] Reservasi: samakan header (`180deg #5C2A0A→#3D1A00` + Montserrat) ke token
      cokelat + Plus Jakarta → **kerjakan bareng redesign reservasi/Reza**,
      jangan ad-hoc.
- [ ] Halaman Role (`setgroup`): font default + token warm sendiri → ikut
      **reskin Role** (butuh ACC Reza). (Drift border di file ini juga sengaja
      belum disentuh.)
- [x] Polish minor: palet avatar disatukan ke palet §3 (customer/doctor/
      pettycash audit feed), drift border `#eadfd6`/`#efe4d6` → `#EFE0CE`,
      marker biru `#2779a7` → `#3F6BAF`, jam kembar "10:22–10:22" disembunyikan.

---

## 9. Dasar riset
- **Fluent 2 (Microsoft)** — suite multi-produk: warna dasar diselaraskan agar
  dikenali satu keluarga; produk dibedakan lewat warna brand di **area
  tertentu** untuk "menambatkan" pengguna; brand **jangan** di permukaan besar.
  https://fluent2.microsoft.design/color
- **Apple HIG — Sidebars**: sidebar diberi treatment visual berbeda dari konten;
  fungsinya wayfinding.
  https://developers.apple.com/design/human-interface-guidelines/components/navigation-and-search/sidebars/
- **Nord Design System — Color**: variabel warna sidebar dipisah dari konten,
  supaya sidebar bisa distyle berbeda secara konsisten.
  https://nordhealth.design/design/foundations/colors/
- **EightShapes (Nathan Curtis) — Color in Design Systems**: pisahkan warna
  brand dari warna utilitas/semantik lewat token.
  https://medium.com/eightshapes-llc/color-in-design-systems-a1c80f65fa3
