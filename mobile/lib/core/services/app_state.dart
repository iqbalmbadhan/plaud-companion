import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import '../models/app_models.dart';
import 'ai_service.dart';
import 'export_service.dart';
import 'plaud_device_service.dart';

class AppState extends ChangeNotifier {
  static final AppState instance = AppState._();
  AppState._() {
    _initDefaultData();
    PlaudDeviceService.instance.addListener(notifyListeners);
  }

  final List<Transcript> _transcripts = [];
  final List<ExportItem> _exportHistory = [];
  String _selectedProject = 'All';

  List<Transcript> get transcripts => List.unmodifiable(_transcripts);
  List<ExportItem> get exportHistory => List.unmodifiable(_exportHistory);
  String get selectedProject => _selectedProject;

  List<String> get projects {
    final set = {'All'};
    for (final t in _transcripts) {
      set.add(t.project);
    }
    return set.toList();
  }

  List<Transcript> get filteredTranscripts {
    if (_selectedProject == 'All') return transcripts;
    return _transcripts.where((t) => t.project == _selectedProject).toList();
  }

  void setProjectFilter(String project) {
    _selectedProject = project;
    notifyListeners();
  }

  void addTranscript(Transcript transcript) {
    _transcripts.insert(0, transcript);
    notifyListeners();
  }

  void removeTranscript(String id) {
    _transcripts.removeWhere((t) => t.id == id);
    notifyListeners();
  }

  Future<void> syncFromPlaudDevice() async {
    final t = await PlaudDeviceService.instance.syncLatestSession();
    addTranscript(t);
  }

  Future<ExportItem> exportTranscript({
    required Transcript transcript,
    required String format, // 'pdf', 'docx', 'both'
    bool includeSummary = true,
    bool includeActions = true,
  }) async {
    final now = DateTime.now();
    Uint8List bytes;
    String filename;

    if (format.toLowerCase() == 'docx') {
      bytes = ExportService.buildDocx(
        transcript: transcript,
        includeSummary: includeSummary,
        includeActions: includeActions,
      );
      filename = '${transcript.title}.docx';
    } else {
      bytes = ExportService.buildPdf(
        transcript: transcript,
        includeSummary: includeSummary,
        includeActions: includeActions,
      );
      filename = '${transcript.title}.pdf';
    }

    final item = ExportItem(
      id: 'exp-${now.millisecondsSinceEpoch}',
      title: filename,
      format: format.toUpperCase(),
      contentPreview: 'Exported from ${transcript.title} (${transcript.durationFormatted})',
      createdAt: now,
      sizeBytes: bytes.length,
    );

    _exportHistory.insert(0, item);
    notifyListeners();
    return item;
  }

  Future<ExportItem> exportBulk({
    required List<Transcript> selectedTranscripts,
    required String format,
  }) async {
    final now = DateTime.now();
    final title = 'PLAUD Bulk Export (${selectedTranscripts.length} recordings)';
    final item = ExportItem(
      id: 'bulk-${now.millisecondsSinceEpoch}',
      title: '$title.$format',
      format: format.toUpperCase(),
      contentPreview: 'Package containing ${selectedTranscripts.length} transcripts with AI summaries & diarization',
      createdAt: now,
      sizeBytes: selectedTranscripts.length * 14200,
    );
    _exportHistory.insert(0, item);
    notifyListeners();
    return item;
  }

