@extends('layouts.app')

@section('content')
  <div id="register"
    data-errors="{{ json_encode($errors->toArray()) }}"
    data-old-name="{{ old('name', '') }}"
    data-old-email="{{ old('email', '') }}"
    data-old-invite-code="{{ old('invite_code', '') }}"
  ></div>
@endsection

@push('scripts')
  @vite(['resources/js/auth/register.tsx'])
@endpush
