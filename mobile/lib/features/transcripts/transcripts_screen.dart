import 'package:flutter/material.dart';
import '../../core/models/app_models.dart';
import '../../core/services/ai_service.dart';
import '../../core/services/app_state.dart';
import '../../core/theme/app_tokens.dart';

class TranscriptsScreen extends StatefulWidget {
  const TranscriptsScreen({
    super.key,
    required this.onOpenDetail,
  });

  final ValueChanged<Transcript> onOpenDetail;

  @override
  State<TranscriptsScreen> createState() => _TranscriptsScreenState();
}

class _TranscriptsScreenState extends State<TranscriptsScreen> {
  String _searchQuery = '';
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _showImportModal() {
    final titleController = TextEditingController();
    final bodyController = TextEditingController();

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: AppSpacing.md,
          right: AppSpacing.md,
          top: AppSpacing.md,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + AppSpacing.md,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('New / Import Transcript', style: Theme.of(ctx).textTheme.titleLarge),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: titleController,
              decoration: const InputDecoration(
                labelText: 'Transcript Title',
                hintText: 'e.g. Client Architecture Review',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: bodyController,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Transcript Text (with speaker tags or timestamps)',
                hintText: '[00:00] Sarah: First agenda item...\n[00:15] David: Progress update...',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            ElevatedButton.icon(
              onPressed: () {
                final title = titleController.text.trim();
                final text = bodyController.text.trim();
                if (title.isEmpty || text.isEmpty) return;

                final lines = text.split('\n');
                final segments = <TranscriptSegment>[];
                int curMs = 0;
                for (int i = 0; i < lines.length; i++) {
                  final l = lines[i].trim();
                  if (l.isEmpty) continue;
                  segments.add(TranscriptSegment(
                    id: 'seg-$i',
                    speaker: 'Speaker ${(i % 2) + 1}',
                    startMs: curMs,
                    endMs: curMs + 10000,
                    text: l,
                  ));
                  curMs += 10000;
                }

                final t = Transcript(
                  id: 'tr-${DateTime.now().millisecondsSinceEpoch}',
                  title: title,
                  recordedAt: DateTime.now(),
                  contentText: text,
                  segments: segments,
                  durationSeconds: curMs ~/ 1000,
                  sourceFormat: 'Manual Import',
                );

                AppState.instance.addTranscript(t);
                Navigator.pop(ctx);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Imported "$title" successfully!')),
                );
              },
              icon: const Icon(Icons.check),
              label: const Text('Create & Segment Transcript'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.emerald,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: AppState.instance,
      builder: (context, _) {
        final state = AppState.instance;
        final allTranscripts = state.filteredTranscripts;
        final list = _searchQuery.isEmpty
            ? allTranscripts
            : allTranscripts.where((t) =>
                t.title.toLowerCase().contains(_searchQuery.toLowerCase()) ||
                t.contentText.toLowerCase().contains(_searchQuery.toLowerCase())).toList();

        return Scaffold(
          appBar: AppBar(
            title: const Text('Transcripts & Recordings'),
            actions: [
              IconButton(
                icon: const Icon(Icons.add),
                tooltip: 'Import / Paste',
                onPressed: _showImportModal,
              ),
            ],
          ),
          body: Column(
            children: [
              // Search Bar
              Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, 0),
                child: TextField(
                  controller: _searchController,
                  onChanged: (v) => setState(() => _searchQuery = v),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.search),
                    hintText: 'Search transcripts, speakers, keywords...',
                    suffixIcon: _searchQuery.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _searchQuery = '');
                            },
                          )
                        : null,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppRadius.md)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  ),
                ),
              ),

              // Project Filter Chips
              SizedBox(
                height: 48,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: 8),
                  children: state.projects.map((proj) {
                    final isSel = state.selectedProject == proj;
                    return Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: FilterChip(
                        label: Text(proj),
                        selected: isSel,
                        onSelected: (_) => state.setProjectFilter(proj),
                        selectedColor: AppColors.paleEmerald,
                        checkmarkColor: AppColors.emerald,
                      ),
                    );
                  }).toList(),
                ),
              ),

              // Transcripts List
              Expanded(
                child: list.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.search_off, size: 48, color: AppColors.muted),
                            const SizedBox(height: 8),
                            Text('No transcripts found matching "$_searchQuery"', style: const TextStyle(color: AppColors.muted)),
                          ],
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        itemCount: list.length,
                        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, idx) {
                          final t = list[idx];
                          return Card(
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(AppRadius.md),
                              side: const BorderSide(color: AppColors.border),
                            ),
                            child: ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              title: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      t.title,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: AppColors.paleEmerald,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      t.project,
                                      style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: AppColors.deepNavy),
                                    ),
                                  ),
                                ],
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 4),
                                  Text(
                                    '${t.durationFormatted} • ${t.segments.length} turns • ${t.sourceFormat}',
                                    style: const TextStyle(fontSize: 12, color: AppColors.muted),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    t.contentText.replaceAll('\n', ' '),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 11.5, color: Color(0xFF475569)),
                                  ),
                                ],
                              ),
                              onTap: () => widget.onOpenDetail(t),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class TranscriptDetailScreen extends StatefulWidget {
  const TranscriptDetailScreen({super.key, required this.transcript});

  final Transcript transcript;

  @override
  State<TranscriptDetailScreen> createState() => _TranscriptDetailScreenState();
}

