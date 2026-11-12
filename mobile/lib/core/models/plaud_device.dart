class PlaudDevice {
  const PlaudDevice({
    required this.id,
    required this.name,
    required this.model,
    required this.batteryLevel,
    this.isConnected = false,
    this.isSyncing = false,
    this.storageUsedBytes = 0,
    this.storageTotalBytes = 64 * 1024 * 1024 * 1024, // 64 GB standard
    this.firmwareVersion = 'v1.4.2',
    this.pendingRecordingsCount = 0,
  });

  final String id;
  final String name;
  final String model;
  final int batteryLevel;
  final bool isConnected;
  final bool isSyncing;
  final int storageUsedBytes;
  final int storageTotalBytes;
  final String firmwareVersion;
  final int pendingRecordingsCount;

  PlaudDevice copyWith({
    String? id,
    String? name,
    String? model,
    int? batteryLevel,
    bool? isConnected,
    bool? isSyncing,
    int? storageUsedBytes,
    int? storageTotalBytes,
    String? firmwareVersion,
    int? pendingRecordingsCount,
  }) {
    return PlaudDevice(
      id: id ?? this.id,
      name: name ?? this.name,
      model: model ?? this.model,
      batteryLevel: batteryLevel ?? this.batteryLevel,
      isConnected: isConnected ?? this.isConnected,
      isSyncing: isSyncing ?? this.isSyncing,
      storageUsedBytes: storageUsedBytes ?? this.storageUsedBytes,
      storageTotalBytes: storageTotalBytes ?? this.storageTotalBytes,
      firmwareVersion: firmwareVersion ?? this.firmwareVersion,
      pendingRecordingsCount:
          pendingRecordingsCount ?? this.pendingRecordingsCount,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'name': name,
      'model': model,
      'batteryLevel': batteryLevel,
      'isConnected': isConnected,
      'isSyncing': isSyncing,
      'storageUsedBytes': storageUsedBytes,
      'storageTotalBytes': storageTotalBytes,
      'firmwareVersion': firmwareVersion,
      'pendingRecordingsCount': pendingRecordingsCount,
    };
  }

  factory PlaudDevice.fromMap(Map<String, dynamic> map) {
    return PlaudDevice(
      id: map['id'] as String,
      name: map['name'] as String,
      model: map['model'] as String? ?? 'PLAUD NOTE',
      batteryLevel: map['batteryLevel'] as int? ?? 100,
      isConnected: map['isConnected'] as bool? ?? false,
      isSyncing: map['isSyncing'] as bool? ?? false,
      storageUsedBytes: map['storageUsedBytes'] as int? ?? 0,
      storageTotalBytes: map['storageTotalBytes'] as int? ?? 64 * 1024 * 1024 * 1024,
      firmwareVersion: map['firmwareVersion'] as String? ?? 'v1.4.2',
      pendingRecordingsCount: map['pendingRecordingsCount'] as int? ?? 0,
    );
  }
}

class PlaudDeviceRecording {
  const PlaudDeviceRecording({
    required this.id,
    required this.filename,
    required this.durationMs,
    required this.recordedAt,
    required this.sizeBytes,
    this.format = 'wav',
    this.isTransferred = false,
  });

  final String id;
  final String filename;
  final int durationMs;
  final DateTime recordedAt;
  final int sizeBytes;
  final String format;
  final bool isTransferred;

  String get formattedDuration {
    final minutes = (durationMs / 60000).floor();
    final seconds = ((durationMs % 60000) / 1000).floor();
    return '${minutes}m ${seconds.toString().padLeft(2, '0')}s';
  }

  String get formattedSize {
    final mb = sizeBytes / (1024 * 1024);
    return '${mb.toStringAsFixed(1)} MB';
  }
}
