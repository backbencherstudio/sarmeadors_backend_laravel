@extends('layouts.master')
@section('title', 'Softvence :: User Edit')
@section('page-title', 'User Edit')

@section('content')

<div class="py-4">
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
            <li class="breadcrumb-item">
                <a href="#">
                    <svg class="icon icon-xxs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3"/>
                    </svg>
                </a>
            </li>
            <li class="breadcrumb-item"><a href="#">Softvence</a></li>
            <li class="breadcrumb-item active">User Edit</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow">
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="userForm" action="{{ route('users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name"
                                   class="form-control"
                                   value="{{ old('name', $user->name) }}" required>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email"
                                   class="form-control"
                                   value="{{ old('email', $user->email) }}" required>
                        </div>

                        <!-- Mobile -->
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile"
                                   class="form-control"
                                   value="{{ old('mobile', $user->mobile) }}">
                        </div>

                        <!-- Department -->
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" name="department"
                                   class="form-control"
                                   value="{{ old('department', $user->department) }}">
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Password <small class="text-muted">(leave blank to keep)</small>
                            </label>
                            <input type="password" id="password" name="password" class="form-control">

                            <div class="invalid-feedback" id="passwordLengthError" style="display:none;">
                                Password must be at least 6 characters.
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" id="password_confirmation"
                                   name="password_confirmation" class="form-control">

                            <div class="invalid-feedback" id="passwordMismatch" style="display:none;">
                                Passwords do not match.
                            </div>
                        </div>

                        <!-- Role -->
                        <div class="col-md-12">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}"
                                        {{ $currentRoleId == $role->id ? 'selected' : '' }}>
                                        {{ ucfirst($role->name) }} ({{ $role->guard_name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    <div class="text-end mt-4">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                        <button class="btn btn-success ms-2">Update User</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
    <script>
        document.getElementById('userForm').addEventListener('submit', function (e) {

            const password = document.getElementById('password');
            const confirm  = document.getElementById('password_confirmation');

            const lengthError = document.getElementById('passwordLengthError');
            const mismatch    = document.getElementById('passwordMismatch');

            lengthError.style.display = 'none';
            mismatch.style.display = 'none';
            password.classList.remove('is-invalid');
            confirm.classList.remove('is-invalid');

            // Only validate if password is entered
            if (password.value.length > 0) {

                if (password.value.length < 6) {
                    e.preventDefault();
                    password.classList.add('is-invalid');
                    lengthError.style.display = 'block';
                    return;
                }

                if (password.value !== confirm.value) {
                    e.preventDefault();
                    confirm.classList.add('is-invalid');
                    mismatch.style.display = 'block';
                    return;
                }
            }
        });
    </script>
@endsection
