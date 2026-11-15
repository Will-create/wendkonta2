@php
    $mobileBuild = substr(hash('sha256', implode('', array_map(function ($asset) {
        return is_file(public_path($asset)) ? hash_file('sha256', public_path($asset)) : '';
    }, ['css/salepro-mobile.css', 'js/salepro-mobile.js', 'js/salepro-pwa.js', 'service-worker.js', 'offline.html', 'manifest.json', 'icons/icon-192x192.png', 'icons/icon-512x512.png']))), 0, 16);
    $saleproMobileConfig = ['build' => $mobileBuild, 'home' => url('/dashboard'),
        'summary' => url('/dashboard/mobile-summary'), 'currency' => config('currency'),
        'precision' => (int) config('decimal', 2), 'strings' => trans('mobile')];
@endphp
<meta name="theme-color" content="#733686">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="stylesheet" href="{{ asset('css/salepro-mobile.css') }}?v={{ $mobileBuild }}">
<script id="salepro-mobile-config" type="application/json">@json($saleproMobileConfig)</script>
<script defer src="{{ asset('js/salepro-mobile.js') }}?v={{ $mobileBuild }}"></script>
<script defer src="{{ asset('js/salepro-pwa.js') }}?v={{ $mobileBuild }}"></script>
