<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AccessPath;
use Illuminate\Support\Facades\DB;

class Access extends Controller
{
    /**
     * Pusat Akun → Akses — pohon node akses (hi-fi handoff). LANGKAH 1: read-only
     * (pohon + penanda masalah + panel detail). Mutasi grant/hapus/tambah + guardrail
     * + banner rebuild = langkah berikutnya. CRUD lama (create/edit/dst) dibiarkan.
     *
     * Penanda diturunkan dari data:
     *   yatim            = node tak di-grant ke role mana pun
     *   dobel            = link sama dipakai ≥2 node
     *   parent_ungranted = node punya grant tapi PARENT-nya tidak → gugur dari menu
     */
    public function index()
    {
        $nodes = AccessPath::orderByRaw('COALESCE(urutan, 999)')->orderBy('nama')
            ->get(['id', 'pid', 'nama', 'link', 'icon', 'urutan']);

        // role per node ({id, nama} — id buat tombol cabut)
        $roleByNode = [];
        foreach (DB::table('group_access as ga')
                    ->join('group_path as g', 'g.id', '=', 'ga.group_id')
                    ->select('ga.access_id', 'g.id', 'g.nama')->orderBy('g.nama')->get() as $r) {
            $roleByNode[$r->access_id][] = ['id' => $r->id, 'nama' => $r->nama];
        }

        // link dobel
        $linkCount = [];
        foreach ($nodes as $n) if ($n->link) $linkCount[$n->link] = ($linkCount[$n->link] ?? 0) + 1;

        // index by id + children map
        $byId = $nodes->keyBy('id');
        $children = [];
        foreach ($nodes as $n) {
            $pid = $n->pid ?: '__root__';
            $children[$pid][] = $n;
        }

        // flatten tree (DFS) dengan depth + penanda
        $flat = [];
        $walk = function ($pid, $depth) use (&$walk, &$flat, $children, $roleByNode, $linkCount, $byId) {
            foreach ($children[$pid] ?? [] as $n) {
                $roles = $roleByNode[$n->id] ?? [];
                $parentRoles = $n->pid ? ($roleByNode[$n->pid] ?? []) : ['(root)'];
                $flat[] = [
                    'id'     => $n->id,
                    'nama'   => $n->nama,
                    'link'   => $n->link,
                    'icon'   => $n->icon,
                    'depth'  => $depth,
                    'roles'  => $roles,
                    'punyaAnak' => isset($children[$n->id]),
                    'yatim'  => count($roles) === 0,
                    'dobel'  => $n->link && ($linkCount[$n->link] ?? 0) > 1,
                    'parent_ungranted' => $n->pid && count($roles) > 0 && count($parentRoles) === 0,
                ];
                $walk($n->id, $depth + 1);
            }
        };
        $walk('__root__', 0);

        $lastRebuild = $this->lastRebuildLabel();
        $pending = $this->pendingCount();
        $allRoles = DB::table('group_path')->orderBy('nama')->get(['id', 'nama']);

        return view('set.access.tree', compact('flat', 'lastRebuild', 'pending', 'allRoles'));
    }

    /** Grant satu role ke satu node (additive; idempoten). */
    public function grantRole($id)
    {
        $node = AccessPath::findOrFail($id);
        $groupId = request('group_id');
        abort_unless($groupId, 422);
        // role harus ADA — group_id sembarang = baris group_access hantu
        abort_unless(DB::table('group_path')->where('id', $groupId)->exists(), 422, 'Role tidak ditemukan.');

        $exists = DB::table('group_access')->where('group_id', $groupId)->where('access_id', $id)->exists();
        if (! $exists) {
            DB::table('group_access')->insert([
                'id'        => (string) \Illuminate\Support\Str::uuid(),
                'group_id'  => $groupId,
                'access_id' => $id,
            ]);
            $this->bumpPending();
            \App\Models\AuditLog::record('update', 'Grant akses "' . $node->nama . '" ke role: '
                . (DB::table('group_path')->where('id', $groupId)->value('nama') ?? $groupId));
        }
        return redirect()->route('setaccess')->with('ak_sel', $id);
    }

    /** Cabut satu role dari satu node. */
    public function revokeRole($id, $groupId)
    {
        $node = AccessPath::findOrFail($id);
        $n = DB::table('group_access')->where('group_id', $groupId)->where('access_id', $id)->delete();
        if ($n) {
            $this->bumpPending();
            \App\Models\AuditLog::record('update', 'Cabut akses "' . $node->nama . '" dari role: '
                . (DB::table('group_path')->where('id', $groupId)->value('nama') ?? $groupId));
        }
        return redirect()->route('setaccess')->with('ak_sel', $id);
    }

