# PLAUD Companion — interactive demo (`demo.html`)

A single, self-contained HTML file for showing the app without accounts or a server.
Upload `demo.html` to any web folder (or open it from disk). It needs no build step.

- Sample data only; everything is stored in the visitor's browser (`localStorage`).
- A Content-Security-Policy blocks every network request, so nothing is sent anywhere.
- AI answers come from a labelled, extractive demo engine (no AI model).
- Simulated: sign-in, PLAUD Bluetooth device, AI providers, signed updates, server backups.
- Real in the browser: TXT/SRT/VTT/JSON/DOCX import, permissions, DOCX/PDF/ZIP export.
- Link straight to a persona and design: `demo.html?as=u_morgan&theme=modern`
  (`u_morgan` owner, `u_priya` editor, `u_sam` viewer, `u_dev` developer).

## Tests

```bash
node --test Docs/demo-app/tests/                      # core logic (15 tests)
JSDOM_DIR=/path/with/jsdom node --test Docs/demo-app/tests/   # + UI flows (11 tests)
```

Known gaps compared with the full app (not planned for the demo): device setup wizard,
quick AI prompt presets, provider latency test, .plaudvault backup/restore, PLAUD Partner API
settings, create-workspace, global search, audio import, self-registration, installer preview.
