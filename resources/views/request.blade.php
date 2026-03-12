@extends('layouts.app')

@section('title', 'Request a Pass')

@section('content')
  <div id="request" data-csrf-token="{{ csrf_token() }}" data-season-id="{{ $season->id }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/request/index.tsx'])
@endpush
