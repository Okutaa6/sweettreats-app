@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
@php
    $statusColor = \App\Models\EpKonten::statusColors()[$campaign->status] ?? null;
    $statusLabel = $statuses[$campaign->status]['label'] ?? $campaign->status;
    $bulanLabel  = $bulanOpt[$campaign->bulan] ?? $campaign->bulan;
@endphp
<div class="container-fluid">
    <div class="note-page-header">
        <a href="{{ route('epcampaign') }}" class="note-back-link">
            <i class="fa fa-arrow-left"></i> Kembali
        </a>
        <div class="note-page-meta">
            @if($statusColor)
                <span class="note-status-pill" style="background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }};">
                    <span class="status-dot" style="background:{{ $statusColor['dot'] }};"></span>
                    {{ $statusLabel }}
                </span>
            @endif
            <span class="note-meta-item"><i class="fa fa-calendar"></i> {{ $bulanLabel }} {{ $campaign->tahun }}</span>
            @if($campaign->kontens->count() > 0)
                <span class="note-meta-item"><i class="fa fa-link"></i> {{ $campaign->kontens->count() }} konten EP</span>
            @endif
            @if($campaign->updated_at)
                <span class="note-meta-item text-muted"><i class="fa fa-clock"></i> {{ $campaign->updated_at->diffForHumans() }}</span>
            @endif
            <button type="button" class="note-action-btn"
                    onclick="if(confirm('Hapus campaign ini? Pastikan tidak ada konten EP yang masih terkait.')) document.getElementById('deleteCampaign').submit()">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    @include('layouts.notif')

    <form id="epCampaignEditForm" method="POST" action="{{ route('epcampaign.update', $campaign->id) }}" autocomplete="off">
        @csrf
        @method('PUT')
        @include('crud.ep-campaign._form')

        <div class="note-save-bar">
            <a href="{{ route('epcampaign') }}" class="st-btn-outline-brown">Batal</a>
            <button class="st-btn-brand" type="submit" id="epCampaignSaveBtn">
                <i class="fa fa-save"></i> <span class="btn-label">Simpan Perubahan</span>
            </button>
        </div>
    </form>

    <form id="deleteCampaign" method="POST" action="{{ route('epcampaign.destroy', $campaign->id) }}" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    var form = document.getElementById('epCampaignEditForm');
    var btn  = document.getElementById('epCampaignSaveBtn');
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

            var updatedEl = document.querySelector('.note-page-meta .note-meta-item.text-muted');
            if (updatedEl && data.updated_at_human) {
                updatedEl.innerHTML = '<i class="fa fa-clock"></i> ' + data.updated_at_human;
            }
            var pillEl = document.querySelector('.note-page-meta .note-status-pill');
            if (pillEl && data.status_colors) {
                pillEl.style.background = data.status_colors.bg;
                pillEl.style.color      = data.status_colors.text;
                var dot = pillEl.querySelector('.status-dot');
                if (dot) dot.style.background = data.status_colors.dot;
                pillEl.lastChild.textContent = ' ' + data.status_label;
            }

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

    var delForm = document.getElementById('deleteCampaign');
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
