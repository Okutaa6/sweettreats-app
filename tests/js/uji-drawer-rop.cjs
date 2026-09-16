/**
 * Uji perilaku drawer antrian ROP — bagian yang TIDAK tersentuh phpunit.
 *
 * Skripnya dijalankan APA ADANYA: blok <script> diekstrak dari blade yang
 * sama yang dikirim ke browser, bukan disalin ulang ke sini. Jadi kalau
 * kodenya berubah, uji ini ikut menguji versi barunya.
 *
 * Jalankan:  node tests/js/uji-drawer-rop.cjs
 * Keluar 0 kalau semua hijau, 1 kalau ada yang merah.
 *
 * ⚠️ Ekstensi .cjs, bukan .js — package.json repo ini "type": "module",
 *    jadi berkas .js diperlakukan sebagai ES module dan require() mati.
 *
 * DOM-nya tiruan seadanya (±100 baris di bawah), cukup untuk alur yang diuji:
 * klik, ganti kata, fetch, toast, geser angka, baris hilang. Bukan pengganti
 * uji browser sungguhan — pengganti TIDAK ADA uji sama sekali.
 */
const fs = require('fs');
const path = process.argv[2]
  || require('path').join(__dirname, '..', '..', 'resources/views/finance/rop/index.blade.php');

let sumber = fs.readFileSync(path, 'utf8');
const m = sumber.match(/@section\('js'\)[\s\S]*?<script>([\s\S]*?)<\/script>/);
if (!m) { console.error('blok script tidak ketemu'); process.exit(1); }

// Blade {{ ... }} diganti nilai yang masuk akal (url dasar & token).
let js = m[1]
  .replace(/\{\{\s*url\('finance\/rop'\)\s*\}\}/g, '/finance/rop')
  .replace(/\{\{[^}]*\}\}/g, 'X');

// Bocorkan beberapa fungsi internal untuk diperiksa langsung.
js = js.replace(/\}\)\(\);\s*$/, `
  globalThis.__uji = { siapkanBlokTolak: siapkanBlokTolak, perbaruiAngka: perbaruiAngka, modeTolak: function(){return modeTolak;} };
})();`);

