<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttDayKoreksi;
use App\Models\AuditLog;
use App\Services\KoreksiAbsen;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Absensi → Koreksi Absen" — mengubah status satu orang satu tanggal, dengan
 * alasan, nama pelaku, dan waktu.
 *
 * Sebelum layar ini satu-satunya jalan membetulkan alpa keliru di hari LAMPAU
 * adalah menembak basis data langsung: tanpa alasan, tanpa pelaku, tanpa jejak.
 * Koreksi cuti sudah punya layarnya sendiri sejak lama; kehadiran belum.
 *
 * Yang TIDAK dilakukan di sini — sengaja:
 *   • Tidak menulis ke `att_day`. Itu tabel turunan; lihat App\Services\KoreksiAbsen.
 *   • Tidak menyentuh tanggal di periode gaji yang sudah DITUTUP. Angkanya sudah
 *     dibayarkan; koreksi di sana ditolak dengan menyebut nama periodenya.
 *   • Tidak menghapus koreksi. Membatalkan = mencabut, dan cabutannya ikut tercatat.
 *
 * Guard: sama dengan Kehadiran (canSeeAllBranches) — yang boleh melihat rekap
 * seluruh cabang adalah yang boleh membetulkannya.
 */
class KoreksiAbsenController extends Controller
{
    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Koreksi absen hanya untuk Finance / Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        // Buku besar, terbaru di atas. Dibatasi supaya halaman tetap ringan;
        // riwayat penuh satu orang dibaca lewat penyaring di bawah.
        $q = AttDayKoreksi::query()->orderByDesc('id');
        if ($request->filled('user_id')) $q->where('user_id', $request->input('user_id'));

        $riwayat = $q->limit(200)->get();

        $nama = DB::table('users')
            ->whereIn('id', $riwayat->pluck('user_id')->merge($riwayat->pluck('dibuat_oleh'))->filter()->unique())
            ->pluck('nama', 'id');

        // Roster = orang yang PUNYA aturan kerja. Orang tanpa aturan tak punya
        // baris att_day sama sekali, jadi tak ada apa pun untuk dikoreksi —
        // menawarkannya di dropdown cuma melahirkan koreksi yang diam-diam
        // tak berefek. Yang mereka butuhkan adalah aturan kerja, bukan koreksi.
        $orang = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->where('u.status', 1)
            ->orderBy('u.nama')
            ->distinct()
            ->get(['u.id', 'u.nama']);

        // Hanya yang boleh dipilih. 'cuti' tak ditawarkan — jatahnya punya buku
        // besar sendiri; alasannya dijelaskan di layar, bukan cuma dihilangkan.
        $status = KoreksiAbsen::STATUS_PILIHAN;

