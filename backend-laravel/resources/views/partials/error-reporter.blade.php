{{-- Sends uncaught browser errors on this page to the developer debug log (rate-limited server-side). --}}
<script>
(function () {
    var endpoint = @json(route('client-errors.store')), sent = 0;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || @json(csrf_token());
    function report(message, url, line, column, stack) {
        if (sent >= 5 || !message) return; // at most 5 reports per page view
        sent++;
        try {
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ message: String(message).slice(0, 1000), url: String(url || location.href).split('?')[0].slice(0, 500), line: line || 0, column: column || 0, stack: String(stack || '').slice(0, 4000) })
            }).catch(function () {});
        } catch (e) { /* never let error reporting break the page */ }
    }
    window.addEventListener('error', function (e) { report(e.message, e.filename, e.lineno, e.colno, e.error && e.error.stack); });
    window.addEventListener('unhandledrejection', function (e) { var r = e.reason || {}; report(r.message || String(r), location.href, 0, 0, r.stack); });
})();
</script>
