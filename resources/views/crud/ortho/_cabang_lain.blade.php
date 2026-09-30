{{--
    Tab cabang lain — pasien kami yang sedang dipegang cabang lain.

    LIHAT-SAJA. Tidak ada dropdown, tidak ada tombol tindakan. Satu-satunya
    tombol yang boleh muncul adalah "Ingatkan <Cabang>", dan hanya kalau pasien
    itu benar-benar mandek di sana. Kalau semuanya baik-baik saja, tidak ada
    tombol sama sekali — halamannya tenang, dan itu memang kabar baik.

    Kolomnya sengaja BERBEDA dari tab sendiri, karena pertanyaannya berbeda:
    bukan "apa yang harus saya kerjakan", tapi "pasien saya baik-baik saja
    tidak di sana".

    Ini satu-satunya tempat di halaman yang menembus kunci cabang — penyaringnya
    "pasien yang cabang pasangnya kita, tapi penagihnya bukan".
--}}
@php
    $namaTujuan = $peta[$cabangDilihat]['nama'] ?? $cabangDilihat;
@endphp

<div class="ort-meta-line">
    <span>
        <i class="fas fa-exchange-alt" style="color:#6B2400;"></i>
        <strong class="tabular">{{ $baris->total() }}</strong> pasien {{ $namaCabang }}
        sedang dipegang {{ $namaTujuan }} · hanya bisa dilihat
    </span>
    <span style="color:#A9866A;">Tidak ada tindakan di sini — kecuali mengingatkan cabang tujuan</span>
</div>

