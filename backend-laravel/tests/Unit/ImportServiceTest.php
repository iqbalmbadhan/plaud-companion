<?php

namespace PHPUnit\Framework;

if (!class_exists(TestCase::class)) {
    class TestCase
    {
        public static function assertTrue(mixed $condition, string $msg = ''): void
        {
            if (!$condition) {
                throw new \AssertionError($msg ?: 'Failed asserting that condition is true.');
            }
        }

        public static function assertFalse(mixed $condition, string $msg = ''): void
        {
            if ($condition) {
                throw new \AssertionError($msg ?: 'Failed asserting that condition is false.');
            }
        }

        public static function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
        {
            if ($expected !== $actual) {
                throw new \AssertionError($msg ?: sprintf('Failed asserting that %s matches expected %s.', var_export($actual, true), var_export($expected, true)));
            }
        }

        public static function assertCount(int $expectedCount, countable|array $haystack, string $msg = ''): void
        {
            $actualCount = count($haystack);
            if ($expectedCount !== $actualCount) {
                throw new \AssertionError($msg ?: sprintf('Failed asserting that count %d matches expected %d.', $actualCount, $expectedCount));
            }
        }

        public static function assertStringContainsString(string $needle, string $haystack, string $msg = ''): void
        {
            if (!str_contains($haystack, $needle)) {
                throw new \AssertionError($msg ?: sprintf('Failed asserting that "%s" contains "%s".', $haystack, $needle));
            }
        }

        public static function assertNotNull(mixed $actual, string $msg = ''): void
        {
            if ($actual === null) {
                throw new \AssertionError($msg ?: 'Failed asserting that value is not null.');
            }
        }
    }
}

namespace Tests\Unit;

