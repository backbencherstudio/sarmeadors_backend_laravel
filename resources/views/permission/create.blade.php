@extends('layouts.master')

@section('title', 'Softvence :: Permission Create')
@section('page-title', 'Permission Create')

@section('content')

<div class="py-4">
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}">
                    <svg class="icon icon-xxs" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3"></path>
                    </svg>
                </a>
            </li>
            <li class="breadcrumb-item">Permission</li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-12">

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

                {{-- ADD PERMISSION FORM --}}
                <form id="addPermissionForm" onsubmit="return false;">
                    <div class="row g-3 align-items-end">

                        <div class="col-md-3">
                            <label class="form-label">Route Name</label>
                            <input type="text" id="route_name"
                                   class="form-control"
                                   placeholder="user.view">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Permission Name</label>
                            <input type="text" id="permission_name"
                                   class="form-control"
                                   placeholder="User View">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select id="permission_type" class="form-select">
                                <option value="">Select</option>
                                <option value="view">View</option>
                                <option value="create">Create</option>
                                <option value="edit">Edit</option>
                                <option value="destroy">Destroy</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Group</label>
                            <select id="group_name" class="form-select">
                                <option value="">Select</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->group_name }}">
                                        {{ $group->group_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Guard Name</label>
                            <select id="guard_name" class="form-select">
                                <option value="">Select</option>
                                <option value="web" selected>Web</option>
                                <option value="api">Api</option>
                            </select>
                        </div>

                        <div class="col-md-1 d-grid">
                            <button type="button" id="addPermissionBtn"
                                    class="btn btn-primary"  style="margin-bottom: 0px;">
                                Add
                            </button>
                        </div>

                    </div>
                </form>

                {{-- PERMISSION LIST --}}
                <form action="{{ route('permission.store') }}" method="POST">
                    @csrf

                    <div class="table-responsive mt-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Route</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Group</th>
                                    <th>Guard</th>
                                    <th width="80">Action</th>
                                </tr>
                            </thead>
                            <tbody id="permissionTable">
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center text-muted">
                                        No permissions added
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-3">
                        <a href="{{ route('permission.index') }}"
                           class="btn btn-secondary me-2">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            Save All Permissions
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
@endsection

@section('script')

{{-- SCRIPT --}}
<script>
    document.getElementById('addPermissionBtn').addEventListener('click', function () {

        let route = route_name.value.trim();
        let name  = permission_name.value.trim();
        let type  = permission_type.value;
        let group = group_name.value;
        let guard = guard_name.value;

        if (!route || !name || !type || !group || !guard) {
            alert('All fields are required');
            return;
        }

        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        const index = document.querySelectorAll('#permissionTable tr').length;
        const row = `
            <tr>
                <td>
                    ${route}
                    <input type="hidden" name="permissions[${index}][route_name]" value="${route}">
                </td>
                <td>
                    ${name}
                    <input type="hidden" name="permissions[${index}][permission_name]" value="${name}">
                </td>
                <td>
                    ${type}
                    <input type="hidden" name="permissions[${index}][permission_type]" value="${type}">
                </td>
                <td>
                    ${group}
                    <input type="hidden" name="permissions[${index}][group_name]" value="${group}">
                </td>
                <td>
                    ${guard}
                    <input type="hidden" name="permissions[${index}][guard_name]" value="${guard}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger removeRow">✕</button>
                </td>
            </tr>
        `;

        permissionTable.insertAdjacentHTML('beforeend', row);
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('removeRow')) {
            e.target.closest('tr').remove();

            const rows = document.querySelectorAll('#permissionTable tr');
            rows.forEach((tr, i) => {
                const inputs = tr.querySelectorAll('input');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    const field = name.match(/\[([a-z_]+)\]$/)[1];
                    input.setAttribute('name', `permissions[${i}][${field}]`);
                });
            });

            if (!rows.length) {
                permissionTable.innerHTML = `
                    <tr id="emptyRow">
                        <td colspan="5" class="text-center text-muted">No permissions added</td>
                    </tr>`;
            }
        }
    });

    document.querySelector('form[action="{{ route('permission.store') }}"]').addEventListener('submit', function () {
        route_name.value = '';
        permission_name.value = '';
        permission_type.value = '';
        group_name.value = '';
        grard_name.value = '';
    });
</script>

@endsection
