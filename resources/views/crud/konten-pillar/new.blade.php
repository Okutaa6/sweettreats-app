@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Tambah Pillar</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('kontenpillar') }}" class="st-btn-outline-brown">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>
            </div>

            <form id="kontenPillarNewForm" method="POST" action="{{ route('kontenpillar.store') }}">
                @csrf
                @include('crud.konten-pillar._form')

                <div class="st-card">
                    <div class="st-card__body d-flex justify-content-end" style="gap:8px;">
                        <a href="{{ route('kontenpillar') }}" class="st-btn-outline-brown">Batal</a>
                        <button class="st-btn-brand" type="submit" id="kontenPillarSaveBtn">
                            <i class="fa fa-save"></i> <span class="btn-label">Simpan Pillar</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    var form = document.getElementById('kontenPillarNewForm');
    var btn  = document.getElementById('kontenPillarSaveBtn');
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
            setTimeout(function () { window.location = data.edit_url; }, 400);
        })
        .catch(function (err) {
            console.error(err);
            var msg = err.data?.message || 'Gagal menyimpan. Periksa input.';
            if (err.data?.errors) msg = Object.values(err.data.errors).flat().slice(0, 3).join(' | ');
            alert(msg);
            btn.disabled = false;
            if (btnLbl) btnLbl.textContent = 'Simpan Pillar';
            btn.querySelector('i').className = 'fa fa-save';
        });
    });
})();
</script>
@endsection
