@extends('layouts.app')

@section('content')
  <div id="admin-users" data-csrf-token="{{ csrf_token() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/users.tsx'])
@endpush
