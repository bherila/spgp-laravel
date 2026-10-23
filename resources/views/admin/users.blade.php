@extends('layouts.app')

@section('title', 'Users - Admin')

@section('content')
  <x-page-data :data="$pageData" />
  <div id="admin-users" data-current-user-id="{{ auth()->id() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/users.tsx'])
@endpush
