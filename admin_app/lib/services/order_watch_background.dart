import 'dart:async';
import 'dart:convert';
import 'dart:ui';

import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

const _kBaseUrl = 'admin_base_url';
const _kToken = 'admin_token';
const _kSeenKey = 'admin_seen_notification_ids';
const _kPendingSnap = 'admin_bg_pending_ids';
const _kPaidSnap = 'admin_bg_paid_ids';
const _kVoidSnap = 'admin_bg_void_ids';
const _kSeeded = 'admin_bg_seeded';

const _orderChannelId = 'stair_pos_orders';
const _serviceChannelId = 'stair_order_watch';
const _serviceNotifId = 77001;

const _orderActions = {
  'pos.order_placed',
  'pos.order_updated',
  'pos.order_paid',
  'pos.order_cancelled',
  'pos.kitchen_void',
};

/// Foreground Android service — keeps polling even when Stair UI is closed.
Future<void> configureOrderWatchService() async {
  final notifications = FlutterLocalNotificationsPlugin();
  const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
  await notifications.initialize(const InitializationSettings(android: androidInit));

  final android = notifications.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
  await android?.createNotificationChannel(
    const AndroidNotificationChannel(
      _serviceChannelId,
      'Order Watch',
      description: 'Keeps Stair watching for new orders in background',
      importance: Importance.low,
    ),
  );
  await android?.createNotificationChannel(
    const AndroidNotificationChannel(
      _orderChannelId,
      'POS Orders',
      description: 'New order, paid bill, and cancel alerts',
      importance: Importance.max,
      playSound: true,
      enableVibration: true,
    ),
  );
  await android?.requestNotificationsPermission();

  final service = FlutterBackgroundService();
  await service.configure(
    androidConfiguration: AndroidConfiguration(
      onStart: orderWatchOnStart,
      autoStart: false,
      autoStartOnBoot: true,
      isForegroundMode: true,
      notificationChannelId: _serviceChannelId,
      initialNotificationTitle: 'Stair',
      initialNotificationContent: 'Orders watch chal rahi hai…',
      foregroundServiceNotificationId: _serviceNotifId,
      foregroundServiceTypes: const [AndroidForegroundType.dataSync],
    ),
    iosConfiguration: IosConfiguration(autoStart: false),
  );
}

Future<void> startOrderWatchService({bool resetSeed = false}) async {
  final prefs = await SharedPreferences.getInstance();
  if (resetSeed) {
    await prefs.setBool(_kSeeded, false);
  }

  final service = FlutterBackgroundService();
  final running = await service.isRunning();
  if (!running) {
    await service.startService();
  }
  service.invoke('refresh');
}

Future<void> stopOrderWatchService() async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setBool(_kSeeded, false);

  final service = FlutterBackgroundService();
  final running = await service.isRunning();
  if (running) {
    service.invoke('stop');
  }
}

@pragma('vm:entry-point')
void orderWatchOnStart(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();

  final notifications = FlutterLocalNotificationsPlugin();
  await notifications.initialize(
    const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher')),
  );

  Timer? timer;

  Future<void> tick() async {
    try {
      await _pollOnce(notifications);
      if (service is AndroidServiceInstance) {
        if (await service.isForegroundService()) {
          final now = DateTime.now();
          final hh = now.hour.toString().padLeft(2, '0');
          final mm = now.minute.toString().padLeft(2, '0');
          await service.setForegroundNotificationInfo(
            title: 'Stair',
            content: 'Orders watch · $hh:$mm',
          );
        }
      }
    } catch (_) {}
  }

  service.on('stop').listen((_) async {
    timer?.cancel();
    await service.stopSelf();
  });

  service.on('refresh').listen((_) => tick());

  await tick();
  timer = Timer.periodic(const Duration(seconds: 4), (_) => tick());
}

