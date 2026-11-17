{{-- App-like connectivity awareness: a slim banner when offline, and an automatic data
     refresh when the connection returns. Included in the workspace shell. --}}
<style>
  #pcNet {
    position: fixed; left: 50%; transform: translateX(-50%) translateY(-120%);
    top: 10px; z-index: 2147482000; display: inline-flex; align-items: center; gap: 10px;
    padding: 10px 18px; border-radius: 999px; font: 600 13.5px/1 var(--pc-font, Inter, system-ui, sans-serif);
    box-shadow: 0 10px 28px rgba(0,0,0,0.22); transition: transform .28s ease; max-width: 92vw;
  }
  #pcNet.show { transform: translateX(-50%) translateY(0); }
  #pcNet.off { background: #7F1D1D; color: #FEE2E2; }
  #pcNet.on  { background: var(--pc-emerald, #18A879); color: #fff; }
  #pcNet .d { width: 9px; height: 9px; border-radius: 50%; background: currentColor; opacity: .85; }
</style>
<div id="pcNet" role="status" aria-live="polite"><span class="d"></span><span id="pcNetText"></span></div>
<script>
  (function () {
    var el = document.getElementById('pcNet'), txt = document.getElementById('pcNetText');
    var hideTimer = null;
    function show(kind, message, autohide) {
      el.classList.remove('on', 'off');
      el.classList.add(kind, 'show');
      txt.textContent = message;
      if (hideTimer) clearTimeout(hideTimer);
      if (autohide) hideTimer = setTimeout(function () { el.classList.remove('show'); }, 3200);
    }
    function goOffline() { show('off', "You're offline — changes won't be saved until you reconnect.", false); }
    function goOnline() {
      show('on', 'Back online.', true);
      // Refresh workspace data if the app exposes a loader.
      try { if (typeof loadWorkspaceData === 'function') loadWorkspaceData(); } catch (e) {}
    }
    window.addEventListener('offline', goOffline);
    window.addEventListener('online', goOnline);
    if (!navigator.onLine) goOffline();
  })();
</script>
