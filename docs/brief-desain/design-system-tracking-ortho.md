# Design System — Tracking Pasien Ortho

**2 Oktober 2026 · `public/assets/css/ort-tracking.sweettreats.css` · 1.533 baris · 273 selector · 31 token · 7 keyframes · 7 blok `@media` · 23 view**

> Teman berkas ini: `brief-tracking-ortho-berjalan.md` (apa yang ditampilkan)
> dan `alur-fungsi-tracking-ortho.md` (apa yang terjadi saat ditekan).

---

## 0. Tiga aturan yang dipegang seluruh berkas

1. **Cokelat hanya untuk chrome** — header tabel, tab aktif, tombol primer.
   Haram mewarnai permukaan besar.
2. **Merah `#C0392B` khusus makna negatif.** Bukan aksen.
3. **Gradient dipasang di BARIS header, bukan per sel.** Kalau per sel,
   gradient-nya me-restart dan tabelnya jadi belang. Berlaku untuk `.ort-thead`
   (CSS:242) dan `.ort-cal-dow` (CSS:902).

---

## 1. Ini BUKAN sistem baru — ini zona Cokelat yang dipakai

`docs/spec/peta-tema.md` sudah menetapkan empat zona warna rumah. Modul ini
memakai zona **Cokelat (default/operasional)** apa adanya:

| Peran | Nilai | Sama dengan peta-tema? |
|---|---|---|
| Header tabel / panel | `linear-gradient(120deg, #5A2410, #3A1100)` | ✅ |
| Teks di atas cokelat | `#FBEADB` | ✅ |
| Tombol primer | `linear-gradient(135deg, #6B2400, #461500)` | ✅ |
| Error / minus | `#C0392B` di `#FBE3DE` | ✅ |
| Sukses / hadir | `#2F8E55` di `#E3F4E8` | ✅ |
| Warning / telat | `#B5701A` di `#FBEFD9` | ✅ |
| Info / netral-biru | `#3F6BAF` di `#E4ECFB` | ✅ |
| Nol / mati / muted | `#7E5F46` di `#F1E9DF` | ⚠ **teksnya beda** |

⚠ **Satu-satunya penyimpangan:** peta-tema menetapkan teks muted `#A08A76`,
modul ini memakai `#7E5F46` — lebih gelap, kontras lebih tinggi di atas latar
krem `#F1E9DF`. Latarnya sama. Kalau ada yang menyeragamkan nanti, CSS:37 adalah
baris yang disentuh.

---

## 2. Token — 31 custom property

Semua di **selector `.ort`** (CSS:16–46), nol di `:root`.

```css
.ort {
  /* teks */
  --ort-ink:    #2E1000;  /* nama, angka besar       */
  --ort-ink2:   #402009;  /* teks isi sel            */
  --ort-ink3:   #5F4229;  /* sekunder / kaki tabel   */
  --ort-muted:  #8A6A50;  /* meta, label, redup      */
  /* permukaan & garis */
  --ort-line:   #EFE0CE;  /* garis kartu/KPI         */
  --ort-line2:  #E7D3BE;  /* garis input & tombol    */
  --ort-row:    #F4EADD;  /* garis bawah baris tabel */
  --ort-sunken: #FCF7F0;  /* baris terbuka, kaki     */
  --ort-strip:  #FAF1E6;  /* kepala kelompok         */
  --ort-hover:  #FBF4EC;  /* hover baris             */
  /* chrome cokelat */
  --ort-brand1: #5A2410;  --ort-brand2: #3A1100;   /* gradien kepala  */
  --ort-btn1:   #6B2400;  --ort-btn2:   #461500;   /* gradien tombol  */
  --ort-onbrand:#FBEADB;                           /* teks di atasnya */
  --ort-accent: #C9962F;  /* emas: bintang, fokus, loadbar, hari ini  */
  /* semantik */
  --ort-ok:       #2F8E55;  --ort-ok-bg:       #E3F4E8;
  --ort-warn:     #B5701A;  --ort-warn-bg:     #FBEFD9;
  --ort-bad:      #C0392B;  --ort-bad-bg:      #FBE3DE;
  --ort-info:     #3F6BAF;  --ort-info-bg:     #E4ECFB;
  --ort-neu:      #7E5F46;  --ort-neu-bg:      #F1E9DF;
  --ort-wa:       #2C8C63;  --ort-wa-line:     #BFE3CD;
  --ort-transfer: #6B2400;  --ort-transfer-bg: #F6EBE1;
  --ort-faint:    #7C5B41;  /* "sudah lewat / sudah beres" — 5 pemakai */
}
```

### ⚠ Jebakan yang sudah menggigit

Token di-scope ke `.ort`. Apa pun yang dirender **di luar** pembungkus itu
membuat semua `var(--ort-*)` gagal di computed-value time: warna jatuh ke
initial, dan `linear-gradient(120deg, var(--ort-brand1), var(--ort-brand2))`
gagal **total** → latar transparan.

Yang paling berisiko adalah yang di-*inject* JavaScript: `.ort-drawer`,
`.ort-sheet`, `.ort-scrim`, `.ort-toast`, `.ort-loadbar`. Repo sudah
membentenginya di tiga tempat:

