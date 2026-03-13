<!DOCTYPE html>
<html lang="en">
<meta charset="utf-8" />
<title>{{ $sysprofile['systitle'] }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- App favicon -->
<link rel="shortcut icon" href="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}">

<!-- App css -->
<link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/css/style.css') }}" rel="stylesheet" type="text/css" />
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body class="account-body">
    <!-- Reset Password page -->
    <div class="row vh-100 ">
        <div class="col-12 align-self-center">
            <div class="auth-page">
                <div class="card auth-card shadow-lg">
                    <div class="card-body">
                        <div class="px-3">
                            <div class="auth-logo-box">
                                <a href="#" class="logo logo-admin">
                                    <img src="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}" height="55"
                                        alt="logo" class="auth-logo" style="border-radius: 16px; padding:8px">
                                </a>
                            </div>
                            <!--end auth-logo-box-->
                            <div class="text-center auth-logo-text">
                                <h4 class="mt-0 mb-3 mt-5">Reset Password</h4>
                                <p class="text-muted ">{{ $sysprofile['systitle'] }}</p>
                            </div>
                            @include('layouts.notif')

                            <!--end auth-logo-text-->
                            <form class="form-horizontal auth-form my-4"
                                action="{{ route('reset-password.process', $token) }}" method="post">
                                @csrf

                                <div class="form-group">
                                    <label for="password">New Password</label>
                                    <div class="input-group mb-3">
                                        <span class="auth-form-icon">
                                            <i class="dripicons-lock"></i>
                                        </span>
                                        <input type="password" name="password"
                                            class="form-control @error('password') is-invalid @enderror" id="password"
                                            placeholder="Enter new password">
                                    </div>
                                    @error('password')
                                        <small class="form-text text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <!--end form-group-->

                                <div class="form-group">
                                    <label for="password_confirmation">Confirm New Password</label>
                                    <div class="input-group mb-3">
                                        <span class="auth-form-icon">
                                            <i class="dripicons-lock"></i>
                                        </span>
                                        <input type="password" name="password_confirmation" class="form-control"
                                            id="password_confirmation" placeholder="Confirm new password">
                                    </div>
                                </div>
                                <!--end form-group-->

                                <div class="form-group mt-4">
                                    <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_SITE_KEY') }}"></div>
                                </div>

                                <div class="form-group mb-0 row">
                                    <div class="col-12 mt-2">
                                        <button type="submit"
                                            class="btn btn-primary btn-round btn-block waves-effect waves-light">Reset
                                            Password
                                            <i class="fas fa-key ml-1"></i></button>
                                    </div>
                                    <!--end col-->
                                </div>
                                <!--end form-group-->
                            </form>
                            <!--end form-->
                        </div>
                        <!--end /div-->

                    </div>
                    <!--end card-body-->
                </div>
                <!--end card-->
                <!--end account-social-->
            </div>
            <!--end auth-page-->
        </div>
        <!--end col-->
    </div>
    <!--end row-->
    <!-- End Reset Password page -->

    <script>
        // when enter key is pressed
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.btn').click();
            }
        });
    </script>
</body>

</html>
