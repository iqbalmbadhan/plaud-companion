{{-- Adds a show/hide (eye) button to every password field on the page. Self-contained. --}}
<style>
  .pw-wrap { position: relative; display: block; }
  .pw-wrap > input { padding-right: 48px !important; }
  .pw-eye {
    position: absolute; top: 50%; right: 8px; transform: translateY(-50%);
    width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
    border: 0; background: transparent; cursor: pointer; padding: 0; border-radius: 8px;
    color: var(--muted, #94A3B8); line-height: 0;
  }
  .pw-eye:hover { color: var(--emerald, #18A879); background: rgba(24,168,121,0.10); }
  .pw-eye:focus-visible { outline: 2px solid var(--emerald, #18A879); outline-offset: 1px; }
  .pw-eye svg { width: 20px; height: 20px; }
  .pw-eye .pw-eye-off { display: none; }
  .pw-eye.is-on .pw-eye-on { display: none; }
  .pw-eye.is-on .pw-eye-off { display: inline; }
</style>
<script>
  (function () {
    function enhance(input) {
      if (!input || input.dataset.pwEye) return;
      input.dataset.pwEye = '1';
      var wrap = document.createElement('span');
      wrap.className = 'pw-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-eye';
      btn.setAttribute('aria-label', 'Show password');
      btn.setAttribute('tabindex', '-1');
      btn.innerHTML =
        '<svg class="pw-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
        '<svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-on', show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        input.focus();
      });
      wrap.appendChild(btn);
    }
    function run() {
      document.querySelectorAll('input[type="password"]').forEach(enhance);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }
  })();
</script>
