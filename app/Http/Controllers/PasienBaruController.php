<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Form publik "Pasien Baru" per cabang.
 *
 * Nomor Cekat AI disebar di sosmed → calon pasien diarahkan ke link ringan
 * /pasien-baru/{slug-cabang}. Isian minimal (nama, HP, tgl lahir, JK,
 * keluhan). Submit menyimpan sebuah LEAD di raw_customer
 * (source_app='pasien_baru', lead_status='baru'). Cabang otomatis dari slug.
 *
 * Sales lalu follow-up via WA di tab "Pasien Baru" pada halaman reservasi
 * (ReservationController), lalu klik "Tambah Reservasi" untuk membuat jadwal.
 */
class PasienBaruController extends Controller
{
    use ResolvesPatientContact;

    /** GET /pasien-baru/{branch:slug} — tampilkan form (cabang terkunci). */
    public function show(RawBranch $branch)
    {
        return view('pasien-baru.form', compact('branch'));
    }

    /** Hubungan pasien dgn pemilik nomor — nilai yang boleh + defaultnya. */
    private const RELASI = ['diri_sendiri', 'anak', 'pasangan', 'orang_tua', 'lainnya'];

    /**
     * POST /pasien-baru/{branch:slug} — simpan lead pasien baru.
     *
     * Model 1-nomor-banyak-pasien: satu nomor WhatsApp bisa mendaftarkan
     * beberapa pasien sekaligus (mis. orang tua + anak-anaknya). Semua pasien
     * menempel ke satu raw_contact (jangkar nomor) → banyak raw_customer.
     */
    public function store(RawBranch $branch)
    {
        // Anti-spam: field umpan "website" tak terlihat manusia, dan submit
        // < 3 detik setelah halaman dirender hampir pasti bot. Dua-duanya
        // dibalas seolah sukses supaya bot tidak belajar apa yang menahannya.
        if (filled(request('website')) || (time() - (int) request('ts')) < 3) {
            return $this->thanks($branch);
        }

        // Normalisasi DULU baru validasi. Link ini disebar via WhatsApp, jadi
        // orang menempel "+62812…", "62812…", atau "0812-3456-7890" —
        // Phone::normalize() sanggup menangani semuanya, sementara regex di
        // bawah hanya penjaga akhir untuk input yang benar-benar bukan nomor.
        request()->merge(['phone' => Phone::normalize(request('phone')) ?? request('phone')]);

        $data = request()->validate([
            'phone'                   => 'required|regex:/^0[0-9]{9,14}$/',
            'patients'                => 'required|array|min:1|max:10',
            'patients.*.name'         => 'required|string|max:255',
            'patients.*.relasi'       => 'nullable|in:' . implode(',', self::RELASI),
            'patients.*.jenis_kelamin'=> 'nullable|in:L,P',
            'patients.*.tanggal_lahir'=> 'nullable|date|before:today',
            'patients.*.keluhan'      => 'required|string|max:2000',
        ], [
            'phone.required'             => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'                => 'Nomor WhatsApp tidak valid.',
            'patients.required'          => 'Tambahkan minimal satu data pasien.',
            'patients.min'               => 'Tambahkan minimal satu data pasien.',
            'patients.*.name.required'   => 'Nama pasien wajib diisi.',
            'patients.*.keluhan.required'=> 'Keluhan gigi/mulut wajib diisi.',
        ]);

        $phone    = $data['phone'];
        $patients = $data['patients'];

        DB::transaction(function () use ($patients, $phone, $branch) {
            // Satu jangkar nomor untuk semua pasien di submit ini.
            $contact = $this->resolveContact($phone, $patients[0]['name'], 'landing', 'landing');

            foreach ($patients as $p) {
                $name = trim($p['name']);
                if ($name === '') continue;

                // Dedup PER pasien: refresh / submit berulang dari nomor + nama
                // yang sama tidak boleh membuat kartu ganda di tab sales.
                // Cocokkan lead aktif (belum batal, belum punya reservasi) di
                // cabang yang sama dengan nama yang sama (case-insensitive).
                $existing = RawCustomer::where('source_app', RawCustomer::SOURCE_PASIEN_BARU)
                    ->where('raw_branch_id', $branch->id)
                    ->whereIn('phone', $this->phoneVariants($phone))
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                    ->where(function ($q) {
                        $q->whereNull('lead_status')->orWhere('lead_status', '!=', 'batal');
                    })
                    ->whereDoesntHave('reservations')
                    ->orderByDesc('tanggal_daftar')
                    ->first();

                if ($existing) {
                    // Hanya timpa field yang benar-benar diisi di submit terakhir.
                    // lead_status & tanggal_daftar sengaja TIDAK disentuh: itu
                    // jejak sales dan urutan antrian follow-up.
                    $existing->fill(array_filter([
                        'name'           => $name,
                        'phone'          => $phone,
                        'relasi'         => $p['relasi'] ?? null,
                        'jenis_kelamin'  => $p['jenis_kelamin'] ?? null,
                        'tanggal_lahir'  => $p['tanggal_lahir'] ?? null,
                        'catatan'        => $p['keluhan'] ?? null,
                        'raw_contact_id' => $existing->raw_contact_id ?: $contact->id,
                    ], fn ($v) => filled($v)));
                    $existing->save();

                    continue;
                }

                RawCustomer::create([
                    'raw_contact_id'   => $contact->id,
                    'relasi'           => $p['relasi'] ?? 'diri_sendiri',
                    'name'             => $name,
                    'phone'            => $phone,
                    'jenis_kelamin'    => $p['jenis_kelamin'] ?? null,
                    'tanggal_lahir'    => $p['tanggal_lahir'] ?? null,
                    'catatan'          => $p['keluhan'] ?? null,
                    'tanggal_daftar'   => now()->toDateString(),
                    'pasien_baru_lama' => 'baru',
                    'raw_branch_id'    => $branch->id,
                    'source_app'       => RawCustomer::SOURCE_PASIEN_BARU,
                    'channel'          => 'landing',
                    'lead_status'      => 'baru',
                ]);
            }
        });

        return $this->thanks($branch);
    }

    /** Redirect PRG + pesan terima kasih (dipakai jalur sukses & jalur honeypot). */
    private function thanks(RawBranch $branch)
    {
        return redirect()
            ->route('pasien-baru.show', $branch->slug)
            ->with('success', 'Terima kasih! Data kamu sudah kami terima. '
                . 'Tim kami akan menghubungi via WhatsApp untuk mengatur jadwal. 😊');
    }
}
