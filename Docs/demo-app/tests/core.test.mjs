// Unit tests for the pure logic in demo.html (<script id="demo-core">).
// Run: node --test Docs/demo-app/tests/
// Writes sample DOCX/PDF/ZIP files to $DEMO_TEST_OUT (if set) for external validation.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const here = dirname(fileURLToPath(import.meta.url));
const html = readFileSync(join(here, '..', 'demo.html'), 'utf8');
const core = html.match(/<script id="demo-core">([\s\S]*?)<\/script>/)[1];
const ctx = { TextEncoder, TextDecoder, Blob, Response, DecompressionStream, console, Math, Date, JSON };
ctx.globalThis = ctx;
vm.createContext(ctx);
vm.runInContext(core, ctx);
const PD = ctx.PD;
const OUT = process.env.DEMO_TEST_OUT;
if (OUT) mkdirSync(OUT, { recursive: true });

test('page has a CSP that blocks all network connections', () => {
  assert.match(html, /connect-src 'none'/);
  assert.match(html, /default-src 'none'/);
  assert.doesNotMatch(html, /<script[^>]+src=/, 'no external scripts');
  assert.doesNotMatch(html, /<link[^>]+href=/, 'no external stylesheets');
});

test('escaping neutralises HTML in untrusted transcript text', () => {
  assert.equal(PD.esc('<img src=x onerror=alert(1)>"\''), '&lt;img src=x onerror=alert(1)&gt;&quot;&#39;');
  assert.equal(PD.xmlEsc('a\u0001b & <c>'), 'ab &amp; &lt;c&gt;');
});

test('time formatting and clock parsing', () => {
  assert.equal(PD.fmtMs(65000), '01:05');
  assert.equal(PD.fmtMs(3725000), '1:02:05');
  assert.equal(PD.parseClock('01:02:03,500'), 3723500);
  assert.equal(PD.parseClock('02:03'), 123000);
  assert.equal(PD.parseClock('nope'), null);
});

test('plain text parser reads timestamps and speakers', () => {
  const segs = PD.parseText('[00:00] Morgan: Hello team.\n[00:12] Daniel: Budget is fine.\nplain line without speaker');
  assert.equal(segs.length, 3);
  assert.deepEqual([segs[0].speaker, segs[0].start, segs[0].end], ['Morgan', 0, 12000]);
  assert.equal(segs[1].speaker, 'Daniel');
  assert.equal(segs[2].speaker, null);
  assert.ok(segs[2].start >= 12000);
});

test('SRT and VTT parsers', () => {
  const srt = '1\n00:00:01,000 --> 00:00:04,000\nAlex: First line\n\n2\n00:00:05,500 --> 00:00:07,000\n<i>Second</i> line\n';
  const a = PD.parseSrt(srt);
  assert.equal(a.length, 2);
  assert.deepEqual([a[0].speaker, a[0].start, a[0].end, a[0].text], ['Alex', 1000, 4000, 'First line']);
  assert.equal(a[1].text, 'Second line');
  const vtt = 'WEBVTT\n\n00:00.000 --> 00:02.000\n<v Nina>Reporting first.\n';
  const b = PD.parseSrt(vtt);
  assert.deepEqual([b[0].speaker, b[0].text], ['Nina', 'Reporting first.']);
});

test('PLAUD-style JSON parser accepts seconds and milliseconds', () => {
  const j = PD.parseJson(JSON.stringify({ title: 'Exec Sync', segments: [
    { speaker_label: 'CEO', start_ms: 0, end_ms: 15000, text: 'Revenue grew.' },
    { speaker: 'CTO', start: 15, end: 30, text: 'Milestone done.' },
    { text: '' }
  ] }));
  assert.equal(j.title, 'Exec Sync');
  assert.equal(j.segments.length, 2);
  assert.equal(j.segments[1].start, 15000);
  assert.throws(() => PD.parseJson('{"foo":1}'), /no segments/);
});

test('permissions: viewers read only, editors work, only owner/admin manage', () => {
  assert.ok(PD.can('viewer', 'transcript.view'));
  assert.ok(!PD.can('viewer', 'ai.run'));
  assert.ok(!PD.can('viewer', 'export.create'));
  assert.ok(PD.can('editor', 'export.create'));
  assert.ok(!PD.can('editor', 'ai.connect'));
  assert.ok(!PD.can('editor', 'team.manage'));
  assert.ok(PD.can('admin', 'team.manage'));
  assert.ok(!PD.can(null, 'transcript.view'));
});

test('demo engine only uses text from the transcript', () => {
  const s = PD.seed();
  const t = s.transcripts.find(x => x.id === 't_lead');
  const sum = PD.engine.summary(t);
  const allText = t.segments.map(x => x.text).join(' ');
  for (const b of sum.bullets) assert.ok(allText.includes(b.replace(/^[^:]+: /, '')), 'bullet comes from transcript: ' + b);
  assert.match(sum.engine, /no AI model/);
  const acts = PD.engine.actions(t);
  assert.ok(acts.length >= 3);
  const priya = acts.find(a => /hiring timeline/.test(a.task));
  assert.equal(priya.owner, 'Priya Raman');
  assert.equal(priya.due, 'by Friday');
  for (const a of acts) assert.ok(allText.includes(a.task));
  const none = PD.engine.actions({ segments: [{ speaker: 'A', start: 0, text: 'Nice weather today.' }] });
  assert.equal(none.length, 0);
});

