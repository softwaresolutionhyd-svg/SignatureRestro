import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import 'home_shell.dart';

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final d = state.dashboard;
    final money = NumberFormat('#,##0');
    final cur = d?.currency ?? 'Rs.';

    return AdminDarkScaffold(
      title: 'Reports',
      body: RefreshIndicator(
        onRefresh: () => state.refreshDashboard(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (state.error != null && d == null)
              Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
            _row('Today sales', '$cur ${money.format(d?.todaySales ?? 0)}'),
            _row('Today bills', '${d?.todayBills ?? 0}'),
            _row('Today expenses', '$cur ${money.format(d?.todayExpenses ?? 0)}'),
            const Divider(color: Colors.white12, height: 28),
            _row('Month sales', '$cur ${money.format(d?.monthSales ?? 0)}'),
            _row('Month expenses', '$cur ${money.format(d?.monthExpenses ?? 0)}'),
            _row('Pending bills', '${d?.pendingOrders ?? 0}'),
            _row('Low stock items', '${d?.lowStock ?? 0}'),
            const SizedBox(height: 16),
            Text(
              'Detailed PDF/Excel reports web Admin → Reports pe available hain.',
              style: TextStyle(color: Colors.white.withOpacity(0.45), fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: Colors.white70))),
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
        ],
      ),
    );
  }
}