Future<void> _pollOnce(FlutterLocalNotificationsPlugin notifications) async {
  final prefs = await SharedPreferences.getInstance();
  final baseUrl = (prefs.getString(_kBaseUrl) ?? '').trim();
  final token = prefs.getString(_kToken) ?? '';
  if (baseUrl.isEmpty || token.isEmpty) return;

  final client = ApiClient(baseUrl: baseUrl, token: token);
  final seeded = prefs.getBool(_kSeeded) ?? false;

  final results = await Future.wait([
    client.get('/api/admin/orders/pending'),
    client.get('/api/admin/orders/paid'),
    client.get('/api/admin/kitchen-voids'),
    client.get('/api/admin/notifications'),
  ]);

  final pending = _idMap(results[0]['orders']);
  final paid = _idMap(results[1]['orders']);
  final voids = _voidMap(results[2]['items']);
  final feed = results[3]['notifications'];

  if (!seeded) {
    await prefs.setString(_kPendingSnap, jsonEncode(pending.keys.toList()));
    await prefs.setString(_kPaidSnap, jsonEncode(paid.keys.toList()));
    await prefs.setString(_kVoidSnap, jsonEncode(voids.keys.toList()));
    if (feed is List) {
      final ids = <String>[];
      for (final item in feed) {
        if (item is Map && item['id'] != null) ids.add(item['id'].toString());
      }
      await prefs.setString(_kSeenKey, jsonEncode(ids));
    }
    await prefs.setBool(_kSeeded, true);
    return;
  }

  final prevPending = _loadIntSet(prefs.getString(_kPendingSnap));
  final prevPaid = _loadIntSet(prefs.getString(_kPaidSnap));
  final prevVoids = _loadIntSet(prefs.getString(_kVoidSnap));
  final seenFeed = _loadStringSet(prefs.getString(_kSeenKey));

  for (final entry in pending.entries) {
    if (prevPending.contains(entry.key)) continue;
    await _showAlert(notifications, 'pending-${entry.key}', 'New Order', entry.value);
  }

  for (final entry in paid.entries) {
    if (prevPaid.contains(entry.key)) continue;
    await _showAlert(notifications, 'paid-${entry.key}', 'Bill Paid', entry.value);
  }

  for (final id in prevPending) {
    if (pending.containsKey(id) || paid.containsKey(id) || prevPaid.contains(id)) continue;
    await _showAlert(notifications, 'cancelled-$id', 'Order Cancelled', 'Order #$id');
  }

  for (final entry in voids.entries) {
    if (prevVoids.contains(entry.key)) continue;
    await _showAlert(notifications, 'void-${entry.key}', 'Item Cancelled', entry.value);
  }

  if (feed is List) {
    for (final item in feed) {
      if (item is! Map) continue;
      final id = item['id']?.toString() ?? '';
      if (id.isEmpty || seenFeed.contains(id)) continue;
      final data = item['data'];
      final map = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
      final action = map['action']?.toString() ?? '';
      if (!_orderActions.contains(action)) {
        seenFeed.add(id);
        continue;
      }
      seenFeed.add(id);
      await _showAlert(
        notifications,
        'feed-$id',
        map['title']?.toString() ?? 'Stair',
        map['message']?.toString() ?? '',
      );
    }
    await prefs.setString(_kSeenKey, jsonEncode(seenFeed.toList()));
  }

  await prefs.setString(_kPendingSnap, jsonEncode(pending.keys.toList()));
  await prefs.setString(_kPaidSnap, jsonEncode(paid.keys.toList()));
  await prefs.setString(_kVoidSnap, jsonEncode(voids.keys.toList()));
}

Map<int, String> _idMap(dynamic raw) {
  final out = <int, String>{};
  if (raw is! List) return out;
  for (final item in raw) {
    if (item is! Map) continue;
    final id = int.tryParse(item['id']?.toString() ?? '') ?? 0;
    if (id <= 0) continue;
    final orderNo = item['order_no']?.toString() ?? '#$id';
    final table = item['table']?.toString() ?? '';
    final guest = item['guest_name']?.toString() ?? '';
    final total = item['grand_total'];
    final parts = <String>[orderNo];
    if (table.isNotEmpty) parts.add(table);
    if (guest.isNotEmpty) parts.add(guest);
    if (total != null) parts.add('Rs. ${total is num ? total.toStringAsFixed(0) : total}');
    out[id] = parts.join(' · ');
  }
  return out;
}

Map<int, String> _voidMap(dynamic raw) {
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

Set<int> _loadIntSet(String? raw) {
  if (raw == null || raw.isEmpty) return {};
  try {
    final list = jsonDecode(raw);
    if (list is! List) return {};
    return list.map((e) => int.tryParse(e.toString()) ?? 0).where((e) => e > 0).toSet();
  } catch (_) {
    return {};
  }
}

Set<String> _loadStringSet(String? raw) {
  if (raw == null || raw.isEmpty) return {};
  try {
    final list = jsonDecode(raw);
    if (list is! List) return {};
    return list.map((e) => e.toString()).toSet();
  } catch (_) {
    return {};
  }
}

Future<void> _showAlert(
  FlutterLocalNotificationsPlugin notifications,
  String key,
  String title,
  String body,
) async {
  await notifications.show(
    key.hashCode & 0x7fffffff,
    title,
    body,
    NotificationDetails(
      android: AndroidNotificationDetails(
        _orderChannelId,
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
