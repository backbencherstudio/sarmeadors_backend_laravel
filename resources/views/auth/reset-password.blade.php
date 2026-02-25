@extends('layouts.master')
@section('title', 'Softvence :: My Profile')
@section('page-title', 'My Profile')

@section('content')
    <div class="py-4 d-flex justify-content-between align-items-center" style="padding-right: 23px;">
        <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
            <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}">
                        <svg class="icon icon-xxs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                    </a>
                </li>
                <li class="breadcrumb-item"><a href="#">User</a></li>
                <li class="breadcrumb-item active" aria-current="page">My Profile</li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <!-- Profile Info -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center profile-header" style="background: linear-gradient(135deg, #4caf50, #006d62); color: #fff;">
                    <span>Profile Information</span>

                    <button type="button"
                            class="btn btn-sm border border-primary edit-profile-btn"
                            id="editProfileBtn" style="color: #000;">
                            <i class="bi bi-pencil-square"></i> Edit
                    </button>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="profile-avatar" style="width: 80px; height: 80px; border-radius: 50%; background: #ddd; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; color: #555;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <h5 class="mt-2">{{ auth()->user()->name }}</h5>
                    </div>
                    <table class="table table-bordered text-center mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%; background-color: #f1f1f1;">Email</th>
                                <td>{{ auth()->user()->email }}</td>
                            </tr>
                            <tr>
                                <th style="background-color: #f1f1f1;">Mobile</th>
                                <td>{{ auth()->user()->mobile ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="background-color: #f1f1f1;">Department</th>
                                <td>{{ auth()->user()->department ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4 d-none" id="editProfileCard">
                <div class="card-header" style="background: linear-gradient(135deg, #4caf50, #006d62); color: #fff;">
                    Edit Profile
                </div>

                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text"
                                name="name"
                                class="form-control"
                                value="{{ auth()->user()->name }}"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email"
                                name="email"
                                class="form-control"
                                value="{{ auth()->user()->email }}"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="text"
                                name="mobile"
                                class="form-control"
                                value="{{ auth()->user()->mobile }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text"
                                name="department"
                                class="form-control"
                                value="{{ auth()->user()->department }}">
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Reset Password -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header text-center" style="background: linear-gradient(135deg, #006d62, #4caf50); color: #fff;">
                    Change Password
                </div>
                <div class="card-body"style="padding-bottom: 22px;">

                    <form action="{{ route('password.update') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" name="current_password" id="current_password"
                                class="form-control" placeholder="Enter current password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" name="new_password" id="new_password"
                                class="form-control" placeholder="Enter new password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation"
                                class="form-control" placeholder="Confirm new password" required>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary mb-3">Update Password</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <style>
        /* Overall body adjustments */
        body {
            background-color: #f4f6f9;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #4caf50, #006d62);
            color: #fff;
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #555;
            margin-bottom: 1rem;
            margin-left: auto;
            margin-right: auto;
        }

        table th {
            width: 35%;
            font-weight: 600;
            color: #333;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #006d62);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3e8e41, #005747);
        }

        .edit-profile-btn {
            font-weight: 500;
        }

        .edit-profile-btn:hover {
            background-color: #0ba74f;
            border-color: #4caf50;
        }

        @media (max-width: 768px) {
            .profile-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
    </style>
@endsection

@section('script')
    <script>
        document.getElementById('editProfileBtn')?.addEventListener('click', function () {
            document.getElementById('editProfileCard').classList.toggle('d-none');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notyf = new Notyf({
                position: { x: 'right', y: 'top' },
                duration: 4000,
                dismissible: true
            });

            @if (session('success'))
                notyf.success("{{ session('success') }}");
            @endif

            @if (session('error'))
                notyf.error("{{ session('error') }}");
            @endif

            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    notyf.error("{{ $error }}");
                @endforeach
            @endif
        });
    </script>
@endsection
