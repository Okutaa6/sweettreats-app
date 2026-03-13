<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\SysProfile;

class SystemProfile extends Controller
{
    public function index()
    {
        $sysprofile = SysProfile::first();

        return view('set.sysprofile.list', compact('sysprofile'));
    }

    public function edit($id)
    {
        $sysprofile = SysProfile::find($id);

        $data = [
            'id' => $sysprofile->id,
            'data' => $sysprofile,
            'listMutasi' => array('' => 'Debit & Kredit', 'D' => 'Debit Only', 'K' => 'Kredit Only'),
        ];

        return view('set.sysprofile.edit', $data);
    }

    public function update($id)
    {
        request()->validate([
            'systitle' => 'required',
            'sysname' => 'required',
        ]);

        $data = request()->all();
        unset($data['_token']);
        unset($data['_method']);

        SysProfile::find($id)->update($data);

        return redirect()->route('setsysprofile')->with('success', 'Berhasil simpan data');
    }

    public function uploadProcess($id)
    {
        request()->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $img = request()->file('logo');
        $newFileName = $id . '.' . $img->getClientOriginalExtension();
        $img->move(public_path('sysprofile'), $newFileName);

        SysProfile::find($id)->update(['syslogo' => $newFileName]);

        return redirect()->route('setsysprofile')->with('success', 'Berhasil simpan data');
    }
}
