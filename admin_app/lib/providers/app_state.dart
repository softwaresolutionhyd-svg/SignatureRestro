import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/session.dart';

class AppState extends ChangeNotifier {
  Session? _session;
  DashboardData? dashboard;
  AnalyticsOverview? analytics;
  List<AdminOrder> pending = [];
  List<AdminOrder> paid = [];
  List<KitchenVoidItem> voids = [];
  List<ExpenseItem> expenses = [];
  List<LowStockItem> lowStock = [];
  List<AttendanceRow> attendance = [];
  int attendancePresent = 0;
  int attendanceAbsent = 0;
  String attendanceDate = '';
  bool loading = false;
  String? error;
  double paidTotal = 0;

  void bindSession(Session session) {
    if (_session == session) return;
    _session = session;
  }

  Session get session => _session!;

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

  Future<void> refreshPending() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/orders/pending');
      final raw = res['orders'];
      pending = raw is List
          ? raw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshPaid() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/orders/paid');
      final raw = res['orders'];
      paid = raw is List
          ? raw.whereType<Map>().map((e) => AdminOrder.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
      paidTotal = (res['total'] is num) ? (res['total'] as num).toDouble() : 0;
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> refreshVoids() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await session.client.get('/api/admin/kitchen-voids');
      final raw = res['items'];
      voids = raw is List
          ? raw.whereType<Map>().map((e) => KitchenVoidItem.fromJson(Map<String, dynamic>.from(e))).toList()
          : [];
    } catch (e) {
      error = e.toString();
    } finally {
      loading = false;
      notifyListeners();
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
