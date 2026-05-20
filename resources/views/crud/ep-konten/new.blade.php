@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('epkonten') }}" class="st-btn-outline-brown">
            <i class="fa fa-arrow-left"></i> Kembali
        </a>
    </div>

    @include('layouts.notif')

    <form id="epKontenNewForm" method="POST" action="{{ route('epkonten.store') }}" autocomplete="off">
        @csrf
        @include('crud.ep-konten._form')

        <div class="note-save-bar">
            <a href="{{ route('epkonten') }}" class="st-btn-outline-brown">Batal</a>
            <button class="st-btn-brand" type="submit" id="epKontenSaveBtn">
                <i class="fa fa-save"></i> <span class="btn-label">Simpan Konten</span>
            </button>
        </div>
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    var form   = document.getElementById('epKontenNewForm');
    var btn    = document.getElementById('epKontenSaveBtn');
    var btnLbl = btn ? btn.querySelector('.btn-label') : null;
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled) return;

        btn.disabled = true;
        if (btnLbl) btnLbl.textContent = 'Menyimpan...';
        btn.querySelector('i').className = 'fa fa-spinner fa-spin';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: new FormData(form),
        })
        .then(async function (res) {
            var data = await res.json().catch(function () { return null; });
            if (!res.ok) throw { status: res.status, data: data };
            return data;
        })
        .then(function (data) {
            if (btnLbl) btnLbl.textContent = 'Tersimpan';
            btn.querySelector('i').className = 'fa fa-check';
            // Redirect ke edit page (silent — no toast)
            setTimeout(function () {
                window.location = data.edit_url;
            }, 400);
        })
        .catch(function (err) {
            console.error(err);
            var msg = err.data?.message || 'Gagal menyimpan. Periksa input.';
            if (err.data?.errors) {
                msg = Object.values(err.data.errors).flat().slice(0, 3).join(' | ');
            }
            alert(msg);
            btn.disabled = false;
            if (btnLbl) btnLbl.textContent = 'Simpan Konten';
            btn.querySelector('i').className = 'fa fa-save';
        });
    });
})();
</script>
@endsection