| Tempat | Benteng |
|---|---|
| `list.blade.php:248-250` | `#ortDrawerHost` dan `#ortSheetHost` sengaja berada **di dalam** `<div class="ort">` |
| `list.blade.php:581-589` | `querySelector('.ort').appendChild(toastBaru)` + komentar *"Sudah kena sekali — jangan dipindah ke body lagi."* Toast pernah tampil putih pucat karena di-append ke `<body>` |
| `list.blade.php:334` | Loadbar `(document.querySelector('.ort') \|\| document.body)` — fallback-nya berarti loadbar emas jadi tak berwarna kalau `.ort` tidak ada |

### `--ort-faint` — dulu hantu, sekarang token ke-31

Sampai 2 Okt 2026 ia dirujuk `var(--ort-faint, #7C5B41)` di `.ort-urow__hint`
tapi **tidak pernah didefinisikan** — selalu jatuh ke fallback. Sementara itu
nilai `#7C5B41` yang sama di-hardcode di **empat tempat lain**.

Sekarang satu definisi, lima pemakai:

| Pemakai | Guna |
|---|---|
| `.ort-urow__hint` | hint di baris sub-tab |
| `.ort-chip--selesai` | chip "selesai" |
| `.ort-held--selesai .ort-held__icon` / `__text` | kartu kaki perawatan selesai |
| `.ort-roster-head__n` | hitungan pasien di kepala roster |

Semuanya bermakna sama: **"sudah lewat / sudah beres"** — lebih tenang dari
`--ort-ink3`, lebih gelap dari `--ort-muted`. Warnanya di layar tidak berubah
sedikit pun; yang berubah cuma satu tempat mengaturnya.

### `.ort` dideklarasikan 2× dengan peran beda

| Baris | Peran |
|---|---|
| CSS:16 | Pembawa token saja |
| CSS:1084 | Layout halaman: `padding: 16px 26px 26px; display:flex; flex-direction:column; gap:13px` |

Keduanya harus ikut kalau selectornya diganti nama.

### Pemuatan

Hanya oleh halaman ortho, dengan cache-busting `filemtime`
(`list.blade.php:21`). Pembungkus `.ort` dipasang di dua tempat:
`list.blade.php:63` dan `belum-siap.blade.php:18`.

---

## 3. Tipografi & angka

| Kelas | Guna |
|---|---|
| `.ort .tabular` (CSS:48) | `tabular-nums` — **inilah yang dipakai view**, 68 kemunculan |
| `.ort .ort-num` (CSS:49) | Alias yang sama — **YATIM, 0 pemakaian**. Buang `, .ort-num` |
| `.ort-name` | Nama pasien 14px/700, line-height 1.35 (final CSS:1309) |
| `.ort-cell` · `--sub` | Sel 13px / sub-teks 11px block (final CSS:1311-1312) |
| `.ort-clamp2` | `-webkit-line-clamp:2` + `overflow-wrap: anywhere` supaya nama dokter tidak terpotong tengah kata |
| `.ort-lbl` | Label form uppercase 10.5px/800 ls .55 |
| Judul kolom | 10.5px/800 uppercase ls .6 (final CSS:686) |

Tidak ada webfont — font sistem.

⚠ `text-overflow: ellipsis` **tidak bekerja** pada container `inline-flex`. Itu
sebabnya `.ort-nodo__t` (CSS:359) ada: pembungkus teks **wajib**, bukan
kerapian. Kolomnya terkunci 190px.

---

## 4. Ikon

**Font Awesome 5, self-host.** Nama gaya FA6 (`fa-circle-check`) render sebagai
kotak kosong. Pakai nama FA5 (`fa-check-circle`). **Tanpa emoji.**

---

## 5. Tangga z-index

Satu-satunya urutan yang berlaku — hafalkan sebelum menambah lapisan:

```
thead sticky         2
menu pemilih cabang 20
scrim drawer        40
drawer              41
scrim sheet         48   ← lembar boleh menutupi drawer
sheet               49
loadbar             60
toast               70   ← selalu paling atas
```

---

## 6. Empat grid tabel — nilai FINAL

⚠ Beberapa grid punya dua sampai tiga deklarasi `grid-template-columns`; hanya
yang terakhir berlaku. Di bawah ini **nilai yang menang**, bukan yang pertama
ditulis.

| Grid | Kolom final | min-width | Baris menang |
|---|---|---|---|
| `.ort-grid` | `minmax(186px,1.5fr) 146px 112px 96px 112px 132px 190px 30px` | **1080px** | CSS:1300 |
| `.ort-grid--antrean` | `minmax(156px,1.4fr) 124px 126px 162px 84px 124px 232px` | **1086px** | CSS:1341 |
| `.ort-grid--lintas` | `minmax(210px,1.5fr) 146px 158px 168px 150px 34px` | **940px** | CSS:1323 |
| `.ort-rgrid` | `minmax(180px,1.4fr) 108px minmax(120px,1fr) 104px 196px 128px 92px 22px` | **1050px** | CSS:1350 |

