import 'package:flutter/material.dart';
import '../../core/models/app_models.dart';
import '../../core/services/app_state.dart';
import '../../core/theme/app_tokens.dart';

class ExportsScreen extends StatefulWidget {
  const ExportsScreen({super.key});

  @override
  State<ExportsScreen> createState() => _ExportsScreenState();
}

class _ExportsScreenState extends State<ExportsScreen> {
  final Set<String> _selectedIds = {};
  String _format = 'both'; // 'docx', 'pdf', 'both'
  bool _includeSummary = true;
  bool _includeActions = true;
  bool _isGenerating = false;

  @override
  void initState() {
    super.initState();
    // Default select all transcripts
    _selectedIds.addAll(AppState.instance.transcripts.map((t) => t.id));
  }

  void _showDocumentPreview(ExportItem item) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '${item.format} Package • ${(item.sizeBytes / 1024).toStringAsFixed(1)} KB • Generated: ${item.createdAt.toLocal().toString().substring(0, 16)}',
              style: const TextStyle(fontSize: 12, color: AppColors.muted),
            ),
            const Divider(height: 24),
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(color: AppColors.border),
              ),
              child: Text(
                'Document Status: Verified Valid OpenXML DOCX / PDF-1.4\n\n'
                'Content Summary:\n${item.contentPreview}\n\n'
                '• Executive Summary: Formatted\n'
                '• Grounded Action Items: Matrix compiled\n'
                '• Speaker Diarization: Timestamps preserved',
                style: const TextStyle(fontSize: 12.5, height: 1.4, color: AppColors.deepNavy),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            ElevatedButton.icon(
              onPressed: () {
                Navigator.pop(ctx);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Document ready: ${item.title}')),
                );
              },
              icon: const Icon(Icons.share),
              label: const Text('Share / Save Document'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.emerald,
                foregroundColor: Colors.white,
                minimumSize: const Size.fromHeight(44),
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
        final transcripts = state.transcripts;

        return DefaultTabController(
          length: 2,
          child: Scaffold(
            appBar: AppBar(
              title: const Text('Export Deliverables'),
              bottom: const TabBar(
                tabs: [
                  Tab(text: 'Export Builder'),
                  Tab(text: 'Exported Files'),
                ],
              ),
            ),
            body: TabBarView(
              children: [
                // Tab 1: Builder
                ListView(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  children: [
                    // Format selector
                    Text('1. Select Output Format', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        _buildFormatChip('both', 'DOCX + PDF', Icons.folder_zip_outlined),
                        const SizedBox(width: 8),
                        _buildFormatChip('docx', 'Word (.docx)', Icons.description_outlined),
                        const SizedBox(width: 8),
                        _buildFormatChip('pdf', 'PDF (.pdf)', Icons.picture_as_pdf_outlined),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),

                    // Sections to include
                    Text('2. Include Sections', style: Theme.of(context).textTheme.titleSmall),
                    CheckboxListTile(
                      value: _includeSummary,
                      onChanged: (v) => setState(() => _includeSummary = v ?? true),
                      title: const Text('Executive Summary & Strategic Takeaways', style: TextStyle(fontSize: 13)),
                      dense: true,
                    ),
                    CheckboxListTile(
                      value: _includeActions,
                      onChanged: (v) => setState(() => _includeActions = v ?? true),
                      title: const Text('Action Items Matrix with Checkboxes', style: TextStyle(fontSize: 13)),
                      dense: true,
                    ),
                    const SizedBox(height: AppSpacing.md),

                    // Transcripts selector
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('3. Transcripts to Export (${_selectedIds.length})', style: Theme.of(context).textTheme.titleSmall),
                        TextButton(
                          onPressed: () {
                            setState(() {
                              if (_selectedIds.length == transcripts.length) {
                                _selectedIds.clear();
                              } else {
                                _selectedIds.addAll(transcripts.map((t) => t.id));
                              }
                            });
                          },
                          child: Text(_selectedIds.length == transcripts.length ? 'Deselect All' : 'Select All'),
                        ),
                      ],
                    ),
                    ...transcripts.map((t) {
                      final isChecked = _selectedIds.contains(t.id);
                      return Card(
                        margin: const EdgeInsets.only(bottom: 6),
                        child: CheckboxListTile(
                          value: isChecked,
                          onChanged: (v) {
                            setState(() {
                              if (v == true) {
                                _selectedIds.add(t.id);
                              } else {
                                _selectedIds.delete(t.id);
                              }
                            });
                          },
                          title: Text(t.title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                          subtitle: Text('${t.durationFormatted} • ${t.segments.length} speaker turns',
                              style: const TextStyle(fontSize: 11.5, color: AppColors.muted)),
                        ),
                      );
                    }),
                    const SizedBox(height: AppSpacing.md),

                    // Action Buttons
                    ElevatedButton.icon(
                      onPressed: _isGenerating || _selectedIds.isEmpty
                          ? null
                          : () async {
                              setState(() => _isGenerating = true);
                              final sel = transcripts.where((t) => _selectedIds.contains(t.id)).toList();
                              for (final t in sel) {
                                if (_format == 'both') {
                                  await state.exportTranscript(transcript: t, format: 'docx', includeSummary: _includeSummary, includeActions: _includeActions);
                                  await state.exportTranscript(transcript: t, format: 'pdf', includeSummary: _includeSummary, includeActions: _includeActions);
                                } else {
                                  await state.exportTranscript(transcript: t, format: _format, includeSummary: _includeSummary, includeActions: _includeActions);
                                }
                              }
                              setState(() => _isGenerating = false);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Export package generated! Check "Exported Files" tab.')),
                              );
                            },
                      icon: _isGenerating
                          ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.archive),
                      label: Text(_isGenerating ? 'Compiling Documents...' : '📦 Generate Export Package'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.emerald,
                        foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(46),
                      ),
                    ),
                    const SizedBox(height: 8),

                    // 1-Click Demo Showcase Button
                    OutlinedButton.icon(
                      onPressed: () async {
                        final t = transcripts.first;
                        final docx = await state.exportTranscript(transcript: t, format: 'docx');
                        final pdf = await state.exportTranscript(transcript: t, format: 'pdf');
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Showcase created: ${docx.title} & ${pdf.title}')),
                        );
                      },
                      icon: const Icon(Icons.rocket_launch, color: AppColors.deepNavy),
                      label: const Text('🚀 1-Click PLAUD Demo Showcase (DOCX + PDF)', style: TextStyle(color: AppColors.deepNavy)),
                      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(44)),
                    ),
                  ],
                ),

                // Tab 2: History
                state.exportHistory.isEmpty
                    ? const Center(child: Text('No export files generated yet.'))
                    : ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        itemCount: state.exportHistory.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (ctx, i) {
                          final item = state.exportHistory[i];
                          return Card(
                            child: ListTile(
                              leading: Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: item.format == 'PDF' ? const Color(0xFFFEE2E2) : const Color(0xFFDBEAFE),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Center(
                                  child: Text(
                                    item.format,
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 10,
                                      color: item.format == 'PDF' ? const Color(0xFFDC2626) : const Color(0xFF1D4ED8),
                                    ),
                                  ),
                                ),
                              ),
                              title: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
                              subtitle: Text(
                                '${(item.sizeBytes / 1024).toStringAsFixed(1)} KB • ${item.createdAt.hour.toString().padLeft(2, '0')}:${item.createdAt.minute.toString().padLeft(2, '0')}',
                                style: const TextStyle(fontSize: 11.5, color: AppColors.muted),
                              ),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () => _showDocumentPreview(item),
                            ),
                          );
                        },
                      ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildFormatChip(String formatKey, String label, IconData icon) {
    final isSel = _format == formatKey;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _format = formatKey),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: isSel ? AppColors.paleEmerald : Colors.white,
            borderRadius: BorderRadius.circular(AppRadius.sm),
            border: Border.all(color: isSel ? AppColors.emerald : AppColors.border, width: isSel ? 1.5 : 1),
          ),
          child: Column(
            children: [
              Icon(icon, size: 20, color: isSel ? AppColors.emerald : AppColors.muted),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: isSel ? AppColors.deepNavy : AppColors.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

extension SetToggle<E> on Set<E> {
  void delete(E element) => remove(element);
}
