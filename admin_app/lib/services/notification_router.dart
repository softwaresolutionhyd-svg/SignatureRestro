import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import '../screens/bills_screen.dart';
import '../screens/kitchen_voids_screen.dart';
import '../screens/orders_screen.dart';

/// Opens the right admin screen when a push notification is tapped.
class NotificationRouter {
  NotificationRouter._();

  static final NotificationRouter instance = NotificationRouter._();

  GlobalKey<NavigatorState>? navigatorKey;

  void openFromData(Map<String, dynamic> data) {
    final nav = navigatorKey?.currentState;
    if (nav == null) {
      // Retry once after first frame (cold start from terminated).
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final n = navigatorKey?.currentState;
        if (n != null) _navigate(n, data);
      });
      return;
    }
    _navigate(nav, data);
  }

  void _navigate(NavigatorState nav, Map<String, dynamic> data) {
    final screen = (data['screen'] ?? '').toString().toLowerCase();
    final action = (data['action'] ?? '').toString().toLowerCase();

    final ctx = nav.context;
    final appState = Provider.of<AppState>(ctx, listen: false);

    if (screen == 'kitchen_voids' || action.contains('kitchen_void') || action.contains('cancel')) {
      appState.refreshVoids();
      nav.push(MaterialPageRoute(
        builder: (_) => Scaffold(
          appBar: AppBar(title: const Text('Cancel Orders')),
          body: const KitchenVoidsScreen(),
        ),
      ));
      return;
    }

    if (screen == 'bills_paid' || action.contains('paid') || action.contains('refund') || action.contains('reopen')) {
      appState.refreshBills().then((_) {
        if (!nav.mounted) return;
        nav.push(MaterialPageRoute(builder: (_) => const OrdersScreen(mode: OrdersMode.paid)));
      });
      return;
    }

    if (screen == 'bills_pending' || action.contains('placed') || action.contains('updated')) {
      appState.refreshBills().then((_) {
        if (!nav.mounted) return;
        nav.push(MaterialPageRoute(builder: (_) => const OrdersScreen(mode: OrdersMode.pending)));
      });
      return;
    }

    // Default: Pending / Paid Bills hub
    appState.refreshBills();
    nav.push(MaterialPageRoute(builder: (_) => const BillsScreen()));
  }
}
