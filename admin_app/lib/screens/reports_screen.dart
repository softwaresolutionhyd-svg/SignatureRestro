import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../providers/app_state.dart';
import 'home_shell.dart';

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  String _money(String cur, double v) {
    final f = NumberFormat('#,##0.##');
    return '$cur ${f.format(v)}';
  }

  void _showWebHint(BuildContext context, String report) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Full $report report web Admin → Reports pe available hai.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final r = state.reports;

    return AdminDarkScaffold(
      title: 'Reports',
      body: RefreshIndicator(
        onRefresh: () => state.refreshReports(),
        child: state.loading && r == null
            ? ListView(children: const [SizedBox(height: 120), Center(child: CircularProgressIndicator())])
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                children: [
                  const Text(
                    'Business overview & analytics',
                    style: TextStyle(color: Colors.white54, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  if (state.error != null && r == null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
                    ),
                  if (r != null) ...[
                    _ReportKpiCard(
                      label: 'Total Sales (All Time)',
                      value: _money(r.currency, r.totalSales),
                      valueColor: const Color(0xFF7C3AED),
                      linkLabel: 'View Sales',
                      onTap: () => _showWebHint(context, 'Sales'),
                    ),
                    const SizedBox(height: 12),
                    _ReportKpiCard(
                      label: 'Total Purchases (All Time)',
                      value: _money(r.currency, r.totalPurchases),
                      valueColor: const Color(0xFF22C55E),
                      linkLabel: 'View Purchases',
                      onTap: () => _showWebHint(context, 'Purchases'),
                    ),
                    const SizedBox(height: 12),
                    _ReportKpiCard(
                      label: 'Total Expenses (All Time)',
                      value: _money(r.currency, r.totalExpenses),
                      valueColor: const Color(0xFFF97316),
                      linkLabel: 'View Expenses',
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const ExpensesPage()),
                        );
                      },
                    ),
                    const SizedBox(height: 12),
                    _ReportKpiCard(
                      label: 'Active Products',
                      value: '${r.activeProducts}',
                      valueColor: const Color(0xFF0EA5E9),
                      linkLabel: 'View Inventory',
                      onTap: () => _showWebHint(context, 'Inventory'),
                    ),
                    const SizedBox(height: 12),
                    _ReportKpiCard(
                      label: 'Active Employees',
                      value: '${r.activeEmployees}',
                      valueColor: const Color(0xFFEC4899),
                      linkLabel: 'View Employees',
                      onTap: () => _showWebHint(context, 'Employees'),
                    ),
                  ],
                ],
              ),
      ),
    );
  }
}

class _ReportKpiCard extends StatelessWidget {
  const _ReportKpiCard({
    required this.label,
    required this.value,
    required this.valueColor,
    required this.linkLabel,
    required this.onTap,
  });

  final String label;
  final String value;
  final Color valueColor;
  final String linkLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFF151C2C),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withOpacity(0.06)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.2),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: Colors.white54, fontSize: 13)),
              const SizedBox(height: 8),
              Text(
                value,
                style: TextStyle(color: valueColor, fontWeight: FontWeight.w800, fontSize: 26, height: 1.1),
              ),
              const SizedBox(height: 10),
              Text(
                '$linkLabel →',
                style: TextStyle(color: valueColor.withOpacity(0.9), fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
