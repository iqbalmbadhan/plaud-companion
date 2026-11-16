<?php

namespace App\Support;

/**
 * Sample recordings shaped exactly like Plaud's own API output, for demonstrating the
 * "Connect my Plaud account" flow without a physical device or live Plaud account.
 *
 * SAMPLE DATA — NOT REAL. Shapes mirror Plaud's MCP tools (docs.plaud.ai):
 *   - files():   one entry per list_files `data` row (id, name, created_at, start_at, duration ms, serial_number).
 *   - segments(): get_transcript `segments` rows (speaker + content + start_time/end_time in ms).
 *   - summary():  get_note `data_content` Markdown.
 *
 * Fed through PlaudAccountService::importRecording(), the same path live data uses, so what
 * the user sees here is what a real import produces.
 */
class PlaudSampleRecordings
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'file' => [
                    'id' => 'sample-plaud-0001',
                    'name' => 'Weekly Team Standup',
                    'created_at' => '2026-11-16T09:02:00Z',
                    'start_at' => 1763283720000,
                    'duration' => 214000,
                    'serial_number' => 'SAMPLE-NOTEPRO',
                ],
                'segments' => [
                    ['speaker' => 'Dawn', 'content' => 'Good morning everyone. Let us go around and share what we finished last week.', 'start_time' => 1500, 'end_time' => 8200],
                    ['speaker' => 'Iqbal', 'content' => 'I wrapped up the Plaud account connection and the installable app. Both are on the test server now.', 'start_time' => 8200, 'end_time' => 19400],
                    ['speaker' => 'Dawn', 'content' => 'Wonderful. What is left before we show it to a client?', 'start_time' => 19400, 'end_time' => 25000],
                    ['speaker' => 'Iqbal', 'content' => 'Encrypted backups and the audio player. I will have both this week.', 'start_time' => 25000, 'end_time' => 33600],
                ],
                'summary' => "## Summary\nThe team reviewed last week's progress. The Plaud account connection and the installable web app are live on the test server.\n\n## Action Items\n- Finish encrypted backups (Iqbal, this week)\n- Add the recording audio player (Iqbal, this week)\n\n## Key Topics\nProgress review, client-readiness, remaining work.",
            ],
            [
                'file' => [
                    'id' => 'sample-plaud-0002',
                    'name' => 'Client Discovery Call — Northwind',
                    'created_at' => '2026-11-14T15:30:00Z',
                    'start_at' => 1763134200000,
                    'duration' => 383000,
                    'serial_number' => 'SAMPLE-NOTEPRO',
                ],
                'segments' => [
                    ['speaker' => 'Client', 'content' => 'We record a lot of meetings but the notes never get written up. That is our real problem.', 'start_time' => 4200, 'end_time' => 14800],
                    ['speaker' => 'Dawn', 'content' => 'That is exactly what this solves. Your recordings come in on their own, with transcripts and a summary on each one.', 'start_time' => 14800, 'end_time' => 27300],
                    ['speaker' => 'Client', 'content' => 'And can my assistant get to them too, not just me?', 'start_time' => 27300, 'end_time' => 33100],
                    ['speaker' => 'Dawn', 'content' => 'Yes. You share a workspace, and you control who can see what.', 'start_time' => 33100, 'end_time' => 41900],
                ],
                'summary' => "## Summary\nNorthwind's main pain point is that recorded meetings never become written notes. We showed how recordings import automatically with transcripts and per-recording summaries, and how workspace sharing gives an assistant access under the owner's control.\n\n## Action Items\n- Send Northwind a trial workspace invite (Dawn)\n- Follow up after they test one week of recordings (Dawn)\n\n## Key Topics\nMeeting notes, automatic import, workspace sharing, access control.",
            ],
            [
                'file' => [
                    'id' => 'sample-plaud-0003',
                    'name' => 'Product Idea — Voice Memo',
                    'created_at' => '2026-11-13T18:05:00Z',
                    'start_at' => 1763057100000,
                    'duration' => 96000,
                    'serial_number' => 'SAMPLE-NOTEPIN',
                ],
                'segments' => [
                    ['speaker' => 'Iqbal', 'content' => 'Quick note to self. We should let people search across every transcript by a word or a name.', 'start_time' => 900, 'end_time' => 9800],
                    ['speaker' => 'Iqbal', 'content' => 'And a weekly email with everything that was recorded that week would be a nice touch.', 'start_time' => 9800, 'end_time' => 18500],
                ],
                'summary' => "## Summary\nA solo voice memo capturing two product ideas: full-text search across all transcripts, and a weekly email digest of that week's recordings.\n\n## Action Items\n- Add transcript search to the backlog\n- Consider a weekly recording digest email\n\n## Key Topics\nSearch, weekly digest, product backlog.",
            ],
        ];
    }
}
