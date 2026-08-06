{{--
    Satu baris tabel posisi. Var: $row (user/position/pending/division/branch),
    $divisions, $branches. Dipakai di dua tabel: pending & aktif.
--}}
<tr>
    <td>
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--cl-brown-2),var(--cl-brown)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0;">
                {{ strtoupper(mb_substr($row->user->nama ?? '?', 0, 1)) }}
            </div>
            <div style="min-width:0;">
                <div style="font-weight:600; color:var(--cl-ink);">{{ $row->user->nama ?? '—' }}</div>
                <div style="font-size:11px; color:var(--cl-subtle);">{{ $row->user->email ?? '—' }}</div>
            </div>
        </div>
    </td>
    <td>
        <span class="st-pill st-pill--neutral">{{ $row->user->group_name ?? '—' }}</span>
    </td>
    <td>
        {{-- Divisi otomatis dari role (terkunci) --}}
        @if($row->roleDivision)
            <span class="st-pill st-pill--success" title="Otomatis dari role">
                <i class="fas fa-lock" style="font-size:9px;"></i> {{ $row->roleDivision->name }}
            </span>
        @else
            <span style="color:var(--cl-subtle); font-style:italic; font-size:12px;">Role tanpa divisi</span>
        @endif
    </td>
    <td style="color:var(--cl-muted); font-size:12.5px;">
        {{ $row->branch?->name ?? '—' }}
        @if(isset($row->rolls) && $row->rolls->count())
            @foreach($row->rolls as $roll)
                <div style="margin-top:4px;">
                    <span class="st-pill st-pill--warning" title="Penempatan sementara (rolling)">
                        <i class="fas fa-people-arrows" style="font-size:9px;"></i>
                        {{ $roll->branch?->name ?? $roll->raw_branch_id }}
                        <span style="opacity:.8;">s/d {{ optional($roll->effective_to)->translatedFormat('d M') }}</span>
                    </span>
                </div>
            @endforeach
        @endif
    </td>
    <td>
        <form method="POST" action="{{ route('checklist.positions.assign') }}"
              style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            @csrf
            <input type="hidden" name="user_id" value="{{ $row->user->id }}">
            @php
                $prefill = $row->position;
                // Default cabang: posisi aktif kalau ada, kalau belum → cabang user (dari profil).
                $defaultBranch = optional($prefill)->raw_branch_id ?: ($row->user->raw_branch_id ?? '');
            @endphp
            {{-- Divisi tidak dipilih — otomatis dari role user, dikunci. Cabang otomatis dari cabang user, bisa diubah. --}}
            <select name="raw_branch_id" required class="cl-inline-select" style="flex:1; min-width:140px;">
                <option value="">— Cabang —</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ (string) $defaultBranch === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="st-btn-brand" style="padding:7px 14px; font-size:12px; white-space:nowrap;">
                <i class="fas fa-save"></i> {{ $row->position ? 'Update' : 'Set langsung' }}
            </button>
        </form>
    </td>
    <td style="text-align:center;">
        @if($row->position)
            <form method="POST" action="{{ route('checklist.positions.remove', $row->position->id) }}"
                  style="display:inline;"
                  onsubmit="return confirm('Keluarkan posisi {{ $row->user->nama }} dari {{ $row->division->name ?? '-' }}? Karyawan tidak akan bisa isi checklist sampai di-assign ulang.');">
                @csrf @method('DELETE')
                <button type="submit" class="st-icon-btn" title="Keluarkan (take out) posisi"
                        style="color:var(--cl-danger); border-color:var(--cl-danger-bg);">
                    <i class="fas fa-user-slash"></i>
                </button>
            </form>
        @else
            <span style="color:var(--cl-subtle); font-size:11px;">—</span>
        @endif
    </td>
</tr>
