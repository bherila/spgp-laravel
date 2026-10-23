@extends('layouts.app')

@section('title', 'Email Log - Admin')

@section('content')
  <x-page-data :data="$pageData" />
    <div id="email-log"></div>
@endsection

@push('scripts')
    @viteReactRefresh
    @vite('resources/js/admin/email-log.tsx')
@endpush