  void _initDefaultData() {
    final t1 = Transcript(
      id: 'tr-101',
      title: 'Q4 Product Strategy & Hardware Architecture',
      project: 'Mobile Launch',
      sourceFormat: 'PLAUD Note BLE',
      durationSeconds: 240,
      recordedAt: DateTime.now().subtract(const Duration(hours: 3)),
      status: 'Ready',
      contentText: '[00:00] Sarah (Product Lead): Welcome everyone. Today we are reviewing the production rollout for the PLAUD Companion app across Web, Android, and iOS.\n'
          '[00:20] David (Lead Architect): The core hardware integration is complete. The Web Bluetooth and mobile BLE SDK handle direct GATT connections to PLAUD Note and NotePin, reading battery levels and audio chunks.\n'
          '[00:45] Elena (AI Specialist): On the intelligence layer, we have connected OpenAI GPT-4o, Google Gemini 2.0 Flash, Anthropic Claude 3.5 Sonnet, plus our embedded neural engine for air-gapped environments.\n'
          '[01:10] Sarah (Product Lead): What about export deliverables? Enterprise clients require immediate high-fidelity Word and PDF summaries.\n'
          '[01:30] David (Lead Architect): Our pure OpenXML engine compiles formatted DOCX with executive takeaways and action item checkboxes. The PDF engine generates compliant PDF 1.4 archives with zero external CLI dependencies.\n'
          '[01:55] Marcus (QA Director): Everything has passed multi-device testing and is ready for customer deployment.',
      segments: [
        const TranscriptSegment(id: 's1', speaker: 'Sarah (Product Lead)', startMs: 0, endMs: 19000, text: 'Welcome everyone. Today we are reviewing the production rollout for the PLAUD Companion app across Web, Android, and iOS.'),
        const TranscriptSegment(id: 's2', speaker: 'David (Lead Architect)', startMs: 20000, endMs: 44000, text: 'The core hardware integration is complete. The Web Bluetooth and mobile BLE SDK handle direct GATT connections to PLAUD Note and NotePin, reading battery levels and audio chunks.'),
        const TranscriptSegment(id: 's3', speaker: 'Elena (AI Specialist)', startMs: 45000, endMs: 69000, text: 'On the intelligence layer, we have connected OpenAI GPT-4o, Google Gemini 2.0 Flash, Anthropic Claude 3.5 Sonnet, plus our embedded neural engine for air-gapped environments.'),
        const TranscriptSegment(id: 's4', speaker: 'Sarah (Product Lead)', startMs: 70000, endMs: 89000, text: 'What about export deliverables? Enterprise clients require immediate high-fidelity Word and PDF summaries.'),
        const TranscriptSegment(id: 's5', speaker: 'David (Lead Architect)', startMs: 90000, endMs: 114000, text: 'Our pure OpenXML engine compiles formatted DOCX with executive takeaways and action item checkboxes. The PDF engine generates compliant PDF 1.4 archives with zero external CLI dependencies.'),
        const TranscriptSegment(id: 's6', speaker: 'Marcus (QA Director)', startMs: 115000, endMs: 135000, text: 'Everything has passed multi-device testing and is ready for customer deployment.'),
      ],
      aiSummary: 'Executive Summary:\n'
          '• Cross-platform PLAUD Companion app signed off for Android and iOS.\n'
          '• Real BLE GATT service connects to PLAUD Note & NotePin with live battery monitoring.\n'
          '• Multi-model AI operators (OpenAI, Gemini, Claude, OpenRouter) and local engine active.\n'
          '• Native PDF and DOCX generators produce immediate export deliverables.',
      actionItems: [
        ActionItem(task: 'Deploy PLAUD Companion mobile app to internal testing track', assignee: 'Marcus (QA)', dueDate: 'Sprint 4'),
        ActionItem(task: 'Validate Bluetooth Low Energy packet sync with NotePin hardware', assignee: 'David', dueDate: 'Friday EOD'),
        ActionItem(task: 'Verify Word (.docx) and PDF export format fidelity across devices', assignee: 'Sarah', dueDate: 'Monday 10 AM'),
      ],
    );

    final t2 = Transcript(
      id: 'tr-102',
      title: 'Customer Discovery: AI Note-Taking Workflows',
      project: 'Interviews',
      sourceFormat: 'PLAUD NotePin BLE',
      durationSeconds: 160,
      recordedAt: DateTime.now().subtract(const Duration(days: 1)),
      status: 'Ready',
      contentText: '[00:00] Interviewer: How do you currently record and review your client calls?\n'
          '[00:15] Executive: We use the PLAUD Note on the back of our phone. But having instant Word and PDF export with speaker names is the game changer.\n'
          '[00:35] Interviewer: Would you use the built-in AI for action items and meeting summaries?\n'
          '[00:50] Executive: Absolutely. We need summaries ready immediately after walking out of the conference room.',
      segments: [
        const TranscriptSegment(id: 's1', speaker: 'Interviewer', startMs: 0, endMs: 14000, text: 'How do you currently record and review your client calls?'),
        const TranscriptSegment(id: 's2', speaker: 'Executive', startMs: 15000, endMs: 34000, text: 'We use the PLAUD Note on the back of our phone. But having instant Word and PDF export with speaker names is the game changer.'),
        const TranscriptSegment(id: 's3', speaker: 'Interviewer', startMs: 35000, endMs: 49000, text: 'Would you use the built-in AI for action items and meeting summaries?'),
        const TranscriptSegment(id: 's4', speaker: 'Executive', startMs: 50000, endMs: 70000, text: 'Absolutely. We need summaries ready immediately after walking out of the conference room.'),
      ],
      aiSummary: 'Customer Feedback Insights:\n'
          '• High demand for immediate post-meeting executive summaries.\n'
          '• Word (.docx) and PDF export formats rated critical for client deliverables.\n'
          '• Seamless Bluetooth Low Energy connection to PLAUD Note is the preferred workflow.',
      actionItems: [
        ActionItem(task: 'Share customer interview findings with product management', assignee: 'Interviewer', dueDate: 'Done', isDone: true),
      ],
    );

    _transcripts.addAll([t1, t2]);

    _exportHistory.add(ExportItem(
      id: 'exp-init-1',
      title: 'Q4 Product Strategy & Hardware Architecture.docx',
      format: 'DOCX',
      contentPreview: 'Formatted Word package with Executive Summary, Action Matrix & Diarization',
      createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      sizeBytes: 18450,
    ));

    _exportHistory.add(ExportItem(
      id: 'exp-init-2',
      title: 'PLAUD Note Showcase Package.pdf',
      format: 'PDF',
      contentPreview: 'Standard PDF 1.4 document with diarization timestamps',
      createdAt: DateTime.now().subtract(const Duration(hours: 1)),
      sizeBytes: 12890,
    ));
  }
}
