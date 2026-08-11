{{--
    KartuAbsenHarian — komponen kartu detail absen 1 orang 1 hari (dari
    handoff_kehadiran). Dipakai di: Kehadiran (popover/sheet), Kehadiran Saya
    (panel/sheet), Pengawasan Absen (panel kanan, chrome=false).

    Kamus status (warna + ikon + kata) tinggal DI SINI — halaman pemanggil pakai
    window.KAH.ST, jangan menyalin hex sendiri. Kalau kamus berubah, ubah sekali.

    Pemakaian:
        @include('absensi._kartu_absen_harian')
        el.innerHTML = KAH.render({ person, roleBranch, dateLabel, status,
            jamIn, jamOut, lateMin, lemburJam, crossBranch, single, photos,
            flex, hol, variant, chrome, showPhotos, monthBtnLabel, hint,
            onClose: 'jsCall()', onOpenMonth: 'jsCall()' });

    Posisi/penempatan (fixed/anchored/sheet) = tanggung jawab pemanggil.
    Komponen hanya mengisi lebar induknya, max 480px.
--}}
<style>
    .kah { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #3B1500; display: flex; flex-direction: column; background: #fff; width: 100%; max-width: 480px; }
    .kah *, .kah *::before, .kah *::after { box-sizing: border-box; }
    .kah .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }
    .kah .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
    .kah-btn { transition: filter .12s ease, background .12s ease; }
    .kah-btn:hover { filter: brightness(.96); }
    .kah-handle { display: flex; justify-content: center; padding: 9px 0 3px; }
    .kah-handle span { width: 38px; height: 4px; border-radius: 4px; background: #E7D3BE; }
    .kah-head { display: flex; align-items: flex-start; gap: 11px; padding: 15px 16px 12px; }
    .kah-av { width: 38px; height: 38px; flex: none; border-radius: 50%; color: #FFF3E9; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; letter-spacing: .2px; }
    .kah-id { flex: 1; min-width: 0; }
    .kah-name { font-size: 14.5px; font-weight: 800; color: #3B1500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kah-sub { font-size: 11.5px; color: #A9866A; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kah-x { width: 32px; height: 32px; flex: none; border-radius: 9px; border: none; background: #F7EFE6; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .kah-body { padding: 0 16px 14px; display: flex; flex-direction: column; gap: 13px; }
    .kah-body.nochrome { padding: 14px 16px; }
    .kah-badge-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .kah-badge { display: inline-flex; align-items: center; gap: 7px; border-radius: 20px; padding: 7px 14px; font-size: 13px; font-weight: 800; letter-spacing: .2px; border: 1px solid transparent; }
    .kah-date { font-size: 12.5px; font-weight: 600; color: #7A5B43; }
    .kah-hol { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #C0392B; background: #FBE3DE; border: 1px solid #F2CFC7; border-radius: 10px; padding: 9px 11px; }
    .kah-jam-row { display: flex; align-items: stretch; gap: 9px; }
    .kah-jam { flex: 1; background: #FBF5ED; border: 1px solid #F2E6D8; border-radius: 12px; padding: 10px 12px; }
    .kah-jam-k { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 800; letter-spacing: .6px; color: #A9866A; }
    .kah-jam-v { font-size: 19px; font-weight: 700; color: #3B1500; margin-top: 3px; }
    .kah-jam-note { font-size: 10px; color: #B79A82; margin-top: 2px; }
    .kah-facts { display: flex; flex-direction: column; gap: 7px; }
    .kah-fact { display: flex; align-items: center; gap: 9px; border-radius: 11px; padding: 10px 12px; border: 1px solid transparent; }
    .kah-fact-l { font-size: 12px; font-weight: 700; }
    .kah-fact-v { margin-left: auto; font-size: 12.5px; font-weight: 800; }
    .kah-flexnote { display: flex; gap: 9px; font-size: 11.5px; color: #8A6A52; background: #F1E9DF; border-radius: 10px; padding: 9px 11px; line-height: 1.45; }
    .kah-flexnote svg { flex: none; margin-top: 1px; }
    .kah-photos-k { display: flex; align-items: center; gap: 7px; font-size: 10px; font-weight: 800; letter-spacing: .6px; color: #A9866A; margin-bottom: 8px; }
    .kah-photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
    .kah-photo-grid.single { grid-template-columns: 1fr; }
    .kah-photo-box { height: 132px; border-radius: 11px; border: 1px solid #E7D3BE; overflow: hidden; background: #241309; }
    .kah-photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .kah-photo-cap { font-size: 10.5px; color: #A9866A; margin-top: 5px; }
    .kah-nofoto { font-size: 12px; color: #A9866A; background: #FBF5ED; border: 1px dashed #E7D3BE; border-radius: 11px; padding: 12px; text-align: center; }
    .kah-foot { display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: #FCF7F0; border-top: 1px solid #F2E6D8; }
    .kah-monthbtn { display: inline-flex; align-items: center; gap: 7px; background: #fff; color: #8A3A12; border: 1px solid #E7D3BE; border-radius: 10px; padding: 10px 13px; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; min-height: 40px; }
    .kah-hint { margin-left: auto; font-size: 10.5px; color: #B79A82; text-align: right; }
</style>
<script>
window.KAH = (function () {
    'use strict';

    var AV = ['#8A3A12', '#A8481B', '#2F8E55', '#3C5FA8', '#B5701A', '#7A3415', '#5A6B2E', '#8E2419'];

    var ICO = {
        check: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><path d="M3 8.4 6.2 11.6 13 4.6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        clock: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.6"/><path d="M8 4.6V8l2.4 1.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        cross: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.6"/><path d="M5.8 5.8l4.4 4.4M10.2 5.8l-4.4 4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        sun: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M8 1.4v1.6M8 13v1.6M1.4 8h1.6M13 8h1.6M3.5 3.5l1.1 1.1M11.4 11.4l1.1 1.1M12.5 3.5l-1.1 1.1M4.6 11.4l-1.1 1.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        note: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><rect x="3" y="1.8" width="10" height="12.4" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5.6 5.6h4.8M5.6 8.4h4.8M5.6 11.2h2.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        moon: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><path d="M13 10.2A5.6 5.6 0 0 1 5.8 3a5.8 5.8 0 1 0 7.2 7.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
        home: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><path d="M2.4 7.2 8 2.6l5.6 4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.8 8.2v5h8.4v-5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        link: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><path d="M6 10.5H4a2.5 2.5 0 0 1 0-5h2M10 5.5h2a2.5 2.5 0 0 1 0 5h-2M5.6 8h4.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        plus: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.5"/><path d="M8 5.2v5.6M5.2 8h5.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        camera: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><rect x="1.5" y="4" width="13" height="10" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 4l1-1.6h3l1 1.6" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="8" cy="9" r="2.4" stroke="currentColor" stroke-width="1.4"/></svg>',
        calendar: '<svg width="S" height="S" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 6.5h12M5.5 1.6v2.4M10.5 1.6v2.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        login: '<svg width="S" height="S" viewBox="0 0 14 14" fill="none"><path d="M8.5 2.5h2A1.5 1.5 0 0 1 12 4v6a1.5 1.5 0 0 1-1.5 1.5h-2M6.5 4.5 9 7l-2.5 2.5M9 7H2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        logout: '<svg width="S" height="S" viewBox="0 0 14 14" fill="none"><path d="M5.5 2.5h-2A1.5 1.5 0 0 0 2 4v6a1.5 1.5 0 0 0 1.5 1.5h2M9 4.5 11.5 7 9 9.5M11.5 7H5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        info: '<svg width="S" height="S" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.4" stroke="currentColor" stroke-width="1.3"/><path d="M7 4.3v.1M7 6.2v3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
        x: '<svg width="S" height="S" viewBox="0 0 14 14" fill="none"><path d="M3.5 3.5l7 7M10.5 3.5l-7 7" stroke="#8A6A52" stroke-width="1.7" stroke-linecap="round"/></svg>'
    };

    // KAMUS STATUS — warna + ikon + kata + kode huruf (mode padat).
    // Identik dengan handoff_kehadiran/README.md §2. Ubah di sini = ubah di semua
    // halaman zona Absensi yang meng-include partial ini.
    var ST = {
        hadir: { l: 'Hadir', code: 'H', bg: '#E3F4E8', c: '#2F8E55', br: '#C9E6D3', ico: 'check' },
        telat: { l: 'Telat', code: 'T', bg: '#FBEFD9', c: '#B5701A', br: '#F0DDBB', ico: 'clock' },
        alpa:  { l: 'Alpa',  code: 'A', bg: '#FBE3DE', c: '#C0392B', br: '#F2CFC7', ico: 'cross' },
        cuti:  { l: 'Cuti',  code: 'C', bg: '#E4ECFB', c: '#3F6BAF', br: '#CFDCF3', ico: 'sun' },
        izin:  { l: 'Izin',  code: 'I', bg: '#F4E7D6', c: '#9A6614', br: '#E8D5B9', ico: 'note' },
        sakit: { l: 'Sakit', code: 'S', bg: '#EDE6F4', c: '#6B4C9A', br: '#DCD0EA', ico: 'note' },
        libur: { l: 'Libur', code: 'L', bg: '#F1E9DF', c: '#8A6A52', br: '#E4D8C9', ico: 'moon' },
        wfh:   { l: 'WFH',   code: 'W', bg: '#DFEDE9', c: '#2E7D74', br: '#C7DED8', ico: 'home' }
    };
    var ORDER = ['hadir', 'telat', 'alpa', 'cuti', 'izin', 'sakit', 'libur', 'wfh'];

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function svg(name, size) {
        return '<span style="display:flex;align-items:center;justify-content:center;flex:none">'
            + (ICO[name] || '').split('S').join(String(size || 14)) + '</span>';
    }
    function ini(name) {
        var p = String(name || '').replace(/^drg\.\s*/i, '').trim().split(/\s+/);
        return (((p[0] || '')[0] || '') + ((p[1] || '')[0] || '')).toUpperCase() || '?';
    }
    function avColor(name) {
        var s = String(name || ''), h = 0;
        for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 997;
        return AV[h % AV.length];
    }
    function jamNum(x) { return (x % 1 === 0) ? String(x) : String(x).replace('.', ','); }

    function render(p) {
        p = p || {};
        var st = ST[p.status] || ST.libur;
        var chrome = p.chrome !== false;
        var showPhotos = p.showPhotos !== false;
        var jamIn = p.jamIn || '', jamOut = p.jamOut || '';
        var late = Number(p.lateMin || 0), lembur = Number(p.lemburJam || 0);
        var h = '<div class="kah">';

        if ((p.variant || 'popover') === 'sheet') h += '<div class="kah-handle"><span></span></div>';

        if (chrome) {
            h += '<div class="kah-head">'
                + '<div class="kah-av" style="background:' + avColor(p.person) + '">' + esc(ini(p.person)) + '</div>'
                + '<div class="kah-id"><div class="kah-name">' + esc(p.person || '—') + '</div>'
                + '<div class="kah-sub">' + esc(p.roleBranch || '') + '</div></div>'
                + (p.onClose ? '<button type="button" class="kah-x kah-btn" onclick="' + esc(p.onClose) + '" title="Tutup">' + svg('x', 14) + '</button>' : '')
                + '</div>';
        }

        h += '<div class="kah-body' + (chrome ? '' : ' nochrome') + '">';

        h += '<div class="kah-badge-row">'
            + '<span class="kah-badge" style="background:' + st.bg + ';color:' + st.c + ';border-color:' + st.br + '">' + svg(st.ico, 15) + esc(st.l) + '</span>'
            + '<span class="kah-date">' + esc(p.dateLabel || '') + '</span></div>';

        if (p.hol) {
            h += '<div class="kah-hol">' + svg('calendar', 14) + 'Tanggal merah · ' + esc(p.hol) + '</div>';
        }

        if (jamIn || jamOut) {
            var outVal = jamOut || '—';
            var outNote = (!jamOut && p.single) ? '<div class="kah-jam-note">scan tunggal — tidak ada scan pulang</div>' : '';
            h += '<div class="kah-jam-row">'
                + '<div class="kah-jam"><div class="kah-jam-k">' + svg('login', 12) + 'JAM MASUK</div><div class="kah-jam-v mono">' + esc(jamIn || '—') + '</div></div>'
                + '<div class="kah-jam"><div class="kah-jam-k">' + svg('logout', 12) + 'JAM PULANG</div><div class="kah-jam-v mono">' + esc(outVal) + '</div>' + outNote + '</div>'
                + '</div>';
        }

        var facts = [];
        if (late) facts.push({ bg: '#FBEFD9', br: '#F0DDBB', c: '#B5701A', ico: 'clock', l: 'Telat', v: late + ' menit' });
        if (lembur) facts.push({ bg: '#FBEFD9', br: '#F0DDBB', c: '#B5701A', ico: 'plus', l: 'Lembur disetujui', v: jamNum(lembur) + ' jam' });
        if (p.crossBranch) facts.push({ bg: '#F4E7D6', br: '#E8D5B9', c: '#8A5A16', ico: 'link', l: 'Lintas cabang', v: p.crossBranch });
        if (facts.length) {
            h += '<div class="kah-facts">';
            facts.forEach(function (f) {
                h += '<div class="kah-fact" style="background:' + f.bg + ';border-color:' + f.br + ';color:' + f.c + '">'
                    + svg(f.ico, 14) + '<span class="kah-fact-l">' + esc(f.l) + '</span>'
                    + '<span class="kah-fact-v num">' + esc(f.v) + '</span></div>';
            });
            h += '</div>';
        }

        if (p.flex) {
            h += '<div class="kah-flexnote">' + svg('info', 14)
                + '<span>Karyawan <b>Kantor (ABG)</b> — jam kerja bebas, scan tak dinilai. Tidak ditandai telat atau alpa.</span></div>';
        }

        if (showPhotos) {
            var photos = p.photos || [];
            h += '<div><div class="kah-photos-k">' + svg('camera', 13) + 'FOTO SELFIE ABSEN</div>';
            if (photos.length) {
                h += '<div class="kah-photo-grid' + (photos.length === 1 ? ' single' : '') + '">';
                photos.slice(0, 2).forEach(function (f) {
                    h += '<div><div class="kah-photo-box"><img src="' + esc(f.url) + '" alt="selfie ' + esc(f.jenis || '') + '" loading="lazy"></div>'
                        + '<div class="kah-photo-cap mono">' + esc(f.jenis || '') + (f.jam ? ' ' + esc(f.jam) : '') + '</div></div>';
                });
                h += '</div>';
            } else {
                h += '<div class="kah-nofoto">tidak ada foto — absen dari mesin sidik jari</div>';
            }
            h += '</div>';
        }

        h += '</div>'; // body

        if (chrome && (p.onOpenMonth || p.hint)) {
            h += '<div class="kah-foot">';
            if (p.onOpenMonth) {
                h += '<button type="button" class="kah-monthbtn kah-btn" onclick="' + esc(p.onOpenMonth) + '">'
                    + svg('calendar', 14) + esc(p.monthBtnLabel || 'Lihat sebulan') + '</button>';
            }
            if (p.hint) h += '<span class="kah-hint">' + esc(p.hint) + '</span>';
            h += '</div>';
        }

        h += '</div>';
        return h;
    }

    return { ST: ST, ORDER: ORDER, ICO: ICO, AV: AV, render: render, svg: svg, ini: ini, avColor: avColor, esc: esc, jamNum: jamNum };
})();
</script>
