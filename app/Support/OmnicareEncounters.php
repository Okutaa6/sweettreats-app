<?php

namespace App\Support;

/**
 * Helper tunggal untuk meng-group baris omnicare_sales_detail menjadi
 * "encounter" (satu kunjungan = beberapa baris item).
 *
 * Satu sumber kebenaran untuk aturan revenue net: Service Charge & Voucher
 * Tunai (item_type 'V') DIKECUALIKAN dari total — sudah diverifikasi cocok
 * dengan catatan manual cabang. Dipakai OmnicareReconcile, Reconciliation-
 * Controller, dan CustomerController supaya aturannya tidak pernah drift.
 */
class OmnicareEncounters
{
    /**
     * Group baris sales_detail per encounter_id.
     *
     * @param  iterable  $lines  baris dengan kolom: encounter_id, trx_date,
     *         place_id, branch_name, doctor, net_sell_price, item_type,
     *         item_name, qty_sold, payment_status
     * @return array<int,array<string,mixed>>  tiap encounter: encounter_id,
     *         trx_date, place_id, branch_name, doctor, revenue, items[],
     *         payment_status
     */
    public static function group(iterable $lines): array
    {
        $enc = [];

        foreach ($lines as $ln) {
            $key = $ln->encounter_id;
            if (! isset($enc[$key])) {
                $enc[$key] = [
                    'encounter_id' => $ln->encounter_id,
                    'trx_date'     => $ln->trx_date,
                    'place_id'     => $ln->place_id ?? null,
                    'branch_name'  => $ln->branch_name ?? null,
                    'doctor'       => $ln->doctor ?? null,
                    'revenue'      => 0.0,
                    'items'        => [],
                    'payments'     => [],
                ];
            }

            // Net revenue: kecualikan Service Charge & Voucher Tunai (item_type V)
            $excluded = $ln->item_type === 'V'
                || str_starts_with(strtolower((string) $ln->item_name), 'service charge');
            if (! $excluded) {
                $enc[$key]['revenue'] += (float) $ln->net_sell_price;
            }

            $enc[$key]['items'][] = trim((string) $ln->item_name)
                . ' ×' . rtrim(rtrim((string) $ln->qty_sold, '0'), '.');
            $enc[$key]['payments'][$ln->payment_status] = true;
        }

        foreach ($enc as &$e) {
            $statuses = array_map('strtolower', array_keys($e['payments']));
            $e['payment_status'] = (in_array('unpaid', $statuses, true) && count($statuses) > 1)
                ? 'partial'
                : ($statuses[0] ?? 'unpaid');
            unset($e['payments']);
        }
        unset($e);

        return array_values($enc);
    }
}
