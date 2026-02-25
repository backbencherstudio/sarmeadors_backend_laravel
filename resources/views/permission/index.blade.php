@extends('layouts.master')
@section('title', 'Softvence :: Permission List')
@section('page-title', 'Permission List')

@section('content')

    <div class="py-4 d-flex justify-content-between align-items-center" style="padding-right: 23px;">
        <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
            <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
                <li class="breadcrumb-item">
                    <a href="#">
                        <svg class="icon icon-xxs" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </a>
                </li>
                <li class="breadcrumb-item"><a href="#">Softvence</a></li>
                <li class="breadcrumb-item active" aria-current="page">Permission List</li>
            </ol>
        </nav>

        <a href="{{ route('permission.create') }}" class="btn btn-success btn-sm">
            + Create Permission
        </a>
    </div>

    <div class="card border-0 shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table id="permissionTable" class="table table-hover table-bordered nowrap" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Route Name</th>
                            <th>Permission Name</th>
                            <th>Permission Type</th>
                            <th>Permission Group</th>
                            <th>Guard</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#permissionTable').DataTable({
                ajax: {
                    url: '{{ route('permission.data') }}',
                    dataSrc: 'data'
                },
                columns: [
                    { data: null, render: (data, type, row, meta) => meta.row + 1 },
                    { data: 'name' },
                    { data: 'permission_name' },
                    { data: 'permission_type' },
                    { data: 'group_name' },
                    { data: 'guard_name' },
                    {
                        data: null,
                        render: function (data, type, row) {
                            return `
                                <a href="/permission-edit/${row.id}" class="btn btn-sm btn-warning me-1">Edit</a>
                                <form action="/permission-delete/${row.id}" method="POST" class="d-inline deleteForm">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>`;
                        },
                        orderable: false,
                        searchable: false,
                        className: 'text-end'
                    }
                ],
                order: [[0, 'asc']],
                responsive: true,
                rowGroup: {
                    dataSrc: 'group_name'
                }
            });
        });

        $(document).on('submit', '.deleteForm', function(e) {
            e.preventDefault();
            const form = this;

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary me-2'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notyf = new Notyf({
                position: {
                    x: 'right',
                    y: 'top',
                },
                duration: 3000,
                dismissible: true
            });

            @if (session('success'))
                notyf.success("{{ session('success') }}");
            @endif

            @if (session('error'))
                notyf.error("{{ session('error') }}");
            @endif
        });
    </script>
@endsection
