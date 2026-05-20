@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
@php
    $statusOpt   = $statuses[$konten->status] ?? ['label' => $konten->status, 'color' => 'neutral'];
    $statusColor = \App\Models\EpKonten::statusColors()[$konten->status] ?? null;
@endphp
<div class="container-fluid">
    {{-- Compact header --}}
    <div class="note-page-header">
        <a href="{{ route('epkonten') }}" class="note-back-link">
            <i class="fa fa-arrow-left"></i> Kembali ke kalender
        </a>
        <div class="note-page-meta">
            @if($statusColor)
                <span class="note-status-pill" style="background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }};">
                    <span class="status-dot" style="background:{{ $statusColor['dot'] }};"></span>
                    {{ $statusOpt['label'] }}
                </span>
            @endif
            @if($konten->tanggal_upload)
                <span class="note-meta-item"><i class="fa fa-calendar-day"></i> {{ $konten->tanggal_upload->translatedFormat('d M Y') }}</span>
            @endif
            @if($konten->updated_at)
                <span class="note-meta-item text-muted"><i class="fa fa-clock"></i> Diperbarui {{ $konten->updated_at->diffForHumans() }}</span>
            @endif
            <button type="button" class="note-action-btn"
                    onclick="if(confirm('Hapus konten ini? Tindakan ini tidak bisa di-undo.')) document.getElementById('deleteKonten').submit()">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    @include('layouts.notif')

    <form id="epKontenEditForm" method="POST" action="{{ route('epkonten.update', $konten->id) }}" autocomplete="off"
          data-id="{{ $konten->id }}">
        @csrf
        @method('PUT')
        @include('crud.ep-konten._form')

        <div class="note-save-bar">
            <a href="{{ route('epkonten') }}" class="st-btn-outline-brown">Batal</a>
            <button class="st-btn-brand" type="submit" id="epKontenSaveBtn">
                <i class="fa fa-save"></i> <span class="btn-label">Simpan Perubahan</span>
            </button>
        </div>
    </form>

    <form id="deleteKonten" method="POST" action="{{ route('epkonten.destroy', $konten->id) }}" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@section('js')
<script>
(function () {
    var form   = document.getElementById('epKontenEditForm');
    var btn    = document.getElementById('epKontenSaveBtn');
    var btnLbl = btn ? btn.querySelector('.btn-label') : null;
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled) return;

        // Visual loading state
        btn.disabled = true;
        if (btnLbl) btnLbl.textContent = 'Menyimpan...';
        btn.querySelector('i').className = 'fa fa-spinner fa-spin';

        var fd = new FormData(form);

        fetch(form.action, {
            method: 'POST', // form method-spoofed via _method=PUT in FormData
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: fd,
        })
        .then(async function (res) {
            var data = await res.json().catch(function () { return null; });
            if (!res.ok) throw { status: res.status, data: data };
            return data;
        })
        .then(function (data) {
            // Feedback singkat di button — tanpa toast
            if (btnLbl) btnLbl.textContent = 'Tersimpan';
            btn.querySelector('i').className = 'fa fa-check';

            // Update timestamp "Diperbarui X" tanpa reload
            var updatedEl = document.querySelector('.note-page-meta .note-meta-item.text-muted');
            if (updatedEl && data.updated_at_human) {
                updatedEl.innerHTML = '<i class="fa fa-clock"></i> Diperbarui ' + data.updated_at_human;
            }

            // Update status pill di header (kalau status berubah)
            var pillEl = document.querySelector('.note-page-meta .note-status-pill');
            if (pillEl && data.status_colors) {
                pillEl.style.background = data.status_colors.bg;
                pillEl.style.color      = data.status_colors.text;
                var dot = pillEl.querySelector('.status-dot');
                if (dot) dot.style.background = data.status_colors.dot;
                pillEl.lastChild.textContent = ' ' + data.status_label;
            }

            // Reset button state setelah 2 detik
            setTimeout(function () {
                btn.disabled = false;
                if (btnLbl) btnLbl.textContent = 'Simpan Perubahan';
                btn.querySelector('i').className = 'fa fa-save';
            }, 2000);
        })
        .catch(function (err) {
            console.error(err);
            var msg = err.data?.message || 'Gagal menyimpan. Periksa input.';
            if (err.data?.errors) {
                msg = Object.values(err.data.errors).flat().slice(0, 3).join(' | ');
            }
            alert(msg);
            btn.disabled = false;
            if (btnLbl) btnLbl.textContent = 'Simpan Perubahan';
            btn.querySelector('i').className = 'fa fa-save';
        });
    });

    // Delete via AJAX juga
    var delForm = document.getElementById('deleteKonten');
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
                if (data.success && data.redirect_url) {
                    window.location = data.redirect_url;
                }
            })
            .catch(function () {
                alert('Gagal menghapus. Coba lagi.');
            });
        });
    }
})();
</script>
@endsection