class _TranscriptDetailScreenState extends State<TranscriptDetailScreen> {
  bool _isLoadingAi = false;

  Future<void> _generateSummary() async {
    setState(() => _isLoadingAi = true);
    final summary = await AiService.generateSummary(widget.transcript);
    final actions = await AiService.extractActionItems(widget.transcript);
    setState(() {
      widget.transcript.aiSummary = summary;
      widget.transcript.actionItems = actions;
      _isLoadingAi = false;
    });
    AppState.instance.refresh();
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.transcript;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(t.title, style: const TextStyle(fontSize: 15)),
          actions: [
            PopupMenuButton<String>(
              onSelected: (val) async {
                if (val == 'pdf' || val == 'docx') {
                  final item = await AppState.instance.exportTranscript(transcript: t, format: val);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Generated ${item.title}! Ready in Exports tab.')),
                  );
                } else if (val == 'delete') {
                  AppState.instance.removeTranscript(t.id);
                  Navigator.pop(context);
                }
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'docx', child: Text('Export to Word (.docx)')),
                PopupMenuItem(value: 'pdf', child: Text('Export to PDF (.pdf)')),
                PopupMenuDivider(),
                PopupMenuItem(value: 'delete', child: Text('Delete Transcript', style: TextStyle(color: Colors.red))),
              ],
            ),
          ],
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Diarization'),
              Tab(text: 'AI Summary'),
              Tab(text: 'Action Items'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            // Tab 1: Segments
            ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: t.segments.length,
              itemBuilder: (ctx, i) {
                final seg = t.segments[i];
                return Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(seg.timeFormatted, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.muted)),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(seg.speaker, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppColors.deepNavy)),
                            const SizedBox(height: 2),
                            Text(seg.text, style: const TextStyle(fontSize: 13, height: 1.4, color: Color(0xFF1E293B))),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),

            // Tab 2: AI Summary
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: ListView(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Meeting Synthesis', style: Theme.of(context).textTheme.titleMedium),
                      ElevatedButton.icon(
                        onPressed: _isLoadingAi ? null : _generateSummary,
                        icon: _isLoadingAi
                            ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.auto_awesome, size: 16),
                        label: Text(_isLoadingAi ? 'Synthesizing...' : 'Re-run AI'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.emerald,
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Text(
                      t.aiSummary ?? 'No summary generated yet. Tap "Re-run AI" to generate executive notes.',
                      style: const TextStyle(fontSize: 13, height: 1.5, color: Color(0xFF334155)),
                    ),
                  ),
                ],
              ),
            ),

            // Tab 3: Action Items
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: ListView(
                children: [
                  Text('Extracted Action Items', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: AppSpacing.sm),
                  if (t.actionItems.isEmpty)
                    const Text('No action items extracted yet. Run AI to analyze tasks.', style: TextStyle(color: AppColors.muted))
                  else
                    ...t.actionItems.map(
                      (item) => CheckboxListTile(
                        value: item.isDone,
                        onChanged: (v) => setState(() => item.isDone = v ?? false),
                        title: Text(item.task, style: TextStyle(decoration: item.isDone ? TextDecoration.lineThrough : null)),
                        subtitle: item.assignee != null ? Text('Assignee: ${item.assignee} • ${item.dueDate ?? "ASAP"}') : null,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
