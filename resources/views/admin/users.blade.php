@extends('layouts.app')

@section('title', 'Users - Admin')

@section('content')
  <div id="admin-users" data-csrf-token="{{ csrf_token() }}" data-current-user-id="{{ auth()->id() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/users.tsx'])
@endpush
