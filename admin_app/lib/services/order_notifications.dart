import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

/// Polls POS activity and shows phone notifications for new order / paid / cancel.
class OrderNotificationService {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  static const _seenKey = 'admin_seen_notification_ids';
  static const _pollEvery = Duration(seconds: 5);

  static const _orderActions = {
    'pos.order_placed',
    'pos.order_updated',
    'pos.order_paid',
    'pos.order_cancelled',
    'pos.kitchen_void',
  };

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();

  Timer? _timer;
  ApiClient? _client;
  bool _ready = false;
  bool _seeded = false;
  final Set<String> _seenIds = {};

  Future<void> init() async {
    if (_ready) return;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: android);
    await _plugin.initialize(initSettings);

    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.requestNotificationsPermission();

    await _loadSeenIds();
    _ready = true;
  }

  Future<void> start(ApiClient client) async {
    await init();
    _client = client;
    _seeded = false;
    _timer?.cancel();
    await _poll();
    _timer = Timer.periodic(_pollEvery, (_) => _poll());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    _client = null;
    _seeded = false;
  }

  Future<void> _loadSeenIds() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_seenKey);
    if (raw == null || raw.isEmpty) return;
    try {
      final list = jsonDecode(raw);
      if (list is List) {
        _seenIds
          ..clear()
          ..addAll(list.map((e) => e.toString()));
      }
    } catch (_) {}
  }

  Future<void> _saveSeenIds() async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = _seenIds.length > 120 ? _seenIds.skip(_seenIds.length - 120) : _seenIds;
    await prefs.setString(_seenKey, jsonEncode(trimmed.toList()));
  }

  Future<void> _poll() async {
    final client = _client;
    if (client == null) return;

    try {
      final res = await client.get('/api/admin/notifications');
      final raw = res['notifications'];
      if (raw is! List) return;

      for (final item in raw) {
        if (item is! Map) continue;
        final id = item['id']?.toString() ?? '';
        if (id.isEmpty) continue;

        final data = item['data'];
        final map = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
        final action = map['action']?.toString() ?? '';

        if (!_orderActions.contains(action)) {
          _seenIds.add(id);
          continue;
        }

        if (!_seeded) {
          _seenIds.add(id);
          continue;
        }

        if (_seenIds.contains(id)) continue;

        _seenIds.add(id);
        await _showNotification(
          id: id,
          title: map['title']?.toString() ?? 'Stair',
          body: map['message']?.toString() ?? '',
          action: action,
        );
      }

      if (!_seeded) {
        _seeded = true;
      }

      await _saveSeenIds();
    } catch (e) {
      if (kDebugMode) {
        debugPrint('OrderNotificationService poll failed: $e');
      }
    }
  }

  Future<void> _showNotification({
    required String id,
    required String title,
    required String body,
    required String action,
  }) async {
    final details = NotificationDetails(
      android: AndroidNotificationDetails(
        'stair_pos_orders',
        'POS Orders',
        channelDescription: 'New order, paid bill, and cancel alerts',
        importance: Importance.high,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
        styleInformation: body.isNotEmpty ? BigTextStyleInformation(body) : null,
      ),
    );

    final notifId = id.hashCode & 0x7fffffff;
    await _plugin.show(notifId, title, body, details);
  }
}
