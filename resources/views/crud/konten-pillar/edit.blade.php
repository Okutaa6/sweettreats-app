@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="page-title mb-1">Edit Pillar</h4>
                    <div class="text-muted" style="font-size:13px;">
                        @if($pillar->color)
                            <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:{{ $pillar->color }}; margin-right:6px; vertical-align:middle;"></span>
                        @endif
                        <strong>{{ $pillar->nama }}</strong>
                        <span class="ml-2">·</span>
                        <code style="background:#f5e6d3; padding:2px 8px; border-radius:4px; font-size:11px; color:#7d4429;">{{ $pillar->slug }}</code>
                    </div>
                </div>
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
                <button type="button" class="st-btn-danger-outline"
                        onclick="if(confirm('Hapus pillar \'{{ $pillar->nama }}\'? Kalau masih dipakai di EP, akan ditolak.')) document.getElementById('deletePillar').submit()">
                    <i class="fa fa-trash"></i> Hapus
                </button>
            </div>

            <form id="kontenPillarEditForm" method="POST" action="{{ route('kontenpillar.update', $pillar->id) }}">
                @csrf
                @method('PUT')
                @include('crud.konten-pillar._form')

                <div class="st-card">
                    <div class="st-card__body d-flex justify-content-end" style="gap:8px;">
                        <a href="{{ route('kontenpillar') }}" class="st-btn-outline-brown">Batal</a>
                        <button class="st-btn-brand" type="submit" id="kontenPillarSaveBtn">
                            <i class="fa fa-save"></i> <span class="btn-label">Simpan Perubahan</span>
                        </button>
                    </div>
                </div>
            </form>

            <form id="deletePillar" method="POST" action="{{ route('kontenpillar.destroy', $pillar->id) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    var form = document.getElementById('kontenPillarEditForm');
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

            // Update slug di header (kalau berubah karena nama berubah)
            var slugEl = document.querySelector('.page-title-box code');
            if (slugEl && data.slug) slugEl.textContent = data.slug;

            setTimeout(function () {
                btn.disabled = false;
                if (btnLbl) btnLbl.textContent = 'Simpan Perubahan';
                btn.querySelector('i').className = 'fa fa-save';
            }, 2000);
        })
        .catch(function (err) {
            console.error(err);
            var msg = err.data?.message || 'Gagal menyimpan. Periksa input.';
            if (err.data?.errors) msg = Object.values(err.data.errors).flat().slice(0, 3).join(' | ');
            alert(msg);
            btn.disabled = false;
            if (btnLbl) btnLbl.textContent = 'Simpan Perubahan';
            btn.querySelector('i').className = 'fa fa-save';
        });
    });

    var delForm = document.getElementById('deletePillar');
    if (delForm) {
        delForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch(delForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: new FormData(delForm),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success && data.redirect_url) window.location = data.redirect_url;
                else if (!data.success) alert(data.message || 'Gagal menghapus.');
            })
            .catch(function () { alert('Gagal menghapus. Coba lagi.'); });
        });
    }
})();
</script>
@endsection
