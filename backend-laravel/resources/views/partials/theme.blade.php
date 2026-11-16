{{--
  Shared design tokens + theme toggle for every full page.

  One canonical palette (--pc-*) in light (default) and dark. Each page maps its own local
  token names onto these in its :root, so a single toggle re-themes all pages consistently and
  the same name can play different roles per page (e.g. --navy is body text in the workspace but
  a surface in settings). Include this once near the top of <head>, before the page's own <style>.
--}}
<style>
  :root {
    /* Surfaces */
    --pc-bg: #F6F8FB;
    --pc-surface: #FFFFFF;
    --pc-surface-alt: #EDF2F8;
    --pc-border: #DCE3EE;
    /* Text */
    --pc-text: #101B33;
    --pc-text-soft: #2D3F57;
    --pc-muted: #64748B;
    /* Brand */
    --pc-emerald: #18A879;
    --pc-emerald-hover: #11855E;
    --pc-emerald-soft: #E8F8F2;
    /* Semantic */
    --pc-blue: #2563EB;
    --pc-blue-soft: #EAF1FF;
    --pc-amber: #D97706;
    --pc-amber-soft: #FFF7ED;
    --pc-danger: #DC2626;
    --pc-danger-soft: #FEF2F2;
    --pc-purple: #7C3AED;
    /* Scale */
    --pc-font: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    --pc-radius-sm: 8px;
    --pc-radius: 12px;
    --pc-radius-lg: 16px;
    --pc-radius-pill: 999px;
    --pc-shadow: 0 1px 2px rgba(16, 27, 51, 0.06), 0 8px 24px rgba(16, 27, 51, 0.06);
    --pc-glow: #E9EFF7;
    --pc-ink: #101B33;        /* inverted chips: dark in light theme */
    --pc-ink-text: #FFFFFF;
    color-scheme: light;
  }

  html[data-theme="dark"] {
    --pc-bg: #0B1324;
    --pc-surface: #13203B;
    --pc-surface-alt: #0F1A30;
    --pc-border: #253861;
    --pc-text: #F1F5F9;
    --pc-text-soft: #CBD5E1;
    --pc-muted: #94A3B8;
    --pc-emerald: #18A879;
    --pc-emerald-hover: #149168;
    --pc-emerald-soft: #10251C;
    --pc-blue: #3B82F6;
    --pc-blue-soft: #16233F;
    --pc-amber: #F59E0B;
    --pc-amber-soft: #2A2112;
    --pc-danger: #EF4444;
    --pc-danger-soft: #2A1416;
    --pc-purple: #8B5CF6;
    --pc-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 8px 24px rgba(0, 0, 0, 0.35);
    --pc-glow: #172A45;
    --pc-ink: #223252;        /* inverted chips: a raised surface in dark theme */
    --pc-ink-text: #F1F5F9;
    color-scheme: dark;
  }

  /* Floating theme toggle, present on every page that includes this partial. */
  .pc-theme-toggle {
    position: fixed; z-index: 2147483000; bottom: 18px; right: 18px;
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 14px; border-radius: var(--pc-radius-pill);
    border: 1px solid var(--pc-border); background: var(--pc-surface); color: var(--pc-text);
    font: 600 13px/1 var(--pc-font); cursor: pointer; box-shadow: var(--pc-shadow);
    transition: transform .12s ease, background .2s ease, color .2s ease;
  }
  .pc-theme-toggle:hover { transform: translateY(-1px); }
  .pc-theme-toggle:focus-visible { outline: 2px solid var(--pc-emerald); outline-offset: 2px; }
  @media print { .pc-theme-toggle { display: none; } }
</style>
<script>
  (function () {
    var KEY = 'pc-theme';
    var root = document.documentElement;
    function system() { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    function stored() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
    function apply(theme) { root.setAttribute('data-theme', theme); }
    // Apply as early as possible to avoid a flash.
    apply(stored() || system());
    document.addEventListener('DOMContentLoaded', function () {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pc-theme-toggle';
      btn.setAttribute('aria-label', 'Switch between light and dark theme');
      function label() {
        var dark = root.getAttribute('data-theme') === 'dark';
        btn.innerHTML = (dark ? '☀️' : '🌙') + ' <span>' + (dark ? 'Light' : 'Dark') + '</span>';
      }
      btn.addEventListener('click', function () {
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        apply(next);
        try { localStorage.setItem(KEY, next); } catch (e) {}
        label();
      });
      label();
      document.body.appendChild(btn);
    });
  })();
</script>
