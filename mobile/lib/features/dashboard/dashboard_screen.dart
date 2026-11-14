import 'package:flutter/material.dart';
import '../../core/models/app_models.dart';
import '../../core/services/app_state.dart';
import '../../core/services/plaud_device_service.dart';
import '../../core/theme/app_tokens.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    super.key,
    required this.onNavigateTab,
    required this.onOpenTranscript,
  });

  final ValueChanged<int> onNavigateTab;
  final ValueChanged<Transcript> onOpenTranscript;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: AppState.instance,
      builder: (context, _) {
        final state = AppState.instance;
        final transcripts = state.transcripts;
        final totalSegments = transcripts.fold<int>(0, (acc, t) => acc + t.segments.length);
        final connectedDevice = PlaudDeviceService.instance.connectedDevice;

        return Scaffold(
          appBar: AppBar(
            title: const Row(
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundColor: AppColors.emerald,
                  child: Text(
                    'PC',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                ),
                SizedBox(width: 8),
                Text('PLAUD Companion'),
              ],
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.refresh),
                tooltip: 'Refresh',
                onPressed: () => AppState.instance.notifyListeners(),
              ),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              // 1. PLAUD Hardware Bridge Banner
              _buildHardwareCard(context, connectedDevice),
              const SizedBox(height: AppSpacing.md),

              // 2. 1-Click Demo Showcase Exporter Banner
              _buildShowcaseBanner(context),
              const SizedBox(height: AppSpacing.md),

              // 3. Stats Grid
              Row(
                children: [
                  Expanded(
                    child: _buildMetricCard(
                      context,
                      title: 'Recordings',
                      value: '${transcripts.length}',
                      subtitle: 'Active workspace',
                      icon: Icons.mic_none,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _buildMetricCard(
                      context,
                      title: 'Diarized Turns',
                      value: '$totalSegments',
                      subtitle: 'Speaker segments',
                      icon: Icons.record_voice_over_outlined,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _buildMetricCard(
                      context,
                      title: 'Export Files',
                      value: '${state.exportHistory.length}',
                      subtitle: 'DOCX & PDF ready',
                      icon: Icons.archive_outlined,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),

              // 4. Section: Recent Transcripts
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Recent Recordings',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.deepNavy,
                        ),
                  ),
                  TextButton(
                    onPressed: () => onNavigateTab(1),
                    child: const Text('View All'),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),

              if (transcripts.isEmpty)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.xl),
                    child: Column(
                      children: [
                        const Icon(Icons.mic_off_outlined, size: 40, color: AppColors.muted),
                        const SizedBox(height: 8),
                        const Text('No recordings yet.', style: TextStyle(fontWeight: FontWeight.w600)),
                        const SizedBox(height: 4),
                        const Text('Connect your PLAUD Note or tap sync to import recordings.',
                            textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: AppColors.muted)),
                      ],
                    ),
                  ),
                )
              else
                ...transcripts.take(3).map(
                      (t) => Card(
                        margin: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          leading: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: AppColors.paleEmerald,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(Icons.audio_file, color: AppColors.emerald),
                          ),
                          title: Text(
                            t.title,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(
                            '${t.durationFormatted} • ${t.segments.length} speakers • ${t.project}',
                            style: const TextStyle(fontSize: 12, color: AppColors.muted),
                          ),
                          trailing: const Icon(Icons.chevron_right, color: AppColors.muted),
                          onTap: () => onOpenTranscript(t),
                        ),
                      ),
                    ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildHardwareCard(BuildContext context, PlaudDevice? device) {
    final isConnected = device != null && device.isConnected;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08101B33),
            offset: Offset(0, 3),
            blurRadius: 10,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: isConnected ? AppColors.paleEmerald : const Color(0xFFF1F5F9),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isConnected ? Icons.bluetooth_connected : Icons.bluetooth,
                  color: isConnected ? AppColors.emerald : AppColors.muted,
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isConnected ? device.name : 'PLAUD Note / NotePin Hardware',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    Text(
                      isConnected
                          ? 'BLE GATT Connected • ${device.batteryLevel}% Battery'
                          : 'Bluetooth Low Energy Ready to Pair',
                      style: TextStyle(
                        fontSize: 12,
                        color: isConnected ? AppColors.emerald : AppColors.muted,
                        fontWeight: isConnected ? FontWeight.w600 : FontWeight.normal,
                      ),
                    ),
                  ],
                ),
              ),
              if (isConnected)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.paleEmerald,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${device.batteryLevel}% 🔋',
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.deepNavy),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: isConnected
                    ? ElevatedButton.icon(
                        onPressed: () async {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Syncing recordings from connected PLAUD device...')),
                          );
                          await AppState.instance.syncFromPlaudDevice();
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Synced recording successfully! Diarization ready.')),
                          );
                        },
                        icon: const Icon(Icons.sync, size: 18),
                        label: const Text('⚡ Sync Recordings'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.emerald,
                          foregroundColor: Colors.white,
                        ),
                      )
                    : ElevatedButton.icon(
                        onPressed: () => onNavigateTab(4), // Open Workspace/Device Tab
                        icon: const Icon(Icons.bluetooth_searching, size: 18),
                        label: const Text('Pair PLAUD Device'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.deepNavy,
                          foregroundColor: Colors.white,
                        ),
                      ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildShowcaseBanner(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F172A), Color(0xFF1E293B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: const [
          BoxShadow(color: Color(0x1F0F172A), offset: Offset(0, 4), blurRadius: 14),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.rocket_launch, color: Color(0xFF38BDF8), size: 18),
              ),
              const SizedBox(width: 8),
              const Text(
                '1-Click PLAUD Demo Exporter',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Generate a complete executive showcase package with Word (.docx) and PDF (.pdf) documents, speaker diarization, and action items matrix.',
            style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 12, height: 1.4),
          ),
          const SizedBox(height: 12),
          ElevatedButton.icon(
            onPressed: () async {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Generating Word (.docx) and PDF (.pdf) showcase package...')),
              );
              final t = AppState.instance.transcripts.first;
              final docx = await AppState.instance.exportTranscript(transcript: t, format: 'docx');
              final pdf = await AppState.instance.exportTranscript(transcript: t, format: 'pdf');
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Showcase packages generated!\n• ${docx.title}\n• ${pdf.title}'),
                  action: SnackBarAction(label: 'View', onPressed: () => onNavigateTab(3)),
                ),
              );
            },
            icon: const Icon(Icons.download, size: 16),
            label: const Text('Export Showcase (DOCX + PDF)'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.emerald,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMetricCard(
    BuildContext context, {
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sm),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.muted),
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppColors.deepNavy)),
          const SizedBox(height: 2),
          Text(title, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.deepNavy)),
          Text(subtitle, style: const TextStyle(fontSize: 10, color: AppColors.muted)),
        ],
      ),
    );
  }
}
