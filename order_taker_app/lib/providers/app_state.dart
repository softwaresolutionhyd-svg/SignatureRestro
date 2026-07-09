import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/session.dart';

class AppState extends ChangeNotifier {
  Session? _session;
  BootstrapData? bootstrap;
  List<PendingOrder> pending = [];
  bool loading = false;
  String? error;

  void bindSession(Session session) {
    if (_session == session) return;
    _session = session;
  }

  Session get session => _session!;

  Future<void> refreshAll() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final bootRes = await session.client.get('/api/order-taker/bootstrap');
      bootstrap = BootstrapData.fromJson(bootRes);

      final pendingRes = await session.client.get('/api/order-taker/pending');
      final ordersRaw = pendingRes['orders'];
      pending = ordersRaw is List
          ? ordersRaw
              .whereType<Map>()
              .map((e) => PendingOrder.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<OrderDetail> loadOrder(int id) async {
    final res = await session.client.get('/api/order-taker/orders/$id');
    return OrderDetail.fromJson(Map<String, dynamic>.from(res['order'] as Map));
  }

  Future<String> submitOrder({
    required int? orderId,
    required String customerType,
    required String guestName,
    required String roomNo,
    required String waiterName,
    required String serveTime,
    required String serveDate,
    required String serveMeal,
    required int? tableId,
    required List<CartLine> cart,
  }) async {
    final payload = {
      'customer_type': customerType,
      'guest_name': guestName,
      'room_no': roomNo,
      'waiter_name': waiterName,
      'serve_time': serveTime,
      'serve_date': serveDate,
      'serve_meal': serveMeal.isEmpty ? null : serveMeal,
      'table_id': tableId,
      'items': cart.map((c) => c.toPayload()).toList(),
    };

    if (orderId == null) {
      final res = await session.client.post('/api/order-taker/orders', payload);
      await refreshAll();
      return res['message']?.toString() ?? 'Order saved';
    }

    final res = await session.client.put('/api/order-taker/orders/$orderId', {
      'items': cart.map((c) => c.toPayload()).toList(),
    });
    await refreshAll();
    return res['message']?.toString() ?? 'Order updated';
  }
}
