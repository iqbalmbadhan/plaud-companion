enum WorkspaceRole {
  owner('Owner'),
  admin('Admin'),
  member('Member'),
  reviewer('Read-only Reviewer');

  const WorkspaceRole(this.label);
  final String label;

  bool get canManageWorkspace =>
      this == WorkspaceRole.owner || this == WorkspaceRole.admin;
  bool get canImportOrExport => this != WorkspaceRole.reviewer;
}

class DemoWorkspace {
  const DemoWorkspace({
    required this.id,
    required this.name,
    required this.isPersonal,
    required this.role,
    required this.memberCount,
    required this.transcriptCount,
  });

  final String id;
  final String name;
  final bool isPersonal;
  final WorkspaceRole role;
  final int memberCount;
  final int transcriptCount;
}

class DemoProject {
  const DemoProject({
    required this.id,
    required this.name,
    required this.transcriptCount,
    required this.colorHex,
  });

  final String id;
  final String name;
  final int transcriptCount;
  final int colorHex;
}

class TranscriptSegment {
  const TranscriptSegment({
    required this.id,
    required this.speaker,
    required this.timestamp,
    required this.text,
  });

  final String id;
  final String speaker;
  final String timestamp;
  final String text;
}

class DemoTranscript {
  const DemoTranscript({
    required this.id,
    required this.title,
    required this.project,
    required this.sourceFormat,
    required this.durationLabel,
    required this.recordedAt,
    required this.status,
    required this.hasAiSummary,
    required this.summary,
    required this.actionItems,
    required this.keyDecisions,
    required this.segments,
  });

  final String id;
  final String title;
  final String project;
  final String sourceFormat;
  final String durationLabel;
  final String recordedAt;
  final String status;
  final bool hasAiSummary;
  final String summary;
  final List<String> actionItems;
  final List<String> keyDecisions;
  final List<TranscriptSegment> segments;
}

class DemoChatMessage {
  const DemoChatMessage({
    required this.id,
    required this.isUser,
    required this.content,
    this.citations = const [],
  });

  final String id;
  final bool isUser;
  final String content;
  final List<String> citations;
}

class DemoExportJob {
  const DemoExportJob({
    required this.id,
    required this.title,
    required this.format,
    required this.itemCount,
    required this.status,
    required this.createdAt,
  });

  final String id;
  final String title;
  final String format;
  final int itemCount;
  final String status;
  final String createdAt;
}

class DemoMember {
  const DemoMember({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.status,
  });

  final String id;
  final String name;
  final String email;
  final WorkspaceRole role;
  final String status;
}

class DemoNotificationItem {
  const DemoNotificationItem({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.timeAgo,
    required this.unread,
  });

  final String id;
  final String title;
  final String subtitle;
  final String timeAgo;
  final bool unread;
}
