import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import 'home_shell.dart';
import 'orders_screen.dart';

class BillsScreen extends StatefulWidget {
  const BillsScreen({super.key});

  @override
  State<BillsScreen> createState() => _BillsScreenState();
}

class _BillsScreenState extends State<BillsScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    return AdminDarkScaffold(
      title: 'Pending / Paid Bills',
      body: Column(
        children: [
          TabBar(
            controller: _tabs,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white54,
            indicatorColor: const Color(0xFF0D9488),
            tabs: [
              Tab(text: 'Pending (${state.pending.length})'),
              Tab(text: 'Paid (${state.paid.length})'),
            ],
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: const [
                OrdersScreen(mode: OrdersMode.pending),
                OrdersScreen(mode: OrdersMode.paid),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
