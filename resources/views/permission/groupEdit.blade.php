@extends('layouts.master')
@section('title', 'Softvence :: Edit Permission Group')
@section('page-title', 'Edit Permission Group')
@section('content')

<div class="py-4">
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
            <li class="breadcrumb-item"><a href="#">Softvence</a></li>
            <li class="breadcrumb-item"><a href="{{ route('permission.group.index') }}">Permission Groups</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Group</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow components-section">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="groupForm" action="{{ route('permission.group.update', $group->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <!-- Group Name -->
                        <div class="col-md-12">
                            <label for="group_name" class="form-label">Permission Group Name</label>
                            <input type="text" name="group_name" id="group_name"
                                   class="form-control @error('group_name') is-invalid @enderror"
                                   value="{{ old('group_name', $group->group_name) }}" required>
                            @error('group_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <a href="{{ route('permission.group.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success me-2">Update Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
