import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import '../services/session.dart';
import 'expenses_screen.dart';
import 'kitchen_voids_screen.dart';
import 'low_stock_screen.dart';
import 'orders_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppState>().refreshDashboard();
    });
  }

  Future<void> _onTab(int i) async {
    setState(() => _index = i);
    final state = context.read<AppState>();
    switch (i) {
      case 0:
        await state.refreshDashboard();
        break;
      case 1:
        await state.refreshPending();
        break;
      case 2:
        await state.refreshPaid();
        break;
      case 3:
        await state.refreshVoids();
        break;
      case 4:
        await state.refreshExpenses();
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final pages = [
      const _DashboardPage(),
      const OrdersScreen(mode: OrdersMode.pending),
      const OrdersScreen(mode: OrdersMode.paid),
      const KitchenVoidsScreen(),
      const ExpensesScreen(),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(_index == 0 ? 'Dashboard' : ['', 'Pending', 'Paid', 'Cancelled', 'Expenses'][_index]),
        actions: [
          if (_index == 0)
            IconButton(
              tooltip: 'Low stock',
              icon: const Icon(Icons.inventory_2_outlined),
              onPressed: () async {
                await context.read<AppState>().refreshLowStock();
                if (!context.mounted) return;
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LowStockScreen()));
              },
            ),
          PopupMenuButton<String>(
            onSelected: (v) async {
              if (v == 'logout') {
                await session.logout();
              }
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                enabled: false,
                child: Text('${session.userName}\n${session.userRole}', style: const TextStyle(fontSize: 13)),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(value: 'logout', child: Text('Logout')),
            ],
          ),
        ],
      ),
      body: pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: _onTab,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.hourglass_empty), selectedIcon: Icon(Icons.hourglass_top), label: 'Pending'),
          NavigationDestination(icon: Icon(Icons.payments_outlined), selectedIcon: Icon(Icons.payments), label: 'Paid'),
          NavigationDestination(icon: Icon(Icons.cancel_outlined), selectedIcon: Icon(Icons.cancel), label: 'Cancel'),
          NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'Expense'),
        ],
      ),
    );
  }
}

class _DashboardPage extends StatelessWidget {
  const _DashboardPage();

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final d = state.dashboard;
    final money = NumberFormat('#,##0');

    if (state.loading && d == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && d == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(state.error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => state.refreshDashboard(), child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    final cur = d?.currency ?? 'Rs.';

    return RefreshIndicator(
      onRefresh: () => state.refreshDashboard(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (d?.sessionOpenedAt != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text('POS session: ${d!.sessionOpenedAt}', style: TextStyle(color: Colors.grey.shade700)),
            ),
          Text('Aaj', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _KpiCard(label: 'Sales', value: '$cur ${money.format(d?.todaySales ?? 0)}', color: const Color(0xFF0F766E)),
              _KpiCard(label: 'Bills', value: '${d?.todayBills ?? 0}', color: const Color(0xFF1D4ED8)),
              _KpiCard(label: 'Expenses', value: '$cur ${money.format(d?.todayExpenses ?? 0)}', color: const Color(0xFFB45309)),
            ],
          ),
          const SizedBox(height: 20),
          Text('Is mahina', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _KpiCard(label: 'Sales', value: '$cur ${money.format(d?.monthSales ?? 0)}', color: const Color(0xFF065F46)),
              _KpiCard(label: 'Expenses', value: '$cur ${money.format(d?.monthExpenses ?? 0)}', color: const Color(0xFF9A3412)),
            ],
          ),
          const SizedBox(height: 20),
          Text('Alerts', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          ListTile(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
            leading: const Icon(Icons.pending_actions),
            title: const Text('Pending bills'),
            trailing: Text('${d?.pendingOrders ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          ),
          const SizedBox(height: 8),
          ListTile(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
            leading: Icon(Icons.warning_amber_rounded, color: (d?.lowStock ?? 0) > 0 ? Colors.orange : null),
            title: const Text('Low stock items'),
            trailing: Text('${d?.lowStock ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            onTap: () async {
              await state.refreshLowStock();
              if (!context.mounted) return;
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LowStockScreen()));
            },
          ),
        ],
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 160,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
        ],
      ),
    );
  }
}
