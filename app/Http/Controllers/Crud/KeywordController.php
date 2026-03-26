<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawKeyword;

class KeywordController extends Controller
{
    public function index()
    {
        $perPage  = $this->perPage;
        $keywords = RawKeyword::orderBy('end_date')->paginate($perPage);

        return view('crud.keyword.list', compact('keywords'));
    }

    public function create()
    {
        return view('crud.keyword.new');
    }

    public function store()
    {
        request()->validate([
            'keyword'     => 'required|max:255',
            'description' => 'nullable',
            'end_date'    => 'nullable|date',
        ]);

        RawKeyword::create([
            'keyword'     => request('keyword'),
            'description' => request('description'),
            'end_date'    => request('end_date') ?: null,
        ]);

        return redirect()->route('rawkeyword')->with('success', 'Keyword berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $keyword = RawKeyword::findOrFail($id);

        return view('crud.keyword.edit', compact('keyword'));
    }

    public function update($id)
    {
        request()->validate([
            'keyword'     => 'required|max:255',
            'description' => 'nullable',
            'end_date'    => 'nullable|date',
        ]);

        $keyword = RawKeyword::findOrFail($id);
        $keyword->update([
            'keyword'     => request('keyword'),
            'description' => request('description'),
            'end_date'    => request('end_date') ?: null,
        ]);

        return redirect()->route('rawkeyword')->with('success', 'Keyword berhasil diupdate.');
    }

    public function destroy($id)
    {
        RawKeyword::findOrFail($id)->delete();

        return redirect()->route('rawkeyword')->with('success', 'Keyword berhasil dihapus.');
    }
}