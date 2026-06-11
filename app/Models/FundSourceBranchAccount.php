<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FundSourceBranchAccount
 *
 * Nomor rekening (bank) / telepon (e-wallet) untuk 1 sumber dana di 1
 * cabang. Bank cabang A bisa pakai rekening berbeda dari cabang B, jadi
 * dipisah dari master fund_sources.
 *
 * Diisi/diupdate dari form Top up Saldo Petty Cash — bersifat opsional.
 * UNIQUE (raw_branch_id, fund_source_id) → 1 nomor per pasangan.
 */
class FundSourceBranchAccount extends Model
{
    protected $table = 'fund_source_branch_accounts';

    protected $fillable = [
        'raw_branch_id',
        'fund_source_id',
        'account_number',
    ];

    protected $casts = [
        'fund_source_id' => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Upsert nomor untuk pasangan (branch, source). Kalau string kosong
     * → tidak menulis apa-apa (preserve existing). Gunakan
     * deleteForPair() untuk menghapus eksplisit.
     */
    public static function upsertFor(string $branchId, int $sourceId, ?string $number): void
    {
        $number = $number !== null ? trim($number) : '';
        if ($number === '') return;
        static::updateOrCreate(
            ['raw_branch_id' => $branchId, 'fund_source_id' => $sourceId],
            ['account_number' => $number],
        );
    }

    /**
     * Lookup map: source_id → account_number untuk 1 branch.
     * Dipakai saat render breakdown card supaya tidak query per-row.
     */
    public static function mapForBranch(?string $branchId): array
    {
        if (! $branchId) return [];
        return static::where('raw_branch_id', $branchId)
            ->pluck('account_number', 'fund_source_id')
            ->toArray();
    }
}
