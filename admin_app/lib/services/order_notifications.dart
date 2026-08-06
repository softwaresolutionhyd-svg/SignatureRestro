import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'order_watch_background.dart';

/// Foreground service (app closed) + in-app poll (app open) for order alerts.
class OrderNotificationService with WidgetsBindingObserver {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  static const _channelId = 'stair_pos_orders';
  static const _pollEvery = Duration(seconds: 4);

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();

  bool _ready = false;
  bool _lifecycleAttached = false;
  bool _startedOnce = false;
  bool _seeded = false;
  Timer? _uiTimer;
  ApiClient? _client;

  final Set<int> _pendingIds = {};
  final Set<int> _paidIds = {};
  final Set<int> _voidIds = {};

  Future<void> init() async {
    if (_ready) return;
    await configureOrderWatchService();

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _plugin.initialize(const InitializationSettings(android: android));
    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        _channelId,
        'POS Orders',
        description: 'New order, paid bill, and cancel alerts',
        importance: Importance.max,
        playSound: true,
        enableVibration: true,
      ),
    );
    await androidPlugin?.requestNotificationsPermission();

    if (!_lifecycleAttached) {
      WidgetsBinding.instance.addObserver(this);
      _lifecycleAttached = true;
    }
    _ready = true;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _startedOnce && _client != null) {
      startOrderWatchService(resetSeed: false);
      _uiTimer?.cancel();
      _uiTimer = Timer.periodic(_pollEvery, (_) => _uiPoll());
      unawaited(_uiPoll());
    }
  }

  Future<void> start(ApiClient client, {bool isNewLogin = false}) async {
    await init();
    final prefs = await SharedPreferences.getInstance();
    if (client.baseUrl.isNotEmpty) {
      await prefs.setString('admin_base_url', client.baseUrl);
    }
    if (client.token.isNotEmpty) {
      await prefs.setString('admin_token', client.token);
    }

    _client = client;
    _startedOnce = true;
    if (isNewLogin) {
      _seeded = false;
      _pendingIds.clear();
      _paidIds.clear();
      _voidIds.clear();
    }

    await startOrderWatchService(resetSeed: isNewLogin);

    _uiTimer?.cancel();
    _uiTimer = Timer.periodic(_pollEvery, (_) => _uiPoll());
    await _uiPoll();
  }

  /// Only call on logout — NOT when app UI closes.
  Future<void> stop({bool keepLifecycle = false}) async {
    _startedOnce = false;
    _uiTimer?.cancel();
    _uiTimer = null;
    _client = null;
    await stopOrderWatchService();
    if (!keepLifecycle && _lifecycleAttached) {
      WidgetsBinding.instance.removeObserver(this);
      _lifecycleAttached = false;
    }
  }

  Future<void> _uiPoll() async {
    final client = _client;
    if (client == null) return;

    try {
      final results = await Future.wait([
        client.get('/api/admin/orders/pending?limit=150'),
        client.get('/api/admin/orders/paid?limit=150'),
        client.get('/api/admin/kitchen-voids'),
      ]);

      final pending = _parseIds(results[0]['orders']);
      final paid = _parseIds(results[1]['orders']);
      final voids = _parseVoidIds(results[2]['items']);
      final pendingLabels = _parseLabels(results[0]['orders']);
      final paidLabels = _parseLabels(results[1]['orders']);
      final voidLabels = _parseVoidLabels(results[2]['items']);

      if (!_seeded) {
        _pendingIds
          ..clear()
          ..addAll(pending);
        _paidIds
          ..clear()
          ..addAll(paid);
        _voidIds
          ..clear()
          ..addAll(voids);
        _seeded = true;
        return;
      }

      for (final id in pending) {
        if (_pendingIds.contains(id)) continue;
        await _show('pending-$id', 'New Order', pendingLabels[id] ?? 'Order #$id');
      }
      for (final id in paid) {
        if (_paidIds.contains(id)) continue;
        await _show('paid-$id', 'Bill Paid', paidLabels[id] ?? 'Order #$id');
      }
      for (final id in _pendingIds) {
        if (pending.contains(id) || paid.contains(id) || _paidIds.contains(id)) continue;
        await _show('cancelled-$id', 'Order Cancelled', 'Order #$id');
      }
      for (final id in voids) {
        if (_voidIds.contains(id)) continue;
        await _show('void-$id', 'Item Cancelled', voidLabels[id] ?? 'Item');
      }

      _pendingIds
        ..clear()
        ..addAll(pending);
      _paidIds
        ..clear()
        ..addAll(paid);
      _voidIds
        ..clear()
        ..addAll(voids);
    } catch (e) {
      if (kDebugMode) debugPrint('UI order poll failed: $e');
    }
  }

  Set<int> _parseIds(dynamic raw) {
    final out = <int>{};
    if (raw is! List) return out;
    for (final item in raw) {
      if (item is Map) {
        final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
        if (id > 0) out.add(id);
      }
    }
    return out;
  }

  Set<int> _parseVoidIds(dynamic raw) {
    final out = <int>{};
    if (raw is! List) return out;
    for (final item in raw) {
      if (item is Map) {
        final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
        if (id > 0) out.add(id);
      }
    }
    return out;
  }

  Map<int, String> _parseLabels(dynamic raw) {
    final out = <int, String>{};
    if (raw is! List) return out;
    for (final item in raw) {
      if (item is! Map) continue;
      final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
      if (id <= 0) continue;
      final orderNo = item['order_no']?.toString() ?? '#$id';
      final table = item['table']?.toString() ?? '';
      final total = item['grand_total'];
      final parts = <String>[orderNo];
      if (table.isNotEmpty) parts.add(table);
      if (total != null) parts.add('Rs. ${total is num ? total.toStringAsFixed(0) : total}');
      out[id] = parts.join(' · ');
    }
    return out;
  }

  Map<int, String> _parseVoidLabels(dynamic raw) {
    final out = <int, String>{};
    if (raw is! List) return out;
    for (final item in raw) {
      if (item is! Map) continue;
      final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
      if (id <= 0) continue;
      final orderNo = item['order_no']?.toString() ?? '';
      final name = item['item']?.toString() ?? 'Item';
      final qty = item['qty']?.toString() ?? '';
      out[id] = [
        if (orderNo.isNotEmpty) orderNo,
        if (qty.isNotEmpty) '$qty× $name' else name,
      ].join(' · ');
    }
    return out;
  }

  Future<void> _show(String key, String title, String body) async {
    await _plugin.show(
      key.hashCode & 0x7fffffff,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          'POS Orders',
          channelDescription: 'New order, paid bill, and cancel alerts',
          importance: Importance.max,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          playSound: true,
          enableVibration: true,
          styleInformation: body.isNotEmpty ? BigTextStyleInformation(body) : null,
        ),
      ),
    );
  }
}