/* ───────────────────────── DOM tiruan ───────────────────────── */
let idSeq = 0;
function buatEl(tag, attrs) {
  const el = {
    _tag: tag || 'div', _id: ++idSeq,
    style: {}, dataset: {}, classList: {
      _s: new Set(),
      add(c) { this._s.add(c); }, remove(c) { this._s.delete(c); },
      contains(c) { return this._s.has(c); },
    },
    attributes: Object.assign({}, attrs || {}),
    children: [], parentNode: null, _handlers: {},
    textContent: '', innerHTML: '', value: '', placeholder: '', disabled: false, hidden: false,
    action: '', type: '',
    setAttribute(k, v) { this.attributes[k] = String(v); },
    getAttribute(k) { return k in this.attributes ? this.attributes[k] : null; },
    addEventListener(ev, fn) { (this._handlers[ev] = this._handlers[ev] || []).push(fn); },
    dispatch(ev, obj) {
      (this._handlers[ev] || []).forEach(fn => fn.call(this, Object.assign({
        preventDefault() {}, target: { closest() { return null; } },
      }, obj || {})));
    },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
    remove() { if (this.parentNode) this.parentNode.children = this.parentNode.children.filter(x => x !== this); this.parentNode = null; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    scrollIntoView() {}, focus() {}, reset() {},
    closest() { return null; },
  };
  return el;
}

const byId = {};
function el(id, attrs) { return (byId[id] = buatEl('div', attrs)); }

[
  'ropDrawer','ropScrim','ropDAkun','ropDAkunNilai','ropSetujui','ropSetujuiLabel',
  'ropDTolakBlok','ropDTolakJudul','ropDTolakAkibat','ropDAlasan',
  'ropDAlasanChipAjukan','ropDAlasanChipBatal','ropTolakKirim',
  'ropBukaTolak','ropBukaBatalAcc','ropAccForm','ropTolakForm','ropBayarForm',
  'ropDrawerBayar','ropToast','ropStatMenungguN','ropStatMenungguRp',
  'ropStatDisetujuiN','ropStatDisetujuiRp','ropBNomor','ropBNominal',
  'ropDIsi','ropDMuat','ropDNomor','ropDNominal','ropDStatus','ropDJejak',
  'ropDChip','ropDSaran','ropDBuku','ropDAkunBlok','ropAccHint',
  'ropFootAcc','ropFootBayar','ropDPengaju','ropDLampiran','ropDVendor',
].forEach(id => el(id));

// Statistik awal: 3 menunggu (Rp 1.000.000) · 2 antrian bayar (Rp 600.000)
byId.ropStatMenungguN.dataset.n = '3';      byId.ropStatMenungguN.textContent = '3';
byId.ropStatMenungguRp.dataset.rp = '1000000';
byId.ropStatDisetujuiN.dataset.n = '2';     byId.ropStatDisetujuiN.textContent = '2';
byId.ropStatDisetujuiRp.dataset.rp = '600000';

// Dua baris tabel di tab "Antrian bayar" + badge tab.
const baris = [77, 78].map(id => {
  const tr = buatEl('tr', { 'data-rop-buka': String(id) });
  tr.previousElementSibling = null;
  return tr;
});
const badgeDisetujui = buatEl('span', { 'data-badge': 'disetujui' }); badgeDisetujui.textContent = '2';
const badgeMenunggu  = buatEl('span', { 'data-badge': 'menunggu'  }); badgeMenunggu.textContent  = '3';
const kakiDrawer = buatEl('div');

global.document = {
  body: buatEl('body'),
  _handlers: {},
  addEventListener(ev, fn) { (this._handlers[ev] = this._handlers[ev] || []).push(fn); },
  // Auto-vivify: id yang tidak didaftarkan tetap dapat elemen kosong, supaya
  // harness tidak perlu mencantumkan setiap id di halaman.
  getElementById: id => byId[id] || (byId[id] = buatEl('div')),
  createElement: tag => buatEl(tag),
  querySelector(sel) {
    if (sel === '.rop-drawer__foot') return kakiDrawer;
    if (sel === '#ropAccForm input[name=_token]') return { value: 'csrf-uji' };
    if (sel.startsWith('[data-badge=')) {
      const k = sel.match(/"([^"]+)"/)[1];
      return k === 'disetujui' ? (badgeDisetujui.parentNode !== false ? badgeDisetujui : null)
           : k === 'menunggu'  ? badgeMenunggu : null;
    }
    if (sel.startsWith('tr[data-rop-buka=')) {
      const id = sel.match(/"([^"]+)"/)[1];
      return baris.find(b => b.getAttribute('data-rop-buka') === id && b.parentNode !== false) || null;
    }
    return null;
  },
  querySelectorAll(sel) {
    if (sel === '[data-rop-buka]' || sel === 'tr[data-rop-buka]') return baris.filter(b => b.parentNode !== false);
    if (sel === '[data-rop-alasan]') return [];
    if (sel === '[data-rop-acc]' || sel === '[data-rop-bayar]' || sel === '[data-rop-pakai]') return [];
    return [];
  },
};
baris.forEach(b => { b.remove = function () { this.parentNode = false; }; });

global.window = global;
global.location = { reload() { global.__reload = true; } };
global.setTimeout = (fn) => 0;      // toast tidak perlu benar-benar menunggu
global.$ = () => ({ modal() {} });
global.FormData = function () { this._d = {}; this.append = (k, v) => { this._d[k] = v; }; };

// fetch dikendalikan tiap kasus uji.
let jawabanFetch = null;
let permintaan = [];
global.fetch = (url, opt) => {
  permintaan.push({ url, opt });
  const j = typeof jawabanFetch === 'function' ? jawabanFetch(url, opt) : jawabanFetch;
  return Promise.resolve({ ok: j.ok !== false, json: () => Promise.resolve(j.body) });
};

/* ───────────────────────── jalankan skrip ───────────────────────── */
new Function(js)();
const U = globalThis.__uji;

/* ───────────────────────── pemeriksaan ───────────────────────── */
let gagal = 0, lulus = 0;
function cek(nama, aktual, harapan) {
  // Longgar angka-vs-string: dataset DOM asli selalu string, dan kode produksi
  // sengaja menulis balik lewat Number(). Yang diuji nilainya, bukan tipenya.
  const ok = String(aktual) === String(harapan);
  if (ok) { lulus++; console.log('  ok   ' + nama); }
  else { gagal++; console.log('  GAGAL ' + nama + '\n        dapat: ' + JSON.stringify(aktual) + '\n        harap: ' + JSON.stringify(harapan)); }
}

