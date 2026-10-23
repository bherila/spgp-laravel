@props(['data', 'id' => 'page-data'])
<script id="{{ $id }}" type="application/json">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
