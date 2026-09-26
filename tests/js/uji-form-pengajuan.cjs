/**
 * Uji perilaku FORM PENGAJUAN — bagian yang tidak terjangkau phpunit.
 *
 * Blok <script> diekstrak dari blade yang sama yang dikirim ke browser dan
 * dijalankan APA ADANYA di atas DOM tiruan, jadi kalau kodenya berubah, uji
 * ini ikut menguji versi barunya.
 *
 * Yang dijaga: foto WAJIB untuk lembur & WFH terbaca SEBELUM orang menekan
 * kirim (pola P3) — dan jenis lain tidak ikut terkena. Gerbang sungguhannya
 * tetap di server; ini soal apakah orangnya tahu sebelum dipentalkan.
 *
 * Jalankan:  node tests/js/uji-form-pengajuan.cjs
 * ⚠️ .cjs, bukan .js — package.json repo ini "type": "module".
 */
const fs = require('fs');
const path = process.argv[2]
  || require('path').join(__dirname, '..', '..', 'resources/views/absensi/akun/_pengajuan_form.blade.php');

let sumber = fs.readFileSync(path, 'utf8');
const m = sumber.match(/<script>\s*\(function \(\) \{([\s\S]*?)\}\)\(\);\s*<\/script>/);
if (!m) { console.error('blok script tidak ketemu'); process.exit(1); }

let js = m[1]
  .replace(/@json\(\\App\\Models\\AttRequest::TIPE_WAJIB_FOTO\)/g, '["lembur","wfh"]')
  .replace(/\{\{\s*\\App\\Models\\AttRequest::SAKIT_HARI_WAJIB_SURAT\s*\}\}/g, '3')
  .replace(/\{\{[^}]*\}\}/g, 'X');

// Bocorkan yang perlu diperiksa.
js += `
  globalThis.__uji = {
    aturLampiran: aturLampiran,
    aturTombolKirim: aturTombolKirim,
    sync: sync,
    setTipe: function (t) { tipeHidden.value = t; sync(); },
  };
`;

/* ───────────── DOM tiruan ───────────── */
function buatEl(tag) {
  const el = {
    _tag: tag || 'div', style: {}, dataset: {}, attributes: {},
    children: [], _handlers: {}, files: [],
    textContent: '', innerHTML: '', value: '', placeholder: '',
    disabled: false, hidden: false, required: false, type: '',
    classList: { _s: new Set(), add(c){this._s.add(c);}, remove(c){this._s.delete(c);},
                 contains(c){return this._s.has(c);} },
    // childNodes[0].nodeValue dipakai untuk mengganti teks label tanpa
    // membuang <span> catatannya.
    childNodes: [{ nodeValue: '' }],
    setAttribute(k,v){ this.attributes[k]=String(v); },
    getAttribute(k){ return k in this.attributes ? this.attributes[k] : null; },
    addEventListener(ev,fn){ (this._handlers[ev]=this._handlers[ev]||[]).push(fn); },
    dispatch(ev,obj){ (this._handlers[ev]||[]).forEach(fn => fn.call(this,
      Object.assign({ preventDefault(){}, target:{ closest(){return null;} } }, obj||{}))); },
    appendChild(c){ this.children.push(c); return c; },
    remove(){}, querySelector(){ return null; }, querySelectorAll(){ return []; },
    scrollIntoView(){}, focus(){}, click(){}, reset(){}, closest(){ return null; },
  };
  return el;
}

const byId = {};
const form = buatEl('form');
form.querySelector = (sel) => {
  const id = sel.startsWith('#') ? sel.slice(1) : null;
  if (id) return byId[id] || (byId[id] = buatEl('div'));
  return buatEl('div');
};
form.querySelectorAll = () => [];

global.document = {
  body: buatEl('body'),
  _handlers: {},
  addEventListener(ev,fn){ (this._handlers[ev]=this._handlers[ev]||[]).push(fn); },
  getElementById: id => byId[id] || (byId[id] = buatEl('div')),
  createElement: tag => buatEl(tag),
  querySelector(sel){ return sel === '.absk-ajform' ? form : null; },
  querySelectorAll(){ return []; },
};
global.window = global;
global.fetch = () => Promise.resolve({ json: () => Promise.resolve({}) });
global.setTimeout = (fn) => 0;

