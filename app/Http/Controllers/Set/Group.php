<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AccessPath;
use App\Models\GroupAccess;
use App\Models\GroupPath;
use Illuminate\Support\Facades\DB;

class Group extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $groups = GroupPath::paginate($perPage);

        $data = [
            'groups' => $groups
        ];
        return view('set.group.list', $data);
    }

    public function create()
    {
        return view('set.group.new');
    }

    public function store()
    {
        request()->validate([
            'nama' => 'required',
            'deskripsi' => 'required',
            'landing_page' => 'required'
        ]);

        $data = request()->all();
        unset($data['_token']);

        GroupPath::create($data);

        return redirect()->route('setgroup')->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $group = GroupPath::find($id);

        return view('set.group.edit', compact('group'));
    }

    public function update($id)
    {
        request()->validate([
            'nama' => 'required',
            'deskripsi' => 'required',
            'landing_page' => 'required'
        ]);

        $data = request()->all();
        unset($data['_token']);

        GroupPath::find($id)->update($data);

        return redirect()->route('setgroup')->with('success', 'Data berhasil diubah');
    }

    public function destroy($id)
    {
        $group = GroupPath::find($id);
        $group->groupAccesses;
        $group->users;

        if ($group->groupAccesses->count() > 0) {
            return redirect()->route('setgroup')->with('error', 'Data suddah digunakan untuk akses');
        }

        if ($group->users->count() > 0) {
            return redirect()->route('setgroup')->with('error', 'Data suddah digunakan untuk user');
        }

        $group->delete();

        return redirect()->route('setgroup')->with('success', 'Data berhasil dihapus');
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
        $filepath = public_path('group_access/' . $id . '.txt');
        file_put_contents($filepath, $filecontent);

        return true;
    }
}
