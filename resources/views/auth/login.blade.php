@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
  <div id="login" 
    data-errors="{{ json_encode($errors->toArray()) }}"
    data-old-email="{{ old('email', '') }}"
  ></div>
@endsection

@push('scripts')
  @vite(['resources/js/auth/login.tsx'])
@endpush
