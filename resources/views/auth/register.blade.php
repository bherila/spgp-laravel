@extends('layouts.app')

@section('title', 'Register')

@section('content')
  <div id="register"
    data-errors="{{ json_encode($errors->toArray()) }}"
    data-old-first-name="{{ old('first_name', '') }}"
    data-old-last-name="{{ old('last_name', '') }}"
    data-old-email="{{ old('email', '') }}"
    data-old-invite-code="{{ old('invite_code', '') }}"
    data-old-agreement="{{ old('agreement') ? '1' : '0' }}"
  ></div>
@endsection

@push('scripts')
  @vite(['resources/js/auth/register.tsx'])
@endpush
