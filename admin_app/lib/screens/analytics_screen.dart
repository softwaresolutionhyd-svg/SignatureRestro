import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../providers/app_state.dart';

/// Light Analytics Overview — live KPIs (same source as web Analytics).
class AnalyticsScreen extends StatelessWidget {
  const AnalyticsScreen({super.key});

  String _money(String cur, double v) {
    final f = NumberFormat('#,##0.##');
    return '$cur ${f.format(v)}';
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final a = state.analytics;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF1F5F9),
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0,
        title: const Text('Analytics Overview', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: state.loading ? null : () => state.refreshAnalytics(),
            icon: state.loading
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.refresh),
          ),
          IconButton(
            tooltip: 'Home',
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => state.refreshAnalytics(),
        child: state.loading && a == null
            ? ListView(children: const [SizedBox(height: 160), Center(child: CircularProgressIndicator())])
            : a == null
                ? ListView(
                    padding: const EdgeInsets.all(24),
                    children: [
                      Text(state.error ?? 'Data load nahi hui.', style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: () => state.refreshAnalytics(), child: const Text('Retry')),
                    ],
                  )
                : ListView(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                    children: [
                      Text(
                        'Real-time business snapshot — ${a.snapshotDate}',
                        style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                      ),
                      const SizedBox(height: 14),
                      _SessionCard(a: a, money: _money),
                      const SizedBox(height: 12),
                      _KpiCard(
                        label: 'Income This Month',
                        value: _money(a.currency, a.monthIncome),
                        sub:
                            'Restaurant sales · ${a.monthIncomeGrowthPct >= 0 ? '▲' : '▼'} ${a.monthIncomeGrowthPct.abs()}% vs last month',
                        color: const Color(0xFF7C3AED),
                        icon: Icons.payments_outlined,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Restaurant Income (month)',
                        value: _money(a.currency, a.restaurantProfit),
                        sub: 'Restaurant profit only (sales - discount - product cost)',
                        color: a.restaurantProfit >= 0 ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                        icon: Icons.trending_up,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Purchases This Month',
                        value: _money(a.currency, a.purchases),
                        sub: 'Confirmed & received POs',
                        color: const Color(0xFF2563EB),
                        icon: Icons.shopping_bag_outlined,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Expenses This Month',
                        value: _money(a.currency, a.expenses),
                        sub: 'Approved & paid expenses',
                        color: const Color(0xFFEA580C),
                        icon: Icons.receipt_long_outlined,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Outstanding Credit',
                        value: _money(a.currency, a.outstandingCredit),
                        sub: 'Total unpaid credit dues',
                        color: const Color(0xFFEF4444),
                        icon: Icons.assignment_late_outlined,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Active Employees',
                        value: '${a.activeEmployees}',
                        sub: 'Currently active staff',
                        color: const Color(0xFFEC4899),
                        icon: Icons.person_outline,
                      ),
                      const SizedBox(height: 10),
                      _KpiCard(
                        label: 'Total Products',
                        value: '${a.productsTotal}',
                        sub: '${a.outOfStock} out of stock · ${a.lowStock} low',
                        color: const Color(0xFF0D9488),
                        icon: Icons.inventory_2_outlined,
                      ),
                    ],
                  ),
      ),
    );
  }
}

class _SessionCard extends StatelessWidget {
  const _SessionCard({required this.a, required this.money});

  final AnalyticsOverview a;
  final String Function(String, double) money;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Current Session Sale', style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Text(
            money(a.currency, a.sessionSale),
            style: const TextStyle(color: Color(0xFF16A34A), fontSize: 28, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            [
              '${a.sessionPaidBills} paid bills',
              '${a.sessionPending} pending',
              if ((a.sessionNos ?? '').isNotEmpty) 'Session ${a.sessionNos}',
              if ((a.cashiers ?? '').isNotEmpty) 'Cashier: ${a.cashiers}',
              if (!a.sessionOpen) 'No open session',
            ].join(' · '),
            style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 16,
            runSpacing: 6,
            children: [
              _pay('Cash', money(a.currency, a.sessionCash)),
              _pay('Card', money(a.currency, a.sessionCard)),
              _pay('Bank', money(a.currency, a.sessionBank)),
            ],
          ),
          const Divider(height: 22),
          Text(
            "Today's sales: ${money(a.currency, a.todaySales)} · ${a.todayPaidBills} paid bills.",
            style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _pay(String k, String v) => Text.rich(
        TextSpan(children: [
          TextSpan(text: '$k ', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
          TextSpan(text: v, style: const TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.w700, fontSize: 13)),
        ]),
      );
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({
    required this.label,
    required this.value,
    required this.sub,
    required this.color,
    required this.icon,
  });

  final String label;
  final String value;
  final String sub;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10, offset: const Offset(0, 3))],
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(width: 4, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(4))),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(label, style: const TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w600, fontSize: 13)),
                      ),
                      Icon(icon, size: 18, color: color.withOpacity(0.85)),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(value, style: TextStyle(color: color, fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text(sub, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
