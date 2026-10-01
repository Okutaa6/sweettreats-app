{{--
    Panel filter yang bisa dilipat — design brief §6.

    ⚠ TIDAK ADA field cabang di sini. Cabang dikunci ke akun, jadi ia bukan
      filter; menaruhnya sebagai dropdown akan menyiratkan bisa diganti.
    ⚠ Placeholder pencarian menyebut nama dan nomor telepon saja — TIDAK
      pernah nomor RM (§22 dan aturan domain 18).
--}}
<div class="ort-filter" data-ort-filter-panel {{ empty($filter) ? 'hidden' : '' }}>
    <form method="GET" action="{{ route('ortho.index') }}">
        {{-- Pertahankan konteks yang bukan filter. --}}
        @foreach (['tab', 'urgensi', 'cabang', 'kartu'] as $bawa)
            @if (request($bawa))
                <input type="hidden" name="{{ $bawa }}" value="{{ request($bawa) }}">
            @endif
        @endforeach

        <div class="ort-filter__r1">
            <div>
                <label class="ort-lbl">Pencarian</label>
                <span class="ort-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" class="ort-inp"
                           value="{{ $filter['q'] ?? '' }}"
                           placeholder="Nama atau no. telepon...">
                </span>
            </div>

            <div>
                <label class="ort-lbl">Dokter ortho</label>
                <select name="dokter" class="ort-inp">
                    <option value="">Semua dokter</option>
                    @foreach ($dokterPilihan as $id => $nama)
                        <option value="{{ $id }}" {{ ($filter['dokter'] ?? '') === (string) $id ? 'selected' : '' }}>
                            {{ $nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="ort-lbl">Status</label>
                <select name="status" class="ort-inp">
                    <option value="">Semua status</option>
                    @foreach (['Menunggu', 'Sudah di-FU', 'Ada reservasi', 'Terlewat',
                               'Reservasi batal', 'Usulan jadwal', 'Transfer'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach

                    {{-- Saringan kerja harian, dipisah garis karena menjawab
                         pertanyaan yang beda: bukan "baris ini statusnya apa"
                         melainkan "apa yang harus saya kerjakan sekarang".
                         "Saya FU hari ini" hanya muncul kalau sesinya memang
                         punya nama — tanpa itu saringannya tidak punya arti. --}}
                    <optgroup label="Kerja hari ini">
                        <option value="Belum pernah di-FU" {{ request('status') === 'Belum pernah di-FU' ? 'selected' : '' }}>
                            Belum pernah di-FU
                        </option>
                        @if (filled(session('name')))
                            <option value="Saya FU hari ini" {{ request('status') === 'Saya FU hari ini' ? 'selected' : '' }}>
                                Saya FU hari ini
                            </option>
                        @endif
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="ort-filter__r2">
            <div>
                <label class="ort-lbl">Jadwal mulai</label>
                <input type="date" name="dari" class="ort-inp" value="{{ $filter['dari'] ?? '' }}">
            </div>
            <div>
                <label class="ort-lbl">Jadwal akhir</label>
                <input type="date" name="sampai" class="ort-inp" value="{{ $filter['sampai'] ?? '' }}">
            </div>
            <div class="ort-filter__act">
                <a href="{{ route('ortho.index', array_filter(['tab' => request('tab'), 'cabang' => request('cabang')])) }}"
                   class="ort-obtn"><i class="fas fa-undo" style="font-size:10px;"></i> Reset</a>
                <button type="submit" class="ort-btn">Terapkan</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var tombol = document.querySelector('[data-ort-filter]');
    var panel  = document.querySelector('[data-ort-filter-panel]');
    if (tombol && panel) {
        tombol.addEventListener('click', function () { panel.hidden = !panel.hidden; });
    }
})();
</script>
