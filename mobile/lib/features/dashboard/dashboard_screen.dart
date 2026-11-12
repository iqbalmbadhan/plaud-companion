import 'package:flutter/material.dart';
import '../../core/data/demo_data.dart';
import '../../core/models/demo_models.dart';
import '../../core/theme/app_tokens.dart';
import '../../shared/widgets/demo_banner.dart';
import '../../shared/widgets/stat_card.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    super.key,
    required this.workspace,
    required this.onNavigateTab,
    required this.onOpenTranscript,
  });

  final DemoWorkspace workspace;
  final ValueChanged<int> onNavigateTab;
  final ValueChanged<DemoTranscript> onOpenTranscript;

  @override
  Widget build(BuildContext context) {
    final canEdit = workspace.role.canImportOrExport;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        const DemoModeBanner(),
        const SizedBox(height: AppSpacing.md),
        Text(
          '${workspace.name} Overview',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: AppSpacing.xxs),
        Text(
          'Role: ${workspace.role.label} · ${workspace.memberCount} member(s)',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.md),
        LayoutBuilder(
          builder: (context, constraints) {
            final isWide = constraints.maxWidth >= 680;
            final cards = [
              StatCard(
                label: 'Total Transcripts',
                value: '${workspace.transcriptCount}',
                icon: Icons.description_outlined,
                helper: 'TXT, DOCX, PDF, SRT',
              ),
              const StatCard(
                label: 'Recording Time',
                value: '46.8h',
                icon: Icons.schedule_outlined,
                helper: 'Across all projects',
              ),
              const StatCard(
                label: 'AI Summaries',
                value: '94',
                icon: Icons.auto_awesome_outlined,
                helper: 'OpenAI & Gemini',
              ),
            ];

            if (isWide) {
              return Row(
                children: [
                  for (var i = 0; i < cards.length; i++) ...[
                    Expanded(child: cards[i]),
                    if (i < cards.length - 1) const SizedBox(width: AppSpacing.sm),
                  ],
                ],
              );
            }
            return Column(
              children: [
                for (var i = 0; i < cards.length; i++) ...[
                  cards[i],
                  if (i < cards.length - 1) const SizedBox(height: AppSpacing.sm),
                ],
              ],
            );
          },
        ),
        const SizedBox(height: AppSpacing.lg),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            if (canEdit)
              ElevatedButton.icon(
                onPressed: () => onNavigateTab(1),
                icon: const Icon(Icons.upload_file),
                label: const Text('Import Transcript'),
              ),
            OutlinedButton.icon(
              onPressed: () => onNavigateTab(2),
              icon: const Icon(Icons.auto_awesome),
              label: const Text('Ask AI Assistant'),
            ),
            if (canEdit)
              OutlinedButton.icon(
                onPressed: () => onNavigateTab(3),
                icon: const Icon(Icons.archive_outlined),
                label: const Text('Bulk Export (.zip)'),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(
          'Recent Transcripts',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.sm),
        ...DemoData.transcripts.map(
          (t) => Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Card(
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.md,
                  vertical: AppSpacing.xs,
                ),
                title: Text(
                  t.title,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                subtitle: Text(
                  '${t.project} · ${t.sourceFormat} · ${t.durationLabel} · ${t.recordedAt}',
                ),
                trailing: StatusBadge(
                  label: t.status,
                  backgroundColor: t.status == 'Ready'
                      ? AppColors.paleEmerald
                      : AppColors.warningSurface,
                  textColor: t.status == 'Ready'
                      ? AppColors.deepNavy
                      : AppColors.warning,
                ),
                onTap: () => onOpenTranscript(t),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
