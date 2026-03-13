<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AccessPath;

class Access extends Controller
{

    public function index()
    {
        $access = [];
        // Handle both null and empty string for parent items
        $parent = AccessPath::select('id', 'urutan', 'nama', 'pid')
            ->where(function($query) {
                $query->whereNull('pid')->orWhere('pid', '');
            })
            ->orderBy('urutan')
            ->get();
        foreach ($parent as $pid => $parentItem) {

            $access[$pid] = $parentItem->toArray();

            // subparent
            $subparentData = AccessPath::select('id', 'urutan', 'nama', 'pid')->where('pid', $parentItem->id)->orderBy('urutan')->get();
            foreach ($subparentData as $subid => $subparentItem) {
                $access[$pid]['sub'][$subid] = $subparentItem->toArray();

                // child
                $childData = AccessPath::select('id', 'urutan', 'nama', 'pid')->where('pid', $subparentItem->id)->orderBy('urutan')->get();
                foreach ($childData as $child) {
                    $access[$pid]['sub'][$subid]['child'][] = $child->toArray();
                }
            }
        }

        $data = [
            'access' => $access
        ];

        return view('set.access.list', $data);
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

        $data = request()->all();
        $urutanExist = AccessPath::where([
            'urutan' => $data['urutan'],
            'pid' => $data['pid']
        ])->where('id', '!=', $id)->first();

        if ($urutanExist) {
            return redirect()->back()->with('error', 'Urutan ' . $data['urutan'] . ' sudah digunakan');
        }

        if ($data['pid'] != null) {
            $parent = AccessPath::where('id', $data['pid'])->first();
            $urutan_path = $parent->urutan_path . ',' . substr('0' . $data['urutan'], -2);
        } else {
            $urutan_path = substr('0' . $data['urutan'], -2);
        }

        $data['urutan_path'] = $urutan_path;

        unset($data['_token']);
        unset($data['_method']);
        AccessPath::where('id', $id)->update($data);

        return redirect()->route('setaccess')->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $isHaveChild = AccessPath::where('pid', $id)->first();
        if ($isHaveChild) {
            return redirect()->route('setaccess')->with('error', 'Data ini memiliki sub data');
        } else {
            AccessPath::where('id', $id)->delete();
            return redirect()->route('setaccess')->with('success', 'Data berhasil dihapus');
        }
    }
}
