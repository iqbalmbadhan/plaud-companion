import 'dart:convert';

class PlaudDevice {
  final String id;
  final String name;
  final String model;
  final int batteryLevel;
  final bool isConnected;
  final int rssi;
  final String firmwareVersion;

  const PlaudDevice({
    required this.id,
    required this.name,
    this.model = 'PLAUD Note',
    this.batteryLevel = 94,
    this.isConnected = false,
    this.rssi = -54,
    this.firmwareVersion = 'v2.4.12',
  });

  PlaudDevice copyWith({
    String? id,
    String? name,
    String? model,
    int? batteryLevel,
    bool? isConnected,
    int? rssi,
    String? firmwareVersion,
  }) {
    return PlaudDevice(
      id: id ?? this.id,
      name: name ?? this.name,
      model: model ?? this.model,
      batteryLevel: batteryLevel ?? this.batteryLevel,
      isConnected: isConnected ?? this.isConnected,
      rssi: rssi ?? this.rssi,
      firmwareVersion: firmwareVersion ?? this.firmwareVersion,
    );
  }
}

class TranscriptSegment {
  final String id;
  final String speaker;
  final int startMs;
  final int endMs;
  final String text;

  const TranscriptSegment({
    required this.id,
    required this.speaker,
    required this.startMs,
    required this.endMs,
    required this.text,
  });

  String get timeFormatted {
    final s = startMs ~/ 1000;
    final m = s ~/ 60;
    final remS = s % 60;
    return '${m.toString().padLeft(2, '0')}:${remS.toString().padLeft(2, '0')}';
  }
}

class ActionItem {
  final String task;
  final String? assignee;
  final String? dueDate;
  bool isDone;

  ActionItem({
    required this.task,
    this.assignee,
    this.dueDate,
    this.isDone = false,
  });
}

class Transcript {
  final String id;
  String title;
  String project;
  String sourceFormat;
  int durationSeconds;
  DateTime recordedAt;
  String status;
  String contentText;
  List<TranscriptSegment> segments;
  String? aiSummary;
  List<ActionItem> actionItems;

  Transcript({
    required this.id,
    required this.title,
    this.project = 'General',
    this.sourceFormat = 'BLE Sync',
    this.durationSeconds = 180,
    required this.recordedAt,
    this.status = 'Ready',
    required this.contentText,
    required this.segments,
    this.aiSummary,
    List<ActionItem>? actionItems,
  }) : actionItems = actionItems ?? [];

  String get durationFormatted {
    final m = durationSeconds ~/ 60;
    final s = durationSeconds % 60;
    return '${m}m ${s.toString().padLeft(2, '0')}s';
  }
}

class AiProviderConfig {
  final String id;
  final String name;
  final String defaultModel;
  final List<String> availableModels;
  String? apiKey;
  String activeModel;
  bool isConnected;
  int? latencyMs;

  AiProviderConfig({
    required this.id,
    required this.name,
    required this.defaultModel,
    required this.availableModels,
    this.apiKey,
    String? activeModel,
    this.isConnected = false,
    this.latencyMs,
  }) : activeModel = activeModel ?? defaultModel;
}

class ExportItem {
  final String id;
  final String title;
  final String format;
  final String contentPreview;
  final DateTime createdAt;
  final int sizeBytes;

  const ExportItem({
    required this.id,
    required this.title,
    required this.format,
    required this.contentPreview,
    required this.createdAt,
    required this.sizeBytes,
  });
}
