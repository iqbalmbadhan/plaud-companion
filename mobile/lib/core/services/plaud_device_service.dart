import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import '../models/app_models.dart';

/// PLAUD hardware over Bluetooth Low Energy, through the official PLAUD Embedded SDK.
///
/// The SDK (iOS xcframework / Android aar) is only available to approved PLAUD
/// partners, so the native side of [channel] is registered once the SDK is added.
/// Until then every call reports "not available" — this service never invents
/// devices, battery levels or recordings.
///
/// Flow (docs.plaud.ai/plaud-embedded): the backend exchanges our partner
/// credentials for a 24h PLAUD user token (POST /api/v1/plaud/user-token);
/// the app passes it to `initSDK`, then scans, binds and syncs over BLE.
class PlaudDeviceService extends ChangeNotifier {
  static final PlaudDeviceService instance = PlaudDeviceService._();
  PlaudDeviceService._();

  static const MethodChannel channel = MethodChannel('com.plaudcompanion.app/plaud_sdk');

  static const String notInstalledMessage =
      'PLAUD device connection is not available yet. It is switched on once the '
      'PLAUD Embedded SDK and partner credentials are added to this build.';

  bool _isScanning = false;
  bool _sdkReady = false;
  String? _statusMessage;
  PlaudDevice? _connectedDevice;
  final List<PlaudDevice> _discoveredDevices = [];

  bool get isScanning => _isScanning;
  bool get sdkReady => _sdkReady;
  String? get statusMessage => _statusMessage;
  PlaudDevice? get connectedDevice => _connectedDevice;
  List<PlaudDevice> get discoveredDevices => List.unmodifiable(_discoveredDevices);

  /// Initialise the native SDK with a PLAUD user token issued by our backend.
  Future<bool> initialize({required String plaudUserToken, String domain = 'platform-us.plaud.ai'}) async {
    final ok = await _invoke<bool>('initSDK', {'userAccessToken': plaudUserToken, 'customDomain': domain});
    _sdkReady = ok == true;
    notifyListeners();
    return _sdkReady;
  }

  Future<void> startScan({Duration duration = const Duration(seconds: 8)}) async {
    if (_isScanning) return;
    _isScanning = true;
    _discoveredDevices.clear();
    notifyListeners();
    try {
      final raw = await _invoke<List<dynamic>>('startScan', {'timeoutMs': duration.inMilliseconds});
      for (final item in raw ?? const []) {
        _discoveredDevices.add(_deviceFromMap(Map<String, dynamic>.from(item as Map)));
      }
    } finally {
      _isScanning = false;
      notifyListeners();
    }
  }

  Future<bool> connect(PlaudDevice device) async {
    final ok = await _invoke<bool>('connectDevice', {'deviceId': device.id});
    if (ok == true) {
      _connectedDevice = device.copyWith(isConnected: true);
      _statusMessage = null;
    }
    notifyListeners();
    return ok == true;
  }

  Future<void> disconnect() async {
    final device = _connectedDevice;
    if (device == null) return;
    await _invoke<bool>('disconnectDevice', {'deviceId': device.id});
    _connectedDevice = null;
    notifyListeners();
  }

  /// Pulls the newest recording from the device. The audio is transcribed by the
  /// PLAUD Transcription API on the server, so no transcript is returned here.
  Future<Transcript> syncLatestSession() async {
    final device = _connectedDevice;
    if (device == null) {
      throw StateError('No PLAUD device is connected.');
    }
    final result = await _invoke<Map<dynamic, dynamic>>('syncLatestRecording', {'deviceId': device.id});
    if (result == null) {
      throw StateError(_statusMessage ?? notInstalledMessage);
    }
    throw UnimplementedError(
      'Recording synced from the device; server-side transcription import is the next integration step.',
    );
  }

  /// Calls the native SDK. Returns null (and sets [statusMessage]) when the SDK
  /// is missing or the call fails, so the UI can show an honest message.
  Future<T?> _invoke<T>(String method, [Map<String, dynamic>? args]) async {
    try {
      final value = await channel.invokeMethod<T>(method, args);
      _statusMessage = null;
      return value;
    } on MissingPluginException {
      _statusMessage = notInstalledMessage;
    } on PlatformException catch (e) {
      _statusMessage = 'PLAUD device error: ${e.message ?? e.code}';
    }
    notifyListeners();
    return null;
  }

  static PlaudDevice _deviceFromMap(Map<String, dynamic> m) => PlaudDevice(
        id: (m['id'] ?? m['deviceId'] ?? '').toString(),
        name: (m['name'] ?? 'PLAUD device').toString(),
        model: (m['model'] ?? 'PLAUD').toString(),
        batteryLevel: (m['batteryLevel'] as num?)?.toInt() ?? 0,
        rssi: (m['rssi'] as num?)?.toInt() ?? 0,
        firmwareVersion: (m['firmwareVersion'] ?? '').toString(),
      );
}
