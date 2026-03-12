@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
  <div id="reset-password"
    data-token="{{ $token }}"
    data-email="{{ old('email', $email) }}"
    data-errors="{{ json_encode($errors->toArray()) }}"
  ></div>
@endsection

@push('scripts')
  @vite(['resources/js/auth/reset-password.tsx'])
@endpush