        return view('absensi.koreksi', compact('riwayat', 'nama', 'orang', 'status'));
    }

    public function simpan(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'work_date' => 'required|date',
            // STATUS_PILIHAN, bukan STATUS: 'cuti' sengaja di luar jangkauan
            // layar ini (punya buku besar sendiri). Ditolak di SERVER, bukan cuma
            // disembunyikan dari dropdown — form buatan sendiri tetap bisa mengirim.
            'status'    => 'required|in:' . implode(',', KoreksiAbsen::STATUS_PILIHAN),
            // Alasan WAJIB dan tak boleh sekadar "koreksi": enam bulan lagi
            // yang tersisa dari keputusan ini cuma kalimat ini.
            'alasan'    => 'required|string|min:8|max:500',
        ]);

        // Validasi di atas menolak 'cuti' dengan pesan generik Laravel yang tak
        // menerangkan apa pun. Ditangkap lebih awal supaya penolakannya menyebut
        // ALASAN dan JALAN KELUARNYA — pesan 'tombol mati bilang kenapa'.
        if ($larangan = KoreksiAbsen::alasanTerlarang((string) $request->input('status'))) {
            return back()->withInput()->with('error', $larangan);
        }

        $tanggal = Carbon::parse($data['work_date'])->toDateString();
        $namaOrang = DB::table('users')->where('id', $data['user_id'])->value('nama');

        if ($tanggal > Carbon::today()->toDateString()) {
            return back()->withInput()->with('error',
                'Tanggal belum terjadi — tak ada yang bisa dikoreksi di masa depan.');
        }

        if (KoreksiAbsen::terkunci($data['user_id'], $tanggal)) {
            $periode = KoreksiAbsen::periodePengunci($data['user_id'], $tanggal);

            return back()->withInput()->with('error',
                'Ditolak: ' . $tanggal . ' ada di periode gaji yang sudah DITUTUP'
                . ($periode ? ' (' . $periode . ')' : '') . '. '
                . 'Angka di rentang itu sudah jadi dasar gaji yang dibayarkan, jadi tak boleh berubah diam-diam. '
                . 'Kalau memang harus dikoreksi, periodenya dibuka dulu oleh Finance — supaya perubahannya terlihat.');
        }

        $sekarang = KoreksiAbsen::statusSekarang($data['user_id'], $tanggal);
        if ($sekarang === null) {
            return back()->withInput()->with('error',
                $namaOrang . ' tidak punya baris absensi di ' . $tanggal . ' — biasanya karena aturan '
                . 'kerjanya belum berlaku pada tanggal itu. Betulkan aturan kerjanya dulu, lalu jalankan '
                . 'rekonsiliasi; koreksi tak bisa membuat hari yang belum pernah ada.');
        }

        if ($sekarang === $data['status']) {
            return back()->withInput()->with('error',
                'Status ' . $namaOrang . ' pada ' . $tanggal . ' memang sudah "' . $sekarang . '".');
        }

        $k = KoreksiAbsen::simpan(
            $data['user_id'], $tanggal, $data['status'], trim($data['alasan']),
            (string) session('id') ?: null, (string) session('name') ?: null
        );

        // Peringatan ikut MASUK KE JEJAK, bukan cuma ditempel di layar sesaat:
        // yang membaca audit enam bulan lagi perlu tahu bahwa hari ini dulunya
        // cuti dan jatahnya tidak ikut dikembalikan.
        $peringatan = KoreksiAbsen::peringatanStatusLama($sekarang);

        AuditLog::record('update',
            "Koreksi absen {$namaOrang} {$tanggal}: {$sekarang} → {$data['status']} — " . trim($data['alasan'])
            . ($peringatan ? ' [jatah cuti TIDAK dikembalikan otomatis]' : ''),
            $k, ['status' => $sekarang]
        );

        // notif.blade hanya merender SATU pesan (if/elseif), jadi peringatannya
        // disatukan ke pesan sukses — kalau di-flash terpisah, ia tak pernah tampil.
        return redirect()->route('absensi.koreksi')->with('success',
            "Koreksi tersimpan — {$namaOrang}, {$tanggal}: {$sekarang} → {$data['status']}."
            . ($peringatan ? ' ' . $peringatan : ''));
    }

    /** Cabut koreksi: hari itu kembali ke hasil rekonsiliasi apa adanya. */
    public function cabut(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate(['alasan' => 'nullable|string|max:500']);
        $k = AttDayKoreksi::findOrFail($id);

        if ($k->dicabut_at) {
            return back()->with('error', 'Koreksi ini sudah dicabut sebelumnya.');
        }

        $tanggal = $k->work_date->toDateString();
        if (KoreksiAbsen::terkunci($k->user_id, $tanggal)) {
            $periode = KoreksiAbsen::periodePengunci($k->user_id, $tanggal);

            return back()->with('error',
                'Ditolak: ' . $tanggal . ' ada di periode gaji yang sudah DITUTUP'
                . ($periode ? ' (' . $periode . ')' : '') . '. '
                . 'Mencabut koreksi di sana akan menggeser angka yang sudah dibayarkan.');
        }

        $namaOrang = DB::table('users')->where('id', $k->user_id)->value('nama');

        KoreksiAbsen::cabut($k, (string) session('id') ?: null, (string) session('name') ?: null,
            trim((string) ($data['alasan'] ?? '')) ?: null);

        AuditLog::record('update',
            "Koreksi absen DICABUT — {$namaOrang} {$tanggal} (sebelumnya dipaksa \"{$k->status}\")", $k);

        return redirect()->route('absensi.koreksi')->with('success',
            "Koreksi dicabut — {$namaOrang}, {$tanggal} kembali ke hasil rekonsiliasi.");
    }
}