console.log('\n[1] Klik "Batalkan persetujuan" mengganti kata-kata blok alasan');
byId.ropBukaBatalAcc.dispatch('click');
cek('judul',            byId.ropDTolakJudul.textContent, 'Alasan pembatalan persetujuan');
cek('label tombol',     byId.ropTolakKirim.textContent, 'Batalkan persetujuan');
cek('akibat terlihat',  byId.ropDTolakAkibat.style.display, '');
cek('chip ajukan mati', byId.ropDAlasanChipAjukan.style.display, 'none');
cek('chip batal hidup', byId.ropDAlasanChipBatal.style.display, '');
cek('blok terbuka',     byId.ropDTolakBlok.style.display, '');
cek('mode',             U.modeTolak(), 'batal-acc');

console.log('\n[2] Klik "Tolak" biasa mengembalikan kata-katanya');
byId.ropBukaTolak.dispatch('click');
cek('judul',            byId.ropDTolakJudul.textContent, 'Alasan penolakan');
cek('label tombol',     byId.ropTolakKirim.textContent, 'Kirim penolakan');
cek('akibat tersembunyi', byId.ropDTolakAkibat.style.display, 'none');
cek('chip batal mati',  byId.ropDAlasanChipBatal.style.display, 'none');
cek('mode',             U.modeTolak(), 'tolak');

console.log('\n[3] perbaruiAngka: pembatalan menggeser ANTRIAN BAYAR, bukan Menunggu ACC');
U.perbaruiAngka('batal-acc', 200000);
cek('menunggu N tetap',  byId.ropStatMenungguN.dataset.n, '3');
cek('menunggu Rp tetap', byId.ropStatMenungguRp.dataset.rp, 1000000);
cek('disetujui N turun', byId.ropStatDisetujuiN.dataset.n, 1);
cek('disetujui Rp turun',byId.ropStatDisetujuiRp.dataset.rp, 400000);
cek('badge disetujui',   badgeDisetujui.textContent, 1);
cek('badge menunggu',    badgeMenunggu.textContent, '3');

console.log('\n[4] Submit form: respons dari server (dari=disetujui) memilih cabang pembatalan');
byId.ropTolakKirim.type = 'submit';
byId.ropTolakForm.querySelector = () => byId.ropTolakKirim;
byId.ropTolakForm.action = '/finance/rop/77/tolak';
jawabanFetch = { ok: true, body: { ok: true, dari: 'disetujui', urung: { id: 77, number: 'ROP/SUN/2609/004' }, amount: 406240 } };
permintaan = [];
byId.ropTolakForm.dispatch('submit');

/** Judul toast = <b> pertama di dalam bungkus pertama toast. */
function judulToast() {
  const t = document.body.children[0];
  if (!t) return '(tidak ada toast)';
  const isi = t.children[0];
  return isi && isi.children[0] ? isi.children[0].textContent : (isi || {}).textContent;
}
function tombolToast() {
  const t = document.body.children[0];
  return t ? t.children.filter(c => c.textContent === 'Urungkan').length : -1;
}

