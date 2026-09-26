# Handoff — Foto bukti WAJIB untuk lembur & WFH

> Untuk **chat build**. Disusun HUB 26 September 2026 dari pengukuran prod.
> Keputusan ada di **Nisa/Supervisor**, disampaikan lewat Ikhsan 26 Sep.
> Sosialisasi ke karyawan **sudah dilakukan** — bukan tanggung jawab IT.

---

## 1. Yang diminta

Pengajuan **lembur** dan **WFH** tidak bisa dikirim tanpa melampirkan foto bukti.
**Tanpa syarat** — tidak peduli apakah scan mesin sudah mencocokkan.

Pengajuan **lama dibiarkan apa adanya**. Tidak ada backfill, tidak ada pengajuan
lama yang perlu dilengkapi ulang.

Jenis lain **tidak berubah**: `sakit` tetap pakai aturannya sendiri (surat dokter
≥3 hari), `cuti`/`izin`/`tukar`/`koreksi` tetap opsional.

---

## 2. Kenapa — angka prod 26 Sep 2026

| | |
|---|---|
| Total pengajuan lembur | **531** (22 orang, ±2 bulan) |
| Tanpa foto | **302 — 56,9%** |
| Tren Agustus → September | **56% → 34,9%** berfoto (memburuk) |
| Disetujui | **513 dari 531 — 96,6%**, termasuk **290 tanpa foto** |
| Klaim `verifikasi = selisih` **dan** tanpa foto | **205** |

**Premis desain lama sudah diuji kenyataan dan gagal.** Komentar di
`AkunPengajuanController:375` berbunyi *"wajib secara SOP untuk lembur/wfh yg tak
ada scan, tapi tak dipaksa di sini (biar tak nge-block; approver yang menilai
kelengkapan)"*. Approver **tidak** menyaring: yang tanpa foto lolos sama mulusnya
(96,6% disetujui). Komentar itu harus diperbarui, jangan ditinggal menyesatkan.

**Ini bukan soal jenis pekerjaan.** Di dalam role yang sama persis:

| Role | Rajin melampirkan | Hampir tak pernah |
|---|---|---|
| General Services | Tito 30/31 · Noval 24/24 | Ramdan 1/36 · Wahyudin 1/31 |
| Assistant Dentist | Rokhmatun 33/36 · Anggita 19/20 | Rizka 2/33 · Alviona 1/29 · Putri 1/13 |
| Front Office | Afrina 38/38 · Firda 29/32 | Verini 2/20 · Mutiara 3/21 |

Cabangnya juga campur (Bintaro punya Afrina 100% dan Ramdan 3%). Jadi alasan
"kerjaanku tak bisa difoto" tidak berdiri — rekan seprofesi melakukannya tiap kali.

**Foto tidak berkorelasi dengan kualitas bukti.** Pada klaim yang scan-nya
`nyambung`, yang berfoto cuma 50% — praktis sama dengan yang `selisih` (42%).
Orang tidak melewatkan foto karena merasa sudah terbukti. → inilah alasan
aturannya **tanpa syarat**: versi bersyarat tidak akan mengubah perilaku dan
susah dijelaskan ("kenapa kemarin ga perlu?").

WFH: 7 pengajuan, 1 berfoto. Volumenya kecil, ikut disertakan atas permintaan.

---

## 3. Di mana diubah — ikuti pola yang SUDAH ADA

Aturan "surat dokter wajib untuk sakit ≥3 hari" adalah cetakannya. Tiru persis.

### a. `app/Models/AttRequest.php`

Tambah konstanta di sebelah `SAKIT_HARI_WAJIB_SURAT` (baris 36) — **satu sumber
kebenaran**, dibaca server DAN blade:

```php
/** Jenis yang WAJIB melampirkan foto bukti (keputusan Nisa/Supervisor, 2026-09-26). */
public const TIPE_WAJIB_FOTO = ['lembur', 'wfh'];
```

### b. `app/Http/Controllers/Absensi/AkunPengajuanController.php` — **gerbang sungguhan**

Sisipkan **sesudah gerbang sakit (baris 333)**, sebelum foto disimpan:

```php
if (in_array($data['tipe'], AttRequest::TIPE_WAJIB_FOTO, true) && ! $request->hasFile('foto')) {
    return back()->withInput()->with('error',
        'Pengajuan ' . self::LABEL[$data['tipe']] . ' wajib melampirkan foto bukti.');
}
```

⛔ **Harus di server, bukan cuma atribut `required` di form.** Alasannya sudah
ditulis di komentar gerbang sakit: input file yang disembunyikan tetap bisa
dilewati form buatan sendiri. Komentar itu berlaku sama di sini.

⛔ **Harus SEBELUM baris 386** (`$request->hasFile('foto')` → `$file->move()`).
Kalau ditaruh sesudahnya, kiriman yang ditolak meninggalkan berkas yatim di
`storage/app/att_request`. Alasan yang sama sudah dicatat di gerbang lembur
kembar (baris 337–343).

⚠️ Pilihan kecil: kalau lembur kembar **dan** tanpa foto dikirim bersamaan,
pesan mana yang muncul duluan? Gerbang kembar ada di baris 344 (sesudah titik
sisip ini), jadi pesan foto akan menang. Menurut HUB itu keliru urutannya —
"kembar" lebih penting diketahui daripada "kurang foto". Pertimbangkan menaruh
gerbang foto **sesudah** blok kembar (baris 362), tetap sebelum 386.

### c. `resources/views/absensi/akun/_pengajuan_form.blade.php` — petunjuk, bukan pagar

