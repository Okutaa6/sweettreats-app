<?php

namespace App\Services;

use App\Models\FundSource;
use App\Models\FundSourceBranchAccount;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;

/**
 * PettyCashTopupService
 *
 * Single source of truth untuk membuat baris top-up petty cash.
 *
 * Dipanggil dari 2 tempat:
 *   - PettyCashController::topup() — manual top up oleh Finance/Sales
 *     yg punya akses langsung (legacy + ad-hoc).
 *   - TopupRequestController::approve() — approval flow Finance.
 *
 * Logika konsisten: create row petty_cash_transactions (direction=in,
 * is_topup=$isTopup, is_transfer=0). Tidak menutup periode dan tidak
 * menulis saldo akhir — saldo dihitung saat render.
 */
class PettyCashTopupService
{
    /**
     * Buat 1 row top-up untuk periode aktif.
     *
     * @param  PettyCashPeriod  $period       Periode tujuan (sudah harus tidak closed).
     * @param  FundSource       $source       Wallet yang akan bertambah saldonya.
     * @param  float            $amount       Nominal top-up (Rupiah).
     * @param  string           $description  Deskripsi (default "Top up kasir").
     * @param  string|null      $attachment   Path relatif file bukti.
     * @param  string           $createdBy    UUID user pencatat (session id).
     * @param  bool             $isTopup      true = dihitung di Total Top Up. false = setoran cash netral.
     * @param  string|null      $trxDate      Y-m-d. Default today.
     * @param  string|null      $refNo        Optional referensi (mis. "REQ-{id}").
     * @return PettyCashTransaction
     */
    public function createTopupTransaction(
        PettyCashPeriod $period,
        FundSource $source,
        float $amount,
        ?string $description,
        ?string $attachment,
        string $createdBy,
        bool $isTopup = true,
        ?string $trxDate = null,
        ?string $refNo = null
    ): PettyCashTransaction {
        $defaultLabel = $isTopup ? 'Top up kasir' : 'Setor cash langsung';

        return PettyCashTransaction::create([
            'period_id'       => $period->id,
            'trx_date'        => $trxDate ?: date('Y-m-d'),
            'description'     => $description !== null && $description !== '' ? $description : $defaultLabel,
            'direction'       => PettyCashTransaction::DIR_IN,
            'amount'          => $amount,
            'fund_source_id'  => $source->id,
            'is_topup'        => $isTopup ? 1 : 0,
            'is_transfer'     => 0,
            'ref_no'          => $refNo,
            'attachment_path' => $attachment,
            'created_by'      => $createdBy,
        ]);
    }

    /**
     * Simpan nomor rekening / telepon untuk pasangan (cabang, source)
     * kalau user isi field tsb di form top-up & source-nya Bank/E-Wallet.
     * Cash di-skip karena tidak punya nomor.
     */
    public function maybeUpsertAccount(
        PettyCashPeriod $period,
        FundSource $source,
        ?string $accountNumber
    ): void {
        $acct = trim((string) $accountNumber);
        if ($acct === '' || ! $period->raw_branch_id) return;
        if (! in_array($source->category, [FundSource::CAT_BANK, FundSource::CAT_EWALLET], true)) return;

        FundSourceBranchAccount::upsertFor(
            (string) $period->raw_branch_id,
            (int) $source->id,
            $acct,
        );
    }
}