<div class="st-card" style="margin-top:10px;">
    <div class="ort-tablewrap">

        <div class="ort-thead">
            <div class="ort-grid ort-grid--lintas">
                <div>Pasien</div>
                <div>Pindah sejak</div>
                <div>Kontrol terakhir</div>
                <div>Jadwal berikut</div>
                <div>Kehadiran</div>
                <div></div>
            </div>
        </div>

        @forelse ($baris as $c)
            @php
                $tinggal = $tertinggal[$c->id] ?? false;
                $ingat   = $pengingat[$c->id] ?? null;

                $pindah  = $c->transfers()->orderByDesc('transfer_date')->first();
                $hariTak = $c->last_visit_date ? $c->last_visit_date->diffInDays($hariIni) : null;

                $telatBerikut = $c->next_sch_date && $c->next_sch_date->lt($hariIni)
                    ? $c->next_sch_date->diffInDays($hariIni) : 0;
            @endphp

            <div class="ort-tr {{ $tinggal ? 'ort-tr--bad ort-tr--nb' : '' }}"
                 style="{{ $tinggal ? 'background:#FDF6F4;' : '' }}">
                <div class="ort-grid ort-grid--lintas">

                    <div>
                        <div class="ort-name">{{ \Illuminate\Support\Str::title($c->patient_name) }}</div>
                        <div class="ort-meta">
                            {{ $c->omnicare_pid ?: 'belum tertaut RM' }}
                            @if ($c->bracket_type) · {{ $c->bracket_type }} @endif
                            @if ($c->doctor_name) · {{ $c->doctor_name }} @endif
                        </div>
                        @if ($ingat)
                            <div class="ort-meta" style="color:#2F8E55;font-weight:700;">
                                <i class="fas fa-check"></i>
                                Diingatkan {{ $ingat->reminded_at->translatedFormat('j M') }}
                                @if ($ingat->reminded_by) oleh {{ $ingat->reminded_by }} @endif
                            </div>
                        @endif
                    </div>

                    <div class="ort-cell">
                        @if ($pindah)
                            <span class="tabular">{{ $pindah->transfer_date->translatedFormat("j M 'y") }}</span>
                            <span class="ort-cell--sub">{{ $pindah->labelAlasan() }}</span>
                            @if ($pindah->is_temporary)
                                <span class="ort-chip ort-chip--transfer" style="margin-top:3px;">sementara</span>
                            @endif
                        @else
                            <span class="ort-x">—</span>
                        @endif
                    </div>

                    <div class="ort-cell">
                        @if ($c->last_visit_date)
                            <span class="tabular">{{ $c->last_visit_date->translatedFormat("j M 'y") }}</span>
                            <span class="ort-cell--sub" style="color:#2F8E55;font-weight:700;">Hadir</span>
                        @else
                            <span class="ort-x">belum pernah datang</span>
                        @endif
                    </div>

                    <div class="ort-cell {{ $telatBerikut > 0 ? 'ort-late' : '' }}">
                        @if ($c->next_sch_date)
                            <span class="tabular">{{ $c->next_sch_date->translatedFormat("j M 'y") }}</span>
                            @if ($telatBerikut > 0)
                                <span class="ort-cell--sub tabular">{{ $telatBerikut }} hari lalu</span>
                            @else
                                <span class="ort-cell--sub">{{ $c->next_is_proposal ? 'usulan' : 'menunggu' }}</span>
                            @endif
                        @else
                            <span class="ort-x">belum dijadwalkan</span>
                            @if ($hariTak !== null)
                                <span class="ort-cell--sub tabular" style="color:#C0392B;">{{ $hariTak }} hari lalu</span>
                            @endif
                        @endif
                    </div>

                    <div class="ort-cell">
                        <span class="tabular">{{ $c->visit_count }}/{{ $c->control_count }}</span>
                        <span class="ort-cell--sub">sejak pindah</span>
                    </div>

                    <div>
                        <a href="{{ $tautanDetail($c->id) }}" class="ort-toggle" title="Lihat detail pasien">
                            <i class="fas fa-chevron-right"></i></a>
                    </div>

                </div>
            </div>

            @if ($tinggal)
                <div class="ort-left-behind">
                    <span>
                        <i class="fas fa-exclamation-triangle"></i>
                        {{-- Sebut ALASANNYA, jangan cuma labelnya. "Tertinggal" saja
                             tidak memberi tahu Sales kenapa baris ini merah dan apa
                             yang harus diminta ke cabang sebelah. --}}
                        <strong>Belum ditangani {{ $namaTujuan }}</strong> —
                        @if ($telatBerikut > 0)
                            jadwalnya sudah lewat {{ $telatBerikut }} hari.
                        @elseif ($hariTak !== null)
                            belum dijadwalkan lagi, terakhir datang {{ $hariTak }} hari lalu.
                        @else
                            belum pernah datang sejak dipindahkan.
                        @endif
                    </span>
                    @php
                        /* Satu klik = dua hal: WhatsApp dibuka DARI gerakan klik
                           pengguna (kalau dibuka setelah form kembali, peramban
                           memblokirnya sebagai popup), lalu form tetap terkirim
                           untuk mencatat ort_reminder. */
                        $waIngat = $peta[$c->owner_place_id]['wa'] ?? null
                            ? app(\App\Services\Ortho\OrtBranches::class)->linkWaIngatkan(
                                (string) $c->owner_place_id,
                                $c->patient_name,
                                $c->omnicare_pid,
                                $namaCabang,
                                $c->next_sch_date && $c->next_sch_date->lt($hariIni)
                                    ? (int) $c->next_sch_date->diffInDays($hariIni) : null,
                              )
                            : null;
                    @endphp
                    <form method="POST" action="{{ route('ortho.ingatkan', $c->id) }}"
                          style="display:inline-flex;" onclick="event.stopPropagation()"
                          @if ($waIngat) onsubmit="window.open(this.dataset.wa, '_blank', 'noopener')" data-wa="{{ $waIngat }}" @endif>
                        @csrf
                        <button type="submit" class="ort-obtn"
                                title="{{ $waIngat ? 'Buka WhatsApp ' . $namaTujuan . ' dan catat pengingatnya' : 'Nomor WhatsApp ' . $namaTujuan . ' belum terdaftar — pengingat tetap tercatat' }}">
                            <i class="fab fa-whatsapp"></i> Ingatkan {{ $namaTujuan }}
                        </button>
                    </form>
                </div>
            @endif

        @empty
            <div style="padding:8px 0;">
                <div class="st-empty">
                    <div class="st-empty__title">Tidak ada pasien {{ $namaCabang }} di {{ $namaTujuan }}.</div>
                </div>
            </div>
        @endforelse

    </div>

    {{-- Pita kaki dibuang. Kalimatnya mengulang petunjuk yang sudah ada di
         baris tab cabang di atas ("pasien <kita> yang sedang dipegang di sana
         — hanya bisa dilihat"), dan hitungan "N perlu diingatkan" hampir
         selalu 0 sehingga cuma jadi baris kosong di bawah tabel. --}}
    @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'pasien'])
</div>
