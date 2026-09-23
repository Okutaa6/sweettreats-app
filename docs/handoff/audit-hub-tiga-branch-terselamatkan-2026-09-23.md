# Audit — tiga branch hasil penyelamatan 23 Sep 2026

> Disusun HUB 23 Sep 2026. Ketiganya di-commit **apa adanya** saat penyelamatan;
> dokumen ini menilai apakah isinya masih benar, masih perlu, dan bisa digabung.
> ⛔ **Belum ada yang di-merge, belum ada yang naik prod.**

---

## Ringkasan putusan

| Branch | Isi (vs merge-base) | Merge kode | Putusan |
|---|---|---|---|
| `feat/wifi-dedup-role-pengawas` | +440 / −7 · 8 berkas · 343 baris uji | ✅ bersih | **Layak naik** sesudah uji dijalankan |
| `fix/cuti-hari-libur` | **+2.273 / −33** · 14 berkas · **1.627 baris uji** | ✅ bersih | **Layak naik** — menutup cacat yang MASIH HIDUP di prod |
| `wip/snapshot-rop-tabs-2026-09-23` | +123 / −113 · 12 berkas | ✅ bersih | **Simpan saja** — jangan merge apa adanya |

⚠️ **Koreksi cara mengukur.** Pengukuran pertama memakai `git diff main..branch`
dan menghasilkan angka omong kosong (cuti tampak "−30.087 baris"). Pada branch
basi, itu menampilkan **kerjaan main sebagai penghapusan**. Semua angka di
dokumen ini memakai `git diff $(git merge-base main branch) branch`.

**Pemisahan commit terbayar.** Ketiganya bentrok di `docs/log-update.md` /
`docs/status-proyek.md` — **nol bentrok di kode**. Karena docs sengaja dipisah
jadi commit sendiri saat penyelamatan, commit KODE-nya bisa di-cherry-pick
bersih tanpa menyentuh bentrokan docs sama sekali.

---

## 1. `feat/wifi-dedup-role-pengawas` — layak naik

**Isi:** +440/−7 di 8 berkas · `WifiDedupTest` 256 baris · `ParafBusinessOwnerTest` 87 baris.
Basis 11 Sep, 26 commit di belakang main. Commit kode: `32976fa`.

**Yang diperiksa dan BENAR** — ini syarat yang paling mudah dilanggar, dan
kodenya lolos:

> `matchBranch()` dipakai sebagai **pagar KEDUA sesudah** `pemilik48Lain()`,
> **bukan pengganti**, dan dipanggil **apa adanya**.

Komentarnya menjelaskan sendiri kenapa kedua pagar itu menjawab bahaya berbeda:
yang lama menolak /48 milik **cabang lain** (absen tertukar cabang), yang baru
menolak jaringan yang **sudah tercakup** (baris kembar menumpuk). Dan pesan
penolakannya **menyebut baris mana** yang sudah mencakup — penting, karena ini
jalur absen harian di lima cabang; pagar yang cuma bilang "tidak boleh" akan
berakhir sebagai orang yang tak bisa absen dan tak tahu kenapa.

**IT-2026-026 sengaja TIDAK dikerjakan sesuai judulnya.** Permintaan aslinya
"cabut `Admin*` dari `ROLE_PENGAWAS`" ditolak — konstanta itu menggerakkan lima
hal, termasuk siapa boleh jadi evaluator. Yang dibuat: konstanta baru
`ROLE_PARAF_BO`, sehingga mempersempit paraf tidak ikut mencabut hak lain.
**Judul tiketnya kini tidak menggambarkan isinya** — perlu diperbarui.

**Sebelum naik:** jalankan `WifiDedupTest` & `ParafBusinessOwnerTest`; pastikan
kendali positif (jaringan BARU tetap DITERIMA) hijau — pagar yang terlalu galak
di jalur ini menghentikan absen orang.

## 2. `fix/cuti-hari-libur` — layak naik, dan cacatnya MASIH HIDUP di prod

**Isi:** +2.273/−33 di 14 berkas, **1.627 baris di 5 berkas uji baru**
(`CutiHariLiburTest`, `CutiHariLiburE2ETest`, `CutiHariLiburPersonaTest`,
`CutiFleksibelSepakatTest`, `HariKerjaEfektifTest`, `WfhHariKerjaTest`).
Basis 3 Sep. Commit kode: `3633f82`.

**Ini bukan kerjaan usang.** `CutiService` di main masih **v1**, dan komentarnya
mengakui sendiri:

