<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PettyCashTransaction
 *
 * Ledger buku kas kecil:
 *   - direction = 'in'  → cash in  (top up atau penerimaan lain)
 *   - direction = 'out' → cash out (pengeluaran)
 *   - is_topup = 1      → flag visual baris top up saldo
 *
 * Saldo berjalan TIDAK disimpan — dihitung saat render (lihat
 * PettyCashController::buildRows()).
 *
 * Soft-delete manual lewat kolom deleted_at supaya jejak audit tetap ada.
 */
class PettyCashTransaction extends Model
{
    protected $table = 'petty_cash_transactions';

    public const DIR_IN  = 'in';
    public const DIR_OUT = 'out';

    /**
     * Batas atas nominal 1 transaksi (top up / pengeluaran / transfer /
     * pengajuan). Menahan salah ketik ekstrem agar memicu pesan validasi,
     * bukan error. Dipakai bersama oleh PettyCashController & TopupRequestController.
     */
    public const MAX_AMOUNT = 50000000;

    /**
     * Jendela "kiriman ganda": baris IDENTIK (pembuat + nominal + keterangan +
     * tanggal) yang datang dalam rentang ini sesudah yang terakhir dianggap
     * klik ganda, bukan transaksi kedua.
     *
     * Diagnosis 6 Sep 2026: dua kembaran nyata berjarak 0 dan 4 detik — klik
     * ganda, dan "halaman belum berpindah, klik lagi" (halaman tujuan berat,
     * 2–4 detik). 10 detik [USULAN HUB] menutup keduanya dengan longgar tanpa
     * menyentuh dua pengeluaran sungguhan yang memang serupa: kembar PERSIS
     * ditolak, MIRIP tetap boleh — pola yang sama dengan pagar lembur.
     */
    public const JENDELA_KEMBAR_DETIK = 10;

    /**
     * Ada baris identik yang baru saja tersimpan oleh pembuat yang sama?
     *
     * Empat kunci, semuanya harus sama persis. Yang berbeda satu saja (mis.
     * keterangan "gojek" vs "gojek pp") bukan kembar — dua pengeluaran serupa
     * di hari yang sama itu wajar, dan pagar yang menolak orang jujur lebih
     * mahal daripada yang lolos sesekali.
     */
    public static function kembarBaruSaja(?string $createdBy, $amount, string $description, string $trxDate, ?int $jendela = null): bool
    {
        if ($createdBy === null || $createdBy === '') return false;

        return static::where('created_by', $createdBy)
            ->where('amount', $amount)
            ->where('description', $description)
            ->where('trx_date', $trxDate)
            ->where('created_at', '>=', now()->subSeconds($jendela ?? self::JENDELA_KEMBAR_DETIK))
            ->exists();
    }
    /**
     * Batas waktu (jam) EDIT & HAPUS transaksi untuk USER BIASA sejak
     * created_at (kebijakan audit). Super Admin bebas tanpa batas — lihat
     * PettyCashController::abortIfBeyondEditWindow() & _table_rows.blade.php.
     */
    public const EDIT_WINDOW_HOURS = 8;

    /**
     * Kategori pengeluaran (hanya relevan saat direction='out' & is_topup=0 & is_transfer=0).
     * NULL untuk top up / transfer / data lama.
     *
     * CATATAN: ini sekarang cuma FALLBACK seed. Sumber kebenaran = tabel
     * `petty_cash_categories` (dikelola dari halaman Kelola Wallet). Dipakai
     * lagi hanya kalau tabel belum ada / kosong (mis. DB lokal belum di-apply
     * SQL 2026_08_14_petty_cash_categories.sql). Gunakan categoryMap() /
     * activeCategoryOptions() untuk baca kategori, JANGAN const ini langsung.
     */
    public const CATEGORIES = [
        'makan'       => 'Konsumsi',
        'transport'   => 'Transport',
        'bensin'      => 'Bensin',
        'operasional' => 'Operasional',
        'server'      => 'Server',
        'prv'         => 'PRV',
    ];

    /** Cache per-request untuk seluruh baris kategori. */
    private static $categoryRowsCache = null;

    /**
     * Ambil semua baris kategori dari DB (ordered), dengan fallback aman.
     * Return Collection|null (null = tabel belum ada / kosong → pakai const).
     */
    private static function categoryRows()
    {
        if (self::$categoryRowsCache !== null) {
            return self::$categoryRowsCache ?: null;
        }
        try {
            $rows = PettyCashCategory::orderBy('sort_order')->orderBy('label')->get();
            self::$categoryRowsCache = $rows->isNotEmpty() ? $rows : false;
        } catch (\Throwable $e) {
            // Tabel belum di-apply / DB error → fallback ke const.
            self::$categoryRowsCache = false;
        }
        return self::$categoryRowsCache ?: null;
    }

    /**
     * Peta SEMUA kategori [key => label] (aktif + nonaktif). Dipakai untuk
     * MENAMPILKAN label (badge tabel, export, dashboard) supaya transaksi lama
     * yang kategorinya sudah dinonaktifkan tetap tampil labelnya.
     */
    public static function categoryMap(): array
    {
        $rows = self::categoryRows();
        return $rows ? $rows->pluck('label', 'key')->all() : self::CATEGORIES;
    }

    /**
     * Peta kategori AKTIF saja [key => label]. Dipakai untuk pilihan input
     * transaksi baru (dropdown pengeluaran).
     */
    public static function activeCategoryOptions(): array
    {
        $rows = self::categoryRows();
        return $rows
            ? $rows->where('is_active', true)->pluck('label', 'key')->all()
            : self::CATEGORIES;
    }

    /** Key kategori aktif — untuk rule validasi transaksi baru. */
    public static function activeCategoryKeys(): array
    {
        return array_keys(self::activeCategoryOptions());
    }

    protected $fillable = [
        'period_id',
        'trx_date',
        'description',
        'category',
        'direction',
        'amount',
        'fund_source_id',
        'is_topup',
        'is_transfer',
        'ref_no',
        'attachment_path',
        'created_by',
        'updated_by',
        'edited_at',
        'deleted_by',
    ];

    protected $casts = [
        'period_id'      => 'integer',
        'trx_date'       => 'date',
        'amount'         => 'decimal:2',
        'fund_source_id' => 'integer',
        'is_topup'       => 'boolean',
        'is_transfer'    => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'edited_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(PettyCashPeriod::class, 'period_id', 'id');
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id', 'id');
    }

    /* ───────── Scopes ───────── */

    public function scopeActive($q)
    {
        return $q->whereNull('deleted_at');
    }

    public function scopeForPeriod($q, int $periodId)
    {
        return $q->where('period_id', $periodId);
    }

    /* ───────── Helpers ───────── */

    /**
     * Net (sum cash_in - cash_out) untuk 1 periode. Dipakai saat
     * carry-forward saldo (ensurePeriod).
     */
    public static function netForPeriod(int $periodId, $since = null): float
    {
        $rows = static::active()->forPeriod($periodId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get(['direction', 'amount']);
        $net = 0.0;
        foreach ($rows as $r) {
            $amt = (float) $r->amount;
            $net += $r->direction === self::DIR_IN ? $amt : -$amt;
        }
        return $net;
    }
}
