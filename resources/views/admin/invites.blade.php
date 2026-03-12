@extends('layouts.app')

@section('title', 'Invite Codes - Admin')

@section('content')
  <div id="admin-invites" data-csrf-token="{{ csrf_token() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/invites.tsx'])
@endpush
