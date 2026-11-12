import 'package:flutter/material.dart';
import '../../core/data/demo_data.dart';
import '../../core/models/demo_models.dart';
import '../../core/models/plaud_device.dart';
import '../../core/services/plaud_sdk_service.dart';
import '../../core/theme/app_tokens.dart';
import '../../shared/widgets/demo_banner.dart';
import '../../shared/widgets/stat_card.dart';

class WorkspaceScreen extends StatefulWidget {
  const WorkspaceScreen({
    super.key,
    required this.activeWorkspace,
    required this.onSelectWorkspace,
    required this.onSignOut,
    this.plaudService,
  });

  final DemoWorkspace activeWorkspace;
  final ValueChanged<DemoWorkspace> onSelectWorkspace;
  final VoidCallback onSignOut;
  final IPlaudSdkService? plaudService;

  @override
  State<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends State<WorkspaceScreen> {
  late final IPlaudSdkService _plaudService;
  late PlaudDevice? _activeDevice;
  bool _isSyncing = false;

  @override
  void initState() {
    super.initState();
    _plaudService = widget.plaudService ?? MockPlaudSdkService();
    _activeDevice = _plaudService.connectedDevice;
  }

  void _syncRecordings() async {
    if (_activeDevice == null) return;
    setState(() => _isSyncing = true);

    await _plaudService.syncRecording(
      deviceId: _activeDevice!.id,
      recordingId: 'rec-001',
    );

    if (mounted) {
      setState(() {
        _isSyncing = false;
        _activeDevice = _activeDevice?.copyWith(pendingRecordingsCount: 0);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Plaud audio recordings successfully synced to workspace!'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final canManage = widget.activeWorkspace.role.canManageWorkspace;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        const DemoModeBanner(compact: true),
        const SizedBox(height: AppSpacing.md),
        Text(
          'Workspaces',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.xs),
        ...DemoData.workspaces.map(
          (ws) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.xs),
            child: RadioListTile<String>(
              value: ws.id,
              groupValue: widget.activeWorkspace.id,
              onChanged: (_) => widget.onSelectWorkspace(ws),
              title: Text(ws.name),
              subtitle: Text('${ws.role.label} · ${ws.transcriptCount} transcripts'),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),

        // --- PLAUD Official Embedded SDK Card ---
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'PLAUD Hardware & SDK',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const StatusBadge(label: 'SDK v1.0'),
          ],
        ),
        const SizedBox(height: AppSpacing.xs),
        Card(
          elevation: 2,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(AppSpacing.sm),
                      decoration: BoxDecoration(
                        color: AppColors.paleEmerald,
                        borderRadius: BorderRadius.circular(AppBorderRadius.sm),
                      ),
                      child: const Icon(
                        Icons.bluetooth_connected,
                        color: AppColors.emeraldAccent,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _activeDevice?.name ?? 'No Device Connected',
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                          Text(
                            _activeDevice != null
                                ? 'Battery: ${_activeDevice!.batteryLevel}% · Firmware ${_activeDevice!.firmwareVersion}'
                                : 'Scan nearby PLAUD Note / NotePin devices via BLE',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '${_activeDevice?.pendingRecordingsCount ?? 0} pending recordings on device',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    FilledButton.icon(
                      onPressed: (_activeDevice != null &&
                              !_isSyncing &&
                              (_activeDevice?.pendingRecordingsCount ?? 0) > 0)
                          ? _syncRecordings
                          : null,
                      icon: _isSyncing
                          ? const SizedBox(
                              width: 14,
                              height: 14,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.sync),
                      label: Text(_isSyncing ? 'Syncing...' : 'Sync Now'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),

        Text(
          'Projects & Folders',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.xs),
        ...DemoData.projects.map(
          (p) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.xs),
            child: ListTile(
              leading: CircleAvatar(
                radius: 8,
                backgroundColor: Color(p.colorHex),
              ),
              title: Text(p.name),
              trailing: Text('${p.transcriptCount} items'),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Members & Roles',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            if (canManage)
              TextButton.icon(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Demo: Invite modal will send invites in Phase 7.'),
                    ),
                  );
                },
                icon: const Icon(Icons.person_add_outlined),
                label: const Text('Invite'),
              ),
          ],
        ),
        ...DemoData.members.map(
          (m) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.xs),
            child: ListTile(
              title: Text(m.name),
              subtitle: Text('${m.email} · ${m.role.label}'),
              trailing: StatusBadge(label: m.status),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          'Notifications',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.xs),
        ...DemoData.notifications.map(
          (n) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.xs),
            child: ListTile(
              leading: Icon(
                n.unread ? Icons.mark_email_unread : Icons.notifications_none,
                color: n.unread ? AppColors.emeraldAccent : AppColors.secondaryText,
              ),
              title: Text(n.title),
              subtitle: Text('${n.subtitle} · ${n.timeAgo}'),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          'Profile & Privacy',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: AppSpacing.xs),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.shield_outlined),
                title: const Text('Data Retention & Privacy Policy'),
                subtitle: const Text('Workspace isolation & zero client secrets'),
                onTap: () {},
              ),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.logout, color: AppColors.error),
                title: const Text(
                  'Sign Out',
                  style: TextStyle(color: AppColors.error),
                ),
                onTap: widget.onSignOut,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
