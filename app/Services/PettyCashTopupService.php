<?php

namespace App\Services;

use App\Models\FundSource;
use App\Models\PettyCashPeriod;
use App\Models\PettyCashTransaction;

/**
 * PettyCashTopupService
 *
 * Single source of truth untuk membuat baris top-up petty cash.
 *
 * Satu-satunya pemanggil: TopupRequestController::approve() — alur pengajuan
 * Sales lalu approval Finance. Jalur top up manual (PettyCashController::topup)
 * sudah dihapus; semua uang baru wajib lewat approval.
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
        ?string $trxDate = null,
        ?string $refNo = null
    ): PettyCashTransaction {
        // Dulu ada mode kedua (is_topup=0) untuk "Setor Cash Langsung" — uang
        // cash masuk tanpa lewat Finance. Fitur itu dihapus: semua uang baru
        // wajib lewat pengajuan top up. Hardcode is_topup=1 supaya baris
        // cash-in non-topup mustahil dibuat, bukan sekadar disembunyikan UI.
        return PettyCashTransaction::create([
            'period_id'       => $period->id,
            'trx_date'        => $trxDate ?: date('Y-m-d'),
            'description'     => $description !== null && $description !== '' ? $description : 'Top up kasir',
            'direction'       => PettyCashTransaction::DIR_IN,
            'amount'          => $amount,
            'fund_source_id'  => $source->id,
            'is_topup'        => 1,
            'is_transfer'     => 0,
            'ref_no'          => $refNo,
            'attachment_path' => $attachment,
            'created_by'      => $createdBy,
        ]);
    }
}
