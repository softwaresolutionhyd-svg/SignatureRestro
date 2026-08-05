import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'order_watch_background.dart';

/// Starts/stops the foreground order watch (keeps running when app UI is closed).
class OrderNotificationService with WidgetsBindingObserver {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  bool _ready = false;
  bool _lifecycleAttached = false;
  bool _startedOnce = false;

  Future<void> init() async {
    if (_ready) return;
    await configureOrderWatchService();
    if (!_lifecycleAttached) {
      WidgetsBinding.instance.addObserver(this);
      _lifecycleAttached = true;
    }
    _ready = true;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Never stop on pause/detach — that was killing alerts when app closed.
    if (state == AppLifecycleState.resumed && _startedOnce) {
      startOrderWatchService(resetSeed: false);
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

    _startedOnce = true;
    await startOrderWatchService(resetSeed: isNewLogin);
  }

  /// Only call on logout — NOT when app UI closes.
  Future<void> stop({bool keepLifecycle = false}) async {
    _startedOnce = false;
    await stopOrderWatchService();
    if (!keepLifecycle && _lifecycleAttached) {
      WidgetsBinding.instance.removeObserver(this);
      _lifecycleAttached = false;
    }
  }
}
