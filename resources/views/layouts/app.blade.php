<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard Monitoring System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/metisMenu.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/customize.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/multi-select/multi-select.css') }}" media="screen" rel="stylesheet"
        type="text/css">
    <link rel="stylesheet" href="{{ asset('assets/plugins/datatables/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.sweettreats.css') }}">
    @yield('css')
</head>

<body>

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
            <script src="{{ asset('assets/js/app.js') }}"></script>

            <!-- custom script -->
            @yield('js')

            <!-- footer -->
            @include('layouts.footer')
            <!-- footer -->
        </div>
    </div>
</body>

</html>