`min-width` disetel agar muat di layar 1440 (ruang isi ±1125px) — alasannya di
komentar CSS:1338. Lebar RIWAYAT 162 dan AKSI 232 pada `--antrean` diukur dari
lebar isi nyata, bukan dikira-kira (komentar CSS:1342).

### Isi kolomnya

| Grid | Kolom |
|---|---|
| `.ort-grid` (8) | Pasien · Dokter · Jadwal · Follow-up · Reservasi · Status · Tindakan berikutnya · toggle |
| `--antrean` (7) | Pasien · Dokter · Kontrol terakhir · Riwayat 6 bulan · Cabang · Status · aksi |
| `--lintas` (6) | Pasien · Pindah sejak · Kontrol terakhir · Jadwal berikut · Kehadiran · toggle |
| `.ort-rgrid` (8) | Pasien · Bracket · Dokter · Pasang · Cabang · Berikutnya/Selesai · Kehadiran · — |

### Kerangka & baris

| Kelas | Guna |
|---|---|
| `.ort-tablewrap` | `overflow-x:auto; max-width:100%` — **satu-satunya** mekanisme responsif tabel |
| `.ort-thead` | Gradien brand, `position:sticky; top:0; z-index:2`, padding `0 18px`, min-height 46 |
| `.ort-thead { border-left: 3px solid transparent }` | Menyamakan kotak isi dengan garis kegentingan 3px di baris, supaya kolom pertama tidak meleset 3px |
| `.ort-thead > .ort-grid` | `flex:1 1 auto` — mencegah grid header menciut ke min-width-nya |
| `.ort-tr` | Latar **putih**, padding `16px 18px`, `border-left:3px solid transparent` |
| `.ort-tr--warn` · `--bad` | Terlambat / terlambat parah: garis kiri amber / merah |
| `.ort-tr--open` | Baris yang dibuka: latar sunken |
| `.ort-tr--nb` | Baris berpita penutup: buang border-bottom |
| `.ort-badge` · `--transfer` · `--walkin` | Badge di bawah nama. ⚠ `--walkin` kini bertuliskan **"Tanpa jadwal tercatat"**, bukan "Datang tanpa reservasi" — nama kelasnya dipertahankan, teksnya diluruskan: `unscheduled` berarti kunjungan tidak cocok dengan jadwal mana pun di sistem, bukan pasien tidak memesan |
| `.ort-group` + `__dot` `__label` `__n` `__hint` | Kepala kelompok, latar strip |
| `.ort-pagin` + `__btn` `__info` `__nav` | Paginasi. Kakinya juga memuat `.st-perpage` — komponen **bersama** dari `components.sweettreats.css:1339`, dipakai apa adanya; token warnanya global, bukan `--ort-*`, jadi tetap benar di dalam scope `.ort` |

---

## 7. Pita penutup `.ort-done` — detail yang mudah dirusak

Pita "kontrol sudah beres" bukan baris sendiri, melainkan **bagian bawah baris
di atasnya** — latar putih yang sama, padding `0 14px 12px 18px`.

| Bagian | Catatan |
|---|---|
| `.ort-done::before` (CSS:588) | Siku ↳ **digambar dari dua border**, bukan glyph `U+21B3`. `margin-top:-10px` menembus ke baris atas |
| `.ort-tr--warn + .ort-done` | Meneruskan setrip kegentingan 3px supaya tidak terputus |
| `.ort-tr:hover + .ort-done` dan `.ort-done:hover` | Sorotan berlaku untuk **pasangannya** — baris dan pita satu objek bagi pengguna |

---

## 8. Chip status — 11 modifier, 7 pasangan warna

Basis `.ort-chip` final: pil radius 20, padding `5px 10px`, 11px (CSS:1314).

| Kelas | Latar | Teks |
|---|---|---|
| `--menunggu` | neu-bg | neu |
| `--usulan` | neu-bg | ink3 |
| `--fu` | info-bg | info |
| `--rsv` | info-bg | info |
| `--hadir` | ok-bg | ok |
| `--absen` | warn-bg | warn |
| `--selesai` | `#EFE7DC` | `#7C5B41` |
| `--telat` | warn-bg | warn |
| `--telatlama` | bad-bg | bad |

⚠ Sejak 4 Okt 2026 teks chip `--telat`/`--telatlama` bisa berbunyi
**"Terlewat N hari · K siklus"** bila sudah ≥ 2 siklus kontrol lewat. **Tidak
ada kelas baru** — hanya teksnya yang bertambah; warnanya tetap mengikuti
ambang hari. Siklus dihitung `OrtCase::hitungSiklusTerlewat()` dari kolom yang
sudah ada di baris, bukan dari kueri tambahan.
| `--batal` | bad-bg | bad |
| `--transfer` | transfer-bg | transfer |

**Pasangan identik, kandidat konsolidasi:** `fu` ≡ `rsv` · `absen` ≡ `telat` ·
`telatlama` ≡ `batal`. `--selesai` satu-satunya yang nilainya hardcode tanpa
token.

Semua dirakit dinamis `ort-chip--{{ $suffix }}` dari sembilan partial, jadi
**grep nama kelasnya di view akan menipu**.

