import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/order_notifications.dart';
import '../services/session.dart';
import '../screens/home_shell.dart';
import '../screens/login_screen.dart';

class RootGate extends StatefulWidget {
  const RootGate({super.key});

  @override
  State<RootGate> createState() => _RootGateState();
}

class _RootGateState extends State<RootGate> {
  Session? _session;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _session = context.read<Session>();
      _session!.addListener(_syncNotifications);
      _syncNotifications();
    });
  }

  @override
  void dispose() {
    _session?.removeListener(_syncNotifications);
    OrderNotificationService.instance.stop();
    super.dispose();
  }

  void _syncNotifications() {
    final session = _session ?? context.read<Session>();
    if (session.isLoggedIn) {
      OrderNotificationService.instance.start(session.client);
    } else {
      OrderNotificationService.instance.stop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    if (!session.loaded) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (session.isLoggedIn) {
      return const HomeShell();
    }

    return const LoginScreen();
  }
}
