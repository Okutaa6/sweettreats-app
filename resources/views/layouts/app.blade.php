<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard Monitoring System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('sysprofile/' . session('syslogo')) }}">

    <!-- Plugins css -->
    <link href="{{ asset('assets/plugins/daterangepicker/daterangepicker.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/plugins/select2/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.css') }}" rel="stylesheet"
        type="text/css" />
    <link href="{{ asset('assets/plugins/timepicker/bootstrap-material-datetimepicker.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/bootstrap-touchspin/css/jquery.bootstrap-touchspin.min.css') }}"
        rel="stylesheet" />
    <link href="{{ asset('assets/plugins/sweet-alert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/metisMenu.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/multi-select/multi-select.css') }}" media="screen" rel="stylesheet"
        type="text/css">
    <link rel="stylesheet" href="{{ asset('assets/plugins/datatables/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/style.sweettreats.css')) ?: '1' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/components.sweettreats.css')) ?: '1' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-marketing.css') }}?v={{ @filemtime(public_path('assets/css/theme-marketing.css')) ?: '1' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/themes.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/themes.sweettreats.css')) ?: '1' }}">
    {{-- Shell redesign (fondasi) — sidebar gelap global, hanya tema default. Di-load paling akhir agar menang. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/shell-redesign.css') }}?v={{ @filemtime(public_path('assets/css/shell-redesign.css')) ?: '1' }}">
    @yield('css')
</head>
@php
    // Theme detection — Marketing pages dapat accent teal (vs default brown untuk Sales/operasional).
    // Future EP routes (editorial-plan, breakdown-konten, campaign-framework) sudah di-anticipate.
    $marketingRoutes = [
        'rawaffiliate', 'rawkeyword', 'rawpromo', 'report-referral',
        'commission-claim', 'content-review',
        'konten-pillar', 'ep-konten', 'ep-breakdown', 'ep-campaign',
        'kol', 'koc',
        'sweet-mates',
    ];
    $currentSegment = request()->segment(1);
    $bodyTheme = in_array($currentSegment, $marketingRoutes, true) ? 'marketing' : 'default';

    // Role-based color theme — di-set per role via Kelola Halaman Raw Reservation.
    // Pull dari DB tiap request (bukan session) supaya:
    //   - User dengan session lama (belum punya group_theme key) tetap dapat tema yang benar
    //   - Theme yang diubah admin langsung apply tanpa logout-login
    $groupTheme = 'theme-default';
    $sessGid = session('group_id');
    if ($sessGid) {
        try {
            $dbTheme = \Illuminate\Support\Facades\DB::table('group_path')
                ->where('id', $sessGid)
                ->value('theme');
            if ($dbTheme) $groupTheme = $dbTheme;
        } catch (\Throwable $e) {
            // Kolom belum ada (SQL migration belum jalan) → fallback ke default
        }
    }

    // Kalau role user pakai theme-marketing, paksa data-theme="marketing"
    // di SELURUH halaman (bukan cuma marketing routes). Ini bikin
    // theme-marketing.css aktif global → sidebar, header, tombol, dll
    // semua ikut palette teal.
    if ($groupTheme === 'theme-marketing') {
        $bodyTheme = 'marketing';
    }

    // Redesign reservasi (Fase 1) — di-scope HANYA ke halaman reservasi via body-class.
    // CSS-nya (reservasi-redesign.css) di-load dari list.blade.php @section('css').
    $reservasiRedesign = ($currentSegment === 'rawreservation') ? ' redesign-reservasi' : '';
@endphp

<body data-theme="{{ $bodyTheme }}" class="{{ $groupTheme }}{{ $reservasiRedesign }}">

    <!-- Top Bar Start -->
    @include('layouts.header')
    <!-- Top Bar End -->

    <div class="page-wrapper">
        <!-- Left Sidenav -->
        @include('layouts.sidebar')
        <!-- end left-sidenav-->

        <!-- Page Content-->
        <div class="page-content">
            <!-- jQuery  -->
            <script src="{{ asset('assets/js/jquery.min.js') }}"></script>


            @yield('content')

            <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

            <script src="{{ asset('assets/plugins/moment/moment.js') }}"></script>
            <script src="{{ asset('assets/plugins/daterangepicker/daterangepicker.js') }}"></script>
            <script src="{{ asset('assets/plugins/select2/select2.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/timepicker/bootstrap-material-datetimepicker.js') }}"></script>
            <script src="{{ asset('assets/plugins/bootstrap-maxlength/bootstrap-maxlength.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/bootstrap-touchspin/js/jquery.bootstrap-touchspin.min.js') }}"></script>

            <script src="{{ asset('assets/js/jquery.priceformat.min.js') }}" type="text/javascript"></script>
            <script src="{{ asset('assets/js/jquery.chained.js') }}"></script>

            <script src="{{ asset('assets/plugins/multi-select/jquery.multi-select.js') }}" type="text/javascript"></script>

            <script src="{{ asset('assets/js/metisMenu.min.js') }}"></script>
            <script src="{{ asset('assets/js/waves.min.js') }}"></script>
            <script src="{{ asset('assets/js/jquery.slimscroll.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/sweet-alert2/sweetalert2.min.js') }}"></script>

            <script src="{{ asset('assets/pages/jquery.forms-advanced.js') }}"></script>
            <script src="{{ asset('assets/plugins/datatables/dataTables.min.js') }}"></script>
            <!-- App js -->
            <script src="{{ asset('assets/js/app.js') }}?v={{ @filemtime(public_path('assets/js/app.js')) ?: '1' }}"></script>

            {{-- Clickable table rows --}}
            <script>
            $(document).on('click', '.st-table--clickable tbody tr[data-href]', function(e) {
                // Baris yang punya data-rr dikelola halaman (mis. Detail drawer reservasi) —
                // jangan navigate, biar tidak dobel dengan handler drawer.
                if (this.hasAttribute('data-rr')) return;
                if ($(e.target).closest('a, button, input, select, textarea').length) return;
                window.location = $(this).data('href');
            });
            </script>

            {{-- Notification polling --}}
            <script>
            (function(){
                var pollInterval = 30000;
                var notifCountEl = document.getElementById('notifCount');
                var notifListEl  = document.getElementById('notifList');
                var markAllBtn   = document.getElementById('markAllRead');

                function escNotif(str) {
                    if (!str) return '';
                    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                }

                function timeAgo(dateStr) {
                    var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
                    if (diff < 60) return 'Baru saja';
                    if (diff < 3600) return Math.floor(diff / 60) + 'm lalu';
                    if (diff < 86400) return Math.floor(diff / 3600) + 'j lalu';
                    return Math.floor(diff / 86400) + 'h lalu';
                }

                function typeIcon(type) {
                    var icons = {reservation:'dripicons-calendar',status_change:'dripicons-toggles',commission:'dripicons-wallet',system:'dripicons-gear'};
                    return icons[type] || 'dripicons-bell';
                }

                function fetchNotifications() {
                    $.get("{{ route('api.notifications') }}", function(data) {
                        if (data.count > 0) {
                            notifCountEl.textContent = data.count > 99 ? '99+' : data.count;
                            notifCountEl.style.display = '';
                        } else {
                            notifCountEl.style.display = 'none';
                        }

                        if (data.notifications.length === 0) {
                            notifListEl.innerHTML = '<div class="text-center text-muted py-3" style="font-size:0.8rem;">Tidak ada notifikasi baru</div>';
                            return;
                        }

                        var html = '';
                        data.notifications.forEach(function(n) {
                            html += '<a href="' + (n.link || '#') + '" class="dropdown-item d-flex align-items-start notif-item" data-id="' + n.id + '" style="white-space:normal;padding:10px 15px;border-bottom:1px solid #f0f0f0;">';
                            html += '<i class="' + typeIcon(n.type) + ' mr-2 mt-1" style="font-size:1rem;color:#888;"></i>';
                            html += '<div style="flex:1;min-width:0;">';
                            html += '<div style="font-size:0.82rem;font-weight:600;color:#333;">' + escNotif(n.title) + '</div>';
                            if (n.body) html += '<div style="font-size:0.75rem;color:#777;margin-top:2px;">' + escNotif(n.body) + '</div>';
                            html += '<div style="font-size:0.7rem;color:#aaa;margin-top:3px;">' + timeAgo(n.created_at) + '</div>';
                            html += '</div></a>';
                        });
                        notifListEl.innerHTML = html;
                    }).fail(function(){});
                }

                $(document).on('click', '.notif-item', function() {
                    var id = $(this).data('id');
                    if (id) {
                        $.ajax({ url: "{{ url('api/notifications') }}/" + id + "/read", method: 'PATCH', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} });
                    }
                });

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $.post("{{ route('api.notifications.read-all') }}", {_token: '{{ csrf_token() }}'}, function() {
                            notifCountEl.style.display = 'none';
                            notifListEl.innerHTML = '<div class="text-center text-muted py-3" style="font-size:0.8rem;">Tidak ada notifikasi baru</div>';
                        });
                    });
                }

                fetchNotifications();
                setInterval(fetchNotifications, pollInterval);
            })();
            </script>

            <!-- custom script -->
            @yield('js')

            {{-- Remember pagination state of list pages, restore on Kembali --}}
            <script>
            (function () {
                const path = window.location.pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
                const segment = path[0] || '';
                if (!segment) return;

                const isListPage = path.length === 1; // mis. /customer atau /rawreservation
                if (isListPage) {
                    try { sessionStorage.setItem('lastListUrl:' + segment, window.location.href); } catch (e) {}
                    return; // di halaman list, tidak perlu rewrite tombol kembali
                }

                // Halaman edit/detail → rewrite tombol Kembali ke list URL terakhir
                const saved = sessionStorage.getItem('lastListUrl:' + segment);
                if (!saved) return;

                document.querySelectorAll('a[href]').forEach(function (a) {
                    try {
                        const u = new URL(a.href, window.location.origin);
                        if (u.origin !== window.location.origin) return;
                        const aPath = u.pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
                        // Cocokkan: link 1 segmen, segmen sama dengan halaman saat ini, tanpa query
                        if (aPath.length === 1 && aPath[0] === segment && !u.search) {
                            a.href = saved;
                        }
                    } catch (e) {}
                });
            })();
            </script>

            <!-- footer -->
            @include('layouts.footer')
            <!-- footer -->
        </div>
    </div>

    {{-- Floating button untuk kirim keluhan (visible for Sales role only) --}}
    @include('layouts._complaint_floating')
</body>

</html>