⚠ `--usulan` **masih hidup**, walaupun chipnya sudah dibuang dari tabel utama
dan panel baris. Ia tetap dipakai kalender (`_kalender.blade.php:46`) dan roster
(`_roster.blade.php:178`) untuk baris `is_proposal`. **Jangan hapus.**

`.ort-chip--*` juga dipinjam tiga komponen yang **tidak punya warna sendiri**:
`.ort-cal-chip` · `.ort-legend-dot` · `.ort-datepill`.

Chip filter aktif berbeda: `.ort-chips` · `.ort-chips__label` · `.ort-chipf`.

---

## 9. Tombol

| Kelas | Guna |
|---|---|
| `.ort-btn` | Primer, gradien btn1→btn2, radius 10, 11.5px/800 + shadow |
| `button.ort-btn` | Reset `<button>`: border none, `font-family: inherit` — wajib karena dipakai sebagai `<a>` **dan** `<button>` |
| `.ort-btn--sm` | Padding 6/10, tanpa shadow |
| `.ort-btn.is-sibuk` | Menunggu server: `opacity:.6; cursor:progress` |
| `.ort-btn--mati` | Disabled, semua `!important` (CSS:1221) |
| `.ort-btn--wa` · `--wa-bad` | 30×30 hijau WhatsApp; varian nomor bermasalah pakai border **dashed** amber |
| `.ort-obtn` · `--warn` · `.is-on` · `__n` | Sekunder bergaris; `.is-on` jadi btn1 penuh; `__n` badge hitungan |
| `.ort-drawer__btn` · `--solid` | Di kepala drawer; `background` + `font-family` wajib eksplisit |
| `.ort-toggle` | Lingkaran 28px pembuka baris |
| `.ort-toggle:focus-visible` | `outline: 2px solid btn1` offset 2 |
| `.ort-absen` | 28×28 tandai tidak hadir, amber |
| `.ort-nodo` · `__t` · `--warn` | **Bukan tombol** — keadaan "tidak ada tugas" |
| `.ort-dash` | Strip `—` untuk baris tanpa aksi |

⚠ `.ort-tr:hover .ort-toggle` membuat seluruh baris jadi satu sasaran: toggle
langsung cokelat penuh. **Jangan kembalikan ke cincin box-shadow** (CSS:376).

### Segmented control — tiga ukuran

`.ort-seg` (2 arah: Aktif\|Selesai, Daftar\|Kartu) · `.ort-miniseg` (3 arah di
panel baris) · `.ort-segbig` (radio-sebagai-tombol 3 kolom di drawer Tambah).

---

## 10. Tab cabang — animasi yang pernah salah

| Kelas | Guna |
|---|---|
| `.ort-btablain` | **Pembungkus kelompok** *(5 Okt 2026)*: tombol pembuka + pil cabangnya jadi satu kesatuan. Saat terbuka dapat latar **putih** + bingkai `#EADCC9` radius 14 — resep yang sama dengan `.ort-utabs`. ⚠ **Bukan `--ort-strip`**: `#FAF1E6` nyaris identik dengan latar halaman `#FBF3E9`, jadi wadahnya ada tapi tidak terlihat sama sekali. Pil cabang **utama** sengaja di luar |
| `.ort-btablain` padding/margin | `padding: 5px; margin: -6px 0` — **keduanya tetap di kedua keadaan**. Versi pertama memasang padding hanya saat terbuka, dan baris strip tumbuh 12px tiap kali dibuka sehingga seisi halaman di bawahnya tersentak turun. Margin negatif mengompensasi padding + border, jadi jejak tata letaknya **tidak pernah berubah** (31px, sama dengan tinggi pil) — yang beranimasi cuma warna. Trik yang sama sudah dipakai `.ort-branchtabs__more` |
| `.is-open .ort-btablain .ort-btab` | Pil di dalam kelompok jadi **datar** (latar & bingkai transparan) — pil putih berbingkai di atas wadah putih terbaca sebagai kotak di dalam kotak. Pola persis `.ort-utab`. Yang `.is-active` tetap cokelat penuh, spesifisitas 0,4,0 |
| `.ort-branchtabs.is-open .ort-btab--more` | Tombol pembuka jadi **label kelompok**: bingkainya dilepas supaya tidak ada kotak di dalam kotak, latarnya putih supaya tetap terbaca sebagai tombol di atas permukaan krem |
| `.ort-branchtabs__wrap` | Lapis **beranimasi**: `grid-template-columns: 0fr → 1fr` .32s + opacity .2s + `visibility 0s linear .32s` |
| `.ort-branchtabs__more` | Lapis **pemotong**: `overflow:hidden`, padding/margin ±6px memberi ruang shadow hover |
| `.ort-branchtabs__more > *` | `flex:none` — tab terpotong, bukan mengempis |
| `.ort-branchtabs.is-preload` | `transition: none` — anti-flash muat pertama |
| `.ort-btab` + `__star` `__count` `__eye` `__chev` `__caret` | Pil cabang |
| `.ort-btab.is-active:hover` | Spesifisitas 0,3,0 — mempertahankan gradien saat hover |
| `.ort-btabpick` + `__menu` `__head` `__item` `__nama` `__star` | Dropdown cabang utama, `z-index:20` |