use App\Services\ImportService;
use App\Services\WorkspaceService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class ImportServiceTest extends TestCase
{
    private function createImportService(): ImportService
    {
        // Instantiating with mock or null workspace service for unit testing parser methods
        $reflector = new ReflectionClass(ImportService::class);
        return $reflector->newInstanceWithoutConstructor();
    }

    public function test_allowed_mime_contains_all_required_extensions(): void
    {
        $reflector = new ReflectionClass(ImportService::class);
        $constants = $reflector->getConstants();
        $this->assertTrue(isset($constants['ALLOWED_MIME']), 'ImportService must define ALLOWED_MIME constant.');

        $allowed = $constants['ALLOWED_MIME'];
        $required = [
            'txt' => 'text/plain',
            'srt' => 'application/x-subrip',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
        ];

        foreach ($required as $ext => $expectedMime) {
            $this->assertTrue(isset($allowed[$ext]), "Allowed MIME must include extension: {$ext}");
            $this->assertSame($expectedMime, $allowed[$ext], "MIME for {$ext} must match canonical type {$expectedMime}");
        }
    }

    public function test_extract_official_plaud_json_format(): void
    {
        $service = $this->createImportService();

        $plaudJson = json_encode([
            'transcription_id' => 'task_exec_987',
            'status' => 'SUCCESS',
            'title' => 'Quarterly Operations Review',
            'data' => [
                'text' => "Speaker 1: Meeting started at 10am.\nSpeaker 2: Let's review the quarterly action items.",
                'language' => 'en',
                'duration' => 1843,
                'results' => [
                    [
                        'start' => 0.0,
                        'end' => 4.2,
                        'text' => 'Meeting started at 10am.',
                        'speaker_id' => 'Speaker 1',
                    ],
                    [
                        'start' => 4.5,
                        'end' => 8.1,
                        'text' => "Let's review the quarterly action items.",
                        'speaker_id' => 'Speaker 2',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);

        $result = $service->extractJson($plaudJson, 'quarterly_review.json');

        $this->assertSame('Quarterly Operations Review', $result['title']);
        $this->assertSame('en', $result['language']);
        $this->assertSame('imported', $result['status']);
        $this->assertCount(2, $result['segments']);

        $seg1 = $result['segments'][0];
        $this->assertSame('Speaker 1', $seg1['speaker_label']);
        $this->assertSame(0, $seg1['start_ms']);
        $this->assertSame(4200, $seg1['end_ms']);
        $this->assertSame('Meeting started at 10am.', $seg1['text']);

        $seg2 = $result['segments'][1];
        $this->assertSame('Speaker 2', $seg2['speaker_label']);
        $this->assertSame(4500, $seg2['start_ms']);
        $this->assertSame(8100, $seg2['end_ms']);
        $this->assertSame("Let's review the quarterly action items.", $seg2['text']);
    }

    public function test_extract_diarized_segments_json_format(): void
    {
        $service = $this->createImportService();

        $diarizedJson = json_encode([
            'title' => 'Product Standup',
            'language' => 'en',
            'segments' => [
                [
                    'speaker' => 'Alice',
                    'start_ms' => 1500,
                    'end_ms' => 4800,
                    'text' => 'Good morning everyone.',
                ],
                [
                    'speaker' => 'Bob',
                    'start_time' => 5.2,
                    'end_time' => 9.7,
                    'text' => 'Sprint deliverables are ready.',
                ],
            ],
        ]);

        $result = $service->extractJson($diarizedJson, 'standup.json');

        $this->assertSame('Product Standup', $result['title']);
        $this->assertSame('en', $result['language']);
        $this->assertCount(2, $result['segments']);

        $this->assertSame('Alice', $result['segments'][0]['speaker_label']);
        $this->assertSame(1500, $result['segments'][0]['start_ms']);
        $this->assertSame(4800, $result['segments'][0]['end_ms']);

        $this->assertSame('Bob', $result['segments'][1]['speaker_label']);
        $this->assertSame(5200, $result['segments'][1]['start_ms']);
        $this->assertSame(9700, $result['segments'][1]['end_ms']);
    }

    public function test_extract_top_level_array_json_format(): void
    {
        $service = $this->createImportService();

        $rawArrayJson = json_encode([
            [
                'speaker' => 'Interviewer',
                'start' => 0.0,
                'end' => 3.0,
                'text' => 'Can you describe your architecture?',
            ],
            [
                'speaker' => 'Candidate',
                'start' => 3.2,
                'end' => 6.5,
                'text' => 'We employ zero-knowledge client encryption.',
            ],
        ]);

        $result = $service->extractJson($rawArrayJson, 'interview.json');

        $this->assertCount(2, $result['segments']);
        $this->assertSame('Interviewer', $result['segments'][0]['speaker_label']);
        $this->assertSame(0, $result['segments'][0]['start_ms']);
        $this->assertSame(3000, $result['segments'][0]['end_ms']);
        $this->assertSame('Candidate', $result['segments'][1]['speaker_label']);
        $this->assertSame(3200, $result['segments'][1]['start_ms']);
    }

    public function test_extract_flat_text_json_format(): void
    {
        $service = $this->createImportService();

        $flatJson = json_encode([
            'title' => 'Quick Sync Note',
            'text' => "[00:00] Elena: Updates on the release.\n[00:15] Marcus: Tested and verified.",
        ]);

        $result = $service->extractJson($flatJson, 'quick_sync.json');

        $this->assertSame('Quick Sync Note', $result['title']);
        $this->assertCount(2, $result['segments']);
        $this->assertStringContainsString('Elena', $result['text']);
        $this->assertStringContainsString('Marcus', $result['text']);
    }

    public function test_timestamp_parsing_accuracy(): void
    {
        $service = $this->createImportService();

        // Seconds to ms
        $this->assertSame(4500, $service->parseTimestampToMs(4.5));
        $this->assertSame(10000, $service->parseTimestampToMs(10));

        // Explicit ms
        $this->assertSame(45000, $service->parseTimestampToMs(45000, true));
        $this->assertSame(12345, $service->parseTimestampToMs('12345', true));

        // String formatted HH:MM:SS.mmm
        $this->assertSame(3723450, $service->parseTimestampToMs('01:02:03.450'));

        // String formatted MM:SS.mmm
        $this->assertSame(125300, $service->parseTimestampToMs('02:05.300'));

        // Null & empty values
        $this->assertSame(null, $service->parseTimestampToMs(null));
        $this->assertSame(null, $service->parseTimestampToMs(''));
    }

    public function test_wav_riff_header_duration_estimation(): void
    {
        $service = $this->createImportService();

        // Build synthetic 44-byte WAV header:
        // SampleRate = 16000, 1 Channel, 16-bit (2 bytes per sample) -> ByteRate = 32000 bytes/sec
        // DataSize = 64000 bytes -> Duration = 2000 ms (2.0 sec)
        $riff = 'RIFF';
        $fileSizeMinus8 = pack('V', 64000 + 36);
        $wave = 'WAVE';
        $fmt = 'fmt ';
        $fmtChunkSize = pack('V', 16);
        $audioFormat = pack('v', 1); // PCM
        $channels = pack('v', 1); // mono
        $sampleRate = pack('V', 16000);
        $byteRate = pack('V', 32000);
        $blockAlign = pack('v', 2);
        $bitsPerSample = pack('v', 16);
        $dataChunkHeader = 'data';
        $dataChunkSize = pack('V', 64000);

        $wavBytes = $riff . $fileSizeMinus8 . $wave . $fmt . $fmtChunkSize . $audioFormat . $channels . $sampleRate . $byteRate . $blockAlign . $bitsPerSample . $dataChunkHeader . $dataChunkSize . str_repeat("\x00", 64000);

        $durationMs = $service->estimateWavDurationMs($wavBytes);
        $this->assertSame(2000, $durationMs, 'WAV duration must accurately match calculated bytes / byteRate');
    }

    public function test_audio_import_resilient_fallback(): void
    {
        $service = $this->createImportService();

        // Synthetic 16000 byte MP3 dummy payload (~1000 ms duration at 16000 B/s)
        $rawBytes = str_repeat("\xFF\xFB\x90\x00", 4000);

        $extracted = $service->extractAudio('mp3', $rawBytes, 'team_meeting.mp3');

        $this->assertSame('ready_for_transcription', $extracted['status']);
        $this->assertSame('team_meeting', $extracted['title']);
        $this->assertSame('en', $extracted['language']);
        $this->assertStringContainsString('Audio Recording: team_meeting.mp3', $extracted['text']);
        $this->assertStringContainsString('Ready for neural transcription', $extracted['text']);
        $this->assertCount(1, $extracted['segments']);

        $seg = $extracted['segments'][0];
        $this->assertSame('Audio Track', $seg['speaker_label']);
        $this->assertSame(0, $seg['start_ms']);
        $this->assertTrue($seg['end_ms'] >= 1000);
    }

    public function test_malformed_json_throws_runtime_exception(): void
    {
        $service = $this->createImportService();

        $malformed = '{ "title": "Incomplete json...';
        $threw = false;

        try {
            $service->extractJson($malformed, 'bad.json');
        } catch (RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('Invalid JSON transcript', $e->getMessage());
        }

        $this->assertTrue($threw, 'Malformed JSON must throw RuntimeException.');
    }

    public function test_extract_by_extension_routing(): void
    {
        $service = $this->createImportService();

        // JSON routing
        $jsonPayload = json_encode(['title' => 'Test Route', 'text' => 'Routing works']);
        $jsonResult = $service->extractByExtension('json', $jsonPayload, 'route.json');
        $this->assertSame('Test Route', $jsonResult['title']);

        // Audio routing (AAC / M4A)
        $aacBytes = str_repeat("\x00", 32000);
        $aacResult = $service->extractByExtension('aac', $aacBytes, 'test.aac');
        $this->assertSame('ready_for_transcription', $aacResult['status']);

        $m4aResult = $service->extractByExtension('m4a', $aacBytes, 'test.m4a');
        $this->assertSame('ready_for_transcription', $m4aResult['status']);

        // Unsupported extension
        $unsupportedThrew = false;
        try {
            $service->extractByExtension('xyz', 'content');
        } catch (RuntimeException $e) {
            $unsupportedThrew = true;
            $this->assertStringContainsString('Unsupported format', $e->getMessage());
        }
        $this->assertTrue($unsupportedThrew);
    }

    public function test_speaker_prefix_splitting_in_segments(): void
    {
        $service = $this->createImportService();

        $json = json_encode([
            'segments' => [
                [
                    'text' => 'CEO: Welcome to our all-hands meeting.',
                    'start' => 0.0,
                    'end' => 3.5,
                ],
                [
                    'text' => 'CTO: Product metrics exceeded target.',
                    'start' => 3.6,
                    'end' => 7.0,
                ],
            ],
        ]);

        $res = $service->extractJson($json, 'allhands.json');
        $this->assertCount(2, $res['segments']);
        $this->assertSame('CEO', $res['segments'][0]['speaker_label']);
        $this->assertSame('Welcome to our all-hands meeting.', $res['segments'][0]['text']);
        $this->assertSame('CTO', $res['segments'][1]['speaker_label']);
        $this->assertSame('Product metrics exceeded target.', $res['segments'][1]['text']);
    }
}

// If invoked directly from PHP CLI, run all tests in this file
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once dirname(__DIR__, 2) . '/app/Services/ImportService.php';

    $test = new ImportServiceTest();
    $methods = get_class_methods($test);
    $passed = 0;
    $failed = 0;

    echo "\nRunning ImportServiceTest...\n";
    foreach ($methods as $method) {
        if (str_starts_with($method, 'test_')) {
            try {
                $test->$method();
                echo "  [PASS] {$method}\n";
                $passed++;
            } catch (\Throwable $e) {
                echo "  [FAIL] {$method}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
                $failed++;
            }
        }
    }

    echo "\nResults: {$passed} passed, {$failed} failed.\n";
    exit($failed > 0 ? 1 : 0);
}
