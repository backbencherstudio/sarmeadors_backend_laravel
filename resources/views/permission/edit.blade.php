@extends('layouts.master')
@section('title', 'Softvence :: Edit Permission')
@section('page-title', 'Edit Permission')
@section('content')

<div class="py-4">
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Softvence</a></li>
            <li class="breadcrumb-item"><a href="{{ route('permission.index') }}">Permissions</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Permission</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow components-section">
            <div class="card-body">

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('permission.update', $permission->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="name" class="form-label">Route Name</label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $permission->name) }}"
                                   placeholder="Ex: users.view"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="permission_name" class="form-label">Permission Name</label>
                            <input type="text"
                                   name="permission_name"
                                   id="permission_name"
                                   class="form-control @error('permission_name') is-invalid @enderror"
                                   value="{{ old('permission_name', $permission->permission_name) }}"
                                   placeholder="Ex: User View"
                                   required>
                            @error('permission_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="permission_type" class="form-label">Permission Type</label>
                            <select name="permission_type"
                                    id="permission_type"
                                    class="form-select @error('permission_type') is-invalid @enderror"
                                    required>
                                <option value="">Select Type --</option>
                                @foreach(['view','create','edit','destroy'] as $type)
                                    <option value="{{ $type }}" {{ old('permission_type', $permission->permission_type) == $type ? 'selected' : '' }}>
                                        {{ ucfirst($type) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('permission_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="group_name" class="form-label">Group Name</label>
                            <select name="group_name"
                                    id="group_name"
                                    class="form-select @error('group_name') is-invalid @enderror"
                                    required>
                                <option value="">Select Group --</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->group_name }}"
                                        {{ old('group_name', $permission->group_name) == $group->group_name ? 'selected' : '' }}>
                                        {{ $group->group_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('group_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Guard Name</label>
                            <select id="guard_name" name="guard_name" class="form-select">
                                <option value="">Select</option>
                                <option value="web"
                                    {{ old('guard_name', $permission->guard_name) === 'web' ? 'selected' : '' }}>
                                    Web
                                </option>
                                <option value="api"
                                    {{ old('guard_name', $permission->guard_name) === 'api' ? 'selected' : '' }}>
                                    Api
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="text-end mt-4">
                        <a href="{{ route('permission.index') }}" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Permission</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