⚠ Dipilih `0fr → 1fr` alih-alih `max-width: 0 → N` karena melebar **persis
seukuran isi, tanpa angka ajaib**. Versi `max-width` selesai di sekitar
sepertiga durasi karena isinya 750px sementara plafonnya 1200px (komentar
CSS:114-120).

---

## 11. Drawer & side sheet

| | Lebar | z-index | Animasi masuk |
|---|---|---|---|
| `.ort-drawer` | **760px**, `max-width:96vw` | 41 | `ortDrawerIn .26s` |
| `.ort-drawer--hari` | **540px** | 41 | sama |
| `.ort-sheet` | **410px**, `max-width:96vw` | 49 | `ortDrawerIn` juga |
| `.ort-scrim` | `inset:0`, `rgba(40,12,0,.42)` | 40 | `ortFade .18s` |
| `.ort-scrim--sheet` | lebih tipis `.34` | 48 | sama |

Keduanya memakai `max-width: 96vw`, **bukan breakpoint**.

⚠ `.ort-drawer__body > *` dan `.ort-sheet__body > *` wajib `flex-shrink: 0`
(CSS:1282-1283). Tanpa itu kartu dipadatkan dan baris ke-5 matriks **terpotong
diam-diam**.

⚠ `.ort-sheet__galat` ada karena `layouts.notif` merender galat di halaman
belakang — yang sedang tertutup scrim. Pesan gagal harus muncul **di dalam**
lembar.

### Isi drawer

`.ort-kpi` (grid 3) + `__card` `__lbl` `__val` `__sub` → `.ort-matrix` +
`__scroll` `__head` `__grid` `__row` `__n` `__sep` `__aksi` `__empty` → kartu
kaki **empat keadaan**:

| Kartu | Arti |
|---|---|
| `.ort-next` | Bisa diubah: `#FDF8F2`, border **1.5px dashed** `#DFC5A6` |
| `.ort-held` | Lihat-saja: transfer-bg + border `#E4C7AC` |
| `.ort-held--selesai` | Bentuk sama, warna **netral** `#EFE7DC` — bukan cokelat-transfer, bukan hijau-hadir |

⚠ `.ort-matrix__grid` final **proporsional** `28px 1fr 1fr 1fr 1fr 1fr 1.15fr
1.35fr` dengan `min-width:0` (CSS:953), dan `__scroll` berubah dari `auto` jadi
**`visible`** (CSS:957) — supaya 8 kolom muat di badan drawer 716px tanpa gulir
sama sekali.

⚠ `.ort-matrix__sep` menggambar dua garis 1px lewat `::before`/`::after` yang
mengapit label transfer.

---

## 12. Kalender

| Kelas | Catatan |
|---|---|
| `.ort-cal-dow` | Baris nama hari — gradien di **baris**, bukan per sel |
| `.ort-cal-grid` · `-cell` | `repeat(7,1fr)`, sel `min-height:104px` |
| `.ort-cal-cell.is-out` · `.is-late` · `.is-today` | Luar bulan / ada yang terlewat / hari ini (`inset 0 0 0 2px accent`) |
| `.ort-cal-cell:nth-child(7n)` | Kolom terakhir buang border kanan |
| `.ort-cal-chip` | Chip nama pasien, ellipsis — **warna dari `.ort-chip--*`** |
| `.ort-cal-more` | "+3 lagi" |
| `.ort-cal-month` | `min-width:158px` center — mencegah bar bergeser tiap bulan |
| `.ort-cal-legend` · `.ort-legend-dot` | Legenda hanya memakai 6 dari 11 chip |
| `.ort-daycard` + `__top` `__name` `__mid` `__doc` `__sub` | Kartu pasien di drawer harian, garis kiri `is-warn`/`is-bad` |
| `.ort-timepill` · `--none` | Pil jam; varian amber kalau jam belum ada |

---

## 13. Toast & urungkan

`.ort-toast` fixed **bawah-tengah** (`left:50%` + `translateX(-50%)`),
z-index 70, gradien brand, radius 13.

⚠ Bawah-tengah dipilih supaya tidak menutupi kolom **Tindakan Berikutnya** di
kanan.

⚠ `ortToastIn` harus mengulang `translate(-50%, …)` di setiap keyframe, karena
`transform` menimpa `translateX(-50%)` dari CSS:1237.

Toast hidup **8 detik** di layar; Urungkan berlaku **90 detik** di server.
Setiap toast punya `data-ort-toast="<acak>"` supaya timer toast lama tidak
membunuh toast baru. `.ort-toast form { margin: 0 }` supaya form Urungkan tidak
merusak baris.

`.ort-toast--galat` mengganti gradien cokelat dengan `--ort-bad` rata, dan hanya
lahir di fragmen (`_frag_tabel.blade.php:24`) — di muat-ulang penuh, galat
ditangani `layouts.notif`, dan merendernya di dua tempat membuatnya dobel.

---

## 14. Pemilih hari praktik dokter

`.ort-hdok` + `__row` `__hari` `__jam` `__isi` (`min-width:48px` rata kanan)

