import 'package:flutter/material.dart';
import '../../core/data/demo_data.dart';
import '../../core/models/demo_models.dart';
import '../../core/theme/app_tokens.dart';
import '../../shared/widgets/demo_banner.dart';
import '../../shared/widgets/stat_card.dart';

class AiAssistantScreen extends StatefulWidget {
  const AiAssistantScreen({super.key});

  @override
  State<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiAssistantScreenState extends State<AiAssistantScreen> {
  final List<DemoChatMessage> _messages = List.of(DemoData.initialChat);
  final _promptController = TextEditingController();
  String _provider = 'OpenAI (Server-Managed)';
  bool _aiConsentGranted = true;

  @override
  void dispose() {
    _promptController.dispose();
    super.dispose();
  }

  void _sendPrompt(String text) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return;

    setState(() {
      _messages.add(
        DemoChatMessage(
          id: 'u-${_messages.length}',
          isUser: true,
          content: trimmed,
        ),
      );
      _messages.add(
        DemoChatMessage(
          id: 'a-${_messages.length}',
          isUser: false,
          content: _aiConsentGranted
              ? 'Demo response via $_provider: Grounded answers will use pgvector citations from your active workspace once Phase 4/5 backend is connected.'
              : 'AI processing is paused because AI consent is disabled in Provider Settings.',
          citations: _aiConsentGranted ? ['tr-101 · 00:00:14'] : const [],
        ),
      );
      _promptController.clear();
    });
  }

  void _openProviderSettings() {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'AI Provider & Privacy Settings',
                style: Theme.of(ctx).textTheme.titleLarge,
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                'Provider API keys are stored strictly on the FastAPI backend—never in mobile or web clients.',
                style: Theme.of(ctx).textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<String>(
                value: _provider,
                decoration: const InputDecoration(labelText: 'Active Provider'),
                items: const [
                  DropdownMenuItem(
                    value: 'OpenAI (Server-Managed)',
                    child: Text('OpenAI (Server-Managed)'),
                  ),
                  DropdownMenuItem(
                    value: 'Google Gemini (Server-Managed)',
                    child: Text('Google Gemini (Server-Managed)'),
                  ),
                ],
                onChanged: (v) {
                  if (v == null) return;
                  setSheetState(() => _provider = v);
                  setState(() => _provider = v);
                },
              ),
              const SizedBox(height: AppSpacing.sm),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Allow AI transcript processing'),
                subtitle: const Text(
                  'Required before sending workspace text to external models.',
                ),
                value: _aiConsentGranted,
                onChanged: (v) {
                  setSheetState(() => _aiConsentGranted = v);
                  setState(() => _aiConsentGranted = v);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
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
              const SizedBox(height: AppSpacing.xs),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Workspace AI Assistant',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Provider & Model Settings',
                    onPressed: _openProviderSettings,
                    icon: const Icon(Icons.tune),
                  ),
                ],
              ),
              Wrap(
                spacing: AppSpacing.xs,
                children: [
                  ActionChip(
                    label: const Text('Action items'),
                    onPressed: () =>
                        _sendPrompt('List all action items from Q4 meetings'),
                  ),
                  ActionChip(
                    label: const Text('Executive summary'),
                    onPressed: () =>
                        _sendPrompt('Give an executive summary of recent calls'),
                  ),
                  ActionChip(
                    label: const Text('Key decisions'),
                    onPressed: () =>
                        _sendPrompt('What key decisions were recorded?'),
                  ),
                ],
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.md),
            itemCount: _messages.length,
            separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
            itemBuilder: (context, index) {
              final msg = _messages[index];
              return Align(
                alignment:
                    msg.isUser ? Alignment.centerRight : Alignment.centerLeft,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 560),
                  child: Card(
                    color: msg.isUser
                        ? AppColors.deepNavy
                        : AppColors.whiteSurface,
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            msg.content,
                            style: TextStyle(
                              color: msg.isUser
                                  ? Colors.white
                                  : AppColors.primaryText,
                            ),
                          ),
                          if (msg.citations.isNotEmpty) ...[
                            const SizedBox(height: AppSpacing.xs),
                            Wrap(
                              spacing: AppSpacing.xs,
                              children: msg.citations
                                  .map((c) => StatusBadge(label: c))
                                  .toList(),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _promptController,
                    decoration: const InputDecoration(
                      hintText: 'Ask about your workspace transcripts...',
                    ),
                    onSubmitted: _sendPrompt,
                  ),
                ),
                const SizedBox(width: AppSpacing.xs),
                ElevatedButton(
                  onPressed: () => _sendPrompt(_promptController.text),
                  child: const Icon(Icons.send),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
