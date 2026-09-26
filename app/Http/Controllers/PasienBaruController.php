<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawPromo;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Form publik "Pasien Baru" per cabang.
 *
 * Nomor Cekat AI disebar di sosmed → calon pasien diarahkan ke link ringan
 * /pasien-baru/{slug-cabang}. Isian minimal (NIK, nama, HP, tgl lahir, JK,
 * keluhan). NIK wajib 16 digit — dipakai sebagai identitas pasien di Satu
 * Sehat, jadi lead tanpa NIK tidak ada gunanya. Submit menyimpan LEAD di raw_customer
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

        // NIK: buang dulu spasi/titik/tanda hubung. NIK yang ditempel dari KTP
        // digital atau foto sering berformat "3174 0101 0101 0001" — kalau
        // tidak dibersihkan, isian yang sebenarnya BENAR ditolak "harus 16
        // digit". Sesudah dibersihkan, aturan 16 digit di bawah baru adil.
        $patientsIn = request('patients');
        if (is_array($patientsIn)) {
            foreach ($patientsIn as $i => $p) {
                if (is_array($p) && isset($p['nik'])) {
                    $patientsIn[$i]['nik'] = preg_replace('/[^0-9]/', '', (string) $p['nik']);
                }
            }
            request()->merge(['patients' => $patientsIn]);
        }

        $data = request()->validate([
            'phone'                   => 'required|regex:/^0[0-9]{9,14}$/',
            'patients'                => 'required|array|min:1|max:10',
            'patients.*.nik'          => 'required|digits:16',
            'patients.*.name'         => 'required|string|max:255',
            'patients.*.relasi'       => 'nullable|in:' . implode(',', self::RELASI),
            'patients.*.jenis_kelamin'=> 'nullable|in:L,P',
            'patients.*.tanggal_lahir'=> 'nullable|date|before:today',
            'patients.*.keluhan'      => 'required|string|max:2000',
            'promo_code'              => 'nullable|string|max:50',
        ], [
            'phone.required'             => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'                => 'Nomor WhatsApp tidak valid.',
            'patients.required'          => 'Tambahkan minimal satu data pasien.',
            'patients.min'               => 'Tambahkan minimal satu data pasien.',
            'patients.*.nik.required'    => 'NIK pasien wajib diisi (16 digit).',
            'patients.*.nik.digits'      => 'NIK harus tepat 16 digit angka.',
            'patients.*.name.required'   => 'Nama pasien wajib diisi.',
            'patients.*.keluhan.required'=> 'Keluhan gigi/mulut wajib diisi.',
        ]);

        $phone    = $data['phone'];
        $patients = $data['patients'];

        // Kode promo opsional: SATU kode untuk semua pasien di submit ini,
        // sama seperti form reservasi publik. Kodenya dicocokkan ULANG di sini
        // — tombol "Cek" di halaman cuma umpan balik, dan id apa pun yang
        // dikirim dari form tidak dipercaya. Kode yang tidak dikenal atau
        // sudah kedaluwarsa diabaikan tanpa menggagalkan submit: kehilangan
        // satu lead lebih mahal daripada kehilangan satu kode promo.
        $promoId = $this->resolvePromoId($data['promo_code'] ?? null);

        DB::transaction(function () use ($patients, $phone, $branch, $promoId) {
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
                    // `raw_promo_id` ikut aturan yang sama: submit tanpa kode
                    // (atau dengan kode yang tak dikenal) TIDAK menghapus kode
                    // sah yang sudah tertangkap di submit sebelumnya.
                    $existing->fill(array_filter([
                        'name'           => $name,
                        'nik'            => $p['nik'] ?? null,
                        'phone'          => $phone,
                        'relasi'         => $p['relasi'] ?? null,
                        'jenis_kelamin'  => $p['jenis_kelamin'] ?? null,
                        'tanggal_lahir'  => $p['tanggal_lahir'] ?? null,
                        'catatan'        => $p['keluhan'] ?? null,
                        'raw_promo_id'   => $promoId,
                        'raw_contact_id' => $existing->raw_contact_id ?: $contact->id,
                    ], fn ($v) => filled($v)));
                    $existing->save();

                    continue;
                }

                RawCustomer::create([
                    'raw_contact_id'   => $contact->id,
                    'relasi'           => $p['relasi'] ?? 'diri_sendiri',
                    'name'             => $name,
                    'nik'              => $p['nik'] ?? null,
                    'phone'            => $phone,
                    'jenis_kelamin'    => $p['jenis_kelamin'] ?? null,
                    'tanggal_lahir'    => $p['tanggal_lahir'] ?? null,
                    'catatan'          => $p['keluhan'] ?? null,
                    'tanggal_daftar'   => now()->toDateString(),
                    'pasien_baru_lama' => 'baru',
                    'raw_branch_id'    => $branch->id,
                    'raw_promo_id'     => $promoId,
                    'source_app'       => RawCustomer::SOURCE_PASIEN_BARU,
                    'channel'          => 'landing',
                    'lead_status'      => 'baru',
                ]);
            }
        });

        return $this->thanks($branch);
    }

    /**
     * Cocokkan kode promo yang diketik → id `raw_promo`, atau null.
     *
     * Aturannya sengaja DISAMAKAN PERSIS dengan endpoint `/api/promo/validate`
     * yang dipakai tombol "Cek" di halaman — kalau beda, layar bisa bilang
     * "kedaluwarsa" sementara server diam-diam tetap memakainya. Endpoint itu
     * memakai `valid_until->isPast()`; karena kolomnya di-cast ke tanggal
     * (jam 00:00), promo yang `valid_until`-nya HARI INI sudah dianggap lewat.
     * Jadi syaratnya `valid_until > hari ini`, bukan `>=`. (Perilaku lama
     * tersebut dipertahankan apa adanya, bukan diperbaiki di sini.) Scope
     * referral (behel / non-behel) TIDAK diperiksa di sini — scope diukur dari
     * layanan yang dipilih, sedangkan form ini memang belum memilih layanan.
     * Pemeriksaannya tetap jalan nanti di form reservasi saat Sales membuat
     * jadwal (`validateReferralScopeMatch`), jadi kode yang salah pakai tetap
     * tertahan sebelum jadi komisi.
     */
    private function resolvePromoId(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        return RawPromo::where('promo_code', $code)
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhereDate('valid_until', '>', now()->toDateString());
            })
            ->value('id');
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
