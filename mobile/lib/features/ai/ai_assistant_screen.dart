import 'package:flutter/material.dart';
import '../../core/models/app_models.dart';
import '../../core/services/ai_service.dart';
import '../../core/services/app_state.dart';
import '../../core/theme/app_tokens.dart';

class ChatMessage {
  final String text;
  final bool isUser;
  final DateTime timestamp;

  const ChatMessage({required this.text, required this.isUser, required this.timestamp});
}

class AiAssistantScreen extends StatefulWidget {
  const AiAssistantScreen({super.key});

  @override
  State<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiAssistantScreenState extends State<AiAssistantScreen> {
  final List<ChatMessage> _messages = [
    ChatMessage(
      text: 'Hello! I am your PLAUD Companion AI assistant. Ask me questions across your meeting transcripts or extract action matrices.',
      isUser: false,
      timestamp: DateTime.now().subtract(const Duration(minutes: 5)),
    ),
  ];
  final _textController = TextEditingController();
  bool _isProcessing = false;

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  Future<void> _sendMessage(String query) async {
    final text = query.trim();
    if (text.isEmpty) return;
    _textController.clear();

    setState(() {
      _messages.add(ChatMessage(text: text, isUser: true, timestamp: DateTime.now()));
      _isProcessing = true;
    });

    final answer = await AiService.askQuestion(text, AppState.instance.transcripts);

    setState(() {
      _messages.add(ChatMessage(text: answer, isUser: false, timestamp: DateTime.now()));
      _isProcessing = false;
    });
  }

  void _openProviderConfigModal() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          return Padding(
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
                    Text('AI Operator Settings', style: Theme.of(ctx).textTheme.titleLarge),
                    IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
                  ],
                ),
                const Text(
                  'Configure real API keys for OpenAI, Google Gemini, Anthropic Claude, or use the built-in offline neural engine.',
                  style: TextStyle(fontSize: 12, color: AppColors.muted),
                ),
                const SizedBox(height: AppSpacing.md),
                ...AiService.providers.map((provider) {
                  final isSelected = AiService.activeProvider.id == provider.id;
                  final keyController = TextEditingController(text: provider.apiKey ?? '');

                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Radio<String>(
                                value: provider.id,
                                groupValue: AiService.activeProvider.id,
                                onChanged: (val) {
                                  setSheetState(() => AiService.setActiveProvider(val!));
                                  setState(() {});
                                },
                              ),
                              Expanded(
                                child: Text(
                                  provider.name,
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5),
                                ),
                              ),
                              if (provider.isConnected)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: AppColors.paleEmerald,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    '${provider.latencyMs ?? 4}ms',
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.deepNavy),
                                  ),
                                ),
                            ],
                          ),
                          if (provider.id != 'builtin' && isSelected) ...[
                            const SizedBox(height: 6),
                            TextField(
                              controller: keyController,
                              obscureText: true,
                              decoration: InputDecoration(
                                labelText: '${provider.name} API Key',
                                hintText: 'Enter API key...',
                                border: const OutlineInputBorder(),
                                isDense: true,
                              ),
                              onChanged: (v) => provider.apiKey = v.trim(),
                            ),
                            const SizedBox(height: 8),
                            ElevatedButton.icon(
                              onPressed: () async {
                                final res = await AiService.testConnection(provider);
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text('${provider.name}: ${res["message"]}')),
                                );
                                setSheetState(() {});
                                setState(() {});
                              },
                              icon: const Icon(Icons.speed, size: 16),
                              label: const Text('Test & Measure Latency'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.deepNavy,
                                foregroundColor: Colors.white,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final active = AiService.activeProvider;

    return Scaffold(
      appBar: AppBar(
        title: const Text('AI Meeting Intelligence'),
        actions: [
          IconButton(
            icon: const Icon(Icons.tune),
            tooltip: 'Operator Settings',
            onPressed: _openProviderConfigModal,
          ),
        ],
      ),
      body: Column(
        children: [
          // Active Provider Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: 8),
            color: const Color(0xFFF1F5F9),
            child: Row(
              children: [
                const Icon(Icons.bolt, color: AppColors.emerald, size: 18),
                const SizedBox(width: 6),
                Text(
                  'Operator: ${active.name}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5),
                ),
                const Spacer(),
                InkWell(
                  onTap: _openProviderConfigModal,
                  child: Text(
                    active.apiKey != null && active.apiKey!.isNotEmpty ? 'Configured ⚙' : 'Change ⚙',
                    style: const TextStyle(fontSize: 12, color: AppColors.emerald, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),

          // Messages
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(AppSpacing.md),
              itemCount: _messages.length,
              itemBuilder: (ctx, i) {
                final m = _messages[i];
                return Align(
                  alignment: m.isUser ? Alignment.centerRight : Alignment.centerLeft,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
                    decoration: BoxDecoration(
                      color: m.isUser ? AppColors.emerald : Colors.white,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      border: m.isUser ? null : Border.all(color: AppColors.border),
                      boxShadow: const [
                        BoxShadow(color: Color(0x0A000000), offset: Offset(0, 1), blurRadius: 4),
                      ],
                    ),
                    child: Text(
                      m.text,
                      style: TextStyle(
                        color: m.isUser ? Colors.white : AppColors.deepNavy,
                        fontSize: 13.5,
                        height: 1.4,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),

          if (_isProcessing)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 4),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)),
                  SizedBox(width: 8),
                  Text('Synthesizing answer...', style: TextStyle(fontSize: 12, color: AppColors.muted)),
                ],
              ),
            ),

          // Quick Prompt Pills
          SizedBox(
            height: 38,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              children: [
                ActionChip(
                  label: const Text('Summarize latest meeting', style: TextStyle(fontSize: 11)),
                  onPressed: () => _sendMessage('Summarize the latest meeting discussion points and decisions.'),
                ),
                const SizedBox(width: 6),
                ActionChip(
                  label: const Text('Extract action matrix', style: TextStyle(fontSize: 11)),
                  onPressed: () => _sendMessage('What action items and assignees were identified across recordings?'),
                ),
                const SizedBox(width: 6),
                ActionChip(
                  label: const Text('PLAUD BLE status', style: TextStyle(fontSize: 11)),
                  onPressed: () => _sendMessage('What is the status of the PLAUD Note hardware integration?'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),

          // Chat Input Bar
          Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _textController,
                    decoration: InputDecoration(
                      hintText: 'Ask anything about your meetings...',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppRadius.lg)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      isDense: true,
                    ),
                    onSubmitted: _sendMessage,
                  ),
                ),
                const SizedBox(width: 8),
                IconButton.filled(
                  icon: const Icon(Icons.send, size: 18),
                  onPressed: () => _sendMessage(_textController.text),
                  style: IconButton.styleFrom(backgroundColor: AppColors.emerald),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
