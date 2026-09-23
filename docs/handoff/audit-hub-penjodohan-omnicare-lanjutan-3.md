# Audit HUB putaran keempat — penjodohan Omnicare (16 Sep 2026)

> Lanjutan dari `audit-hub-penjodohan-omnicare-lanjutan-2.md`.

---

## Semua terverifikasi

| pemeriksaan HUB | hasil |
|---|---|
| `RiwayatMultiPidTest` | **7 uji, 15 assertion** ✅ |
| Total gerbong (20 · 25 · 25 · 7) | **77** ✅ |
| `RiwayatRekamMedisTest` (regresi, tak dihitung) | 23 ✅ |
| Kendali bawaan di dalam uji | nyata — `test_kendali_negatif_...` menjalankan bentuk kueri LAMA berdampingan dan **menuntut hasilnya berbeda** ✅ |
| Nol ter-stage | ✅ |
| `AlurPersetujuan*` tersentuh? | tidak — tetap untracked, tak ada di daftar `git add` ✅ |
| Daftar `git add` | 14 entri, cocok persis dengan berkas gerbong ini ✅ |

**Kendali negatif HUB sendiri, dijalankan di kode produksi:**

```
baris 514  whereIn('p_id',$pids) → where('p_id',$pids[0])   ⇒ 3 uji MERAH
baris 534  whereIn('p_id',$pids) → where('p_id',$pids[0])   ⇒ 1 uji MERAH
dipulihkan, md5 12b523da… IDENTIK dengan sebelum kontrol
```

Persis angka yang kalian laporkan. Dan uji yang **membawa kendali negatifnya di
dalam dirinya sendiri** itu ide yang lebih baik daripada yang HUB minta — kontrol
manual hanya bekerja kalau ada orang yang ingat menjalankannya; punya kalian
merah sendiri kalau suatu hari kedua jalur jadi setara.

---

## ⛔ SATU HAL SEBELUM COMMIT — working copy ini 239 commit di belakang main

```
git rev-list --left-right --count HEAD...origin/main   →   0   239
```

Nol commit unik (semua masih di working tree), tapi **239 commit tertinggal**.
Akibatnya terukur di berkas dokumentasi:

| | `docs/log-update.md` |
|---|---|
| HEAD (`c3f1e33`) | **41 entri** |
| `origin/main` | **91 entri** |

Kabar baiknya — HUB sudah memeriksa satu per satu — **kelima berkas kode gerbong
ini IDENTIK antara HEAD dan `origin/main`**:

```
CustomerController.php · MatchOmnicarePatients.php · OmnicareReconcile.php
ReconciliationController.php · report/reconcile.blade.php      semuanya "sama"
```

Jadi **kodenya aman di-commit dari sini** dan akan menyatu bersih. Yang tidak
aman cuma dokumentasinya.

### Yang akan rusak kalau dikerjakan di sini

Aturan tetap proyek: tiap milestone WAJIB punya entri `docs/log-update.md`
(+ `status-proyek.md` bila peta berubah) → commit → **salin keduanya ke
`G:\My Drive\Task Management\Sweet Treats`**.

Kalau entri itu ditulis di working copy ini, ia ditulis di atas versi
**41-entri**. Menyalinnya ke Drive akan **menimpa berkas Drive yang berisi 92
entri dengan versi berisi 42** — 50 entri lenyap, tanpa pesan galat, dan Drive
itu dibaca dari HP lewat Gemini.

Benar bahwa kalian **sudah tepat** mengeluarkan `log-update.md` dan
`status-proyek.md` dari daftar `git add` — jangan dimasukkan. Tapi artinya
milestone ini **belum terdokumentasi**, dan itu sisa pekerjaan, bukan hal yang
selesai dengan sendirinya.

### Cara yang benar

Tulis entri dokumentasinya **di pohon yang sudah berisi 91 entri**, bukan di
sini:

```bash
# worktree yang menunjuk main, disegarkan dulu
git -C ../sweettreats-main fetch origin
git -C ../sweettreats-main checkout main
git -C ../sweettreats-main merge --ff-only origin/main
grep -c '^## ' ../sweettreats-main/docs/log-update.md    # harus 91, bukan 41
```

lalu tulis entrinya di sana, commit di sana, dan **salin ke Drive dari sana**.

⭐ Ini persis yang dilakukan chat ROP hari ini dan kenapa salinan Drive mereka
selamat: worktree-nya berbasis `23fb5b1` (= main), jadi Drive naik **91 → 92**
dan HUB bisa membuktikan nol entri hilang lewat md5. Pola yang sama, hasil yang
sama — asal pohonnya benar.

---

## Yang HUB minta

1. Commit **kode** dari sini dengan daftar 14 berkas itu apa adanya — aman,
   sudah diverifikasi identik dengan main.
2. Tulis entri `log-update.md` + `status-proyek.md` di pohon yang berisi 91
   entri, lalu salin ke Drive **dari sana**. Jangan dari working copy ini.
3. Sebelum menyalin ke Drive, hitung dulu: `grep -c '^## '` di berkas Drive dan
   di berkas sumber. Angka sumber harus **lebih besar**, bukan lebih kecil.
