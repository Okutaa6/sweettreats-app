@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
@php
    $statusColor = \App\Models\EpKonten::statusColors()[$konten->status] ?? null;
    $statusLabel = $statuses[$konten->status]['label'] ?? $konten->status;
@endphp
<div class="container-fluid">
    <div class="note-page-header">
        <a href="{{ route('epbreakdown') }}" class="note-back-link">
            <i class="fa fa-arrow-left"></i> Kembali ke Breakdown
        </a>
        <div class="note-page-meta">
            <span class="bd-mode-pill"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r=".9" fill="currentColor"/></svg> Khusus</span>
            @if($statusColor)
                <span class="note-status-pill" style="background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }};">
                    <span class="status-dot" style="background:{{ $statusColor['dot'] }};"></span>
                    {{ $statusLabel }}
                </span>
            @endif
            @if($konten->tanggal_upload)
                <span class="note-meta-item"><i class="fa fa-calendar-day"></i> {{ $konten->tanggal_upload->translatedFormat('d M Y') }}</span>
            @endif
            @if($konten->updated_at)
                <span class="note-meta-item text-muted"><i class="fa fa-clock"></i> {{ $konten->updated_at->diffForHumans() }}</span>
            @endif
            <button type="button" class="note-action-btn"
                    onclick="if(confirm('Hapus breakdown ini? Akan hilang juga dari kalender EP.')) document.getElementById('deleteBreakdown').submit()">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    @include('layouts.notif')

    <form id="epBreakdownEditForm" method="POST" action="{{ route('epbreakdown.update', $konten->id) }}" autocomplete="off">
        @csrf
        @method('PUT')
        @include('crud.ep-konten._form', ['showBreakdownFields' => true])

        <div class="note-save-bar">
            <a href="{{ route('epbreakdown') }}" class="st-btn-outline-brown">Batal</a>
            <button class="st-btn-brand" type="submit" id="epBreakdownSaveBtn">
                <i class="fa fa-save"></i> <span class="btn-label">Simpan Perubahan</span>
            </button>
        </div>
    </form>

    <form id="deleteBreakdown" method="POST" action="{{ route('epbreakdown.destroy', $konten->id) }}" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    var form = document.getElementById('epBreakdownEditForm');
    var btn  = document.getElementById('epBreakdownSaveBtn');
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

    var delForm = document.getElementById('deleteBreakdown');
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
            .then(function (data) { if (data.success && data.redirect_url) window.location = data.redirect_url; })
            .catch(function () { alert('Gagal menghapus. Coba lagi.'); });
        });
    }
})();
</script>
@endsection
