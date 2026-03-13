<!DOCTYPE html>
<html lang="en">

<head>
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
    <!-- Log In page -->
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
                                <h4 class="mt-0 mb-3 mt-5">Login</h4>
                                <p class="text-muted ">{{ $sysprofile['systitle'] }}</p>
                            </div>
                            @include('layouts.notif')

                            <!--end auth-logo-text-->
                            <form class="form-horizontal auth-form my-4" action="{{ route('signin') }}" method="post">
                                @csrf
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <div class="input-group mb-3">
                                        <span class="auth-form-icon">
                                            <i class="dripicons-user"></i>
                                        </span>
                                        <input type="email" name="email" value="{{ old('email') }}"
                                            class="form-control @error('email') is-invalid @enderror" id="email"
                                            placeholder="Enter email">
                                    </div>
                                    @error('email')
                                        <small class="form-text text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <!--end form-group-->

                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <div class="input-group mb-3">
                                        <span class="auth-form-icon">
                                            <i class="dripicons-lock"></i>
                                        </span>
                                        <input type="password" name="password" value="{{ old('password') }}"
                                            class="form-control @error('password') is-invalid @enderror" id="password"
                                            placeholder="Enter password">
                                    </div>
                                    @error('password')
                                        <small class="form-text text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                                <!--end form-group-->

                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <a href="{{ url('forgot-password') }}" class="text-muted font-13"><i
                                                class="dripicons-lock"></i>
                                            Forgot password?</a>
                                    </div>
                                    <div class="col-sm-6">
                                    </div>
                                </div>

                                <div class="form-group mt-4">
                                    <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_SITE_KEY') }}"></div>
                                </div>

                                <div class="form-group mb-0 row">
                                    <div class="col-12 mt-2">
                                        <button type="submit"
                                            class="btn btn-primary btn-round btn-block waves-effect waves-light">Log In
                                            <i class="fas fa-sign-in-alt ml-1"></i></button>
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
    <!-- End Log In page -->

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
