<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\RawBranch;

class ComplaintController extends Controller
{
    private const ROLE_SALES         = 'Sales';
    private const ROLE_SUPER_ADMIN   = 'Super Admin';
    private const ROLE_ADMIN_GENERAL = 'Admin General';

    private const ADMIN_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN_GENERAL,
    ];

    private function isSales(): bool
    {
        return session('group_name') === self::ROLE_SALES;
    }

    private function isAdmin(): bool
    {
        return in_array(session('group_name'), self::ADMIN_ROLES, true);
    }

    public function index()
    {
        $isAdmin = $this->isAdmin();
        $isSales = $this->isSales();

        if (!$isAdmin && !$isSales) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        $perPage       = $this->perPage;
        $filterBranch  = request('branch_id');
        $filterStatus  = request('status', 'all');

        $query = Complaint::with(['branch', 'sender'])->orderByDesc('created_at');

        // Sales hanya boleh lihat keluhan miliknya sendiri
        if ($isSales) {
            $query->where('sender_user_id', session('id'));
        }

        if ($isAdmin && $filterBranch) {
            $query->where('raw_branch_id', $filterBranch);
        }

        if (in_array($filterStatus, [Complaint::STATUS_NEW, Complaint::STATUS_RECEIVED, Complaint::STATUS_HANDLED], true)) {
            $query->where('status', $filterStatus);
        }

        $complaints = $query->paginate($perPage)->withQueryString();

        $branches = $isAdmin ? RawBranch::orderBy('name')->get() : collect();

        // Counts untuk badge tab
        $baseCount = Complaint::query();
        if ($isSales) {
            $baseCount->where('sender_user_id', session('id'));
        }
        $counts = [
            'all'      => (clone $baseCount)->count(),
            'new'      => (clone $baseCount)->where('status', Complaint::STATUS_NEW)->count(),
            'received' => (clone $baseCount)->where('status', Complaint::STATUS_RECEIVED)->count(),
            'handled'  => (clone $baseCount)->where('status', Complaint::STATUS_HANDLED)->count(),
        ];

        return view('complaint.list', [
            'complaints'    => $complaints,
            'branches'      => $branches,
            'filterBranch'  => $filterBranch,
            'filterStatus'  => $filterStatus,
            'counts'        => $counts,
            'isAdmin'       => $isAdmin,
            'isSales'       => $isSales,
        ]);
    }

    public function store()
    {
        if (!$this->isSales()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya role Sales yang dapat mengirim keluhan.',
            ], 403);
        }

        request()->validate([
            'message' => 'required|string|min:5|max:2000',
        ], [
            'message.required' => 'Pesan keluhan wajib diisi.',
            'message.min'      => 'Keluhan terlalu pendek (minimal 5 karakter).',
            'message.max'      => 'Keluhan terlalu panjang (maksimal 2000 karakter).',
        ]);

        Complaint::create([
            'sender_user_id' => session('id'),
            'sender_name'    => session('name'),
            'raw_branch_id'  => session('raw_branch_id'),
            'message'        => trim(request('message')),
            'status'         => Complaint::STATUS_NEW,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Keluhan kamu sudah terkirim ke Admin. Terima kasih.',
        ]);
    }

    public function advanceStatus($id)
    {
        if (!$this->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        $complaint = Complaint::findOrFail($id);
        $next      = Complaint::nextStatus($complaint->status);

        if (!$next) {
            return redirect()->route('complaint.index')
                ->with('error', 'Keluhan ini sudah selesai ditangani.');
        }

        $complaint->update(['status' => $next]);

        $msg = $next === Complaint::STATUS_RECEIVED
            ? 'Keluhan ditandai Diterima.'
            : 'Keluhan ditandai Sudah Ditangani.';

        return redirect()->route('complaint.index', request()->only('status', 'branch_id', 'page'))
            ->with('success', $msg);
    }

    public function reply($id)
    {
        if (!$this->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        request()->validate([
            'reply' => 'nullable|string|max:2000',
        ], [
            'reply.max' => 'Umpan balik terlalu panjang (maksimal 2000 karakter).',
        ]);

        $complaint   = Complaint::findOrFail($id);
        $replyValue  = trim((string) request('reply'));
        $complaint->update(['reply' => $replyValue !== '' ? $replyValue : null]);

        return redirect()->route('complaint.index', request()->only('status', 'branch_id', 'page'))
            ->with('success', 'Umpan balik berhasil disimpan.');
    }

    public function destroy($id)
    {
        if (!$this->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        Complaint::findOrFail($id)->delete();

        return redirect()->route('complaint.index')->with('success', 'Keluhan berhasil dihapus.');
    }
}