setImmediate(() => {
  cek('URL fetch',          permintaan[0] && permintaan[0].url, '/finance/rop/77/tolak');
  cek('toast judul',        judulToast(), 'Persetujuan ROP/SUN/2609/004 dibatalkan');
  cek('toast TANPA Urungkan', tombolToast(), 0);
  cek('disetujui N turun lagi', byId.ropStatDisetujuiN.dataset.n, 0);
  cek('menunggu N tetap',       byId.ropStatMenungguN.dataset.n, '3');

  console.log('\n[5] Respons dari=diajukan tetap memakai kata & angka penolakan biasa');
  byId.ropStatMenungguN.dataset.n = '3';
  byId.ropStatMenungguRp.dataset.rp = '1000000';
  document.body.children.length = 0;
  byId.ropTolakForm.action = '/finance/rop/78/tolak';
  jawabanFetch = { ok: true, body: { ok: true, dari: 'diajukan', urung: { id: 78, number: 'ROP/BIN/2609/007' }, amount: 195050 } };
  byId.ropTolakForm.dispatch('submit');

  setImmediate(() => {
    cek('toast judul',   judulToast(), 'ROP/BIN/2609/007 ditolak');
    cek('toast PUNYA Urungkan', tombolToast(), 1);
    cek('menunggu N turun', byId.ropStatMenungguN.dataset.n, 2);

    /* [6] Kaki drawer per status — uji #4 handoff dari sisi LAYAR: tombol yang
           gagal lebih buruk daripada tombol yang tidak ada, jadi untuk status
           yang tidak diizinkan TRANSISI, kakinya harus hilang sama sekali. */
    const LEMBAR = { number: 'BAYAR/2609/001', dikirim: '03 Sep 2026', item: 18, dibayar: 15 };

    function bukaStatus(status, lembar) {
      jawabanFetch = { ok: true, body: {
        id: 77, number: 'ROP/SUN/2609/004', status: status, amount: 406240,
        akun: [], bahan_akun: {}, jejak: [], riwayat_vendor: [],
        lembar: lembar || null,
      } };
      baris[0].dispatch('click');
      return new Promise(r => setImmediate(() => setImmediate(r)));
    }

    console.log('\n[6] Kaki drawer per status — tombol batal hanya di cabang `disetujui`');
    bukaStatus('disetujui').then(() => {
      cek('disetujui: kaki drawer tampil', kakiDrawer.style.display, '');
      cek('disetujui: blok bayar tampil',  byId.ropFootBayar.style.display, '');
      cek('disetujui: blok ACC mati',      byId.ropFootAcc.style.display, 'none');
      cek('disetujui: mode batal-acc',     U.modeTolak(), 'batal-acc');

      return bukaStatus('dibayar');
    }).then(() => {
      cek('dibayar: kaki drawer HILANG',   kakiDrawer.style.display, 'none');
      cek('dibayar: blok bayar mati',      byId.ropFootBayar.style.display, 'none');
      cek('dibayar: mode balik ke tolak',  U.modeTolak(), 'tolak');

      return bukaStatus('selesai');
    }).then(() => {
      cek('selesai: kaki drawer HILANG',   kakiDrawer.style.display, 'none');

      return bukaStatus('diajukan');
    }).then(() => {
      cek('diajukan: blok ACC tampil',     byId.ropFootAcc.style.display, '');
      cek('diajukan: blok bayar mati',     byId.ropFootBayar.style.display, 'none');
      cek('diajukan: mode tolak',          U.modeTolak(), 'tolak');
      cek('diajukan: blok alasan tertutup', byId.ropDTolakBlok.style.display, 'none');

      /* [7] Baris keadaan lembar bayar — akibat pembatalan ada DI LUAR layar
             ini (lembarnya mungkin sudah dikirim ke bos dan sebagian sudah
             ditransfer), jadi keadaannya ditulis sebelum orang menekan. */
      console.log('\n[7] Baris keadaan lembar bayar di atas tombol batal');

      return bukaStatus('disetujui', LEMBAR);
    }).then(() => {
      cek('baris lembar tampil', byId.ropDLembar.style.display, '');
      cek('isinya lengkap', byId.ropDLembar.textContent,
          'Ada di lembar BAYAR/2609/001 · dikirim ke bos 03 Sep 2026 · 15 dari 18 item sudah dibayar');

      return bukaStatus('disetujui', { number: 'BAYAR/2609/002', dikirim: null, item: 4, dibayar: 0 });
    }).then(() => {
      cek('lembar belum dikirim', byId.ropDLembar.textContent,
          'Ada di lembar BAYAR/2609/002 · belum dikirim · 0 dari 4 item sudah dibayar');

      return bukaStatus('disetujui', null);
    }).then(() => {
      cek('tanpa lembar: baris hilang', byId.ropDLembar.style.display, 'none');
      cek('tanpa lembar: tidak menyisakan teks lama', byId.ropDLembar.textContent, '');

      // Item berikutnya yang PUNYA lembar, lalu pindah ke status lain: baris
      // tidak boleh ikut terbawa ke pengajuan yang tidak relevan.
      return bukaStatus('disetujui', LEMBAR).then(() => bukaStatus('dibayar', LEMBAR));
    }).then(() => {
      cek('status lain: baris tidak ikut terbawa', byId.ropDLembar.style.display, 'none');

      console.log('\n' + (gagal === 0 ? 'SEMUA LULUS' : gagal + ' GAGAL') + ' — ' + lulus + ' pemeriksaan hijau');
      process.exit(gagal === 0 ? 0 : 1);
    });
  });
});
