import 'package:flutter/material.dart';
import '../../core/data/demo_data.dart';
import '../../core/models/demo_models.dart';
import '../../core/theme/app_tokens.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/demo_banner.dart';
import '../../shared/widgets/stat_card.dart';

class TranscriptsScreen extends StatefulWidget {
  const TranscriptsScreen({
    super.key,
    required this.workspace,
    required this.onOpenDetail,
  });

  final DemoWorkspace workspace;
  final ValueChanged<DemoTranscript> onOpenDetail;

  @override
  State<TranscriptsScreen> createState() => _TranscriptsScreenState();
}

class _TranscriptsScreenState extends State<TranscriptsScreen> {
  String _query = '';
  String _selectedProject = 'All';

  void _openImportFlow() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Import Transcript',
              style: Theme.of(ctx).textTheme.titleLarge,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              'Supported formats: .txt, .docx, .pdf, .srt (max 25 MB, validated server-side in Phase 3).',
              style: Theme.of(ctx).textTheme.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.md),
            OutlinedButton.icon(
              onPressed: () {
                Navigator.pop(ctx);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Demo: File picker will connect in Phase 3.'),
                  ),
                );
              },
              icon: const Icon(Icons.upload_file),
              label: const Text('Select TXT / DOCX / PDF / SRT File'),
            ),
            const SizedBox(height: AppSpacing.md),
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: AppColors.mainBackground,
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.bluetooth_searching,
                          color: AppColors.deepNavy),
                      const SizedBox(width: AppSpacing.xs),
                      Text(
                        'Plaud Device Sync (Preview)',
                        style: Theme.of(ctx).textTheme.titleMedium,
                      ),
                      const Spacer(),
                      const StatusBadge(
                        label: 'SDK Planned',
                        backgroundColor: AppColors.infoSurface,
                        textColor: AppColors.info,
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    'Future integration with Plaud Embedded SDK (BLE + WiFi Fast Transfer) will sync recordings directly from physical devices.',
                    style: Theme.of(ctx).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final projectNames = ['All', ...DemoData.projects.map((p) => p.name)];
    final filtered = DemoData.transcripts.where((t) {
      final matchesQuery = _query.isEmpty ||
          t.title.toLowerCase().contains(_query.toLowerCase()) ||
          t.project.toLowerCase().contains(_query.toLowerCase());
      final matchesProject =
          _selectedProject == 'All' || t.project == _selectedProject;
      return matchesQuery && matchesProject;
    }).toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.md,
            AppSpacing.md,
            AppSpacing.xs,
          ),
          child: Column(
            children: [
              const DemoModeBanner(compact: true),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.search),
                        hintText: 'Search transcripts or projects...',
                      ),
                      onChanged: (v) => setState(() => _query = v),
                    ),
                  ),
                  if (widget.workspace.role.canImportOrExport) ...[
                    const SizedBox(width: AppSpacing.sm),
                    ElevatedButton.icon(
                      onPressed: _openImportFlow,
                      icon: const Icon(Icons.add),
                      label: const Text('Import'),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              SizedBox(
                height: 36,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: projectNames.length,
                  separatorBuilder: (_, __) => const SizedBox(width: AppSpacing.xs),
                  itemBuilder: (context, index) {
                    final name = projectNames[index];
                    return ChoiceChip(
                      label: Text(name),
                      selected: _selectedProject == name,
                      onSelected: (_) => setState(() => _selectedProject = name),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: filtered.isEmpty
              ? AppEmptyView(
                  icon: Icons.search_off,
                  title: 'No matching transcripts',
                  subtitle: 'Try clearing your search filter or importing a file.',
                  actionLabel: 'Clear filter',
                  onAction: () => setState(() {
                    _query = '';
                    _selectedProject = 'All';
                  }),
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  itemCount: filtered.length,
                  separatorBuilder: (_, __) =>
                      const SizedBox(height: AppSpacing.sm),
                  itemBuilder: (context, index) {
                    final t = filtered[index];
                    return Card(
                      child: ListTile(
                        title: Text(
                          t.title,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        subtitle: Text(
                          '${t.project} · ${t.sourceFormat} · ${t.durationLabel}',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => widget.onOpenDetail(t),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class TranscriptDetailScreen extends StatelessWidget {
  const TranscriptDetailScreen({super.key, required this.transcript});

  final DemoTranscript transcript;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(transcript.title),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Transcript'),
              Tab(text: 'AI Summary'),
              Tab(text: 'Action Items'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: transcript.segments.length,
              separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
              itemBuilder: (context, index) {
                final seg = transcript.segments[index];
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              seg.speaker,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            StatusBadge(label: seg.timestamp),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(seg.text),
                      ],
                    ),
                  ),
                );
              },
            ),
            ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Executive Summary',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(transcript.summary),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          'Key Decisions',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        ...transcript.keyDecisions.map(
                          (d) => Padding(
                            padding: const EdgeInsets.only(bottom: AppSpacing.xs),
                            child: Text('• $d'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: transcript.actionItems.length,
              itemBuilder: (context, index) {
                return Card(
                  margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: CheckboxListTile(
                    value: false,
                    onChanged: (_) {},
                    title: Text(transcript.actionItems[index]),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
