import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import 'home_shell.dart';

class AnalyticsScreen extends StatelessWidget {
  const AnalyticsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final d = state.dashboard;
    final money = NumberFormat('#,##0');
    final cur = d?.currency ?? 'Rs.';

    return AdminDarkScaffold(
      title: 'Analytics',
      body: RefreshIndicator(
        onRefresh: () => state.refreshDashboard(),
        child: state.loading && d == null
            ? ListView(children: const [SizedBox(height: 120), Center(child: CircularProgressIndicator())])
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (state.error != null && d == null)
                    Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
                  _section('Today'),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _card('Sales', '$cur ${money.format(d?.todaySales ?? 0)}', const Color(0xFF7C3AED)),
                      _card('Bills', '${d?.todayBills ?? 0}', const Color(0xFF0D9488)),
                      _card('Expenses', '$cur ${money.format(d?.todayExpenses ?? 0)}', const Color(0xFFEA580C)),
                    ],
                  ),
                  const SizedBox(height: 20),
                  _section('This month'),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _card('Sales', '$cur ${money.format(d?.monthSales ?? 0)}', const Color(0xFF14B8A6)),
                      _card('Expenses', '$cur ${money.format(d?.monthExpenses ?? 0)}', const Color(0xFFB45309)),
                      _card('Pending', '${d?.pendingOrders ?? 0}', const Color(0xFF2563EB)),
                    ],
                  ),
                ],
              ),
      ),
    );
  }

  Widget _section(String t) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Text(t, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w600, fontSize: 15)),
      );

  Widget _card(String label, String value, Color color) {
    return Container(
      width: 150,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF151C2C),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withOpacity(0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
