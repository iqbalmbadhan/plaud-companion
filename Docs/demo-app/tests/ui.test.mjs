// End-to-end UI tests for demo.html, driven through jsdom.
// Requires jsdom: set JSDOM_DIR to a folder where `npm install jsdom` was run, or install it next to this repo.
// Run: JSDOM_DIR=/path/to/node_project node --test Docs/demo-app/tests/
import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { createRequire } from 'node:module';

const here = dirname(fileURLToPath(import.meta.url));
const html = readFileSync(join(here, '..', 'demo.html'), 'utf8');
let JSDOM = null;
try {
  const req = createRequire(join(process.env.JSDOM_DIR || process.cwd(), 'package.json'));
  ({ JSDOM } = req('jsdom'));
} catch { /* skipped below */ }
const skip = JSDOM ? false : 'jsdom not available (set JSDOM_DIR)';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const opened = [];
// Each page keeps a 1-second timer; close every window so the test process can exit.
after(() => opened.forEach(d => d.window.close()));

async function open(query = '', storage = null) {
  const downloads = [];
  const dom = new JSDOM(html, {
    url: 'https://demo.example/demo.html' + query,
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    beforeParse(win) {
      if (storage) for (const [k, v] of Object.entries(storage)) win.localStorage.setItem(k, v);
      win.URL.createObjectURL = blob => { downloads.push(blob); return 'blob:demo/' + downloads.length; };
      win.URL.revokeObjectURL = () => {};
      win.scrollTo = () => {};
      win.HTMLAnchorElement.prototype.click = function () { this.dispatchEvent(new win.Event('demo-download')); };
    }
  });
  opened.push(dom);
  const w = dom.window, d = w.document;
  await sleep(10);
  const $ = s => d.querySelector(s), $$ = s => [...d.querySelectorAll(s)];
  const click = async (sel) => { const el = typeof sel === 'string' ? $(sel) : sel; assert.ok(el, 'element not found: ' + sel); el.click(); await sleep(15); };
  const submit = async (sel, values = {}) => {
    const f = $(sel); assert.ok(f, 'form not found: ' + sel);
    for (const [k, v] of Object.entries(values)) { const i = f.querySelector('[name="' + k + '"]'); i.value = v; }
    f.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); await sleep(15);
  };
  const go = async (hash) => { w.location.hash = hash; await sleep(15); };
  const state = () => JSON.parse(w.localStorage.getItem('plaud_companion_demo_v3'));
  const toasts = () => $$('.toast').map(t => t.textContent);
  const login = async (id) => { await click('[data-act="login"][data-id="' + id + '"]'); };
  return { dom, w, d, $, $$, click, submit, go, state, toasts, login, downloads };
}

test('login page offers personas; suspended account cannot sign in', { skip }, async () => {
  const t = await open();
  assert.equal(t.$$('.persona').length, 5);
  assert.match(t.$('.demo-banner').textContent, /Nothing is sent to any server/);
  await t.login('u_jordan');
  assert.ok(t.$('.persona'), 'still on login page');
  assert.equal(t.state()?.session ?? null, null, 'no session created');
});

test('viewer can read and ask but not change anything', { skip }, async () => {
  const t = await open();
  await t.login('u_sam');
  assert.equal(t.$('.topbar h1').textContent, 'Dashboard');
  await t.go('#/t/t_lead');
  assert.ok(t.$('[data-act="summarize"]').disabled, 'summarize disabled for viewer');
  assert.ok(t.$('[data-act="export-one"]').disabled);
  assert.equal(t.$('[data-act="delete-t"]'), null);
  await t.submit('form[data-form="ask-one"]', { q: 'What about the lease break clause?' });
  assert.match(t.$('#chat').textContent, /break clause/);
  assert.match(t.$('#chat').textContent, /Sources:/);
  await t.go('#/import');
  assert.match(t.$('#view').textContent, /cannot import/);
  await t.go('#/dev');
  assert.equal(t.$('.topbar h1').textContent, 'Dashboard', 'developer console not reachable');
});

