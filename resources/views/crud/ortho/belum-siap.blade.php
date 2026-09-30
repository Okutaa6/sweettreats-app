{{--
    Tracking Ortho — halaman menjelaskan diri sendiri saat prasyaratnya belum ada.

    Dua sebab yang mungkin, dan keduanya punya jalan keluar yang jelas:
      • tabelnya belum dibuat  → berkas SQL belum dijalankan di environment ini
      • cabangnya belum terpeta → raw_branch.omnicare_place_id masih kosong

    Lebih baik begini daripada 500 tanpa petunjuk. Pola guard-nya menjiplak
    CustomerController::edit() baris 368.
--}}
@extends('layouts.app')

@section('content')
@php
    $karenaCabang = ($alasan ?? null) === 'cabang';
@endphp

<div class="ort">
    <div class="st-card" style="max-width:640px;margin:32px auto;">
        <div class="st-card__body" style="text-align:center;padding:34px 30px 30px;">

            <div style="width:62px;height:62px;margin:0 auto 16px;border-radius:50%;
                        background:#FBEFD9;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user-clock" style="font-size:24px;color:#B5701A;"></i>
            </div>

            <h2 style="font-size:17px;font-weight:800;margin:0 0 8px;color:#3B1500;">
                Tracking Ortho belum siap dipakai di sini
            </h2>

            @if ($karenaCabang)
                <p style="font-size:13.5px;color:#5A3A22;margin:0 0 18px;line-height:1.6;">
                    Belum ada cabang yang terpeta ke rekam medis, jadi halaman ini tidak punya
                    pijakan. Kolom <code>omnicare_place_id</code> di tabel cabang masih kosong.
                </p>
                <div style="text-align:left;background:#FCF7F0;border:1px solid #EFE0CE;
                            border-radius:12px;padding:14px 16px;font-size:12.5px;color:#5A3A22;">
                    <div style="font-weight:800;margin-bottom:6px;">Yang perlu dijalankan</div>
                    <code style="display:block;color:#6B2400;">database/sql/2026_05_20_omnicare_tables.sql</code>
                    <div style="margin-top:6px;">Bagian seeding cabang di berkas itu yang mengisi pemetaannya.</div>
                </div>
            @else
                <p style="font-size:13.5px;color:#5A3A22;margin:0 0 18px;line-height:1.6;">
                    Tabelnya belum dibuat di database ini. Modul ini memakai skema yang
                    dijalankan manual, bukan <code>php artisan migrate</code>.
                </p>
                <div style="text-align:left;background:#FCF7F0;border:1px solid #EFE0CE;
                            border-radius:12px;padding:14px 16px;font-size:12.5px;color:#5A3A22;">
                    <div style="font-weight:800;margin-bottom:6px;">Yang perlu dijalankan, berurutan</div>
                    <code style="display:block;color:#6B2400;">database/sql/2026_09_23_ort_tracking_schema.sql</code>
                    <code style="display:block;color:#6B2400;">database/sql/2026_09_23_ort_tracking_access.sql</code>
                    <code style="display:block;color:#6B2400;margin-top:4px;">php artisan access:rebuild</code>
                    <div style="margin-top:8px;">
                        Langkah terakhir wajib — kalau dilewat, struktur akses tidak ikut
                        diperbarui dan semua halaman jadi 404, bukan cuma yang ini.
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
