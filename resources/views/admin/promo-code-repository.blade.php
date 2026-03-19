@extends('layouts.app')

@section('title', 'Promo Code Repository - ' . $season->pass_name . ' ' . $season->pass_year . ' - Admin')

@section('content')
  <div id="admin-promo-code-repository"
       data-csrf-token="{{ csrf_token() }}"
       data-season-id="{{ $season->id }}"
       data-season-name="{{ $season->pass_name }}"
       data-season-year="{{ $season->pass_year }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/promo-code-repository.tsx'])
@endpush
