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
@endsection

@push('scripts')
  @vite(['resources/js/dashboard.tsx'])
@endpush
