@extends('layouts.app')

@section('title', 'Seasons - Admin')

@section('content')
  <div id="admin-seasons" data-csrf-token="{{ csrf_token() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/seasons/index.tsx'])
@endpush
