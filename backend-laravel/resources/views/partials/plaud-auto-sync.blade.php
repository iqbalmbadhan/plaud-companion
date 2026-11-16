{{-- Imports new Plaud recordings in the background when the workspace opens (at most every 10 minutes). --}}
<script>
(function () {
  var statusUrl = @json(route('plaud.account.status'));
  var syncUrl = @json(route('plaud.account.sync'));
  function run(rounds) {
    fetch(syncUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.imported > 0 && typeof loadWorkspaceData === 'function') { loadWorkspaceData(); }
        if (d.remaining && rounds < 5) { run(rounds + 1); }
      }).catch(function () {});
  }
  window.addEventListener('load', function () {
    fetch(statusUrl, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (s) {
      if (!s.connected || s.status !== 'connected') return;
      var last = s.last_synced_at ? Date.parse(s.last_synced_at) : 0;
      if (Date.now() - last > 10 * 60 * 1000) { run(0); }
    }).catch(function () {});
  });
})();
</script>
