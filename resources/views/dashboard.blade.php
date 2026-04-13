@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div
    id="dashboard"
    data-user-name="{{ auth()->user()->name }}"
    data-user-id="{{ auth()->id() }}"
    data-is-admin="{{ auth()->user()->isAdmin() ? '1' : '0' }}"
    data-season-id="{{ $seasonId ?? '' }}"
    data-is-questions-view="{{ ($isQuestionsView ?? false) ? '1' : '0' }}"
  ></div>

  @if($adminData !== null)
    <div id="admin-dashboard"></div>
    <script id="admin-dashboard-data" type="application/json">{!! json_encode($adminData, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endif
@endsection

@push('scripts')
  @vite(['resources/js/dashboard.tsx'])
  @if($adminData !== null)
    @vite(['resources/js/admin-dashboard.tsx'])
  @endif
@endpush
