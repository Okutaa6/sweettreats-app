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
        @if($row->division)
            <span class="st-pill st-pill--success">{{ $row->division->name }}</span>
        @elseif($row->pending)
            <span style="display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:700; background:#FAEEDA; color:#A66019; border:1px solid #EBD6B4; padding:3px 10px; border-radius:999px;">
                <i class="fas fa-hourglass-half" style="font-size:9px;"></i> {{ $row->pending->division?->name ?? '—' }}
            </span>
        @else
            <span style="color:var(--cl-subtle); font-style:italic; font-size:12px;">Belum di-assign</span>
        @endif
    </td>
    <td style="color:var(--cl-muted); font-size:12.5px;">
        {{ $row->branch?->name ?? $row->pending?->branch?->name ?? '—' }}
    </td>
    <td>
        <form method="POST" action="{{ route('checklist.positions.assign') }}"
              style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            @csrf
            <input type="hidden" name="user_id" value="{{ $row->user->id }}">
            @php $prefill = $row->position ?? $row->pending; @endphp
            <select name="division_id" required class="cl-inline-select" style="flex:1; min-width:130px;">
                <option value="">— Divisi —</option>
                @foreach($divisions as $div)
                    <option value="{{ $div->id }}" {{ optional($prefill)->division_id === $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                @endforeach
            </select>
            <select name="raw_branch_id" required class="cl-inline-select" style="flex:1; min-width:140px;">
                <option value="">— Cabang —</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ optional($prefill)->raw_branch_id === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
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
                  onsubmit="return confirm('Nonaktifkan posisi {{ $row->user->nama }} dari {{ $row->division->name ?? '-' }}? Karyawan tidak akan bisa isi checklist sampai di-assign ulang.');">
                @csrf @method('DELETE')
                <button type="submit" class="st-icon-btn" title="Nonaktifkan posisi"
                        style="color:var(--cl-danger); border-color:var(--cl-danger-bg);">
                    <i class="fas fa-user-slash"></i>
                </button>
            </form>
        @elseif($row->pending)
            <div style="display:inline-flex; gap:5px;">
                <form method="POST" action="{{ route('checklist.positions.confirm', $row->pending->id) }}" style="display:inline;"
                      onsubmit="return confirm('Konfirmasi posisi {{ $row->user->nama }} sebagai {{ $row->pending->division?->name ?? '-' }} ({{ $row->pending->branch?->name ?? '-' }})?');">
                    @csrf
                    <button type="submit" class="st-icon-btn" title="Konfirmasi usulan"
                            style="color:var(--cl-success); border-color:var(--cl-success-bg);">
                        <i class="fas fa-check"></i>
                    </button>
                </form>
                <form method="POST" action="{{ route('checklist.positions.reject', $row->pending->id) }}" style="display:inline;"
                      onsubmit="return confirm('Tolak usulan posisi {{ $row->user->nama }}? Usulan akan dihapus.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="st-icon-btn" title="Tolak usulan"
                            style="color:var(--cl-danger); border-color:var(--cl-danger-bg);">
                        <i class="fas fa-times"></i>
                    </button>
                </form>
            </div>
        @else
            <span style="color:var(--cl-subtle); font-size:11px;">—</span>
        @endif
    </td>
</tr>
