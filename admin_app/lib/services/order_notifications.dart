import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'order_watch_background.dart';

/// Starts/stops the background order watch (works even when app UI is closed).
class OrderNotificationService with WidgetsBindingObserver {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  bool _ready = false;
  bool _lifecycleAttached = false;
  String? _activeToken;

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
    if (state == AppLifecycleState.resumed && _activeToken != null) {
      startOrderWatchService();
    }
  }

  Future<void> start(ApiClient client) async {
    await init();
    final prefs = await SharedPreferences.getInstance();
    // Ensure credentials are on disk for the background isolate.
    if (client.baseUrl.isNotEmpty) {
      await prefs.setString('admin_base_url', client.baseUrl);
    }
    if (client.token.isNotEmpty) {
      await prefs.setString('admin_token', client.token);
    }

    final resetSeed = _activeToken != client.token;
    _activeToken = client.token;
    await startOrderWatchService(resetSeed: resetSeed);
  }

  void stop({bool keepLifecycle = false}) {
    _activeToken = null;
    // ignore: discarded_futures
    stopOrderWatchService();
    if (!keepLifecycle && _lifecycleAttached) {
      WidgetsBinding.instance.removeObserver(this);
      _lifecycleAttached = false;
    }
  }
}