Memakai **radio asli yang disembunyikan**, bukan tombol + input tersembunyi:
keyboard, pembaca layar, dan "pilih salah satu" jadi gratis dari peramban.
`.ort-hdok__row` memakai `font: inherit` karena ia `<button>`.

⚠ **Hari penuh tetap ditampilkan**, bukan disembunyikan — `:disabled` dengan
latar sunken, teks muted, dan "penuh" berwarna merah bad. Sales perlu tahu bahwa
harinya ada tapi habis.

⚠ Seluruh gaya "penuh" datang dari **`:disabled`** (CSS:1510), bukan dari kelas.
`_sheet.blade.php` sempat memasang `is-penuh` yang tidak pernah ada di CSS mana
pun — hook mati itu sudah dibuang 2 Okt 2026, dan alasannya ditulis di komentar
Blade-nya supaya tidak dipasang lagi. Kalau suatu saat butuh menandai "penuh"
**tanpa** menonaktifkan tombolnya, kelas itu harus didefinisikan dulu di CSS.

`.ort-callfacts` — baris fakta pembuka kepala lembar (dokter · cabang · irama),
latar sunken.

---

## 15. Spanduk

| Kelas | Guna |
|---|---|
| `.ort-banner` | Amber, border `#EBD3A5`, radius 14 — **mendesak** |
| `.ort-banner--info` | neu-bg + line2 — **menjelaskan, bukan memperingatkan** |
| `__icon` `__title` `__text` `__body` | Ubin 28px, judul 13.5px/800 warna transfer, teks `max-width:760px` |
| `.ort-banner code` | Nama perintah artisan monospace 11.5px — dibaca tim IT |

⚠ `--info` sengaja ditaruh di **akhir berkas** (CSS:1438) supaya menang atas
penimpaan `__icon` di blok sebelumnya.

⚠ `.ort-banner__body` (CSS:1453) ada khusus untuk mengganti style sebaris —
konvensi proyek: tidak ada inline style.

Dua pemakaian: spanduk mendesak (`list.blade.php:106`) dan spanduk "kolom ini
belum pernah dipakai" (`list.blade.php:136`).

---

## 16. Kelas kecil yang menanggung makna

| Kelas | Arti |
|---|---|
| `.ort-x` | "Belum/tidak terjadi". **Dipertahankan apa adanya** |
| `.ort-kira` | Tanda `±` di depan tanggal **perkiraan**, bukan fakta. `cursor:help` |
| `.ort-proposal` | Tanggal usulan: garis bawah **1.5px dashed amber** sampai dikonfirmasi manusia |
| `.ort-struck` | Tanggal reservasi dibatalkan, `line-through` |
| `.ort-waitlist` | Pil amber reservasi tanpa tanggal |
| `.ort-datepill` | Pil tanggal di roster — tanpa warna sendiri |
| `.ort-sub-red` | Sub-teks negatif 700 `!important` |
| `.ort-fu` | Nilai follow-up biru 700 |
| `.ort-fact__dim` | Angka pembanding kecil (rata-rata di samping median) |
| `.ort-visit` | Kolom DATANG di matriks: hijau + ikon unduh = **diisi mesin** |
| `.ort-left-behind` | Strip "tertinggal di cabang lain", garis kiri merah |
| `.ort-held` + `__icon` `__text` `__title` | Keadaan lihat-saja |
| `.ort-pill-br` · `--other` | Pil nama cabang; varian cabang lain pakai titik `::before` |
| `.ort-bar` · `--low > i` | Bilah kemajuan kehadiran, hijau → amber saat rendah |
| `.ort-avatar` | 40px gradien `#7A3415 → #5A2410` |
| `.ort-wa` · `--bad` | Tautan WhatsApp hijau; varian nomor bermasalah amber |

### Form & pencarian (lembar + drawer Tambah)

`.ort-inp` (fokus border accent) · `.ort-search` (ikon absolut + padding-left
36) · `.ort-filter__r1/__r2/__act` · `.ort-infobox` + `__k` `--brown` ·
`.ort-checkcard` + `__box` (input disembunyikan absolut) · `.ort-2col` ·
`.ort-divider` · `.ort-nested` · `.ort-link-sm` · `.ort-hint` · `.ort-results` ·
`.ort-result` + `--none` `__ini` · `.ort-picked` + `__x` · `.ort-rmrow` ·
`.ort-rmwarn`

---

## 17. Responsif — 3 titik putus + 3 reduced-motion

| Breakpoint | Yang berubah |
|---|---|
| `max-width: 900px` | `.ort-cards` → 1 kolom · `.ort-expand` → 1 kolom gap 16 · `.ort-facts` → 1 kolom |
| `max-width: 880px` | `.ort-filter__r1/__r2` → `1fr 1fr` · `.ort-kpi` → 1 kolom |
| `max-width: 560px` | `.ort-filter__r1/__r2` → `1fr` |

### Yang sengaja TIDAK pakai breakpoint