new Function(js)();
const U = globalThis.__uji;

/* ───────────── pemeriksaan ───────────── */
let gagal = 0, lulus = 0;
function cek(nama, aktual, harapan) {
  if (String(aktual) === String(harapan)) { lulus++; console.log('  ok   ' + nama); }
  else { gagal++; console.log('  GAGAL ' + nama + '\n        dapat: ' + JSON.stringify(aktual)
        + '\n        harap: ' + JSON.stringify(harapan)); }
}
const lbl  = () => byId.fotoLbl.childNodes[0].nodeValue;
const note = () => byId.fotoNote.textContent;
const tombol = () => byId.ajSubmitTeks.textContent;

console.log('\n[1] Pilih LEMBUR — wajib, dan tombolnya bilang apa yang kurang');
byId.fotoInput.files = [];
U.setTipe('lembur');
cek('label',            lbl().trim(), 'Foto Bukti');
cek('catatan wajib',    note(), '(wajib — lembur tanpa foto bukti tidak bisa dikirim)');
cek('warna peringatan', byId.fotoNote.style.color, '#C0392B');
cek('input.required',   byId.fotoInput.required, true);
cek('tombol mati',      byId.ajSubmit.disabled, true);
cek('tombol bilang kenapa', tombol(), 'Lampirkan foto dulu');

console.log('\n[2] Foto dilampirkan — tombol hidup tanpa perlu memilih ulang jenis');
byId.fotoInput.files = [{ name: 'bukti.jpg' }];
byId.fotoInput.dispatch('change');
cek('tombol hidup', byId.ajSubmit.disabled, false);
cek('teks tombol',  tombol(), 'Kirim Permohonan');

console.log('\n[3] Pilih WFH — kata-katanya menyebut kerja dari rumah');
byId.fotoInput.files = [];
U.setTipe('wfh');
cek('catatan wajib', note(), '(wajib — kerja dari rumah tanpa foto bukti tidak bisa dikirim)');
cek('input.required', byId.fotoInput.required, true);
cek('tombol mati',    byId.ajSubmit.disabled, true);

console.log('\n[4] ⭐ Kendali: jenis LAIN tidak ikut terkena');
U.setTipe('cuti');
cek('cuti: opsional',      note(), '(opsional — lampirkan kalau ada yang perlu ditunjukkan)');
cek('cuti: tak wajib',     byId.fotoInput.required, false);
cek('cuti: tombol hidup',  byId.ajSubmit.disabled, false);
cek('cuti: teks tombol',   tombol(), 'Kirim Permohonan');
cek('cuti: warna normal',  byId.fotoNote.style.color, '');

U.setTipe('izin');
cek('izin: tombol hidup', byId.ajSubmit.disabled, false);

console.log('\n[5] ⭐ Kendali: aturan SAKIT yang lama tidak rusak');
byId.tglFrom.value = '2027-06-01';
byId.tglTo.value   = '2027-06-01';           // 1 hari
U.setTipe('sakit');
cek('sakit 1 hari: opsional', note(), '(opsional — sakit 1–2 hari tidak perlu surat dokter)');
cek('sakit 1 hari: tak wajib', byId.fotoInput.required, false);
cek('sakit 1 hari: tombol hidup', byId.ajSubmit.disabled, false);

byId.tglTo.value = '2027-06-05';             // 5 hari
U.setTipe('sakit');
cek('sakit 5 hari: label', lbl().trim(), 'Surat Dokter');
cek('sakit 5 hari: wajib', byId.fotoInput.required, true);
cek('sakit 5 hari: catatan', note(), '(wajib — sakit 5 hari perlu surat dokter)');
// Sakit TIDAK ada di TIPE_WAJIB_FOTO, jadi tombolnya tidak ikut dikunci oleh
// pagar foto — yang mengunci cuma `required` bawaan peramban.
cek('sakit: tombol tidak dikunci pagar foto', tombol(), 'Kirim Permohonan');

console.log('\n' + (gagal === 0 ? 'SEMUA LULUS' : gagal + ' GAGAL') + ' — ' + lulus + ' pemeriksaan hijau');
process.exit(gagal === 0 ? 0 : 1);
