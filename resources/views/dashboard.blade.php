@extends('layouts.app')

@section('content')
  <div id="dashboard" data-user-name="{{ auth()->user()->name }}" data-is-admin="{{ auth()->user()->isAdmin() ? '1' : '0' }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/dashboard.tsx'])
@endpush
