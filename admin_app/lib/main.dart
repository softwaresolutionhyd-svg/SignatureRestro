import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'providers/app_state.dart';
import 'services/session.dart';
import 'widgets/root_gate.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const StairApp());
}

class StairApp extends StatelessWidget {
  const StairApp({super.key});

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
        title: 'Stair',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          brightness: Brightness.dark,
          scaffoldBackgroundColor: const Color(0xFF0B1220),
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF7C3AED),
            brightness: Brightness.dark,
          ),
          useMaterial3: true,
          cardColor: const Color(0xFF151C2C),
          inputDecorationTheme: const InputDecorationTheme(
            border: OutlineInputBorder(),
            isDense: true,
          ),
        ),
        home: const RootGate(),
      ),
    );
  }
}
