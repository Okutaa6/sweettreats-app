@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Program Rewards — Sweet Mates</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── HEADER ─── --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #c4ebe9; background:#fff;">
                        <div>
                            <h5 style="margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-0.01em; color:#0a4f4d; display:flex; align-items:center; gap:8px;">
                                <i class="fa fa-gift" style="font-size:16px; color:#13928e;"></i>
                                Program Rewards — Sweet Mates
                            </h5>
                            <small style="color:#5a7572; font-size:13px;">
                                KOC &amp; KOL yang aktif sebagai member program rewards
                                — total <strong style="color:#0a4f4d;">{{ $counts['all'] }}</strong> Sweet Mates
                            </small>
                        </div>
                    </div>

                    {{-- ─── STAT CARDS ─── --}}
                    @include('crud.sweet-mates._stat_cards', ['counts' => $counts])

                </div> {{-- end .st-card__body (top card) --}}
            </div> {{-- end .st-card (top card) --}}

            {{-- ═══ CARD KEDUA: Tier tabs + Filter + Table ═══ --}}
            <div class="st-card mt-3">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── TIER TABS ─── --}}
                    @include('crud.sweet-mates._tier_tabs', [
                        'filterTier' => $filterTier,
                        'counts'     => $counts,
                    ])

                    {{-- ─── FILTER BAR ─── --}}
                    <form method="GET" action="{{ route('sweetmates') }}" class="st-filter-bar" style="border-bottom:1px solid #c4ebe9; padding:10px 16px;">
                        <input type="hidden" name="tier" value="{{ $filterTier }}">

                        <label>Cabang</label>
                        <select name="branch" onchange="this.form.submit();">
                            <option value="all" {{ $filterBranch === 'all' ? 'selected' : '' }}>Semua Cabang</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $filterBranch === $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>

                        <label>Tipe</label>
                        <select name="type" onchange="this.form.submit();">
                            <option value="all" {{ $filterType === 'all' ? 'selected' : '' }}>Semua Tipe (KOC + KOL)</option>
                            <option value="koc" {{ $filterType === 'koc' ? 'selected' : '' }}>KOC saja</option>
                            <option value="kol" {{ $filterType === 'kol' ? 'selected' : '' }}>KOL saja</option>
                        </select>

                        <label>Cari</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama / username / kode referral...">

                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>

                        @if($search || $filterBranch !== 'all' || $filterType !== 'all')
                            <a href="{{ route('sweetmates', ['tier' => $filterTier]) }}" class="st-btn-outline-brown">
                                <i class="fa fa-undo"></i> Reset
                            </a>
                        @endif

                        <div class="ml-auto st-muted" style="font-size:12px;">
                            Menampilkan <strong class="st-primary">{{ $members->count() }}</strong> dari <strong class="st-primary">{{ $members->total() }}</strong> Sweet Mates
                        </div>
                    </form>

                    {{-- ─── TABLE ─── --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Member</th>
                                    <th width="10%" style="text-align:center;">Tier</th>
                                    <th width="14%">Kode Referral</th>
                                    <th width="22%">Progress Konten</th>
                                    <th width="13%" style="text-align:right;">Komisi</th>
                                    <th width="8%" style="text-align:center;" title="Jumlah kunjungan kontrol di Omnicare. Hanya info — bukan syarat reward.">Kontrol</th>
                                    <th width="13%" style="text-align:center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($members as $key => $row)
                                    @php
                                        $tier = $row->chosenTier();
                                        $approvedCount = $row->saldo_konten ?? 0;  // saldo: konten approved yang belum dimakan reward
                                        $required = $tier ? (int) $tier->required_content_count : 0;
                                        $percent = $required > 0 ? min(100, (int) round(($approvedCount / $required) * 100)) : 0;
                                        $achieved = $approvedCount >= $required;
                                        $remaining = max(0, $required - $approvedCount);
                                        
                                        // Tier styling
                                        $tierClass = match($row->reward_program) {
                                            '3x'        => 'sm-tier-badge--bronze',
                                            '12x'       => 'sm-tier-badge--silver',
                                            'above_12x' => 'sm-tier-badge--gold',
                                            default     => '',
                                        };
                                        $tierIcon = match($row->reward_program) {
                                            '3x'        => 'fa-medal',
                                            '12x'       => 'fa-award',
                                            'above_12x' => 'fa-crown',
                                            default     => 'fa-gift',
                                        };
                                        $tierLabel = match($row->reward_program) {
                                            '3x'        => 'Bronze',
                                            '12x'       => 'Silver',
                                            'above_12x' => 'Gold',
                                            default     => 'Member',
                                        };
                                        
                                        // Username display
                                        $username = null;
                                        if ($row->social_tiktok)         $username = '@' . $row->social_tiktok;
                                        elseif ($row->social_instagram)  $username = '@' . $row->social_instagram;
                                        elseif ($row->social_username)   $username = '@' . $row->social_username;
                                        
                                        // Promo code (ambil yang aktif paling pertama)
                                        $activePromo = $row->promos->first();
                                        
                                        // Status klaim SIKLUS INI (bukan sembarang klaim tier ini)
                                        $klaim      = $row->klaim_siklus;
                                        $klaimStatus = $klaim?->status;
                                        $hasClaimed = in_array($klaimStatus, ['claimed', 'used'], true);
                                    @endphp
                                    
                                    <tr data-href="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}" style="cursor:pointer;">
                                        <td class="st-num">{{ $members->firstItem() + $key }}</td>

                                        {{-- Member info --}}
                                        <td>
                                            <div class="st-primary" style="font-weight:500;">
                                                {{ $row->name ?: $username }}
                                            </div>
                                            <small style="color:#7a9290; font-size:11px;">
                                                @if($row->name && $username) {{ $username }} · @endif
                                                {{ strtoupper($row->type) }}
                                                @if($row->branch) · {{ $row->branch->name }} @endif
                                            </small>
                                        </td>

                                        {{-- Tier --}}
                                        <td style="text-align:center;">
                                            <span class="sm-tier-badge {{ $tierClass }}" title="{{ $tier->name ?? '' }}">
                                                <i class="fas {{ $tierIcon }}"></i> {{ $tierLabel }}
                                            </span>
                                        </td>

                                        {{-- Promo code --}}
                                        <td>
                                            @if($activePromo)
                                                <code class="sm-promo-code">{{ $activePromo->promo_code }}</code>
                                            @else
                                                <span style="color:#bbb; font-size:11px;">Belum ada</span>
                                            @endif
                                        </td>

                                        {{-- Progress konten --}}
                                        <td>
                                            <div class="sm-progress">
                                                <div class="sm-progress__header">
                                                    <span class="sm-progress__current {{ $achieved ? 'sm-progress__current--achieved' : '' }}">
                                                        {{ $approvedCount }}
                                                    </span>
                                                    <span class="sm-progress__total">/ {{ $required }} konten</span>
                                                    
                                                    @if($achieved && !$hasClaimed)
                                                        <span class="sm-progress__percent sm-progress__percent--achieved">
                                                            <i class="fas fa-trophy"></i> Eligible
                                                        </span>
                                                    @elseif($achieved)
                                                        <span class="sm-progress__percent sm-progress__percent--achieved">100%</span>
                                                    @else
                                                        <span class="sm-progress__percent sm-progress__percent--in-progress">{{ $percent }}%</span>
                                                    @endif
                                                </div>
                                                <div class="sm-progress__bar">
                                                    <div class="sm-progress__fill {{ $achieved ? 'sm-progress__fill--achieved' : 'sm-progress__fill--in-progress' }}"
                                                         style="width: {{ $percent }}%;"></div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Komisi --}}
                                        <td style="text-align:right; font-weight:500;">
                                            @if($row->total_commission)
                                                Rp {{ number_format((float) $row->total_commission, 0, ',', '.') }}
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Kontrol — hanya info, bukan syarat klaim --}}
                                        <td style="text-align:center;">
                                            @include('crud.koc._kontrol', ['k' => $kontrol[$row->id] ?? null, 'affiliateId' => $row->id])
                                        </td>

                                        {{-- Status --}}
                                        <td style="text-align:center;">
                                            @if($klaimStatus === 'used')
                                                <span class="sm-status-badge sm-status-badge--eligible"
                                                      title="Diberikan {{ $klaim->processed_at?->format('d M Y H:i') }}{{ $klaim->processor ? ' oleh ' . $klaim->processor->nama : '' }}">
                                                    Sudah Diberikan
                                                </span>
                                                <br><small style="color:#7a9290; font-size:10.5px;">{{ $klaim->processed_at?->format('j M Y') }}</small>
                                            @elseif($klaimStatus === 'claimed')
                                                <span class="sm-status-badge sm-status-badge--claimed">Menunggu Diberikan</span>
                                                <br><small style="color:#7a9290; font-size:10.5px;">diklaim {{ $klaim->claimed_at?->format('j M') }}</small>
                                                <br>
                                                <button type="button" class="st-btn-brand sm-btn-diberikan"
                                                        style="margin-top:6px; font-size:11px; padding:4px 10px;"
                                                        data-url="{{ route('sweetmates.klaim.diberikan', $klaim->id) }}"
                                                        data-nama="{{ $row->name ?: $username }}"
                                                        data-reward="{{ $tier?->name }}{{ $tier?->reward_description ? ' — ' . $tier->reward_description : '' }}"
                                                        data-konten="{{ $required }}">
                                                    <i class="fa fa-check"></i> Reward sudah diberikan
                                                </button>
                                            @elseif($achieved)
                                                <span class="sm-status-badge sm-status-badge--eligible">Bisa Klaim</span>
                                            @else
                                                <span class="sm-status-badge sm-status-badge--in-progress">{{ $remaining }} lagi</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <div class="st-empty-state">
                                                <i class="fas fa-trophy" aria-hidden="true"></i>
                                                <div class="st-empty-state__title">
                                                    @if($filterTier === 'eligible')
                                                        Belum ada member yang eligible untuk klaim
                                                    @elseif($filterTier !== 'all')
                                                        Belum ada member tier {{ ucfirst($filterTier) }}
                                                    @else
                                                        Belum ada Sweet Mates
                                                    @endif
                                                </div>
                                                <div class="st-empty-state__desc">
                                                    @if($search || $filterBranch !== 'all' || $filterType !== 'all')
                                                        Coba ubah filter atau kata kunci pencarian.
                                                    @else
                                                        Member Sweet Mates muncul di sini setelah KOC/KOL memilih program rewards (Bronze/Silver/Gold)
                                                        di portal mereka.
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- ─── PAGINATION ─── --}}
                    @if($members->hasPages())
                        <div style="padding:12px 20px; border-top:1px solid #c4ebe9; background:#f4faf9;">
                            {{ $members->links() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Konfirmasi "Reward sudah diberikan" — menyentuh uang, jadi nama, reward,
     dan jumlah konten yang dimakan dibaca ulang sebelum dikirim. Tak ada
     tombol urungkan sesudahnya (disengaja). --}}
