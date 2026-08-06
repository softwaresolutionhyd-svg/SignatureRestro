import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../firebase_options.dart';
import 'api_client.dart';
import 'notification_router.dart';

/// Top-level background handler — must be a top-level / static function.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Background isolate: ensure Firebase is ready before reading message.
  try {
    if (Firebase.apps.isEmpty && DefaultFirebaseOptions.isConfigured) {
      await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    }
  } catch (e) {
    if (kDebugMode) debugPrint('FCM bg Firebase init: $e');
  }
  if (kDebugMode) {
    debugPrint('FCM background: ${message.messageId} ${message.notification?.title}');
  }
}

/// Server-side FCM push + local display (foreground) + tap navigation.
class FcmPushService {
  FcmPushService._();

  static final FcmPushService instance = FcmPushService._();

  static const channelId = 'stair_pos_orders';
  static const _prefToken = 'admin_fcm_token';
  static const _prefDeviceId = 'admin_device_id';

  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();

  bool _ready = false;
  bool _firebaseOk = false;
  ApiClient? _client;
  String? _token;
  StreamSubscription<String>? _tokenRefreshSub;
  StreamSubscription<RemoteMessage>? _onMessageSub;
  StreamSubscription<RemoteMessage>? _onOpenedSub;

  bool get isReady => _ready && _firebaseOk;
  String? get token => _token;

  Future<void> init() async {
    if (_ready) return;

    await _initLocalNotifications();

    if (!DefaultFirebaseOptions.isConfigured) {
      if (kDebugMode) {
        debugPrint('FCM skipped: firebase_options.dart still has REPLACE_* placeholders. See FIREBASE_SETUP.md');
      }
      _ready = true;
      return;
    }

    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
      }
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      if (Platform.isAndroid) {
        await messaging.setAutoInitEnabled(true);
      }

      _onMessageSub = FirebaseMessaging.onMessage.listen(_onForegroundMessage);
      _onOpenedSub = FirebaseMessaging.onMessageOpenedApp.listen(_handleTapPayload);

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        // Defer until navigator is ready.
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _handleTapPayload(initial);
        });
      }

      _tokenRefreshSub = messaging.onTokenRefresh.listen((t) async {
        _token = t;
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_prefToken, t);
        await _registerWithBackend();
      });

      _firebaseOk = true;
    } catch (e, st) {
      if (kDebugMode) debugPrint('FCM init failed: $e\n$st');
      _firebaseOk = false;
    }

    _ready = true;
  }

  Future<void> _initLocalNotifications() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _local.initialize(
      const InitializationSettings(android: android),
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload == null || payload.isEmpty) return;
        try {
          final map = jsonDecode(payload);
          if (map is Map<String, dynamic>) {
            NotificationRouter.instance.openFromData(map);
          }
        } catch (_) {}
      },
    );

    final androidPlugin = _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        channelId,
        'POS Orders',
        description: 'New order, paid bill, refund, and cancel alerts',
        importance: Importance.max,
        playSound: true,
        enableVibration: true,
      ),
    );
    await androidPlugin?.requestNotificationsPermission();
  }

  /// Call after login (or when session restores) to bind token to Sanctum user.
  Future<void> start(ApiClient client, {bool isNewLogin = false}) async {
    await init();
    _client = client;

    final prefs = await SharedPreferences.getInstance();
    if (client.baseUrl.isNotEmpty) {
      await prefs.setString('admin_base_url', client.baseUrl);
    }
    if (client.token.isNotEmpty) {
      await prefs.setString('admin_token', client.token);
    }

    if (!_firebaseOk) return;

    try {
      _token = await FirebaseMessaging.instance.getToken();
      if (_token != null && _token!.isNotEmpty) {
        await prefs.setString(_prefToken, _token!);
        await _registerWithBackend();
      }
    } catch (e) {
      if (kDebugMode) debugPrint('FCM getToken failed: $e');
    }
  }

  /// Logout — unregister this device token then clear local state.
  Future<void> stop() async {
    final client = _client;
    final t = _token;
    final prefs = await SharedPreferences.getInstance();
    final deviceId = prefs.getString(_prefDeviceId);

    if (client != null && client.token.isNotEmpty) {
      try {
        await client.delete(
          '/api/admin/device-tokens',
          body: {
            if (t != null && t.isNotEmpty) 'token': t,
            if (deviceId != null && deviceId.isNotEmpty) 'device_id': deviceId,
            'app': 'admin',
          },
        );
      } catch (_) {}
    }

    try {
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {}

    await prefs.remove(_prefToken);
    _token = null;
    _client = null;
  }

  Future<void> disposeListeners() async {
    await _tokenRefreshSub?.cancel();
    await _onMessageSub?.cancel();
    await _onOpenedSub?.cancel();
    _tokenRefreshSub = null;
    _onMessageSub = null;
    _onOpenedSub = null;
  }

  Future<String> deviceId() async {
    final prefs = await SharedPreferences.getInstance();
    var id = prefs.getString(_prefDeviceId);
    if (id == null || id.isEmpty) {
      id = 'android-${DateTime.now().millisecondsSinceEpoch}-${Random().nextInt(1 << 32)}';
      await prefs.setString(_prefDeviceId, id);
    }
    return id;
  }

  Future<void> _registerWithBackend() async {
    final client = _client;
    final t = _token;
    if (client == null || t == null || t.isEmpty || client.token.isEmpty) return;

    try {
      await client.post('/api/admin/device-tokens', {
        'token': t,
        'platform': Platform.isIOS ? 'ios' : 'android',
        'device_id': await deviceId(),
        'app': 'admin',
      });
      if (kDebugMode) debugPrint('FCM token registered');
    } catch (e) {
      if (kDebugMode) debugPrint('FCM register failed: $e');
    }
  }

  Future<void> _onForegroundMessage(RemoteMessage message) async {
    final title = message.notification?.title ?? message.data['title']?.toString() ?? 'Stair';
    final body = message.notification?.body ?? message.data['body']?.toString() ?? message.data['message']?.toString() ?? '';
    final data = Map<String, dynamic>.from(message.data);
    if (!data.containsKey('title')) data['title'] = title;
    if (!data.containsKey('body')) data['body'] = body;

    final id = (message.messageId ?? '${title}_$body').hashCode & 0x7fffffff;
    await _local.show(
      id,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          'POS Orders',
          channelDescription: 'New order, paid bill, refund, and cancel alerts',
          importance: Importance.max,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          playSound: true,
          enableVibration: true,
          styleInformation: body.isNotEmpty ? BigTextStyleInformation(body) : null,
        ),
      ),
      payload: jsonEncode(data),
    );
  }

  void _handleTapPayload(RemoteMessage message) {
    final data = Map<String, dynamic>.from(message.data);
    if (message.notification != null) {
      data.putIfAbsent('title', () => message.notification!.title ?? '');
      data.putIfAbsent('body', () => message.notification!.body ?? '');
    }
    NotificationRouter.instance.openFromData(data);
  }
}
