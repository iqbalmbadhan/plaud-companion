import 'dart:async';
import 'package:flutter/services.dart';
import '../models/plaud_device.dart';

abstract class IPlaudSdkService {
  Future<bool> initialize({
    required String userAccessToken,
    String customDomain = 'platform-us.plaud.ai',
  });

  Stream<List<PlaudDevice>> scanForDevices({
    Duration timeout = const Duration(seconds: 8),
  });

  Future<bool> connectDevice(String deviceId);

  Future<bool> disconnectDevice(String deviceId);

  Future<List<PlaudDeviceRecording>> fetchRecordings(String deviceId);

  Future<bool> syncRecording({
    required String deviceId,
    required String recordingId,
    void Function(double progress)? onProgress,
  });

  PlaudDevice? get connectedDevice;
}

/// Production implementation communicating with native Android (aar) / iOS (xcframework)
/// over Flutter MethodChannel.
class MethodChannelPlaudSdkService implements IPlaudSdkService {
  static const MethodChannel _methodChannel =
      MethodChannel('com.plaudcompanion.app/plaud_sdk');

  PlaudDevice? _connectedDevice;

  @override
  PlaudDevice? get connectedDevice => _connectedDevice;

  @override
  Future<bool> initialize({
    required String userAccessToken,
    String customDomain = 'platform-us.plaud.ai',
  }) async {
    try {
      final result = await _methodChannel.invokeMethod<bool>('initSDK', {
        'userAccessToken': userAccessToken,
        'customDomain': customDomain,
      });
      return result ?? false;
    } on PlatformException {
      return false;
    }
  }

  @override
  Stream<List<PlaudDevice>> scanForDevices({
    Duration timeout = const Duration(seconds: 8),
  }) async* {
    try {
      final List<dynamic>? rawList =
          await _methodChannel.invokeMethod<List<dynamic>>('startScan', {
        'timeoutMs': timeout.inMilliseconds,
      });

      if (rawList != null) {
        final devices = rawList
            .map((item) => PlaudDevice.fromMap(Map<String, dynamic>.from(item as Map)))
            .toList();
        yield devices;
      } else {
        yield [];
      }
    } on PlatformException {
      yield [];
    }
  }

  @override
  Future<bool> connectDevice(String deviceId) async {
    try {
      final success = await _methodChannel.invokeMethod<bool>('connectDevice', {
        'deviceId': deviceId,
      });
      if (success == true) {
        _connectedDevice = PlaudDevice(
          id: deviceId,
          name: 'PLAUD NOTE',
          model: 'NOTE',
          batteryLevel: 92,
          isConnected: true,
        );
        return true;
      }
      return false;
    } on PlatformException {
      return false;
    }
  }

  @override
  Future<bool> disconnectDevice(String deviceId) async {
    try {
      final success = await _methodChannel.invokeMethod<bool>('disconnectDevice', {
        'deviceId': deviceId,
      });
      _connectedDevice = null;
      return success ?? false;
    } on PlatformException {
      return false;
    }
  }

  @override
  Future<List<PlaudDeviceRecording>> fetchRecordings(String deviceId) async {
    try {
      final List<dynamic>? list =
          await _methodChannel.invokeMethod<List<dynamic>>('fetchRecordings', {
        'deviceId': deviceId,
      });
      if (list == null) return [];
      return list.map((item) {
        final map = Map<String, dynamic>.from(item as Map);
        return PlaudDeviceRecording(
          id: map['id'] as String,
          filename: map['filename'] as String,
          durationMs: map['durationMs'] as int? ?? 0,
          recordedAt: DateTime.tryParse(map['recordedAt'] as String? ?? '') ??
              DateTime.now(),
          sizeBytes: map['sizeBytes'] as int? ?? 0,
          format: map['format'] as String? ?? 'wav',
        );
      }).toList();
    } on PlatformException {
      return [];
    }
  }

  @override
  Future<bool> syncRecording({
    required String deviceId,
    required String recordingId,
    void Function(double progress)? onProgress,
  }) async {
    try {
      final success = await _methodChannel.invokeMethod<bool>('syncRecording', {
        'deviceId': deviceId,
        'recordingId': recordingId,
      });
      onProgress?.call(1.0);
      return success ?? false;
    } on PlatformException {
      return false;
    }
  }
}

/// Mock service for widget testing, CI execution, and local development
/// without physical Bluetooth hardware.
class MockPlaudSdkService implements IPlaudSdkService {
  MockPlaudSdkService({List<PlaudDevice>? initialDevices})
      : _devices = initialDevices ??
            [
              const PlaudDevice(
                id: 'PLAUD-NT-8821',
                name: 'PLAUD NOTE (8821)',
                model: 'NOTE',
                batteryLevel: 88,
                isConnected: true,
                storageUsedBytes: 4200000000,
                pendingRecordingsCount: 2,
              ),
              const PlaudDevice(
                id: 'PLAUD-NP-4102',
                name: 'PLAUD NotePin (4102)',
                model: 'NOTEPIN',
                batteryLevel: 74,
                isConnected: false,
                storageUsedBytes: 1500000000,
                pendingRecordingsCount: 0,
              ),
            ];

  final List<PlaudDevice> _devices;
  PlaudDevice? _connectedDevice;

  @override
  PlaudDevice? get connectedDevice =>
      _connectedDevice ?? _devices.firstWhere((d) => d.isConnected, orElse: () => _devices.first);

  @override
  Future<bool> initialize({
    required String userAccessToken,
    String customDomain = 'platform-us.plaud.ai',
  }) async {
    return true;
  }

  @override
  Stream<List<PlaudDevice>> scanForDevices({
    Duration timeout = const Duration(seconds: 8),
  }) async* {
    yield _devices;
  }

  @override
  Future<bool> connectDevice(String deviceId) async {
    final idx = _devices.indexWhere((d) => d.id == deviceId);
    if (idx != -1) {
      _connectedDevice = _devices[idx].copyWith(isConnected: true);
      return true;
    }
    return false;
  }

  @override
  Future<bool> disconnectDevice(String deviceId) async {
    _connectedDevice = null;
    return true;
  }

  @override
  Future<List<PlaudDeviceRecording>> fetchRecordings(String deviceId) async {
    return [
      PlaudDeviceRecording(
        id: 'rec-001',
        filename: 'REC_20261112_1420.wav',
        durationMs: 2520000, // 42 mins
        recordedAt: DateTime.now().subtract(const Duration(hours: 3)),
        sizeBytes: 44236800,
        format: 'wav',
      ),
      PlaudDeviceRecording(
        id: 'rec-002',
        filename: 'REC_20261111_0945.opus',
        durationMs: 3480000, // 58 mins
        recordedAt: DateTime.now().subtract(const Duration(days: 1)),
        sizeBytes: 14680064,
        format: 'opus',
      ),
    ];
  }

  @override
  Future<bool> syncRecording({
    required String deviceId,
    required String recordingId,
    void Function(double progress)? onProgress,
  }) async {
    onProgress?.call(0.5);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    onProgress?.call(1.0);
    return true;
  }
}
