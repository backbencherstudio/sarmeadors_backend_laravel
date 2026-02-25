<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!-- Primary Meta Tags -->
    <title>@yield('title')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    {{-- <meta name="title" content="Volt - Free Bootstrap 5 Dashboard"> --}}
    <meta name="author" content="Themesberg">
    {{-- <meta name="description" content="Volt Pro is a Premium Bootstrap 5 Admin Dashboard featuring over 800 components, 10+ plugins and 20 example pages using Vanilla JS."> --}}
    <meta name="keywords" content="bootstrap 5, bootstrap, bootstrap 5 admin dashboard, bootstrap 5 dashboard, bootstrap 5 charts, bootstrap 5 calendar, bootstrap 5 datepicker, bootstrap 5 tables, bootstrap 5 datatable, vanilla js datatable, themesberg, themesberg dashboard, themesberg admin dashboard" />
    {{-- <link rel="canonical" href="https://themesberg.com/product/admin-dashboard/volt-premium-bootstrap-5-dashboard"> --}}

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    {{-- <meta property="og:url" content="https://demo.themesberg.com/volt-pro"> --}}
    {{-- <meta property="og:title" content="Volt - Free Bootstrap 5 Dashboard"> --}}
    {{-- <meta property="og:description" content="Volt Pro is a Premium Bootstrap 5 Admin Dashboard featuring over 800 components, 10+ plugins and 20 example pages using Vanilla JS."> --}}
    {{-- <meta property="og:image" content="https://themesberg.s3.us-east-2.amazonaws.com/public/products/volt-pro-bootstrap-5-dashboard/volt-pro-preview.jpg"> --}}

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    {{-- <meta property="twitter:url" content="https://demo.themesberg.com/volt-pro"> --}}
    {{-- <meta property="twitter:title" content="Volt - Free Bootstrap 5 Dashboard"> --}}
    {{-- <meta property="twitter:description" content="Volt Pro is a Premium Bootstrap 5 Admin Dashboard featuring over 800 components, 10+ plugins and 20 example pages using Vanilla JS."> --}}
    {{-- <meta property="twitter:image" content="https://themesberg.s3.us-east-2.amazonaws.com/public/products/volt-pro-bootstrap-5-dashboard/volt-pro-preview.jpg"> --}}

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="120x120" href="../../assets/img/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon/favicon.png">
    <link rel="manifest" href="../../assets/img/favicon/site.webmanifest">
    <link rel="mask-icon" href="../../assets/img/favicon/favicon.png" color="#ffffff">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">

    <!-- Sweet Alert -->
    <link type="text/css" href="../../vendor/sweetalert2/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Notyf -->
    <link type="text/css" href="../../vendor/notyf/notyf.min.css" rel="stylesheet">

    <!-- Volt CSS -->
    <link type="text/css" href="../../css/volt.css" rel="stylesheet">

    {{-- My --}}
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">


</head>

