import 'package:flutter/material.dart';
import '../core/data/demo_data.dart';
import '../core/models/demo_models.dart';
import '../core/theme/app_tokens.dart';
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
  DemoWorkspace _activeWorkspace = DemoData.workspaces.first;

  static const _destinations = [
    (label: 'Home', icon: Icons.dashboard_outlined, activeIcon: Icons.dashboard),
    (
      label: 'Transcripts',
      icon: Icons.description_outlined,
      activeIcon: Icons.description
    ),
    (
      label: 'AI',
      icon: Icons.auto_awesome_outlined,
      activeIcon: Icons.auto_awesome
    ),
    (
      label: 'Exports',
      icon: Icons.archive_outlined,
      activeIcon: Icons.archive
    ),
    (
      label: 'Workspace',
      icon: Icons.workspaces_outlined,
      activeIcon: Icons.workspaces
    ),
  ];

  void _openTranscriptDetail(DemoTranscript transcript) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => TranscriptDetailScreen(transcript: transcript),
      ),
    );
  }

  Widget _buildBody() {
    return switch (_selectedIndex) {
      0 => DashboardScreen(
          workspace: _activeWorkspace,
          onNavigateTab: (idx) => setState(() => _selectedIndex = idx),
          onOpenTranscript: _openTranscriptDetail,
        ),
      1 => TranscriptsScreen(
          workspace: _activeWorkspace,
          onOpenDetail: _openTranscriptDetail,
        ),
      2 => const AiAssistantScreen(),
      3 => ExportsScreen(workspace: _activeWorkspace),
      _ => WorkspaceScreen(
          activeWorkspace: _activeWorkspace,
          onSelectWorkspace: (ws) => setState(() => _activeWorkspace = ws),
          onSignOut: widget.onSignOut,
        ),
    };
  }

  Widget _buildWorkspaceSwitcher() {
    return PopupMenuButton<DemoWorkspace>(
      tooltip: 'Switch workspace',
      initialValue: _activeWorkspace,
      onSelected: (ws) => setState(() => _activeWorkspace = ws),
      itemBuilder: (context) => DemoData.workspaces
          .map(
            (ws) => PopupMenuItem<DemoWorkspace>(
              value: ws,
              child: ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(ws.name),
                subtitle: Text(ws.role.label),
                trailing: ws.id == _activeWorkspace.id
                    ? const Icon(Icons.check, color: AppColors.emeraldAccent)
                    : null,
              ),
            ),
          )
          .toList(),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xxs,
        ),
        decoration: BoxDecoration(
          color: AppColors.mainBackground,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.workspaces_outline,
                size: 16, color: AppColors.deepNavy),
            const SizedBox(width: AppSpacing.xs),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 160),
              child: Text(
                _activeWorkspace.name,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primaryText,
                ),
              ),
            ),
            const Icon(Icons.arrow_drop_down, color: AppColors.secondaryText),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDesktop =
        MediaQuery.sizeOf(context).width >= AppSpacing.desktopBreakpoint;

    return Scaffold(
      appBar: AppBar(
        title: const Text('PLAUD Companion'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.md),
            child: Center(child: _buildWorkspaceSwitcher()),
          ),
        ],
      ),
      body: isDesktop
          ? Row(
              children: [
                NavigationRail(
                  extended: true,
                  backgroundColor: AppColors.deepNavy,
                  selectedIndex: _selectedIndex,
                  onDestinationSelected: (idx) =>
                      setState(() => _selectedIndex = idx),
                  selectedIconTheme:
                      const IconThemeData(color: AppColors.emeraldAccent),
                  unselectedIconTheme:
                      const IconThemeData(color: Colors.white70),
                  selectedLabelTextStyle: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                  unselectedLabelTextStyle:
                      const TextStyle(color: Colors.white70),
                  indicatorColor: AppColors.navySurface,
                  destinations: _destinations
                      .map(
                        (d) => NavigationRailDestination(
                          icon: Icon(d.icon),
                          selectedIcon: Icon(d.activeIcon),
                          label: Text(d.label),
                        ),
                      )
                      .toList(),
                ),
                const VerticalDivider(width: 1),
                Expanded(child: _buildBody()),
              ],
            )
          : _buildBody(),
      bottomNavigationBar: isDesktop
          ? null
          : NavigationBar(
              selectedIndex: _selectedIndex,
              onDestinationSelected: (idx) =>
                  setState(() => _selectedIndex = idx),
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
