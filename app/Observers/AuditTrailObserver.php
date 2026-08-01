<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer audit untuk model sensitif yang TIDAK punya pencatatan manual di
 * controller-nya (Pusat Akun Fase D): GroupPath (role dikelola dari halaman
 * hierarki Reza, tanpa audit) dan AttFlag (keputusan SPV di Pengawasan).
 *
 * Model lain (Users, AccessPath, GroupAccess, UserDevice) SENGAJA tidak
 * di-observe — controller-nya sudah menulis audit manual dengan deskripsi
 * yang lebih bermakna; observer di sana = dobel entri.
 *
 * Catatan: event Eloquent TIDAK menyala untuk update/delete massal
 * (Model::where(...)->update()) — jalur itu wajib mencatat manual sendiri
 * (mis. grup-selesai di PengawasanController).
 */
class AuditTrailObserver
{
    /** Perubahan yang tak layak dicatat sendirian (derau cascade/stempel). */
    private const ABAIKAN = ['updated_at', 'created_at', 'level'];

    public function created(Model $model): void
    {
        $this->aman(fn () => AuditLog::record('create', $this->label($model, 'dibuat'), $model));
    }

    public function updated(Model $model): void
    {
        $berarti = array_diff(array_keys($model->getChanges()), self::ABAIKAN);
        if (! $berarti) return; // mis. cascade level saat reparent subtree

        $this->aman(fn () => AuditLog::record(
            'update',
            $this->label($model, 'diubah') . ' (' . implode(', ', $berarti) . ')',
            $model,
            array_intersect_key($model->getOriginal(), array_flip($berarti)),
            $berarti
        ));
    }

    public function deleted(Model $model): void
    {
        $this->aman(fn () => AuditLog::record('delete', $this->label($model, 'dihapus'), $model, $model->getOriginal()));
    }

    /**
     * Audit gagal ≠ aksi bisnis gagal. Observer ini nyala di jalur ramai
     * (absen karyawan menaikkan flag DI DALAM transaksi; cron sweep) — kalau
     * insert audit_log error (tabel belum ada di env baru, DB sesaat down),
     * jangan sampai absennya ikut rollback / sweep berhenti. Cukup report().
     */
    private function aman(callable $tulis): void
    {
        try { $tulis(); } catch (\Throwable $e) { report($e); }
    }

    /** Deskripsi awam per jenis model. */
    private function label(Model $model, string $kata): string
    {
        return match (class_basename($model)) {
            'GroupPath' => 'Role ' . $kata . ': ' . ($model->nama ?? $model->getKey()),
            'AttFlag'   => 'Flag absensi ' . $kata . ': ' . ($model->jenis ?? '?')
                . ' ' . substr((string) ($model->work_date ?? ''), 0, 10)
                . ' (status: ' . ($model->status ?? '?') . ')',
            default     => class_basename($model) . ' ' . $kata . ': ' . $model->getKey(),
        };
    }
}
