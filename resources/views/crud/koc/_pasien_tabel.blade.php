{{-- Tabel pilihan pasien. Butuh: $baris, $kosong, $affiliate, $kontrolPasien, $pidPasien --}}
<div class="st-table-wrap--scroll">
    <table class="st-table">
        <thead>
            <tr>
                <th width="5%"></th>
                <th>Nama</th>
                <th>Lahir</th>
                <th>HP</th>
                <th>pid Omnicare</th>
                <th style="text-align:center;">Kontrol</th>
            </tr>
        </thead>
        <tbody>
            @forelse($baris as $p)
                @php $k = $kontrolPasien[$p->id] ?? ['jumlah' => 0, 'terakhir' => null]; @endphp
                <tr>
                    <td style="text-align:center;">
                        <input type="radio" name="raw_customer_id" value="{{ $p->id }}" id="pas-{{ $p->id }}"
                               {{ $affiliate->raw_customer_id === $p->id ? 'checked' : '' }}>
                    </td>
                    <td><label for="pas-{{ $p->id }}" style="margin:0; cursor:pointer; font-weight:500;">{{ $p->name }}</label></td>
                    <td>{{ $p->tanggal_lahir ? \Carbon\Carbon::parse($p->tanggal_lahir)->format('d M Y') : '—' }}</td>
                    <td style="font-size:12px;">{{ $p->phone ?: '—' }}</td>
                    <td style="font-size:12px;">{{ implode(', ', $pidPasien[$p->id] ?? []) ?: '—' }}</td>
                    <td style="text-align:center;">
                        {{ $k['jumlah'] }}×
                        @if($k['terakhir'])
                            <br><small style="color:#7a6a5c;">terakhir {{ \Carbon\Carbon::parse($k['terakhir'])->format('j M Y') }}</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#9a8a7c; text-align:center; padding:16px;">{{ $kosong }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
