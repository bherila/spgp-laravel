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

  <x-page-data id="dashboard-data" :data="$seasons" />

  @if($isQuestionsView)
    <x-page-data id="questions-data" :data="$questions" />
  @endif

  @if($adminData !== null)
    <div id="admin-dashboard"></div>
    <x-page-data id="admin-dashboard-data" :data="$adminData" />
  @endif
@endsection

@push('scripts')
  @vite(['resources/js/dashboard.tsx'])
  @if($adminData !== null)
    @vite(['resources/js/admin-dashboard.tsx'])
  @endif
@endpush
