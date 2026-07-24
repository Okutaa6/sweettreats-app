{{-- Node bagan organisasi terpusat (rekursif). Butuh: $node (GroupPath), $memberCount, $divisionsByGroup (keyed by role id). --}}
@php
    $kindClass = $node->parent_id ? 'k-child' : 'k-root';
    $cnt = $memberCount[$node->id] ?? 0;
    $nodeDivs = ($divisionsByGroup[$node->id] ?? collect());
@endphp
<li>
    <div class="oc-node {{ $kindClass }} org-clickable" draggable="true" data-pos="{{ $node->id }}" data-pos-name="{{ $node->nama }}"
         data-id="{{ $node->id }}" data-parent="{{ $node->parent_id }}" data-name="{{ $node->nama }}"
         title="Tarik ke role lain untuk ganti atasan · klik untuk lihat anggota">
        <div class="oc-node__name">{{ $node->nama }}</div>

        <div class="oc-node__holder {{ $cnt === 0 ? 'empty' : '' }}">
            @if ($cnt > 0)
                <i class="fas fa-users"></i> {{ $cnt }} anggota
            @else
                <i class="fas fa-user-slash"></i> belum ada anggota
            @endif
        </div>

        @if ($nodeDivs->isNotEmpty())
            <div class="oc-node__divs">
                @foreach ($nodeDivs as $d)
                    <span class="oc-div-chip oc-div-chip--inline" title="Divisi operasional role ini (otomatis dari nama role)">
                        <i class="fas fa-clipboard-check"></i> {{ $d->name }}
                    </span>
                @endforeach
            </div>
        @endif

        <div class="oc-node__tools">
            <a href="{{ route('setgroup.edit', $node->id) }}" title="Edit role"><i class="fas fa-edit"></i> Edit</a>
            <a class="add" href="{{ route('setgroup.new', ['parent' => $node->id, 'tab' => 'struktur']) }}" title="Tambah bawahan"><i class="fas fa-plus"></i></a>
        </div>
    </div>

    @if ($node->children->count() > 0)
        <ul>
            @foreach ($node->children as $child)
                @include('set.group._orgnode', ['node' => $child, 'memberCount' => $memberCount, 'divisionsByGroup' => $divisionsByGroup])
            @endforeach
        </ul>
    @endif
</li>
