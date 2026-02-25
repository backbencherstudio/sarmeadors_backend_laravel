@extends('layouts.master')
@section('title', 'Softvence :: Permission group Create')
@section('page-title', 'Permission group Create')
@section('content')

    <div class="py-4">
        <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
            <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
                <li class="breadcrumb-item">
                    <a href="#">
                        <svg class="icon icon-xxs" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </a>
                </li>
                <li class="breadcrumb-item"><a href="#">Softvence</a></li>
                <li class="breadcrumb-item active" aria-current="page">Permission group Create</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
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

                    <form id="userForm" action="{{ route('permission.group.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-12">
                                <label for="group_name" class="form-label">Permission Group Name</label>
                                <input type="text" name="group_name" id="group_name"
                                    class="form-control @error('group_name') is-invalid @enderror"
                                    value="{{ old('group_name') }}" required>
                                @error('group_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <a href="{{ route('permission.group.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success me-2">Save Group</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