| Komponen | Mekanisme |
|---|---|
| Keempat grid tabel | `.ort-tablewrap { overflow-x:auto }` — kolom **tidak pernah** berubah |
| Drawer & sheet | `max-width: 96vw` |
| `.ort-cardgrid` | `auto-fill minmax(320px, 1fr)` — intrinsik |
| `.ort-matrix__grid` | Proporsional `1fr`, bukan px |
| `.ort-cal-grid` | Tetap `repeat(7,1fr)`; `min-height:104px` tidak pernah dikurangi |
| 8 baris strip | `flex-wrap: wrap` — navrow, toprow, urow, roster-head, foot, pagin, meta-line, branchtabs |

⚠ **Breakpoint yang hilang:** `.ort` padding `16px 26px 26px` tidak pernah
diperkecil — 52px terbuang di layar sempit. Target modul ini desktop dan tablet,
jadi belum jadi masalah; catat kalau nanti dipakai di HP.

⚠ `max-width: 880px` muncul **dua kali** di blok terpisah (CSS:649 dan 822).
Boleh digabung.

---

## 18. Animasi — 7 keyframes

| Keyframes | Dipakai |
|---|---|
| `ortDrawerIn` | `.ort-drawer` **dan** `.ort-sheet` — satu animasi, dua komponen |
| `ortDrawerOut` | `.is-closing`, `.2s cubic-bezier(.4,0,1,1) forwards` |
| `ortFade` / `ortFadeOut` | Scrim masuk / keluar |
| `ortToastIn` / `ortToastOut` | Toast |
| `ortLoadbar` | `scaleX(0) → scaleX(.92)` — **berhenti di .92** karena durasi server tak diketahui |

Transisi penting di luar keyframes: `.ort-branchtabs__wrap` (.32s) · `.ort-card`
/ `.ort-pcard` (.12s) · `.ort-btab` (5 properti .12s) · `.ort-utab` / `.ort-seg
a` (.12s) · `.ort-toggle` (.15s) · `.ort-btab__caret` (.18s) ·
`.ort-btab__chev` (.32s) · `.ort-hdok__row` (.12s).

⚠ Tiga blok `prefers-reduced-motion` tersebar di CSS:715, 1203, dan 1256 —
kandidat digabung. Di blok 1203, `visibility` **tetap** berganti walaupun
transisinya mati, supaya urutan Tab keyboard tetap benar.

---

## 19. Komponen bersama — yang nyata dipakai

Dari `components.sweettreats.css`, **tidak ditulis ulang**:

| Kelas | Jumlah |
|---|---|
| `.st-card` | 9× |
| `.st-empty__title` | 6× |
| `.st-card__body` | 3× |
| `.st-empty` | 3× |
| `.st-empty__icon` | 2× |
| `.st-view-tabs` · `.st-view-tab` | tab tampilan, diangkat lokal di CSS:1403-1429 |

Penimpaan lokal: `.ort .st-card { overflow:hidden }` (tabel di dalamnya yang
menggulir) dan `.ort > .st-card { margin-top: 0 }` (supaya `gap` tidak dobel).

### ⚠ Koreksi untuk header berkas CSS

Komentar CSS:5-6 mengklaim modul ini memakai ulang `.st-pill`, `.st-page-head`,
`.st-filter-chips`, dan `.st-breadcrumb`. **Keempatnya 0 pemakaian.** Modul
menulis pilnya sendiri (`.ort-chip`, `.ort-pill-br`, `.ort-datepill`,
`.ort-timepill`), membuang kepala halaman, dan mengganti chip filter dengan
`.ort-chips` + `.ort-chipf`.

⚠ Pengecualiannya `.st-pill--ortho` (`components.sweettreats.css:548`). Ia
**ditambahkan oleh modul ini** tapi dipakai **di luar** halaman ortho — dua kali
di `crud/reservation/list.blade.php` (`:562`, `:752`), menandai reservasi yang
lahir dari Tracking Ortho. Warnanya sengaja cokelat supaya tidak bersaing dengan
hijau "Baru" yang berarti hal lain. Jadi ia satu-satunya tempat modul ini
**menulis** ke CSS bersama, bukan sekadar memakainya — dan grep di folder
`crud/ortho/` saja akan salah menyimpulkannya yatim.

Daftar yang akurat: **`.st-card`, `.st-card__body`, `.st-empty` (+`__icon`,
`__title`), `.st-view-tabs`, `.st-view-tab`.**

### `.st-empty__desc` — dulu salah tulis `__text`

Lima keadaan-kosong (`list.blade.php` 1×, `_kosong.blade.php` 4×) memakai
`.st-empty__text`, padahal `components.sweettreats.css:936` mendefinisikan
`.st-empty__desc`. Kelas yang tidak ada tidak mengatur apa pun, jadi kelima teks
itu tampil dengan ukuran dan warna bawaan — bukan 12px redup seperti keadaan
kosong di modul lain.

Diperbaiki 2 Okt 2026 dengan **mengganti kelas di view ortho**, bukan menambah
alias di CSS bersama. Alasannya: `__desc` sudah dipakai modul lain, dan menambah
nama kedua untuk hal yang sama membuat yang menyusul harus menebak mana yang
benar. Perbaikan ini tidak menyentuh satu pun berkas di luar modul ortho.

---

## 20. Kelas yatim — kandidat dibersihkan

Tujuh kelas didefinisikan tapi nol pemakaian di seluruh repo:

