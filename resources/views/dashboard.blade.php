@extends('layouts.app')

@section('content')
  <div id="dashboard" data-user-name="{{ auth()->user()->name }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/dashboard.tsx'])
@endpush
