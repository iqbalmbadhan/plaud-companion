{{-- Connect my Plaud account: one button, then recordings import automatically. --}}
@php($plaudLink = \App\Models\PlaudAccountLink::query()->where('user_id', auth()->id())->first())
<div class="card" id="plaudAccountCard">
    <div class="section-title"><span>🎙️ Your Plaud account</span></div>

    @if (session('plaud_status'))
        <div class="alert-success" role="status" style="font-size:16px;">{{ session('plaud_status') }}</div>
    @endif
    @if (session('plaud_error'))
        <div class="alert-error" role="alert" style="font-size:16px;">{{ session('plaud_error') }}</div>
    @endif

    @if ($plaudLink === null)
        <p style="font-size:16px; line-height:1.6; margin:0 0 16px;">
            Connect once and your Plaud recordings, transcripts and Plaud summaries will appear here automatically.
            You will sign in on Plaud's own page — we never see your Plaud password.
        </p>
        <a href="{{ route('plaud.account.connect') }}" class="btn btn-primary" style="font-size:17px; padding:14px 22px;">Connect my Plaud account</a>
    @else
        @php($needsReconnect = $plaudLink->status === 'reconnect_needed')
        <p style="font-size:16px; line-height:1.6; margin:0 0 12px;">
            @if ($needsReconnect)
                <strong style="color:#f59e0b;">Please connect again.</strong> Plaud asked you to sign in again for security.
            @else
                <strong style="color:#18A879;">✓ Connected.</strong> New recordings are imported automatically when you open the app.
            @endif
            <br>
            <span id="plaudSyncInfo">
                {{ $plaudLink->imported_count }} recording(s) imported{{ $plaudLink->last_synced_at ? ' · last checked ' . $plaudLink->last_synced_at->diffForHumans() : '' }}.
            </span>
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            @if ($needsReconnect)
                <a href="{{ route('plaud.account.connect') }}" class="btn btn-primary" style="font-size:17px; padding:14px 22px;">Connect again</a>
            @else
                <button type="button" class="btn btn-primary" id="plaudSyncNow" style="font-size:17px; padding:14px 22px;">Check for new recordings</button>
            @endif
            <form method="POST" action="{{ route('plaud.account.disconnect') }}" onsubmit="return confirm('Disconnect your Plaud account? Recordings already imported will stay.');">
                @csrf
                <button type="submit" class="btn" style="font-size:15px; padding:14px 18px;">Disconnect</button>
            </form>
        </div>
        <script>
            (function () {
                var btn = document.getElementById('plaudSyncNow');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    btn.disabled = true; btn.textContent = 'Checking…';
                    fetch(@json(route('plaud.account.sync')), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' }
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        document.getElementById('plaudSyncInfo').textContent = d.message || 'Done.';
                        if (d.error === 'reconnect_needed') { window.location.reload(); }
                    }).catch(function () {
                        document.getElementById('plaudSyncInfo').textContent = 'Could not reach Plaud. Please try again later.';
                    }).finally(function () { btn.disabled = false; btn.textContent = 'Check for new recordings'; });
                });
            })();
        </script>
    @endif
</div>
