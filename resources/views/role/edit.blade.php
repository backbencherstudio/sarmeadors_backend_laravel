@extends('layouts.master')
@section('title', 'Softvence :: Role Edit')
@section('page-title', 'Edit Role')

@section('content')

<div class="py-4">
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
            <li class="breadcrumb-item"><a href="#">Softvence</a></li>
            <li class="breadcrumb-item"><a href="{{ route('role.index') }}">Roles</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Role</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow components-section">
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

                <form action="{{ route('role.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- Role Name + Guard --}}
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">Role Name</label>
                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   value="{{ old('name', $role->name) }}"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Guard</label>
                            <select id="guardFilter" name="guard_name" class="form-select" disabled>
                                <option value="web" {{ $role->guard_name === 'web' ? 'selected' : '' }}>Web</option>
                                <option value="api" {{ $role->guard_name === 'api' ? 'selected' : '' }}>Api</option>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-center" style="margin-top: 20px;">
                            <div class="form-check ms-3 mb-0">
                                <input type="checkbox" class="form-check-input" id="checkAllGlobal">
                                <label class="form-check-label mb-0">Give all Permissions</label>
                            </div>
                        </div>
                    </div>

                    {{-- Permission Groups --}}
                    @foreach($permissions->groupBy('group_name') as $groupName => $groupPermissions)
                        <div class="permission-group mt-4" data-group="{{ Str::slug($groupName) }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>{{ $groupName }}</h5>
                                <div class="form-check">
                                    <input type="checkbox"
                                           class="form-check-input checkAllGroup"
                                           data-group="{{ Str::slug($groupName) }}">
                                    <label class="form-check-label">Select All</label>
                                </div>
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>Permission Name</th>
                                            <th>Create</th>
                                            <th>Read</th>
                                            <th>Update</th>
                                            <th>Delete</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $sl = 1; @endphp
                                        @foreach(['web','api'] as $guard)
                                            @foreach(
                                                $groupPermissions->where('guard_name', $guard)->groupBy('permission_name')
                                                as $permissionName => $perms
                                            )
                                                <tr class="text-center permission-row" data-guard="{{ $guard }}">
                                                    <td>{{ $sl++ }}</td>
                                                    <td class="text-start">{{ $permissionName }}</td>

                                                    @foreach (['create','view','edit','destroy'] as $type)
                                                        <td>
                                                            @php
                                                                $perm = $perms->firstWhere('permission_type', $type);
                                                            @endphp

                                                            @if($perm)
                                                                <input type="checkbox"
                                                                    name="permissions[]"
                                                                    value="{{ $perm->id }}"
                                                                    class="perm-checkbox perm-{{ $type }}-{{ Str::slug($groupName) }}"
                                                                    {{ $role->permissions->contains($perm->id) ? 'checked' : '' }}>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="text-end mt-3">
                        <a href="{{ route('role.index') }}" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-success">Update Role</button>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    const guard = "{{ $role->guard_name }}";

    function filterByGuard(guard) {
        document.querySelectorAll('.permission-row').forEach(row => {
            row.style.display = row.dataset.guard === guard ? '' : 'none';
        });

        document.querySelectorAll('.permission-group').forEach(group => {
            const hasVisible = group.querySelectorAll(
                '.permission-row[data-guard="' + guard + '"]'
            ).length > 0;

            group.style.display = hasVisible ? '' : 'none';
        });
    }

    // Default load
    filterByGuard(guard);

    // Global check all
    document.getElementById('checkAllGlobal').addEventListener('change', function () {
        const checked = this.checked;
        document.querySelectorAll('.permission-row').forEach(row => {
            if (row.style.display !== 'none') {
                row.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
            }
        });
        document.querySelectorAll('.checkAllGroup').forEach(cb => cb.checked = checked);
    });

    // Group check all
    document.querySelectorAll('.checkAllGroup').forEach(groupCheck => {
        groupCheck.addEventListener('change', function () {
            const groupSlug = this.dataset.group;
            const checked = this.checked;

            document.querySelectorAll(
                '.perm-create-' + groupSlug +
                ', .perm-view-' + groupSlug +
                ', .perm-edit-' + groupSlug +
                ', .perm-destroy-' + groupSlug
            ).forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = checked;
                }
            });
        });
    });
</script>
@endsection
