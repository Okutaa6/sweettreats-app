<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AccessPath;
use App\Models\ChecklistDivision;
use App\Models\GroupAccess;
use App\Models\GroupPath;
use App\Models\Users;
use Illuminate\Support\Facades\DB;

class Group extends Controller
{
    /**
     * Halaman gabungan: Tab "Role & Izin" + Tab "Struktur Organisasi" — dua
     * tampilan dari DATA YANG SAMA (group_path). Role = jabatan: tab Role atur
     * izin menu, tab Struktur menyusun role jadi pohon (atasan-bawahan) untuk
     * approval berjenjang. Klik kartu → lihat user di role itu.
     */
    public function index()
    {
        $groups = GroupPath::orderBy('nama')->get();

        // Jumlah user + daftar ringkas per role (untuk kartu & klik "lihat anggota").
        $usersByGroup = Users::select('id', 'nama', 'email', 'group_id', 'raw_branch_id')
            ->with('branch:id,name')->get()->groupBy('group_id');

        $membersJs = $usersByGroup->map(function ($rows) {
            return $rows->map(fn ($u) => [
                'nama'   => $u->nama,
                'email'  => $u->email,
                'branch' => optional($u->branch)->name,
            ])->values();
        });

        // Bagan: root = role tanpa atasan yang sudah punya bawahan (sudah
        // disusun). Role "lepas" (belum diberi atasan/bawahan) TIDAK muncul di
        // bagan → default struktur tampil kosong sampai admin menyusun.
        $baganRoots = GroupPath::whereNull('parent_id')
            ->whereHas('children')
            ->orderBy('nama')->get();

        // Detail: semua role, DIURUTKAN mengikuti bagan (pre-order depth-first):
        // puncak dulu, lalu bawahannya tepat setelahnya — jadi kartu tersusun
        // rapi sesuai hierarki, bukan acak.
        $flat     = GroupPath::with('parent')->orderBy('nama')->get();
        $byParent = $flat->groupBy(fn ($r) => $r->parent_id ?: '__root__');

        $allRoles = collect();
        $walk = function ($key) use (&$walk, $byParent, &$allRoles) {
            // Di tiap tingkat: role yang punya bawahan dulu (pohon memimpin),
            // lalu role tanpa bawahan — masing-masing tetap urut nama.
            $children = $byParent->get($key, collect())
                ->sortByDesc(fn ($n) => $byParent->has($n->id) ? 1 : 0)
                ->values();
            foreach ($children as $node) {
                $allRoles->push($node);
                $walk($node->id);
            }
        };
        $walk('__root__');

        // Jaga-jaga: role yang atasannya hilang (orphan) tetap disertakan.
        $allRoles = $allRoles->concat($flat->reject(fn ($r) => $allRoles->contains('id', $r->id)));

        // Divisi checklist untuk ditempel ke role di bagan (drag & drop):
        // yang sudah punya penanggung jawab dikelompokkan per role, sisanya
        // masuk "tray" belum-ditugaskan.
        $divisions           = ChecklistDivision::where('is_active', 1)->orderBy('sort_order')->get();
        $divisionsByGroup    = $divisions->whereNotNull('group_id')->groupBy('group_id');
        $unassignedDivisions = $divisions->whereNull('group_id')->values();

        return view('set.group.list', [
            'groups'              => $groups,
            'memberCount'         => $usersByGroup->map->count(),
            'membersJs'           => $membersJs,
            'baganRoots'          => $baganRoots,
            'allRoles'            => $allRoles,
            'divisionsByGroup'    => $divisionsByGroup,
            'unassignedDivisions' => $unassignedDivisions,
        ]);
    }

    public function create()
    {
        return view('set.group.new', [
            'parents'         => $this->parentOptions(),
            'preselectParent' => request('parent'),
        ]);
    }

    public function store()
    {
        request()->validate([
            'nama'         => 'required',
            'deskripsi'    => 'required',
            'landing_page' => 'required',
            'parent_id'    => 'nullable|exists:group_path,id',
        ]);

        $data = request()->except('_token');
        $data['parent_id'] = $data['parent_id'] ?: null;
        $data['level']     = $this->levelFor($data['parent_id']);

        GroupPath::create($data);

        return redirect()->route('setgroup')->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $group = GroupPath::findOrFail($id);

        return view('set.group.edit', [
            'group'   => $group,
            'parents' => $this->parentOptions($group),
        ]);
    }

    public function update($id)
    {
        request()->validate([
            'nama'         => 'required',
            'deskripsi'    => 'required',
            'landing_page' => 'required',
            'parent_id'    => 'nullable|exists:group_path,id',
        ]);

        $group    = GroupPath::findOrFail($id);
        $parentId = request('parent_id') ?: null;

        // Cegah siklus: parent tak boleh diri sendiri atau bawahannya.
        if ($parentId !== null && in_array($parentId, $group->subtreeIds(), true)) {
            return redirect()->route('setgroup.edit', $id)
                ->with('error', 'Atasan tidak boleh diri sendiri atau bawahannya (siklus).');
        }

        $data = request()->except('_token');
        $data['parent_id'] = $parentId;
        $data['level']     = $this->levelFor($parentId);

        DB::transaction(function () use ($group, $data) {
            $group->update($data);
            // Reparent mengubah kedalaman → sinkronkan level seluruh subtree.
            $this->relevel($group->fresh());
        });

        return redirect()->route('setgroup')->with('success', 'Data berhasil diubah');
    }

