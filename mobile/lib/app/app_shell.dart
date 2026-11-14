import 'package:flutter/material.dart';
import '../core/models/app_models.dart';
import '../features/ai/ai_assistant_screen.dart';
import '../features/dashboard/dashboard_screen.dart';
import '../features/exports/exports_screen.dart';
import '../features/transcripts/transcripts_screen.dart';
import '../features/workspace/workspace_screen.dart';

class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.onSignOut});

  final VoidCallback onSignOut;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int _selectedIndex = 0;

  static const _destinations = [
    (label: 'Home', icon: Icons.dashboard_outlined, activeIcon: Icons.dashboard),
    (label: 'Transcripts', icon: Icons.description_outlined, activeIcon: Icons.description),
    (label: 'AI Studio', icon: Icons.auto_awesome_outlined, activeIcon: Icons.auto_awesome),
    (label: 'Exports', icon: Icons.archive_outlined, activeIcon: Icons.archive),
    (label: 'Hardware', icon: Icons.bluetooth_outlined, activeIcon: Icons.bluetooth),
  ];

  void _openTranscriptDetail(Transcript transcript) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => TranscriptDetailScreen(transcript: transcript),
      ),
    );
  }

  Widget _buildBody() {
    return switch (_selectedIndex) {
      0 => DashboardScreen(
          onNavigateTab: (idx) => setState(() => _selectedIndex = idx),
          onOpenTranscript: _openTranscriptDetail,
        ),
      1 => TranscriptsScreen(
          onOpenDetail: _openTranscriptDetail,
        ),
      2 => const AiAssistantScreen(),
      3 => const ExportsScreen(),
      _ => WorkspaceScreen(
          onSignOut: widget.onSignOut,
        ),
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _buildBody(),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (idx) => setState(() => _selectedIndex = idx),
        destinations: _destinations
            .map(
              (d) => NavigationDestination(
                icon: Icon(d.icon),
                selectedIcon: Icon(d.activeIcon),
                label: d.label,
              ),
            )
            .toList(),
      ),
    );
  }
}
