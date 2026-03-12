@extends('layouts.app')

@section('title', $season->pass_name . ' ' . $season->pass_year . ' Pass Requests - Admin')

@section('content')
  <div id="admin-season-pass-requests" 
       data-csrf-token="{{ csrf_token() }}" 
       data-season-id="{{ $season->id }}"
       data-season-name="{{ $season->pass_name }}"
       data-season-year="{{ $season->pass_year }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/season-pass-requests.tsx'])
@endpush