    /**
     * Reparent via drag-and-drop di bagan. Geser node ke atas node lain untuk
     * menjadikannya atasan baru, atau ke zona "puncak" untuk melepas atasan.
     * Dipanggil via AJAX → balikan JSON.
     */
    public function reparent()
    {
        $data = request()->validate([
            'id'        => 'required|exists:group_path,id',
            'parent_id' => 'nullable|exists:group_path,id',
        ]);

        $group    = GroupPath::findOrFail($data['id']);
        $parentId = $data['parent_id'] ?: null;

        // Cegah siklus: atasan baru tak boleh diri sendiri atau bawahannya.
        if ($parentId !== null && in_array($parentId, $group->subtreeIds(), true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Atasan tidak boleh diri sendiri atau bawahannya (siklus).',
            ], 422);
        }

        // Ditarik ke atasan langsungnya sendiri → TUKAR posisi: node naik ke
        // tempat atasannya, atasannya jadi bawahannya.
        if ($parentId !== null && (string) $group->parent_id === (string) $parentId) {
            $boss = GroupPath::findOrFail($parentId);

            $group->parent_id = $boss->parent_id;        // node ambil posisi atasan lama
            $group->level     = $this->levelFor($boss->parent_id);
            $group->save();

            $boss->parent_id = $group->id;               // atasan lama jadi bawahan node
            $boss->level     = $this->levelFor($group->id);
            $boss->save();

            $this->relevel($group->fresh());

            return response()->json(['ok' => true, 'message' => 'Posisi ditukar.']);
        }

        // Tidak berubah → tak perlu apa-apa.
        if ((string) $group->parent_id === (string) $parentId) {
            return response()->json(['ok' => true, 'message' => 'Posisi tidak berubah.']);
        }

        $group->parent_id = $parentId;
        $group->level     = $this->levelFor($parentId);
        $group->save();

        // Reparent mengubah kedalaman → sinkronkan level seluruh subtree.
        $this->relevel($group->fresh());

