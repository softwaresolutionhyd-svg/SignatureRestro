import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/session.dart';

class AppState extends ChangeNotifier {
  Session? _session;
  DashboardData? dashboard;
  AnalyticsOverview? analytics;
  ReportsOverview? reports;
  List<AdminOrder> pending = [];
  List<AdminOrder> paid = [];
  List<KitchenVoidItem> voids = [];
  List<ExpenseItem> expenses = [];
  List<LowStockItem> lowStock = [];
  List<AttendanceRow> attendance = [];
  int attendancePresent = 0;
  int attendanceAbsent = 0;
  String attendanceDate = '';
  String attendanceMonthLabel = '';
  bool loading = false;
  String? error;
  double paidTotal = 0;
  bool _billsSyncInFlight = false;

  void bindSession(Session session) {
    if (_session == session) return;
    _session = session;
  }

  Session get session => _session!;

  String _ordersFingerprint(List<AdminOrder> orders) {
    final buf = StringBuffer();
    for (final o in orders) {
      buf.write('${o.id}:${o.grandTotal}:${o.status}:${o.time ?? ''};');
    }
    return buf.toString();
  }

  Future<void> refreshDashboard() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/dashboard');
      dashboard = DashboardData.fromJson(res);
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshReports() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/reports/overview');
      reports = ReportsOverview.fromJson(res);
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshAnalytics() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/analytics');
      analytics = AnalyticsOverview.fromJson(res);
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshPending({bool silent = false}) async {
    if (!silent) {
      loading = true;
      error = null;
      notifyListeners();
    }
    try {
      final res = await session.client.get('/api/admin/orders/pending?limit=150');
      final raw = res['orders'];
      final next = raw is List
          ? raw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : <AdminOrder>[];
      final changed = _ordersFingerprint(pending) != _ordersFingerprint(next);
      pending = next;
      if (!silent) {
        error = null;
        loading = false;
        notifyListeners();
      } else if (changed) {
        notifyListeners();
      }
    } catch (e) {
      if (!silent) {
        error = e.toString();
        loading = false;
        notifyListeners();
      }
    }
  }

  Future<void> refreshPaid({bool silent = false}) async {
    if (!silent) {
      loading = true;
      error = null;
      notifyListeners();
    }
    try {
      final res = await session.client.get('/api/admin/orders/paid?limit=150');
      final raw = res['orders'];
      final next = raw is List
          ? raw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : <AdminOrder>[];
      final nextTotal = (res['total'] is num) ? (res['total'] as num).toDouble() : 0.0;
      final changed =
          _ordersFingerprint(paid) != _ordersFingerprint(next) || paidTotal != nextTotal;
      paid = next;
      paidTotal = nextTotal;
      if (!silent) {
        error = null;
        loading = false;
        notifyListeners();
      } else if (changed) {
        notifyListeners();
      }
    } catch (e) {
      if (!silent) {
        error = e.toString();
        loading = false;
        notifyListeners();
      }
    }
  }

  /// Live sync for Pending/Paid bills. [silent] skips loading spinner and keeps old data on errors.
  Future<void> refreshBills({bool silent = false}) async {
    if (_billsSyncInFlight) return;
    _billsSyncInFlight = true;
    try {
      if (!silent) {
        loading = true;
        error = null;
        notifyListeners();
      }
      final results = await Future.wait([
        session.client.get('/api/admin/orders/pending?limit=150'),
        session.client.get('/api/admin/orders/paid?limit=150'),
      ]);
      final pendingRaw = results[0]['orders'];
      final paidRaw = results[1]['orders'];
      final nextPending = pendingRaw is List
          ? pendingRaw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : <AdminOrder>[];
      final nextPaid = paidRaw is List
          ? paidRaw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : <AdminOrder>[];
      final nextPaidTotal = (results[1]['total'] is num) ? (results[1]['total'] as num).toDouble() : 0.0;
      final changed = _ordersFingerprint(pending) != _ordersFingerprint(nextPending) ||
          _ordersFingerprint(paid) != _ordersFingerprint(nextPaid) ||
          paidTotal != nextPaidTotal;
      pending = nextPending;
      paid = nextPaid;
      paidTotal = nextPaidTotal;
      if (!silent) {
        error = null;
        loading = false;
        notifyListeners();
      } else if (changed) {
        notifyListeners();
      }
    } catch (e) {
      if (!silent) {
        error = e.toString();
        loading = false;
        notifyListeners();
      }
    } finally {
      _billsSyncInFlight = false;
    }
  }

  String _voidsFingerprint(List<KitchenVoidItem> items) {
    final buf = StringBuffer();
    for (final v in items) {
      buf.write('${v.id}:${v.qty}:${v.item}:${v.orderNo ?? ''};');
    }
    return buf.toString();
  }

  Future<void> refreshVoids({bool silent = false}) async {
    if (!silent) {
      loading = true;
      error = null;
      notifyListeners();
    }
    try {
      final res = await session.client.get('/api/admin/kitchen-voids');
      final raw = res['items'];
      final next = raw is List
          ? raw.whereType<Map>().map((e) => KitchenVoidItem.fromJson(Map<String, dynamic>.from(e))).toList()
          : <KitchenVoidItem>[];
      final changed = _voidsFingerprint(voids) != _voidsFingerprint(next);
      voids = next;
      if (!silent) {
        error = null;
        loading = false;
        notifyListeners();
      } else if (changed) {
        notifyListeners();
      }
    } catch (e) {
      if (!silent) {
        error = e.toString();
        loading = false;
        notifyListeners();
      }
    }
  }

  Future<void> refreshExpenses() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/expenses');
      final raw = res['expenses'];
      expenses = raw is List
          ? raw.whereType<Map>().map((e) => ExpenseItem.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshLowStock() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/low-stock');
      final raw = res['products'];
      lowStock = raw is List
          ? raw.whereType<Map>().map((e) => LowStockItem.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshAttendance() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/attendance');
      attendanceDate = res['date']?.toString() ?? '';
      attendanceMonthLabel = res['month_label']?.toString() ?? '';
      attendancePresent = (res['present'] is num) ? (res['present'] as num).toInt() : 0;
      attendanceAbsent = (res['absent'] is num) ? (res['absent'] as num).toInt() : 0;
      final raw = res['employees'];
      attendance = raw is List
          ? raw.whereType<Map>().map((e) => AttendanceRow.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshAll() async {
    await refreshDashboard();
  }
}
