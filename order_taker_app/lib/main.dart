import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'providers/app_state.dart';
import 'screens/login_screen.dart';
import 'screens/pending_screen.dart';
import 'services/session.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const OrderTakerApp());
}

class OrderTakerApp extends StatelessWidget {
  const OrderTakerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => Session()..load()),
        ChangeNotifierProxyProvider<Session, AppState>(
          create: (_) => AppState(),
          update: (_, session, state) => state!..bindSession(session),
        ),
      ],
      child: MaterialApp(
        title: 'Order Taker',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF198754),
            brightness: Brightness.light,
          ),
          useMaterial3: true,
          inputDecorationTheme: const InputDecorationTheme(
            border: OutlineInputBorder(),
            isDense: true,
          ),
        ),
        home: const _RootGate(),
      ),
    );
  }
}

class _RootGate extends StatelessWidget {
  const _RootGate();

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    if (!session.loaded) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (session.isLoggedIn) {
      return const PendingScreen();
    }

    return const LoginScreen();
  }
}
