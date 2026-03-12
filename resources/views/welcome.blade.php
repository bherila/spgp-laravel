@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
  <div id="home"></div>
@endsection

@push('scripts')
  @vite(['resources/js/home.tsx'])
@endpush
