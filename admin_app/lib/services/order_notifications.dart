import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';
import 'api_client.dart';

/// Watches POS orders and shows phone alerts for punch / paid / cancel.
class OrderNotificationService with WidgetsBindingObserver {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  static const _seenKey = 'admin_seen_notification_ids';
  static const _pollEvery = Duration(seconds: 3);
  static const _channelId = 'stair_pos_orders';

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
  String? _activeToken;
  bool _ready = false;
  bool _seeded = false;
  bool _lifecycleAttached = false;

  final Set<String> _seenNotificationIds = {};
  final Map<int, AdminOrder> _pending = {};
  final Map<int, AdminOrder> _paid = {};
  final Set<int> _voidIds = {};

  Future<void> init() async {
    if (_ready) return;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _plugin.initialize(
      const InitializationSettings(android: android),
      onDidReceiveNotificationResponse: (_) {},
    );

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

    await _loadSeenNotificationIds();
    _ready = true;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_poll());
    }
  }

  Future<void> start(ApiClient client) async {
    await init();

    if (_activeToken == client.token && _timer != null) {
      _client = client;
      return;
    }

    stop(keepLifecycle: true);
    _client = client;
    _activeToken = client.token;
    _seeded = false;
    _pending.clear();
    _paid.clear();
    _voidIds.clear();

    await _poll();
    _timer = Timer.periodic(_pollEvery, (_) => _poll());
  }

  void stop({bool keepLifecycle = false}) {
    _timer?.cancel();
    _timer = null;
    _client = null;
    _activeToken = null;
    _seeded = false;
    if (!keepLifecycle && _lifecycleAttached) {
      WidgetsBinding.instance.removeObserver(this);
      _lifecycleAttached = false;
    }
  }

  Future<void> _loadSeenNotificationIds() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_seenKey);
    if (raw == null || raw.isEmpty) return;
    try {
      final list = jsonDecode(raw);
      if (list is List) {
        _seenNotificationIds
          ..clear()
          ..addAll(list.map((e) => e.toString()));
      }
    } catch (_) {}
  }

  Future<void> _saveSeenNotificationIds() async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = _seenNotificationIds.length > 120
        ? _seenNotificationIds.skip(_seenNotificationIds.length - 120)
        : _seenNotificationIds;
    await prefs.setString(_seenKey, jsonEncode(trimmed.toList()));
  }

  Future<void> _poll() async {
    final client = _client;
    if (client == null) return;

    if (!await _ensurePermission()) return;

    try {
      if (!_seeded) {
        await _seed(client);
        _seeded = true;
        return;
      }

      await Future.wait([
        _checkOrders(client),
        _checkVoids(client),
        _checkActivityFeed(client),
      ]);
    } catch (e) {
      if (kDebugMode) {
        debugPrint('OrderNotificationService poll failed: $e');
      }
    }
  }

  Future<bool> _ensurePermission() async {
    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (androidPlugin == null) return true;

    final enabled = await androidPlugin.areNotificationsEnabled();
    if (enabled == true) return true;

    final granted = await androidPlugin.requestNotificationsPermission();
    return granted == true;
  }

  Future<void> _seed(ApiClient client) async {
    final results = await Future.wait([
      client.get('/api/admin/orders/pending'),
      client.get('/api/admin/orders/paid'),
      client.get('/api/admin/kitchen-voids'),
      client.get('/api/admin/notifications'),
    ]);

    _pending
      ..clear()
      ..addAll(_parseOrders(results[0]['orders']));
    _paid
      ..clear()
      ..addAll(_parseOrders(results[1]['orders']));
    _voidIds
      ..clear()
      ..addAll(_parseVoidIds(results[2]['items']));

    final feed = results[3]['notifications'];
    if (feed is List) {
      for (final item in feed) {
        if (item is Map && item['id'] != null) {
          _seenNotificationIds.add(item['id'].toString());
        }
      }
      await _saveSeenNotificationIds();
    }
  }

  Map<int, AdminOrder> _parseOrders(dynamic raw) {
    if (raw is! List) return {};
    final map = <int, AdminOrder>{};
    for (final item in raw) {
      if (item is! Map) continue;
      final order = AdminOrder.fromJson(Map<String, dynamic>.from(item));
      map[order.id] = order;
    }
    return map;
  }

  Set<int> _parseVoidIds(dynamic raw) {
    if (raw is! List) return {};
    final ids = <int>{};
    for (final item in raw) {
      if (item is Map && item['id'] != null) {
        ids.add(int.tryParse(item['id'].toString()) ?? 0);
      }
    }
    ids.remove(0);
    return ids;
  }

  Future<void> _checkOrders(ApiClient client) async {
    final results = await Future.wait([
      client.get('/api/admin/orders/pending'),
      client.get('/api/admin/orders/paid'),
    ]);

    final nextPending = _parseOrders(results[0]['orders']);
    final nextPaid = _parseOrders(results[1]['orders']);

    for (final entry in nextPending.entries) {
      if (_pending.containsKey(entry.key)) continue;
      await _alert(
        key: 'pending-${entry.key}',
        title: 'New Order',
        body: _orderLabel(entry.value),
      );
    }

    for (final entry in nextPaid.entries) {
      if (_paid.containsKey(entry.key)) continue;
      final o = entry.value;
      await _alert(
        key: 'paid-${o.id}',
        title: 'Bill Paid',
        body: '${o.orderNo} · Rs. ${o.grandTotal.toStringAsFixed(0)}',
      );
    }

    for (final id in _pending.keys) {
      if (nextPending.containsKey(id)) continue;
      if (nextPaid.containsKey(id) || _paid.containsKey(id)) continue;
      final old = _pending[id];
      if (old != null) {
        await _alert(
          key: 'cancelled-$id',
          title: 'Order Cancelled',
          body: old.orderNo,
        );
      }
    }

    _pending
      ..clear()
      ..addAll(nextPending);
    _paid
      ..clear()
      ..addAll(nextPaid);
  }

  Future<void> _checkVoids(ApiClient client) async {
    final res = await client.get('/api/admin/kitchen-voids');
    final next = _parseVoidIds(res['items']);
    final items = res['items'];
    final byId = <int, Map<String, dynamic>>{};
    if (items is List) {
      for (final item in items) {
        if (item is Map) {
          final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
          if (id > 0) byId[id] = Map<String, dynamic>.from(item);
        }
      }
    }

    for (final id in next) {
      if (_voidIds.contains(id)) continue;
      final row = byId[id];
      final orderNo = row?['order_no']?.toString() ?? '';
      final item = row?['item']?.toString() ?? 'Item';
      final qty = row?['qty']?.toString() ?? '';
      await _alert(
        key: 'void-$id',
        title: 'Item Cancelled',
        body: [
          if (orderNo.isNotEmpty) orderNo,
          if (qty.isNotEmpty) '$qty× $item' else item,
        ].join(' · '),
      );
    }

    _voidIds
      ..clear()
      ..addAll(next);
  }

  Future<void> _checkActivityFeed(ApiClient client) async {
    final res = await client.get('/api/admin/notifications');
    final raw = res['notifications'];
    if (raw is! List) return;

    for (final item in raw) {
      if (item is! Map) continue;
      final id = item['id']?.toString() ?? '';
      if (id.isEmpty || _seenNotificationIds.contains(id)) continue;

      final data = item['data'];
      final map = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
      final action = map['action']?.toString() ?? '';
      if (!_orderActions.contains(action)) {
        _seenNotificationIds.add(id);
        continue;
      }

      _seenNotificationIds.add(id);
      await _alert(
        key: 'feed-$id',
        title: map['title']?.toString() ?? 'Stair',
        body: map['message']?.toString() ?? '',
      );
    }

    await _saveSeenNotificationIds();
  }

  String _orderLabel(AdminOrder o) {
    final parts = <String>[o.orderNo];
    if ((o.table ?? '').isNotEmpty) parts.add(o.table!);
    if ((o.guestName ?? '').isNotEmpty) parts.add(o.guestName!);
    return parts.join(' · ');
  }

  Future<void> _alert({required String key, required String title, required String body}) async {
    final details = NotificationDetails(
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
    );

    final notifId = key.hashCode & 0x7fffffff;
    await _plugin.show(notifId, title, body, details);
  }
}
