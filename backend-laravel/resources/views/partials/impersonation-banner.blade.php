@php($imp = \App\Support\Impersonation::current())
@if ($imp)
    @php($impDev = \App\Models\User::query()->find($imp['impersonator_id']))
    @php($impTarget = \App\Models\User::query()->find($imp['target_id']))
    <div id="impersonationBanner" role="alert" style="position:sticky; top:0; z-index:9999; background:#FFF7ED; color:#9A3412; border-bottom:2px solid #F59E0B; padding:9px 16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:center; font:600 13px/1.4 system-ui, -apple-system, 'Segoe UI', sans-serif;">
        <span>⚠ {{ $impDev?->display_name ?? 'Developer' }} is working as <u>{{ $impTarget?->display_name ?? 'this user' }}</u> · every action is recorded in the audit log · ends in <span id="impersonationLeft" data-expires="{{ (int) $imp['expires_at'] }}">{{ max(0, (int) ceil(((int) $imp['expires_at'] - now()->getTimestamp()) / 60)) }} min</span></span>
        <form method="POST" action="{{ route('impersonation.stop') }}" style="margin:0;">
            @csrf
            <button type="submit" style="background:#9A3412; color:#fff; border:0; border-radius:8px; padding:5px 12px; font-weight:700; cursor:pointer;">End session</button>
        </form>
    </div>
    <script>
    (function () {
        var el = document.getElementById('impersonationLeft');
        if (!el) return;
        var expires = Number(el.getAttribute('data-expires')) * 1000, offset = {{ now()->getTimestamp() }} * 1000 - Date.now();
        setInterval(function () {
            var left = Math.max(0, expires - (Date.now() + offset)), m = Math.floor(left / 60000), s = Math.floor((left % 60000) / 1000);
            el.textContent = m + ':' + String(s).padStart(2, '0');
            if (left <= 0) { location.reload(); }
        }, 1000);
    })();
    </script>
@endif