| Kelas | Catatan |
|---|---|
| `.ort-num` | Duplikat `.tabular` yang dipakai 68× |
| `.ort-sub-amber` | Saudaranya `.ort-sub-red` dipakai 2× |
| `.ort-sub-yakin` | Fitur Tahap C: hijau tenang untuk usulan irama panjang — CSS ada, markup belum dibuat |
| `.ort-proposal--kira` | Fitur Tahap C yang sama |
| `.ort-obtn--mati` | Grup disabled; hanya `.ort-btn--mati` yang terpakai |
| `.ort-drawer__btn--mati` | Punya 2 deklarasi untuk kelas yang tak pernah muncul |
| `.ort-infobox__link` | `.ort-infobox`, `__k`, `--brown` terpakai; hanya `__link` tidak |

⚠ `.ort-sub-yakin` dan `.ort-proposal--kira` adalah **gaya yang menunggu
markup**, bukan sisa — jangan hapus tanpa memutuskan fiturnya dulu.

Tiga kelas lain sudah dihapus dan tinggal komentar alasan penghapusannya
(`.ort-demo` CSS:625, `.ort-fucell` dan `.ort-fu__x` CSS:542) — biarkan
komentarnya.

---

## 21. ⚠ Aturan yang ADA tapi TIDAK BERLAKU

Beberapa kelas punya dua sampai empat deklarasi properti yang sama. **Mengutip
yang pertama akan salah.** Yang menang selalu yang terakhir di berkas:

| Kelas | Deklarasi | Menang |
|---|---|---|
| `.ort-tr` padding | 4× (248, 688, 1037, 1300) | **1300** — komentar CSS:1290 sendiri memperingatkan "blok ini WAJIB di akhir berkas" |
| `.ort-grid--antrean` | 3× (1168, 1313, 1335) | **1335** |
| `.ort-matrix__grid` | 3× (768, 828, 947) | **947** |
| `.ort-chip` | 3× (295, 672, 1308) | **1308** |
| `.ort-thead` padding | 3× (236, 681, 1301) | **1301** |
| `.ort-rgrid` | 3× (843, 1324, 1344) | **1344** |
| `.ort-utab__n` | 3× (224, 513, 691) | **691** |
| `.ort-btn--wa` | 2× radius beda (324 r10, 607 r9) | **607** |
| `.ort-mini__row/__n/__d/__x` | 2 set (409-416, 1052-1059) | **1052-1059** |

⚠ `.ort-mini__x` (teks baris riwayat di panel) punya **tiga** hal yang mudah
salah dikutip, semuanya diputuskan 5 Okt 2026:

- **Satu baris, dipotong elipsis** (`overflow:hidden` + `text-overflow` +
  `white-space:nowrap`). Sejak hasil follow-up ikut ditampilkan, teksnya bisa
  berbunyi *"FU 2 Okt '26 · oleh admin · Sudah dihubungi, bersedia"* dan
  membungkus jadi dua baris — tinggi barisnya lalu tidak seragam. Versi
  utuhnya ada di `title`, termasuk catatan bebas.
- **Warnanya `--ort-ink3`, bukan `--ort-muted`.** Muted `#8A6A50` terlalu
  tipis untuk teks yang memang harus dibaca; ia pantas untuk label, bukan isi.
- Berlaku di **ketiga tab** (Semua · Follow-up · Tanggal reservasi) karena
  ketiganya memakai span yang sama — satu baris DOM, tiga isi.
| `.ort-banner__icon/__title/__text` | 2 set (436-438, 1023-1028) | **1023-1028** |
| `.ort-grid` | 2× (229, 1294) | **1294** |

Juga berlapis dua: `.ort-struck`, `.ort-dash`, `.ort-card__icon`, `.ort-urow`,
`.ort-urow__hint`, `.ort-utabs`, `.ort-toprow`, `.ort-clamp2`,
`.ort-tablewrap`, `.ort-btn--mati`, `.ort-matrix__scroll` (`auto` → **`visible`**).

Berkas ini tumbuh lewat beberapa putaran "adu screenshot", dan tiap putaran
menambah blok penimpaan di akhir alih-alih menyunting di tempat. Kalau ada yang
merapikan, **gabungkan ke deklarasi pertama dan hapus blok penimpaannya** —
jangan menambah lapisan kesembilan.

---

## 22. Kalau menambah gaya baru

1. **Pakai token, jangan nilai literal** — nilai literal akan salah di salah
   satu dari dua tempat begitu palet digeser.
2. **Tulis di berkas ini**, jangan inline style di Blade. Empat style sebaris
   yang tersisa di `list.blade.php` warisan lama, bukan izin menambah baru.
3. **Pastikan ia hidup di dalam `.ort`**, kalau tidak warnanya hilang.
4. **Cek dulu apakah kelasnya sudah punya deklarasi lain** (§21) — kalau ya,
   sunting yang menang, jangan menambah blok baru di akhir.
5. **Jangan tambah radius dan bayangan ke tiap blok.** Border, isian, radius,
   dan bayangan masing-masing berkata "objek terpisah"; menghamburkannya
   meratakan hierarki.
