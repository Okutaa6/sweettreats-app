<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        // Segmen 'auditlog' ada di daftar $skipRbac AuthGuard — RBAC dilewati
        // sepenuhnya — sementara controller ini tak punya pemeriksaan apa pun.
        // Akibatnya siapa pun yang login (termasuk staf cabang) bisa membuka
        // jejak aktivitas SELURUH user, memfilternya per orang untuk mengintai
        // atasan, plus melihat daftar lengkap user beserta email. Ironisnya
        // justru audit log — kontrol deteksi — yang paling terbuka.
        //
        // Halaman ini tak punya entri menu di mana pun (murni URL), jadi tak ada
        // "pemilik" alami. Dibatasi ke admin tingkat sistem + IT (keputusan
        // Pusat Akun 2026-07-24: "namanya juga IT" — audit log bagian kerjanya).
        // Selaras dengan grant Pusat Akun: semua role yang mengelola user
        // (4 admin + IT) berhak melihat jejaknya.
        abort_unless(
            canManagePageAccess() || in_array(session('group_name'), ['Head IT', 'IT', 'Admin', 'Admin Backup'], true),
            403, 'Riwayat aktivitas hanya untuk admin sistem / IT.'
        );

        $query = AuditLog::query()->orderByDesc('created_at');

        if (request('user_id')) {
            $query->where('user_id', request('user_id'));
        }
        if (request('action')) {
            $query->where('action', request('action'));
        }
        if (request('model_type')) {
            $query->where('model_type', request('model_type'));
        }
        if (request('date_from')) {
            $query->whereDate('created_at', '>=', request('date_from'));
        }
        if (request('date_to')) {
            $query->whereDate('created_at', '<=', request('date_to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $users = \App\Models\Users::orderBy('nama')->get();
        $actions = ['create', 'update', 'delete', 'login', 'logout', 'status_change'];

        return view('set.auditlog', compact('logs', 'users', 'actions'));
    }
}