        return response()->json(['ok' => true, 'message' => 'Struktur organisasi diperbarui.']);
    }

    /**
     * Lepas role dari hierarki: hapus atasannya (parent_id = null) sehingga role
     * menjadi PUNCAK (tanpa atasan). Bawahannya tetap ikut (subtree utuh), hanya
     * level-nya disinkronkan ulang mulai dari 0. Dipakai lewat tombol di kartu
     * Detail — redirect + flash (bukan AJAX, konsisten dgn edit/hapus).
     */
    public function detach($id)
    {
        $group = GroupPath::findOrFail($id);

        if (! $group->parent_id) {
            return redirect()->route('setgroup')->with('error', 'Role ini sudah menjadi puncak (tanpa atasan).');
        }

        DB::transaction(function () use ($group) {
            $group->update(['parent_id' => null, 'level' => 0]);
            // Kedalaman berubah → sinkronkan level seluruh subtree.
            $this->relevel($group->fresh());
        });

        return redirect()->route('setgroup')->with('success', "Role {$group->nama} dilepas dari hierarki — kini menjadi puncak (tanpa atasan).");
    }

    /**
     * Tautkan/lepas divisi checklist ke sebuah ROLE STAF lewat drag-drop di bagan
     * (anggota role itu mengisi checklist divisi ini; atasannya yang me-review).
     * group_id null = kembalikan ke tray "belum ditautkan". Balikan JSON.
     */
    public function assignDivision()
    {
        $data = request()->validate([
            'division_id' => 'required|string|exists:sys_checklist_division,id',
            'group_id'    => 'nullable|exists:group_path,id',
        ]);

        $division = ChecklistDivision::findOrFail($data['division_id']);
        $groupId  = $data['group_id'] ?: null;

        if ((string) $division->group_id === (string) $groupId) {
            return response()->json(['ok' => true, 'message' => 'Tidak berubah.']);
        }

        $division->update(['group_id' => $groupId]);

        $roleName = $groupId ? optional(GroupPath::find($groupId))->nama : null;

        return response()->json([
            'ok'      => true,
            'message' => $groupId
                ? "Divisi {$division->name} kini ditangani role {$roleName}."
                : "Divisi {$division->name} dilepas (belum ditautkan).",
        ]);
    }

    public function destroy($id)
    {
        $group = GroupPath::find($id);

        if ($group->children()->exists()) {
            return redirect()->route('setgroup')->with('error', 'Role masih punya bawahan, pindahkan dulu.');
        }

        if ($group->groupAccesses->count() > 0) {
            return redirect()->route('setgroup')->with('error', 'Data suddah digunakan untuk akses');
        }

        if ($group->users->count() > 0) {
            return redirect()->route('setgroup')->with('error', 'Data suddah digunakan untuk user');
        }

        // Lepas tautan divisi checklist supaya tak menunjuk role yang dihapus.
        ChecklistDivision::where('group_id', $id)->update(['group_id' => null]);

        $group->delete();

        return redirect()->route('setgroup')->with('success', 'Data berhasil dihapus');
    }

    /* ---------- helpers hierarki role ---------- */

    /** Level = 0 kalau root, selain itu parent.level + 1. */
    private function levelFor(?string $parentId): int
    {
        if (!$parentId) {
            return 0;
        }
        $parent = GroupPath::find($parentId);
        return $parent ? ((int) $parent->level + 1) : 0;
    }

    /** Sinkronkan kolom level untuk node + seluruh turunannya (rekursif). */
    private function relevel(GroupPath $node): void
    {
        foreach ($node->children as $child) {
            $newLevel = (int) $node->level + 1;
            if ((int) $child->level !== $newLevel) {
                $child->update(['level' => $newLevel]);
            }
            $this->relevel($child->fresh());
        }
    }

    /** Opsi dropdown "Atasan". Saat edit, kecualikan diri + turunannya (cegah siklus). */
    private function parentOptions(?GroupPath $exclude = null)
    {
        $q = GroupPath::orderBy('level')->orderBy('nama');
        if ($exclude) {
            $q->whereNotIn('id', $exclude->subtreeIds());
        }
        return $q->get();
    }

    public function access($id)
    {
        $data_access = [];

        //   access parent
        $parent_data = AccessPath::where(function($query) {
            $query->whereNull('pid')->orWhere('pid', '');
        })->orderBy('urutan', 'asc')->get();
        foreach ($parent_data as $pid => $parent) {
            $data_access[$pid] = $parent->toArray();

            //  access subparent
            $subparent_data = AccessPath::where('pid', $parent->id)->orderBy('urutan', 'asc')->get();
            foreach ($subparent_data as $sid => $subparent) {
                $data_access[$pid]['sub'][$sid] = $subparent->toArray();

                //  access child
                $child_data = AccessPath::where('pid', $subparent->id)->orderBy('urutan', 'asc')->get();
                foreach ($child_data as $cid => $child) {
                    $data_access[$pid]['sub'][$sid]['child'][] = $child->toArray();
                }
            }
        }

        $group = GroupPath::find($id);
        $group_access = GroupAccess::where('group_id', $id)->get();

        $slcaccess = [];
        if ($group_access->count() > 0) {
            foreach ($group_access as $ga) {
                $slcaccess[] = $ga->access_id;
            }
        }

        $data = [
            'group' => $group,
            'data_access' => $data_access,
            'slcaccess' => $slcaccess
        ];

        return view('set.group.access', $data);
    }

    public function accessProcess($id)
    {
        $accesses = request()->input('access_id');

        DB::beginTransaction();

        try {
            GroupAccess::where('group_id', $id)->delete();

            if ($accesses) {
                foreach ($accesses as $access) {
                    GroupAccess::create([
                        'group_id' => $id,
                        'access_id' => $access
                    ]);
                }
            }

            $this->access_generator($id);

            DB::commit();
            return redirect()->route('setgroup')->with('success', 'Data berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('setgroup')->with('error', 'Data gagal disimpan');
        }
    }


    
    private function access_generator($id)
    {
        $access_data = [];

        // parent
        $parent_data = GroupAccess::with('access')
            ->whereHas('access', function ($query) {
                $query->where(function($q) {
                    $q->whereNull('pid')->orWhere('pid', '');
                })->orderBy('urutan', 'asc');
            })
            ->where('group_id', $id)
            ->get();

        foreach ($parent_data as $pid => $parent) {
            $access_data[$pid] = $parent->access->toArray();

            // subparent
            $subparent_data = GroupAccess::with('access')
                ->whereHas('access', function ($query) use ($parent) {
                    $query->where('pid', $parent->access->id)
                        ->orderBy('urutan', 'asc');
                })
                ->where('group_id', $id)
                ->get();

            foreach ($subparent_data as $sid => $subparent) {
                $access_data[$pid]['sub'][$sid] = $subparent->access->toArray();

                // child
                $child_data = GroupAccess::with('access')
                    ->whereHas('access', function ($query) use ($subparent) {
                        $query->where('pid', $subparent->access->id)
                            ->orderBy('urutan', 'asc');
                    })
                    ->where('group_id', $id)
                    ->get();

                foreach ($child_data as $child) {
                    $access_data[$pid]['sub'][$sid]['child'][] = $child->access->toArray();
                }
            }
        }

        // write to file
        $filecontent = json_encode($access_data, JSON_PRETTY_PRINT);
        $filepath = storage_path('app/group_access/' . $id . '.txt');
        file_put_contents($filepath, $filecontent);

        // Invalidate sidebar cache for this group
        \Illuminate\Support\Facades\Cache::forget('group_access_' . $id);

        return true;
    }
}
