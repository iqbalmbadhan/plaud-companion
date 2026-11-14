import 'package:flutter/material.dart';
import '../../core/services/ai_service.dart';
import '../../core/services/plaud_device_service.dart';
import '../../core/theme/app_tokens.dart';

class WorkspaceScreen extends StatefulWidget {
  const WorkspaceScreen({super.key, required this.onSignOut});

  final VoidCallback onSignOut;

  @override
  State<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends State<WorkspaceScreen> {
  final _serverDomainController = TextEditingController(text: 'platform-us.plaud.ai');
  final _backendUrlController = TextEditingController(text: 'https://test.dawncsimmons.com');

  @override
  void dispose() {
    _serverDomainController.dispose();
    _backendUrlController.dispose();
    super.dispose();
  }

  void _scanBleDevices() {
    PlaudDeviceService.instance.startScan();
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => ListenableBuilder(
        listenable: PlaudDeviceService.instance,
        builder: (ctx, _) {
          final isScanning = PlaudDeviceService.instance.isScanning;
          final devices = PlaudDeviceService.instance.discoveredDevices;

          return Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('BLE Hardware Scanner', style: Theme.of(ctx).textTheme.titleLarge),
                    if (isScanning)
                      const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    else
                      IconButton(
                        icon: const Icon(Icons.refresh),
                        onPressed: () => PlaudDeviceService.instance.startScan(),
                      ),
                  ],
                ),
                const Text(
                  'Searching for nearby PLAUD Note, NotePin, and Bluetooth Low Energy audio recorders...',
                  style: TextStyle(fontSize: 12, color: AppColors.muted),
                ),
                const SizedBox(height: AppSpacing.md),
                if (devices.isEmpty && isScanning)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: Text('Listening for BLE GATT advertisements...')),
                  )
                else if (devices.isEmpty && !isScanning)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: Text('No devices found nearby. Ensure your PLAUD Note is powered on.')),
                  )
                else
                  ...devices.map((d) {
                    final isConnected = PlaudDeviceService.instance.connectedDevice?.id == d.id;
                    return Card(
                      child: ListTile(
                        leading: const Icon(Icons.bluetooth, color: AppColors.emerald),
                        title: Text(d.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
                        subtitle: Text('${d.model} • RSSI: ${d.rssi} dBm • Battery: ${d.batteryLevel}%',
                            style: const TextStyle(fontSize: 11.5, color: AppColors.muted)),
                        trailing: ElevatedButton(
                          onPressed: () async {
                            if (isConnected) {
                              await PlaudDeviceService.instance.disconnect();
                            } else {
                              await PlaudDeviceService.instance.connect(d);
                            }
                            if (mounted) Navigator.pop(ctx);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: isConnected ? Colors.red : AppColors.emerald,
                            foregroundColor: Colors.white,
                          ),
                          child: Text(isConnected ? 'Disconnect' : 'Connect'),
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
    return ListenableBuilder(
      listenable: PlaudDeviceService.instance,
      builder: (context, _) {
        final device = PlaudDeviceService.instance.connectedDevice;
        final isConnected = device != null && device.isConnected;

        return Scaffold(
          appBar: AppBar(
            title: const Text('Workspace & Hardware Settings'),
          ),
          body: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              // 1. Hardware Bridge Card
              Container(
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('PLAUD Hardware Bridge', style: Theme.of(context).textTheme.titleMedium),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isConnected ? AppColors.paleEmerald : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            isConnected ? 'Paired' : 'Not Connected',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: isConnected ? AppColors.deepNavy : AppColors.muted,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      isConnected
                          ? 'Connected to ${device.name} (${device.model})\nBattery Level: ${device.batteryLevel}% • Firmware: ${device.firmwareVersion}'
                          : 'Pair your PLAUD Note or NotePin over Bluetooth Low Energy to sync audio recordings with speaker diarization.',
                      style: const TextStyle(fontSize: 12.5, color: Color(0xFF475569)),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        ElevatedButton.icon(
                          onPressed: _scanBleDevices,
                          icon: const Icon(Icons.bluetooth_searching, size: 16),
                          label: Text(isConnected ? 'Switch Device' : 'Scan BLE Devices'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.deepNavy,
                            foregroundColor: Colors.white,
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (isConnected)
                          OutlinedButton.icon(
                            onPressed: () => PlaudDeviceService.instance.disconnect(),
                            icon: const Icon(Icons.close, size: 16, color: Colors.red),
                            label: const Text('Disconnect', style: TextStyle(color: Colors.red)),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),

              // 2. On-Device Cryptography Storage Card
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.shield_outlined, color: AppColors.emerald, size: 20),
                          const SizedBox(width: 8),
                          Text('On-Device Cryptography Storage', style: Theme.of(context).textTheme.titleMedium),
                          const Spacer(),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: AppColors.paleEmerald,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Text(
                              '🔒 AES-256 Active',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.deepNavy),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Meeting recordings, speaker diarization, and transcripts are stored strictly on this device sandbox with zero cloud leakage. Centralized cloud storage is staged for a future opt-in release.',
                        style: TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.md),

              // 3. AI Operator Status
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Active AI Operator', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const Icon(Icons.psychology, color: AppColors.emerald, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            AiService.activeProvider.name,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5),
                          ),
                          const Spacer(),
                          Text(
                            'Latency: ${AiService.activeProvider.latencyMs ?? 4}ms',
                            style: const TextStyle(fontSize: 12, color: AppColors.muted),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Model: ${AiService.activeProvider.activeModel}',
                        style: const TextStyle(fontSize: 12, color: AppColors.muted),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.md),

              // 3. Cloud / Backend Sync Settings
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('PLAUD Cloud & Server Endpoint', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _serverDomainController,
                        decoration: const InputDecoration(
                          labelText: 'PLAUD Partner Cloud Domain',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _backendUrlController,
                        decoration: const InputDecoration(
                          labelText: 'PLAUD Companion Backend URL',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),

              // Sign out / Reset
              OutlinedButton.icon(
                onPressed: widget.onSignOut,
                icon: const Icon(Icons.logout, color: Colors.red),
                label: const Text('Sign Out / Switch Profile', style: TextStyle(color: Colors.red)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFFECACA)),
                  minimumSize: const Size.fromHeight(44),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),

              // Attribution Footer
              Center(
                child: Column(
                  children: [
                    const Text(
                      'PLAUD Companion Mobile',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.muted),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: 6,
                      runSpacing: 4,
                      children: const [
                        Text('Developer: Md. Iqbal Mahmud (www.iqbalmahmud.com)',
                            style: TextStyle(fontSize: 11, color: AppColors.emerald, fontWeight: FontWeight.w600)),
                        Text('•', style: TextStyle(fontSize: 11, color: AppColors.muted)),
                        Text('Advisor: Dawn C Simmons (www.dawncsimmons.com)',
                            style: TextStyle(fontSize: 11, color: AppColors.muted)),
                        Text('•', style: TextStyle(fontSize: 11, color: AppColors.muted)),
                        Text('Brought to you by AI Infos (www.aiinfos.com)',
                            style: TextStyle(fontSize: 11, color: AppColors.muted)),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
            ],
          ),
        );
      },
    );
  }
}
