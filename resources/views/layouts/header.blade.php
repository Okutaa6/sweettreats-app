 
    <div class="topbar">

        <!-- LOGO -->
        <div class="topbar-left">
            <a href="{{ url('index') }}" class="logo">
                <span>
                    <img src="{{ asset('sysprofile/' . session('syslogo')) }}" alt=" logo"
                        class="logo-sm d-none d-lg-inline" style="height:50px;">
                </span>
            </a>
        </div>
        <!--end logo-->
        <!-- Navbar -->
        <nav class="navbar-custom">
            <ul class="list-unstyled topbar-nav float-right mb-0">

                <li class="dropdown">
                    <a class="nav-link dropdown-toggle waves-effect waves-light nav-user" data-toggle="dropdown"
                        href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="dripicons-user"></i>
                        <span class="ml-1 nav-user-name">{{ session('name') }} <i class="mdi mdi-chevron-down"></i> </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="{{ route('signout') }}"><i
                                class="dripicons-exit text-muted mr-2"></i>
                            Logout</a>
                    </div>
                </li>
            </ul>
            <!--end topbar-nav-->

            <ul class="list-unstyled topbar-nav mb-0">
                <li>
                    <button class="nav-link button-menu-mobile waves-effect waves-light">
                        <i class="dripicons-menu"></i>
                    </button>
                </li>
                <li class="hide-phone app-search hidden-sm">
                    <h4 class="mt-1">{{ session('systitle') }}</h4>
                </li>
            </ul>
        </nav>
        <!-- end navbar-->
    </div>