<div class="modal fade" id="modalDiberikan" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" id="formDiberikan">
            @csrf
            <div class="modal-content" style="border-radius:10px;">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-gift mr-2"></i> Tandai reward sudah diberikan</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <p style="margin-bottom:10px;">
                        <strong data-isi="nama"></strong> menerima <strong data-isi="reward"></strong>.
                    </p>
                    <p style="margin-bottom:10px; color:#5a4a3c;">
                        <strong data-isi="konten"></strong> konten approved akan terpakai. Sisanya terbawa ke program berikutnya.
                        KOC bisa pindah program sesudah ini.
                    </p>
                    <p style="margin-bottom:12px; color:#b2641b;">
                        <i class="fa fa-exclamation-triangle"></i> Tidak bisa dibatalkan dari layar ini. Pastikan reward-nya memang sudah diberikan.
                    </p>
                    <label style="font-weight:600;">Catatan (opsional)</label>
                    <textarea name="catatan" rows="2" maxlength="500" class="form-control" placeholder="mis. Scaling di Bintaro 26 Sep, drg. …"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand"><i class="fa fa-check"></i> Ya, sudah diberikan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.sm-btn-diberikan').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var modal = document.getElementById('modalDiberikan');
            document.getElementById('formDiberikan').action = btn.dataset.url;
            modal.querySelector('[data-isi="nama"]').textContent   = btn.dataset.nama;
            modal.querySelector('[data-isi="reward"]').textContent = btn.dataset.reward;
            modal.querySelector('[data-isi="konten"]').textContent = btn.dataset.konten;
            modal.querySelector('textarea[name="catatan"]').value = '';
            $(modal).modal('show');
        });
    });

    document.querySelectorAll('.st-table--clickable tr[data-href]').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input, label, .st-pill, .sm-tier-badge, .sm-promo-code, code')) return;
            window.location = row.dataset.href;
        });
    });
</script>
@endsection
