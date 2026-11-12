import 'package:flutter/material.dart';
import '../../core/data/demo_data.dart';
import '../../core/models/demo_models.dart';
import '../../core/theme/app_tokens.dart';
import '../../shared/widgets/demo_banner.dart';
import '../../shared/widgets/stat_card.dart';

class ExportsScreen extends StatefulWidget {
  const ExportsScreen({super.key, required this.workspace});

  final DemoWorkspace workspace;

  @override
  State<ExportsScreen> createState() => _ExportsScreenState();
}

class _ExportsScreenState extends State<ExportsScreen> {
  final Set<String> _selectedIds = {'tr-101', 'tr-102'};
  final List<DemoExportJob> _exportJobs = List.of(DemoData.exportJobs);
  String _exportFormat = 'DOCX + PDF (ZIP)';
  bool _includeSummary = true;
  bool _bundleAsZip = true;

  @override
  Widget build(BuildContext context) {
    final canExport = widget.workspace.role.canImportOrExport;

    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.md,
              0,
            ),
            child: DemoModeBanner(compact: true),
          ),
          const TabBar(
            tabs: [
              Tab(text: 'Bulk Export Config'),
              Tab(text: 'Export Queue & History'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                ListView(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  children: [
                    if (!canExport)
                      Container(
                        margin: const EdgeInsets.only(bottom: AppSpacing.md),
                        padding: const EdgeInsets.all(AppSpacing.sm),
                        decoration: BoxDecoration(
                          color: AppColors.warningSurface,
                          borderRadius: BorderRadius.circular(AppRadius.sm),
                        ),
                        child: const Text(
                          'Read-only Reviewers cannot generate bulk exports.',
                        ),
                      ),
                    Text(
                      'Select Transcripts (${_selectedIds.length})',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    ...DemoData.transcripts.map(
                      (t) => CheckboxListTile(
                        value: _selectedIds.contains(t.id),
                        onChanged: canExport
                            ? (checked) => setState(() {
                                  if (checked ?? false) {
                                    _selectedIds.add(t.id);
                                  } else {
                                    _selectedIds.remove(t.id);
                                  }
                                })
                            : null,
                        title: Text(t.title),
                        subtitle: Text('${t.sourceFormat} · ${t.durationLabel}'),
                      ),
                    ),
                    const Divider(),
                    DropdownButtonFormField<String>(
                      value: _exportFormat,
                      decoration: const InputDecoration(
                        labelText: 'Export Format',
                      ),
                      items: const [
                        DropdownMenuItem(
                          value: 'DOCX + PDF (ZIP)',
                          child: Text('DOCX + PDF (ZIP Bundle)'),
                        ),
                        DropdownMenuItem(
                          value: 'PDF Only',
                          child: Text('PDF Only (.pdf)'),
                        ),
                        DropdownMenuItem(
                          value: 'DOCX Only',
                          child: Text('Word Only (.docx)'),
                        ),
                      ],
                      onChanged: canExport
                          ? (v) {
                              if (v != null) setState(() => _exportFormat = v);
                            }
                          : null,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    SwitchListTile(
                      title: const Text('Include AI Summary & Action Items'),
                      value: _includeSummary,
                      onChanged: canExport
                          ? (v) => setState(() => _includeSummary = v)
                          : null,
                    ),
                    SwitchListTile(
                      title: const Text('Package as .zip with manifest'),
                      value: _bundleAsZip,
                      onChanged: canExport
                          ? (v) => setState(() => _bundleAsZip = v)
                          : null,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    ElevatedButton.icon(
                      onPressed: canExport && _selectedIds.isNotEmpty
                          ? () {
                              setState(() {
                                _exportJobs.insert(
                                  0,
                                  DemoExportJob(
                                    id: 'exp-${_exportJobs.length + 1}',
                                    title:
                                        '${_selectedIds.length} Transcript(s) Export',
                                    format: _exportFormat,
                                    status: 'Completed',
                                    createdAt: 'Just now',
                                  ),
                                );
                              });
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(
                                    'Generated $_exportFormat for ${_selectedIds.length} transcript(s).',
                                  ),
                                ),
                              );
                            }
                          : null,
                      icon: const Icon(Icons.archive_outlined),
                      label: const Text('Generate DOCX / PDF Export'),
                    ),
                  ],
                ),
                ListView.separated(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  itemCount: _exportJobs.length,
                  separatorBuilder: (_, __) =>
                      const SizedBox(height: AppSpacing.sm),
                  itemBuilder: (context, index) {
                    final job = _exportJobs[index];
                    return Card(
                      child: ListTile(
                        title: Text(job.title),
                        subtitle: Text('${job.format} · ${job.createdAt}'),
                        trailing: StatusBadge(
                          label: job.status,
                          backgroundColor: job.status == 'Completed'
                              ? AppColors.paleEmerald
                              : AppColors.infoSurface,
                          textColor: job.status == 'Completed'
                              ? AppColors.deepNavy
                              : AppColors.info,
                        ),
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
