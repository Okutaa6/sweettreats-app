{{--
    Toast + Urungkan — design brief §11 & §17.

    Muncul setelah aksi tulis, hidup 8 detik, lalu pergi sendiri. Tombol
    Urungkan memulihkan keadaan SEBELUM aksi (token cache 90 detik di
    OrthoController::simpanUrungkan). Ini pengganti dialog "Anda yakin?":
    lebih cepat untuk pekerjaan yang diulang puluhan kali sehari, dan tetap
    bisa dibatalkan.
--}}
@if (session('ort_toast'))
    @php
        /* Identitas per-toast. Tanpa ini timer toast LAMA mencari
           `[data-ort-toast]` saat ia berbunyi — dan yang ditemukannya justru
           toast BARU yang muncul di detik ke-3, lalu membunuhnya di detik ke-8
           padahal umurnya baru 5 detik. Terjadi tiap kali dua aksi tulis
           beruntun, yang di halaman ini justru hal biasa. */
        $idToast = 'ort-toast-' . bin2hex(random_bytes(5));
    @endphp
    <div class="ort-toast" role="status" data-ort-toast="{{ $idToast }}">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('ort_toast') }}</span>

        @if (session('ort_undo'))
            <form method="POST" action="{{ route('ortho.urungkan', session('ort_undo')) }}">
                @csrf
                <button type="submit" class="ort-toast__undo">Urungkan</button>
            </form>
        @endif

        <button type="button" class="ort-toast__x" aria-label="Tutup"
                onclick="this.closest('.ort-toast').remove()">&times;</button>
    </div>

    <script>
    /* Pergi sendiri setelah 8 detik — Urungkan masih berlaku 90 detik di
       server, jadi toast yang hilang tidak menutup pintu; ia cuma berhenti
       menghalangi layar.

       Elemennya dipegang SEKARANG, bukan dicari saat timer berbunyi: timer
       yang mencari belakangan akan menemukan toast yang salah. */
    (function () {
        var t = document.querySelector('[data-ort-toast="{{ $idToast }}"]');
        if (! t) { return; }
        setTimeout(function () {
            if (! t.isConnected) { return; }   // sudah diganti toast lain
            t.classList.add('is-out');
            setTimeout(function () { t.remove(); }, 260);
        }, 8000);
    })();
    </script>
@endif
