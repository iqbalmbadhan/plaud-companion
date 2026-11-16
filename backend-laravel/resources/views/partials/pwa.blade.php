{{-- PWA: iOS home-screen support + service worker (static files only; pages are never cached). --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PLAUD">
<link rel="apple-touch-icon" href="{{ url('/icons/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ url('/icons/icon-192.png') }}">
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(@json(url('/sw.js')), { scope: @json(rtrim(url('/'), '/') . '/') })
        .catch(function (err) { console.warn('[PWA] Service worker registration failed:', err); });
    });
  }
</script>