test('editor: summarize, extract actions, paste import with duplicate check, export ZIP', { skip }, async () => {
  const t = await open();
  await t.login('u_priya');
  await t.go('#/t/t_lead');
  await t.click('[data-act="summarize"]');
  assert.match(t.$('#view').textContent, /no AI model was used/);
  await t.click('[data-act="extract"]');
  assert.ok(t.$$('.check-row').length >= 3);
  const s1 = t.state().transcripts.find(x => x.id === 't_lead');
  assert.ok(s1.summary && s1.actions.length >= 3);

  await t.go('#/import');
  const text = '[00:00] Lena: We will ship the pilot next week.\n[00:10] Omar: I need to review the contract first.';
  await t.submit('form[data-form="paste"]', { title: 'Pilot planning', text });
  assert.match(t.w.location.hash, /^#\/t\/t_/);
  assert.match(t.$('#view').textContent, /Pilot planning/);
  await t.go('#/import');
  await t.submit('form[data-form="paste"]', { title: 'Copy', text });
  assert.ok(t.toasts().some(x => /already exists/.test(x)));

  await t.go('#/exports');
  await t.submit('form[data-form="export"]');
  assert.equal(t.downloads.length, 1);
  assert.equal(t.downloads[0].type, 'application/zip');
  assert.ok(t.downloads[0].size > 5000);
  assert.equal(t.state().exports.length, 1);
});

test('untrusted transcript text is rendered as text, never as HTML', { skip }, async () => {
  const t = await open();
  await t.login('u_priya');
  await t.go('#/import');
  await t.submit('form[data-form="paste"]', { title: '<img src=x onerror="window.__pwned=1">', text: 'Eve: <script>window.__pwned=2</script><b>bold</b>' });
  assert.equal(t.w.__pwned, undefined);
  assert.equal(t.$('#view img'), null);
  assert.equal(t.$('#view b.injected'), null);
  assert.match(t.$('#view').textContent, /<script>window.__pwned=2<\/script><b>bold<\/b>/);
});

test('developer impersonates the boss with an audited, time-limited session', { skip }, async () => {
  const t = await open();
  await t.login('u_dev');
  assert.equal(t.$('.topbar h1').textContent, 'Developer Console');
  await t.click('[data-act="dev-tab"][data-id="users"]');
  await t.click('[data-act="impersonate"][data-id="u_morgan"]');
  await t.submit('form[data-form="imp"]', { reason: 'Prepare the board export for Morgan' });
  assert.match(t.$('.imp-bar').textContent, /Demo Developer is working as Morgan Ellis/);
  assert.equal(t.$('[data-go="dev"]'), null, 'developer console hidden while impersonating');
  await t.go('#/t/t_board');
  await t.click('[data-act="summarize"]');
  const st = t.state();
  const ev = st.audit.find(a => a.action === 'ai.summary');
  assert.equal(ev.actor, 'u_dev');
  assert.equal(ev.as, 'u_morgan');
  assert.ok(st.audit.some(a => a.action === 'impersonation.start' && /board export/.test(a.detail)));
  await t.click('[data-act="end-imp"]');
  assert.equal(t.$('.imp-bar'), null);
  assert.equal(t.$('.topbar h1').textContent, 'Developer Console');
  assert.ok(t.state().audit.some(a => a.action === 'impersonation.end'));
});

test('impersonation ends automatically after the time limit', { skip }, async () => {
  const t = await open();
  await t.login('u_dev');
  await t.click('[data-act="dev-tab"][data-id="users"]');
  await t.click('[data-act="impersonate"][data-id="u_priya"]');
  await t.submit('form[data-form="imp"]', { reason: 'Check her export settings' });
  const realNow = t.w.Date.now;
  t.w.Date.now = () => realNow() + 31 * 60000;
  await sleep(1150);
  assert.equal(t.$('.imp-bar'), null);
  assert.ok(t.state().audit.some(a => a.action === 'impersonation.end' && /time limit/.test(a.detail)));
});

test('developer suspends a user, who is then locked out', { skip }, async () => {
  const t = await open();
  await t.login('u_dev');
  await t.click('[data-act="dev-tab"][data-id="users"]');
  await t.click('[data-act="toggle-suspend"][data-id="u_priya"]');
  assert.equal(t.state().users.find(u => u.id === 'u_priya').status, 'suspended');
  assert.equal(t.$('[data-act="impersonate"][data-id="u_priya"]'), null, 'cannot impersonate a suspended user');
  await t.go('#/settings');
  await t.click('#view [data-act="logout"]');
  await t.login('u_priya');
  assert.equal(t.state().session, null);
  assert.equal(t.$('[data-act="login"][data-id="u_priya"]').getAttribute('aria-disabled'), 'true');
});

test('workspace list in developer console shows titles but not transcript content', { skip }, async () => {
  const t = await open();
  await t.login('u_dev');
  await t.click('[data-act="dev-tab"][data-id="workspaces"]');
  const txt = t.$('#view').textContent;
  assert.match(txt, /Weekly Leadership Sync/);
  assert.doesNotMatch(txt, /break clause/, 'segment text must not appear');
});

test('simulated provider outage shows an error, logs it for the developer, and generates nothing', { skip }, async () => {
  const t = await open();
  await t.login('u_dev');
  await t.click('[data-act="dev-tab"][data-id="debug"]');
  await t.click('[data-act="toggle-outage"]');
  await t.click('[data-act="dev-tab"][data-id="users"]');
  await t.click('[data-act="impersonate"][data-id="u_morgan"]');
  await t.submit('form[data-form="imp"]', { reason: 'Investigate AI errors' });
  await t.go('#/t/t_vendor');
  await t.click('[data-act="summarize"]');
  assert.ok(t.toasts().some(x => /not responding\. Nothing was generated/.test(x)));
  const st = t.state();
  assert.equal(st.transcripts.find(x => x.id === 't_vendor').summary, undefined);
  const err = st.errors.find(e => e.source === 'ai:summary');
  assert.ok(err && err.actingAs === 'u_morgan');
  await t.click('[data-act="end-imp"]');
  await t.click('[data-act="dev-tab"][data-id="debug"]');
  assert.match(t.$('#view').textContent, new RegExp(err.id));
});

test('both designs switch, and shareable links open a persona', { skip }, async () => {
  const t = await open('?as=u_sam&theme=modern');
  assert.equal(t.d.documentElement.getAttribute('data-theme'), 'modern');
  assert.equal(t.state().session.userId, 'u_sam');
  assert.equal(t.w.location.search, '', 'query string removed after use');
  await t.click('[data-act="toggle-theme"]');
  assert.equal(t.d.documentElement.getAttribute('data-theme'), 'classic');
  const blocked = await open('?as=u_jordan');
  assert.equal(blocked.state()?.session ?? null, null, 'suspended user cannot use a link');
});

test('every main page renders without errors for each role', { skip }, async () => {
  for (const who of ['u_morgan', 'u_priya', 'u_sam', 'u_dev']) {
    const t = await open();
    await t.login(who);
    for (const v of ['dashboard', 'transcripts', 'import', 'projects', 'ai', 'exports', 'device', 'team', 'settings', 'profile', 'dev']) {
      await t.go('#/' + v);
      assert.doesNotMatch(t.$('#view').textContent, /Something went wrong/, who + ' ' + v);
    }
    if (who === 'u_dev') {
      for (const tab of ['overview', 'users', 'workspaces', 'debug', 'updates', 'backups', 'audit']) {
        await t.go('#/dev');
        await t.click('[data-act="dev-tab"][data-id="' + tab + '"]');
        assert.doesNotMatch(t.$('#view').textContent, /Something went wrong/, 'dev ' + tab);
      }
    }
    assert.deepEqual(t.state().errors, [], who + ' produced errors: ' + JSON.stringify(t.state().errors));
  }
});
