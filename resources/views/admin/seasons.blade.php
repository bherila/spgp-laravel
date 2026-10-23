@extends('layouts.app')

@section('title', 'Seasons - Admin')

@section('content')
  <x-page-data :data="$pageData" />
  <div id="admin-seasons" data-csrf-token="{{ csrf_token() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/seasons/index.tsx'])
@endpush