    /**
     * Hapus node (guardrail). Server-side WAJIB validasi ulang — jangan percaya
     * klien: node punya anak DITOLAK; node ber-grant → group_access ikut dibersihkan
     * (role kehilangan menu, dicatat). bumpPending → wajib rebuild.
     */
    public function destroyNode($id)
    {
        $node = AccessPath::findOrFail($id);

        if (AccessPath::where('pid', $id)->exists()) {
            return redirect()->route('setaccess')->with('error',
                'Node "' . $node->nama . '" masih punya sub-node — hapus/pindahkan anaknya dulu.');
        }

        $roles = DB::table('group_access as ga')->join('group_path as g', 'g.id', '=', 'ga.group_id')
            ->where('ga.access_id', $id)->pluck('g.nama')->implode(', ');

        DB::table('group_access')->where('access_id', $id)->delete();
        $node->delete();
        $this->bumpPending();

        \App\Models\AuditLog::record('delete',
            'Hapus node akses "' . $node->nama . '"' . ($roles ? ' (role kehilangan: ' . $roles . ')' : ''));

        return redirect()->route('setaccess')->with('success',
            'Node "' . $node->nama . '" dihapus. Jangan lupa Terapkan (rebuild) supaya menu ikut berubah.');
    }

    /** Tambah node baru (drawer). Urutan auto max+1 di bawah induk; grant awal opsional. */
    public function storeNode()
    {
        $data = request()->validate([
            'nama'    => 'required|string|max:191',
            'link'    => 'nullable|string|max:191',
            'icon'    => 'nullable|string|max:191',
            'pid'     => 'nullable|string',
            'roles'   => 'nullable|array',
            'roles.*' => 'string',
        ]);

        $pid = $data['pid'] ?: '';
        // pid palsu = node tersimpan tapi TAK PERNAH tampil di pohon (walker
        // turun dari root) — kegagalan-senyap; tolak di sini.
        if ($pid && ! AccessPath::where('id', $pid)->exists()) {
            return redirect()->route('setaccess')->with('error', 'Node induk tidak ditemukan.');
        }
        $urutan = (int) (AccessPath::where('pid', $pid)->max('urutan') ?? 0) + 1;
        // urutan_path (dipakai rendering menu lama) — ikut pola store() lawas
        if ($pid) {
            $parent = AccessPath::find($pid);
            $urutan_path = ($parent && $parent->urutan_path ? $parent->urutan_path . ',' : '') . substr('0' . $urutan, -2);
        } else {
            $urutan_path = substr('0' . $urutan, -2);
        }

        $id = (string) \Illuminate\Support\Str::uuid();
        AccessPath::create([
            'id'          => $id,
            'pid'         => $pid,
            'nama'        => $data['nama'],
            'link'        => $data['link'] ?: '',
            'icon'        => $data['icon'] ?: 'dripicons-document',
            'urutan'      => $urutan,
            'urutan_path' => $urutan_path,
        ]);

        foreach ($data['roles'] ?? [] as $gid) {
            DB::table('group_access')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'group_id' => $gid, 'access_id' => $id,
            ]);
        }

        $this->bumpPending();
        \App\Models\AuditLog::record('create', 'Tambah node akses: ' . $data['nama']
            . ($data['link'] ? ' (/' . $data['link'] . ')' : ''));

        return redirect()->route('setaccess')->with('ak_sel', $id)
            ->with('success', 'Node "' . $data['nama'] . '" ditambah. Terapkan (rebuild) supaya muncul di menu.');
    }

    /** Ubah node (nama/link/ikon saja — pid/urutan tetap, biar aman). */
    public function updateNode($id)
    {
        $node = AccessPath::findOrFail($id);
        $data = request()->validate([
            'nama' => 'required|string|max:191',
            'link' => 'nullable|string|max:191',
            'icon' => 'nullable|string|max:191',
        ]);

        $node->update([
            'nama' => $data['nama'],
            'link' => $data['link'] ?: '',
            'icon' => $data['icon'] ?: $node->icon,
        ]);
        $this->bumpPending();
        \App\Models\AuditLog::record('update', 'Ubah node akses: ' . $node->nama);

        return redirect()->route('setaccess')->with('ak_sel', $id)->with('success', 'Node diperbarui. Terapkan (rebuild) supaya aktif.');
    }

    /** Terapkan perubahan: jalankan access:rebuild, bersihkan penanda pending. */
    public function rebuild()
    {
        \Illuminate\Support\Facades\Artisan::call('access:rebuild');
        $this->clearPending();
        \App\Models\AuditLog::record('update', 'Terapkan perubahan akses (rebuild)');
        return redirect()->route('setaccess')->with('success', 'Perubahan akses diterapkan (rebuild selesai). Menu sekarang mengikuti grant terbaru.');
    }

    // ── penanda "ada perubahan belum di-rebuild" (file di storage) ──
    private function pendingPath(): string { return storage_path('app/group_access/.pending'); }
    private function bumpPending(): void { $p = $this->pendingPath(); $n = is_file($p) ? (int) file_get_contents($p) : 0; @file_put_contents($p, $n + 1); }
    private function pendingCount(): int { $p = $this->pendingPath(); return is_file($p) ? (int) file_get_contents($p) : 0; }
    private function clearPending(): void { @unlink($this->pendingPath()); }

    /** Waktu file cache akses terakhir ditulis (jejak rebuild). */
    private function lastRebuildLabel(): string
    {
        $dir = storage_path('app/group_access');
        if (! is_dir($dir)) return 'belum pernah';
        $mtimes = [];
        foreach (glob($dir . '/*.txt') as $f) $mtimes[] = filemtime($f);
        if (! $mtimes) return 'belum pernah';
        return \Carbon\Carbon::createFromTimestamp(max($mtimes))->diffForHumans();
    }

    public function create()
    {
        $access = [];
        $parent = AccessPath::select('id', 'urutan', 'nama')
            ->where(function($query) {
                $query->whereNull('pid')->orWhere('pid', '');
            })
            ->orderBy('urutan')
            ->get();

        foreach ($parent as $pid => $parentItem) {
            $access[$pid] = $parentItem->toArray();

            // subparent
            $subparentData = AccessPath::select('id', 'urutan', 'nama')->where('pid', $parentItem->id)->orderBy('urutan')->get();
            foreach ($subparentData as $subid => $subparentItem) {
                $access[$pid]['sub'][$subid] = $subparentItem->toArray();

                // child
                $childData = AccessPath::select('id', 'urutan', 'nama')->where('pid', $subparentItem->id)->orderBy('urutan')->get();
                foreach ($childData as $child) {
                    $access[$pid]['sub'][$subid]['child'][] = $child->toArray();
                }
            }
        }

        $data = [
            'access' =>  $access
        ];

        return view('set.access.new', $data);
    }

    public function store()
    {
        $this->validate(request(), [
            'nama' => 'required',
            'urutan' => 'required|numeric',
        ]);

        $data = request()->all();
        $urutanExist = AccessPath::where([
            'urutan' => $data['urutan'],
            'pid' => $data['pid']
        ])->first();

        if ($urutanExist) {
            return redirect()->back()->with('error', 'Urutan <b>' . $data['urutan'] . '</b> sudah digunakan')->withInput();
        }

        if ($data['pid'] != null) {
            $parent = AccessPath::where('id', $data['pid'])->first();
            $urutan_path = $parent->urutan_path . ',' . substr('0' . $data['urutan'], -2);
        } else {
            $urutan_path = substr('0' . $data['urutan'], -2);
        }
        $data['urutan_path'] = $urutan_path;

        AccessPath::create($data);

        return redirect()->route('setaccess')->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $access = [];
        $parent = AccessPath::select('id', 'urutan', 'nama')
            ->where(function($query) {
                $query->whereNull('pid')->orWhere('pid', '');
            })
            ->orderBy('urutan')
            ->get();
        foreach ($parent as $pid => $parent) {
            $access[$pid] = $parent->toArray();

            // subparent
            $subparentData = AccessPath::select('id', 'urutan', 'nama')->where('pid', $parent->id)->orderBy('urutan')->get();
            foreach ($subparentData as $subid => $subparent) {
                $access[$pid]['sub'][$subid] = $subparent->toArray();

                // child
                $childData = AccessPath::select('id', 'urutan', 'nama')->where('pid', $subparent->id)->orderBy('urutan')->get();
                foreach ($childData as $child) {
                    $access[$pid]['sub'][$subid]['child'][] = $child->toArray();
                }
            }
        }

        $data = [
            'access' => $access,
            'data' => AccessPath::where('id', $id)->first()
        ];

        return view('set.access.edit', $data);
    }

    public function update($id)
    {
        $this->validate(request(), [
            'nama' => 'required',
            'urutan' => 'required|numeric',
        ]);

        // WHITELIST — dulunya request()->all() ke query-builder update (tanpa
        // fillable) = mass assignment: field tambahan bisa menimpa kolom apa pun
        // (id/pid_path/dll). Ambil hanya field form.
        $data = request()->only(['nama', 'urutan', 'pid', 'icon', 'link']);
        $urutanExist = AccessPath::where([
            'urutan' => $data['urutan'],
            'pid' => $data['pid'] ?? null,
        ])->where('id', '!=', $id)->first();

        if ($urutanExist) {
            return redirect()->back()->with('error', 'Urutan ' . $data['urutan'] . ' sudah digunakan');
        }

        if (! empty($data['pid'])) {
            $parent = AccessPath::where('id', $data['pid'])->first();
            if (! $parent) {
                return redirect()->back()->with('error', 'Node induk tidak ditemukan');
            }
            $urutan_path = $parent->urutan_path . ',' . substr('0' . $data['urutan'], -2);
        } else {
            $urutan_path = substr('0' . $data['urutan'], -2);
        }

        $data['urutan_path'] = $urutan_path;
        AccessPath::where('id', $id)->update($data);

        // Perubahan struktur menu = wajib rebuild + jejak (samakan jalur baru)
        $this->bumpPending();
        \App\Models\AuditLog::record('update', 'Ubah node akses (form lama): ' . $data['nama']);

        return redirect()->route('setaccess')->with('success', 'Data berhasil diupdate');
    }

    /**
     * Route hapus LAMA (DELETE setaccess/{id}) — didelegasikan ke destroyNode
     * supaya SATU aturan: grup akses ikut dibersihkan (tanpa ini baris
     * group_access yatim), pending di-bump, audit tercatat.
     */
    public function destroy($id)
    {
        return $this->destroyNode($id);
    }
}