> *"v1: setiap hari kalender dalam rentang = 1 hari potong (belum melihat jadwal
> kerja/hari libur — batasan sadar, lihat rencana §Batas)."*

Artinya **hari ini di prod**, cuti yang melintasi hari libur tetap memotong jatah.
Klinik **tutup tiap Senin**. Terukur di prod 23 Sep: **6 dari 65 potongan cuti
(9,2%) jatuh di hari Senin** — Ramdan 4 hari, Yovanka 2 hari.

⚠️ Jangan langsung menyebut keenamnya salah: benar/tidaknya tergantung **aturan
kerja masing-masing orang** (staf kantor bisa saja memang kerja Senin). Justru
itulah yang diselesaikan branch ini — `hariLibur()` menjawab per ORANG, bukan
per kalender.

**Yang ditambahkan ke `CutiService`:** `hariLibur()` · `tanggalLiburDalamJendela()`
· `dampakJatah()` · `potonganJanggal()`. Rancangannya **gagal-aman**: kalau
resolver jadwal tak bisa menjawab, hasilnya kosong → semua hari dianggap hari
kerja → perilaku lama. Cuti tidak berhenti terpotong gara-gara mesin jadwal
bermasalah. Itu arah kegagalan yang benar.

⚠️ **Catatan memori HUB perlu diluruskan.** Catatan lama berbunyi "Cuti & WFH tak
memakan hari libur — ✅ SUDAH PROD (diukur 4 Sep)". Yang sudah prod itu sisi
**absensi** (`att_day`/reconciler); sisi **potong jatah cuti** (`CutiService`)
belum — dan itulah yang branch ini tutup.

**Sebelum naik:** 186 commit di belakang main — jalankan kelima berkas uji di
atas main, bukan di atas basisnya. `AttendanceReconciler` di branch masih memakai
`Schema::hasColumn()` sementara main sudah pindah ke `kolomAda()`
(⛔ `Schema::has*()` membunuh mysqld lokal) — **cherry-pick, jangan merge mentah**.

## 3. `wip/snapshot-rop-tabs-2026-09-23` — simpan, jangan merge

**Isi:** +123/−113 di 12 berkas — membuang duplikat `.rop-tabs`/`.rop-tab` dari
5 berkas CSS per-zona, diganti satu komponen bersama `.stx-tabs`/`.stx-tab` di
`zona.css` (+65 baris). `docs/spec/pola-interaksi.md` ikut diperbarui.

**Masih relevan:** `.stx-tabs` **belum ada** di main, `.rop-tab` **masih ada**.
Jadi belum disalip pekerjaan lain.

**Tapi jangan di-merge apa adanya.** Basisnya 5 Agustus — **411 commit** di
belakang main. Merge-nya memang bersih secara mekanis, tapi tiga blade yang
disentuh (`kehadiran`, `pengawasan`, `inbox`) sudah jauh berubah sejak itu;
"bersih" di sini berarti git tak melihat bentrok baris, bukan bahwa hasilnya
benar. **Kerjakan ulang di atas main** kalau memang mau dipakai — snapshot ini
gunanya sebagai rujukan rancangan, bukan tambalan siap pasang.

---

## Urutan yang HUB sarankan

1. **`fix/cuti-hari-libur`** lebih dulu — satu-satunya yang menutup cacat yang
   sedang berjalan dan menyentuh uang (jatah cuti).
2. **`feat/wifi-dedup-role-pengawas`** menyusul, gerbong kecil sendiri — supaya
   kalau besoknya ada yang tak bisa mendaftarkan WiFi, penyebabnya tak tercampur.
3. **rop-tabs** tidak masuk antrean deploy sama sekali; ia bahan, bukan barang.

Nol SQL untuk ketiganya. rop-tabs menyentuh `public/assets/css/**` → kalau kelak
dikerjakan ulang dan naik, **wajib rsync terbatas ke `assets/`**.

## Yang sudah dibereskan hari ini

- Working copy bersama `sweettreats`: **35 → 12** berkas kotor. Sisanya 9 berkas
  kode Alur Persetujuan (aman — byte-identik dengan
  `origin/wip/snapshot-konsultan-alur-2026-08-22`, milik chat lain) + 3 docs.
- Berkas nyasar `u699999@x` (skrip uji salah nama, merujuk `sweettreats-rapi`)
  dipindahkan ke scratchpad, tidak dihapus permanen.