test('demo engine Q&A cites matching passages or admits no evidence', () => {
  const s = PD.seed();
  const ts = s.transcripts.filter(x => x.ws === 'w_exec');
  const hit = PD.engine.ask('What did we decide about the lease break clause?', ts);
  assert.ok(hit.sufficient);
  assert.equal(hit.citations[0].transcriptId, 't_lead');
  const miss = PD.engine.ask('Quantum entanglement roadmap?', ts);
  assert.equal(miss.sufficient, false);
  assert.equal(miss.citations.length, 0);
});

test('crc32 matches the standard check value', () => {
  assert.equal(PD.crc32(PD.utf8('123456789')), 0xCBF43926);
});

test('zip round-trip through the reader', async () => {
  const z = PD.zip([{ name: 'a.txt', data: 'hello' }, { name: 'dir/ü.txt', data: 'wörld' }]);
  const entries = await PD.readZip(z);
  assert.deepEqual(Object.keys(entries), ['a.txt', 'dir/ü.txt']);
  assert.equal(new TextDecoder().decode(await PD.zipEntryBytes(entries['dir/ü.txt'])), 'wörld');
});

test('DOCX contains the transcript, escapes markup, and its XML text can be imported back', async () => {
  const t = PD.seed().transcripts[0];
  t.segments.push({ speaker: 'Tester', start: 200000, end: 205000, text: 'Tricky <b>markup</b> & "quotes" — ünïcode' });
  const docx = PD.buildDocx(t, { org: 'Northwind' }, { summary: PD.engine.summary(t), actions: { items: PD.engine.actions(t), engine: PD.ENGINE_LABEL } });
  const entries = await PD.readZip(docx);
  for (const n of ['[Content_Types].xml', '_rels/.rels', 'word/document.xml']) assert.ok(entries[n], n);
  const xml = new TextDecoder().decode(await PD.zipEntryBytes(entries['word/document.xml']));
  assert.ok(xml.includes('Tricky &lt;b&gt;markup&lt;/b&gt; &amp; &quot;quotes&quot; — ünïcode'));
  assert.ok(!xml.includes('<b>markup'));
  const back = PD.docxXmlToText(xml);
  assert.ok(back.includes('Tricky <b>markup</b> & "quotes" — ünïcode'));
  assert.ok(back.includes('Weekly Leadership Sync'));
  if (OUT) writeFileSync(join(OUT, 'sample.docx'), docx);
});

test('PDF is structurally valid, multi-page for long transcripts, ASCII-safe', () => {
  const t = PD.seed().transcripts[0];
  const long = { ...t, segments: Array.from({ length: 220 }, (_, i) => ({ speaker: 'S' + (i % 3), start: i * 5000, end: i * 5000 + 4000, text: 'Line ' + i + ' with “smart quotes” and (parens) and ü.' })) };
  const bytes = PD.buildPdf(long, {}, {});
  const pdf = new TextDecoder().decode(bytes);
  assert.ok(pdf.startsWith('%PDF-1.4'));
  assert.ok(pdf.trimEnd().endsWith('%%EOF'));
  const pages = Number(pdf.match(/\/Count (\d+)/)[1]);
  assert.ok(pages >= 3, 'expected several pages, got ' + pages);
  assert.ok(pdf.includes('Line 219'), 'last line present (no truncation)');
  assert.ok(pdf.includes('\\(parens\\)'));
  assert.ok(!/[^\x00-\x7F]/.test(pdf), 'only ASCII bytes');
  // xref offsets must point at "n 0 obj"
  const xrefAt = Number(pdf.match(/startxref\n(\d+)/)[1]);
  assert.ok(pdf.slice(xrefAt).startsWith('xref'));
  const offs = [...pdf.slice(xrefAt).matchAll(/^(\d{10}) 00000 n $/gm)].map(m => Number(m[1]));
  offs.forEach((o, i) => assert.ok(pdf.slice(o).startsWith((i + 1) + ' 0 obj'), 'object ' + (i + 1) + ' offset'));
  if (OUT) writeFileSync(join(OUT, 'sample.pdf'), bytes);
});

test('export ZIP gives unique names to same-title transcripts and a matching manifest', async () => {
  const s = PD.seed();
  const a = s.transcripts[0], b = { ...s.transcripts[1], title: a.title };
  const res = PD.buildExport([a, b], { format: 'both', template: 'meeting_minutes', summary: true, actions: true });
  const entries = await PD.readZip(res.bytes);
  const names = Object.keys(entries).sort();
  assert.deepEqual(names, ['Weekly-Leadership-Sync-2.docx', 'Weekly-Leadership-Sync-2.pdf', 'Weekly-Leadership-Sync.docx', 'Weekly-Leadership-Sync.pdf', 'manifest.json']);
  const manifest = JSON.parse(new TextDecoder().decode(await PD.zipEntryBytes(entries['manifest.json'])));
  const listed = manifest.items.flatMap(i => i.files).sort();
  assert.deepEqual(listed, names.filter(n => n !== 'manifest.json'));
  assert.equal(manifest.demo, true);
  if (OUT) writeFileSync(join(OUT, 'export.zip'), res.bytes);
});

test('seed data is internally consistent and fictional', () => {
  const s = PD.seed();
  const ids = new Set(s.users.map(u => u.id));
  s.members.forEach(m => assert.ok(ids.has(m.user)));
  s.transcripts.forEach(t => {
    assert.ok(s.workspaces.some(w => w.id === t.ws));
    assert.ok(t.durationMs > 0);
    t.segments.forEach(x => assert.ok(x.end >= x.start));
  });
  s.users.forEach(u => assert.match(u.email, /\.example$|@example\.com$/));
  assert.equal(s.users.filter(u => u.developer).length, 1);
  assert.equal(s.users.find(u => u.id === 'u_jordan').status, 'suspended');
});
