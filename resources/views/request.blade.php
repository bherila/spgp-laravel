@extends('layouts.app')

@section('content')
  <div id="request" data-csrf-token="{{ csrf_token() }}"></div>
@endsection

@push('scripts')
  @vite(['resources/js/request/index.tsx'])
@endpush
