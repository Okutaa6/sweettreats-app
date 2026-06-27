{{--
    Bare layout — markup TANPA shell (header/sidebar/footer/script app).
    Dipakai saat form Edit/Tambah reservasi di-load ke DRAWER via AJAX (?drawer=1).
    Hanya @yield('css') + @yield('content'); JS besar form di-handle file statik
    (public/assets/js/reservation-form.js) yang dipanggil oleh halaman list setelah inject.
    Halaman standalone (tanpa ?drawer=1) tetap pakai layouts.app yang lengkap.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @yield('css')
</head>
<body class="rr-bare-body">
    @yield('content')
</body>
</html>