<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-dark navbar-theme-primary px-4 col-12 d-lg-none">
        <a class="navbar-brand me-lg-5" href="{{ url('/welcome') }}">
            <img class="sidebar-footer-logo" src="../../assets/img/brand/favicon.png" alt="Softvence logo" /> <img class="navbar-brand-light" src="../../assets/img/brand/dark.svg" alt="Softvence logo" />
        </a>
        <div class="d-flex align-items-center">
            <button class="navbar-toggler d-lg-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <nav id="sidebarMenu" class="sidebar d-lg-block bg-gray-800 text-white collapse" data-simplebar>
        <div class="sidebar-inner px-4 pt-3">
            <div class="user-card d-flex d-md-none align-items-center justify-content-between justify-content-md-center pb-4">
                <div class="d-flex align-items-center">
                    <div class="avatar-lg me-4">
                    <img src="../../assets/img/team/user.png" class="card-img-top rounded-circle border-white"
                        alt="Bonnie Green">
                    </div>
                    <div class="d-block">
                    <h2 class="h5 mb-3">Hi, {{ auth()->user()->name }}</h2>
                        <a class="btn btn-secondary btn-sm d-inline-flex align-items-center" href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <svg class="icon icon-xxs me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                </path>
                            </svg>
                            Sign Out
                        </a>

                        <!-- Hidden Logout Form -->
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
                <div class="collapse-close d-md-none">
                    <a href="#sidebarMenu" data-bs-toggle="collapse"
                        data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="true"
                        aria-label="Toggle navigation">
                        <svg class="icon icon-xs" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </a>
                </div>
            </div>

            <ul class="nav flex-column pt-3 pt-md-0">

                {{-- Volt Overview --}}
                <li class="nav-item mb-3 {{ request()->is('welcome') ? 'active' : '' }}">
                    <a href="{{ url('/welcome') }}" class="nav-link d-flex align-items-center">
                        <span class="sidebar-logo">
                            <img src="../../assets/img/brand/logo.png" height="30" width="200" alt="Softvence Logo">
                        </span>
                    </a>
                </li>

                {{-- Dashboard --}}
                {{-- @can('dashboard.view') --}}
                <li class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}">
                    <a href="{{ url('/dashboard') }}" class="nav-link">
                        <span class="sidebar-icon">
                            <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                {{-- @endcan --}}

                {{-- Transactions --}}
                <li class="nav-item {{ request()->is('transactions') ? 'active' : '' }}">
                    <a href="{{ url('/transactions') }}" class="nav-link">
                        <span class="sidebar-icon">
                            <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path>
                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                        <span class="sidebar-text">Transactions</span>
                    </a>
                </li>

                {{-- Settings --}}
                <li class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
                    <a href="{{ url('/settings') }}" class="nav-link">
                        <span class="sidebar-icon">
                            <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path></svg>
                        </span>
                        </span>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>

                {{-- Page Examples --}}
                <li class="nav-item">
                    <span
                        class="nav-link collapsed d-flex justify-content-between align-items-center
                        {{ request()->is('reset-password','404') ? 'active' : '' }}"
                        data-bs-toggle="collapse" data-bs-target="#submenu-pages">

                        <span>
                            <span class="sidebar-icon">
                            <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd"></path><path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z"></path></svg>
                            </span>
                            <span class="sidebar-text">Page examples</span>
                        </span>

                        <span class="link-arrow">
                            <svg class="icon icon-sm" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707L10.586 10 7.293 6.707z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </span>

                    <div class="multi-level collapse {{ request()->is('reset-password','404') ? 'show' : '' }}"
                        id="submenu-pages">
                        <ul class="flex-column nav">
                            <li class="nav-item {{ request()->is('reset-password') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/reset-password') }}">
                                    <span class="sidebar-text">Reset password</span>
                                </a>
                            </li>
                            <li class="nav-item {{ request()->is('404') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/404') }}">
                                    <span class="sidebar-text">404 Not Found</span>
                                </a>
                            </li>
                            <li class="nav-item {{ request()->is('500') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/500') }}">
                                    <span class="sidebar-text">500 Not Found</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">

                    <span
                        class="nav-link collapsed d-flex justify-content-between align-items-center
                        {{ request()->is('buttons','notifications','forms','modals','typography') ? 'active' : '' }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#submenu-components">

                        <span>
                            <span class="sidebar-icon">
                                <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"></path>
                                    <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            <span class="sidebar-text">Components</span>
                        </span>

                        <span class="link-arrow">
                            <svg class="icon icon-sm" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </span>

                    </span>

                    <div class="multi-level collapse
                        {{ request()->is('buttons','notifications','forms','modals','typography') ? 'show' : '' }}"
                        id="submenu-components">

                        <ul class="flex-column nav">

                            <li class="nav-item {{ request()->is('buttons') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/buttons') }}">
                                    <span class="sidebar-text">Buttons</span>
                                </a>
                            </li>

                            <li class="nav-item {{ request()->is('notifications') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/notifications') }}">
                                    <span class="sidebar-text">Notifications</span>
                                </a>
                            </li>

                            <li class="nav-item {{ request()->is('forms') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/forms') }}">
                                    <span class="sidebar-text">Forms</span>
                                </a>
                            </li>

                            <li class="nav-item {{ request()->is('modals') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/modals') }}">
                                    <span class="sidebar-text">Modals</span>
                                </a>
                            </li>

                            <li class="nav-item {{ request()->is('typography') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/typography') }}">
                                    <span class="sidebar-text">Typography</span>
                                </a>
                            </li>

                        </ul>
                    </div>

                </li>

                @canany([
                    'user.create',
                    'user.view',
                    'permission.group.view',
                    'permission.view',
                    'role.view'
                ])
                <li class="nav-item">

                    @php
                        $userManagementActive = request()->is(
                            'users-create',
                            'users',
                            'user-edit/*',
                            'permission-group',
                            'group-create',
                            'group-edit/*',
                            'permissions',
                            'permission-create',
                            'permission-edit/*',
                            'roles',
                            'role-create',
                            'role-edit/*'
                        );
                    @endphp

                    <span
                        class="nav-link collapsed d-flex justify-content-between align-items-center {{ $userManagementActive ? 'active' : '' }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#submenu-user-management"
                        aria-expanded="{{ $userManagementActive ? 'true' : 'false' }}">

                        <span>
                            <span class="sidebar-icon">
                                <svg class="icon icon-xs me-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 2a4 4 0 100 8 4 4 0 000-8zm-7 16a7 7 0 0114 0H3z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            <span class="sidebar-text">User Management</span>
                        </span>

                        <span class="link-arrow {{ $userManagementActive ? 'rotate' : '' }}">
                            <svg class="icon icon-sm" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </span>

                    </span>

                    <div class="multi-level collapse {{ $userManagementActive ? 'show' : '' }}"
                        id="submenu-user-management">

                        <ul class="flex-column nav">

                            @can('user.create')
                            <li class="nav-item {{ request()->is('users-create') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/users-create') }}">
                                    <span class="sidebar-text">Create User</span>
                                </a>
                            </li>
                            @endcan

                            @can('user.view')
                            <li class="nav-item {{ request()->is('users', 'user-edit/*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/users') }}">
                                    <span class="sidebar-text">User List</span>
                                </a>
                            </li>
                            @endcan

                            @can('permission.group.view')
                            <li class="nav-item {{ request()->is('permission-group','group-edit/*','group-create') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/permission-group') }}">
                                    <span class="sidebar-text">Permission Group</span>
                                </a>
                            </li>
                            @endcan

                            @can('permission.view')
                            <li class="nav-item {{ request()->is('permissions','permission-create','permission-edit/*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/permissions') }}">
                                    <span class="sidebar-text">Permission List</span>
                                </a>
                            </li>
                            @endcan

                            @can('role.view')
                            <li class="nav-item {{ request()->is('roles','role-create','role-edit/*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ url('/roles') }}">
                                    <span class="sidebar-text">Role List</span>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </div>

                </li>
                @endcanany


                <li role="separator" class="dropdown-divider mt-4 mb-3 border-gray-700"></li>
                {{-- <li class="nav-item">
                    <a href="#" target="_blank" class="nav-link d-flex align-items-center">
                    <span class="sidebar-footer-logo">
                        <img src="../../assets/img/favicon/favicon.png" height="25" width="28" alt="Softvence Delta Logo">
                    </span>
                    <span class="sidebar-text">&nbsp;Softvence Delta</span>
                    </a>
                </li> --}}
            </ul>

        </div>
    </nav>

    <main class="content flex-fill">

        <nav class="navbar navbar-top navbar-expand navbar-dashboard navbar-dark ps-0 pe-2 pb-0">
            <div class="container-fluid px-0">
                <div class="d-flex justify-content-between w-100" id="navbarSupportedContent">
                    <div class="d-flex align-items-center mb-1">
                        <span class="me-2 border-start border-4 border-primary left-border-gap" style="height: 20px;"></span>
                        <h5 class="mb-0 fw-semibold">
                            @yield('page-title')
                        </h5>
                    </div>
                    <!-- Navbar links -->
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item dropdown">
                            <a class="nav-link text-dark notification-bell unread dropdown-toggle" data-unread-notifications="true" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <svg class="icon icon-sm text-gray-900" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path></svg>
                            </a>
                            {{-- <div class="dropdown-menu dropdown-menu-lg dropdown-menu-center mt-2 py-0">
                                <div class="list-group list-group-flush">
                                <a href="#" class="text-center text-primary fw-bold border-bottom border-light py-3">Notifications</a>
                                <a href="#" class="list-group-item list-group-item-action border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                        <!-- Avatar -->
                                        <img alt="Image placeholder" src="../../assets/img/team/profile-picture-1.jpg" class="avatar-md rounded">
                                        </div>
                                        <div class="col ps-0 ms-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="h6 mb-0 text-small">Jose Leos</h4>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-danger">a few moments ago</small>
                                            </div>
                                        </div>
                                        <p class="font-small mt-1 mb-0">Added you to an event "Project stand-up" tomorrow at 12:30 AM.</p>
                                        </div>
                                    </div>
                                </a>
                                <a href="#" class="dropdown-item text-center fw-bold rounded-bottom py-3">
                                    <svg class="icon icon-xxs text-gray-400 me-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg>
                                    View all
                                </a>
                                </div>
                            </div> --}}
                        </li>
                        <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle pt-1 px-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="media d-flex align-items-center">
                            <img class="avatar rounded-circle" alt="Image placeholder" src="../../assets/img/team/user.png">
                            <div class="media-body ms-2 text-dark align-items-center d-none d-lg-block">
                                <span class="mb-0 font-small fw-bold text-gray-900">{{ auth()->user()->name }}</span>
                            </div>
                            </div>
                        </a>
                        <div class="dropdown-menu dashboard-dropdown dropdown-menu-end mt-2 py-1">
                            <a class="dropdown-item d-flex align-items-center" href="{{ '/profile' }}">
                            <svg class="dropdown-icon text-gray-400 me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path></svg>
                            My Profile
                            </a>
                            {{-- <a class="dropdown-item d-flex align-items-center" href="#">
                            <svg class="dropdown-icon text-gray-400 me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path></svg>
                            Settings
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                            <svg class="dropdown-icon text-gray-400 me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm0 2h10v7h-2l-1 2H8l-1-2H5V5z" clip-rule="evenodd"></path></svg>
                            Messages
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                            <svg class="dropdown-icon text-gray-400 me-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C15.802 8.249 16 9.1 16 10zm-5.165 3.913l1.58 1.58A5.98 5.98 0 0110 16a5.976 5.976 0 01-2.516-.552l1.562-1.562a4.006 4.006 0 001.789.027zm-4.677-2.796a4.002 4.002 0 01-.041-2.08l-.08.08-1.53-1.533A5.98 5.98 0 004 10c0 .954.223 1.856.619 2.657l1.54-1.54zm1.088-6.45A5.974 5.974 0 0110 4c.954 0 1.856.223 2.657.619l-1.54 1.54a4.002 4.002 0 00-2.346.033L7.246 4.668zM12 10a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd"></path></svg>
                            Support
                            </a> --}}
                            <div role="separator" class="dropdown-divider my-1"></div>
                            <a class="dropdown-item d-flex align-items-center" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <svg class="dropdown-icon text-danger me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                    </path>
                                </svg>
                                Logout
                            </a>

                            <!-- Hidden Logout Form -->
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        {{-- Main Content --}}
        @yield('content')
        {{-- End Main Content --}}
    </main>

    <footer class="bg-white rounded shadow p-3 mb-3 mt-3 footer-responsive">
        <div class="row">
            <div class="col-12 col-md-4 col-xl-6 mb-4 mb-md-0">
                <p class="mb-0 text-center text-lg-start">© 2025
                    <a class="text-primary fw-normal" href="https://softvence.agency/" target="_blank">
                        Laravel Team <span class="text-info">Softvence Delta</span>
                    </a>
                </p>
            </div>
            <div class="col-12 col-md-8 col-xl-6 text-center text-lg-start">
                <ul class="list-inline list-group-flush list-group-borderless text-md-end mb-0">
                    <li class="list-inline-item px-0 px-sm-2">
                        <a href="https://softvence.agency/about-us/">About</a>
                    </li>
                    <li class="list-inline-item px-0 px-sm-2">
                        <a href="https://softvence.agency/work/">Work</a>
                    </li>
                    <li class="list-inline-item px-0 px-sm-2">
                        <a href="mailto:hello@softvence.agency">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        @yield('jQuery')
        <!-- Core -->
        <script src="../../vendor/@popperjs/core/dist/umd/popper.min.js"></script>
        <script src="../../vendor/bootstrap/dist/js/bootstrap.min.js"></script>

        <!-- Vendor JS -->
        <script src="../../vendor/onscreen/dist/on-screen.umd.min.js"></script>

        <!-- Slider -->
        <script src="../../vendor/nouislider/distribute/nouislider.min.js"></script>

        <!-- Smooth scroll -->
        <script src="../../vendor/smooth-scroll/dist/smooth-scroll.polyfills.min.js"></script>

        <!-- Charts -->
        <script src="../../vendor/chartist/dist/chartist.min.js"></script>
        <script src="../../vendor/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.min.js"></script>

        <!-- Datepicker -->
        <script src="../../vendor/vanillajs-datepicker/dist/js/datepicker.min.js"></script>

        <!-- Sweet Alerts 2 -->
        <script src="../../vendor/sweetalert2/dist/sweetalert2.all.min.js"></script>

        <!-- Moment JS -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.27.0/moment.min.js"></script>

        <!-- Notyf -->
        <script src="../../vendor/notyf/notyf.min.js"></script>

        <!-- Simplebar -->
        <script src="../../vendor/simplebar/dist/simplebar.min.js"></script>

        <!-- Github buttons -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>

        <!-- Volt JS -->
        <script src="../../assets/js/volt.js"></script>

        {{-- My --}}
        <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        @yield('script')

</body>

</html>