Baris 173 sudah punya polanya:
```blade
var SAKIT_MIN_SURAT = {{ \App\Models\AttRequest::SAKIT_HARI_WAJIB_SURAT }};
```
Tambah sebelahnya:
```blade
var TIPE_WAJIB_FOTO = @json(\App\Models\AttRequest::TIPE_WAJIB_FOTO);
```

Lalu di fungsi `suratDokter(t)` (baris 181–198):
- `var wajib = ((t === 'sakit') && sakitHari() >= SAKIT_MIN_SURAT) || TIPE_WAJIB_FOTO.indexOf(t) !== -1;`
- cabang `else` (baris 192–195) sekarang harus membedakan wajib/opsional. Teks
  lama *"(opsional — kalau lembur/kerja dari rumah tanpa absen mesin, foto ini
  buktinya)"* **sudah tidak benar** untuk lembur/wfh — ganti jadi
  *"(wajib — lembur tanpa foto bukti tidak bisa dikirim)"*, warna `#C0392B`
  seperti cabang sakit.
- `input.required = wajib;` (baris 197) sudah menangani sisanya.

⚠️ Nama fungsi `suratDokter()` jadi menyesatkan — sekarang ia mengatur lampiran
untuk tiga jenis. Ganti jadi `aturLampiran()` (pemanggilnya di baris 200-an).

⚠️ Tombol kirim: pola P3 ("tombol mati bilang kenapa") sudah dipakai di form ini
(`ajSubmitTeks`). Kalau lembur/wfh dipilih dan foto belum ada, lebih baik tombol
bilang **"Lampirkan foto dulu"** daripada menolak sesudah dikirim.

---

## 4. Yang JANGAN disentuh

- ⛔ **302 baris lembur + 6 WFH lama tanpa foto** — biarkan. Itu sejarah, bukan
  cacat. Jangan bikin perintah backfill, jangan tandai merah di layar riwayat.
- ⛔ **`app/Console/Commands/AttImportLembur.php`** sengaja mengisi `foto => null`
  (bukti ada di Google Drive, tak diunduh). Perintah ini **manual, tidak
  dijadwalkan di Kernel**, peninggalan transisi dari Google Form. Gerbang baru
  ada di `store()` jadi command ini tak terkena — biarkan begitu. Kalau nanti
  dipakai lagi, itu keputusan tersendiri.
- ⛔ **Jenis `sakit`** punya aturannya sendiri (1–2 hari sengaja TANPA surat,
  supaya orang tidak masuk kerja sambil sakit). Jangan dilebur ke `TIPE_WAJIB_FOTO`.
- ⛔ Penyimpanan tetap di **`storage/app/att_request`**, bukan `public/`.
  Alasannya sudah ditulis di baris 378–384 (lampiran bisa berupa surat dokter).

---

## 5. Uji yang diharapkan

Sudah ada `tests/Feature/PagarLemburTest.php` dan `PagarLemburLayarTest.php` —
perluas di situ, jangan bikin berkas baru.

Wajib ada **kendali negatifnya**, kalau tidak pagar yang kegalakan berakhir
sebagai orang yang tak bisa mengklaim lembur yang benar-benar dikerjakan:

1. lembur TANPA foto → **ditolak**, pesan menyebut "foto bukti"
2. lembur DENGAN foto → **diterima**
3. WFH tanpa foto → **ditolak**
4. WFH dengan foto → **diterima**
5. ⭐ **sakit 1–2 hari tanpa foto → TETAP DITERIMA** (kendali negatif — pagar baru
   tidak boleh bocor ke jenis lain)
6. ⭐ **cuti/izin tanpa foto → TETAP DITERIMA**
7. ⭐ kiriman yang ditolak **tidak meninggalkan berkas** di `storage/app/att_request`
8. layar: pilih "lembur" → label berbunyi wajib & `input.required` menyala

---

## 6. Catatan deploy

- **Nol SQL.** Kolom `foto` sudah ada di `att_request`.
- **Nol `public/assets/**`** kalau tidak menyentuh CSS → **tanpa rsync**.
  Kalau warna peringatan ditulis di CSS (bukan inline `style.color` seperti
  sekarang), rsync `assets/` jadi WAJIB.
- `routes/web.php` tidak perlu berubah — `store()` satu-satunya jalur HTTP yang
  membuat lembur/wfh (diverifikasi: `create`, `berkas`, `show`, `prefillLembur`,
  `store`; `simpanTukarHari` privat dan khusus tukar).
- ⚠️ Sebelum deploy prod, ukur dulu `git log <HEAD-prod>..main` — prod bisa
  tertinggal jauh dan `deploy.sh main` membawa SELURUH main.

---

## 7. Risiko yang sudah dibahas & diterima

Tujuh orang (Ramdan, Rizka, Alpajri, Wahyudin, Alviona, Verini, Putri) praktis
tak pernah melampirkan foto. Hari pertama aturan ini hidup, mereka menabrak
tembok. Risiko nyatanya: sebagian berhenti mengklaim lembur yang benar-benar
mereka kerjakan — **kerja tak terbayar lebih buruk daripada foto yang hilang.**

**Sudah disampaikan ke Ikhsan dan diterima:** sosialisasi sudah dilakukan,
keputusan dan tanggung jawabnya ada di Nisa/Supervisor. IT membangun pagarnya,
bukan memutuskan kebijakannya. Dicatat di sini supaya kalau keluhan datang,
jelas ini keputusan sadar — bukan efek samping.

➜ Saran HUB: sesudah live, lihat lagi **jumlah pengajuan lembur per minggu**.
Kalau anjlok tajam (bukan cuma bergeser), itu tandanya orang berhenti mengklaim,
bukan tandanya aturan berhasil.
