<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
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
