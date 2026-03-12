@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
  <div id="forgot-password" 
    data-status="{{ session('status', '') }}"
    data-errors="{{ json_encode($errors->toArray()) }}"
  ></div>
@endsection

@push('scripts')
  @vite(['resources/js/auth/forgot-password.tsx'])
@endpush
