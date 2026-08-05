import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import 'home_shell.dart';
import 'orders_screen.dart';

class BillsScreen extends StatefulWidget {
  const BillsScreen({super.key});

  @override
  State<BillsScreen> createState() => _BillsScreenState();
}

class _BillsScreenState extends State<BillsScreen> {
  Timer? _liveSync;
  static const _pollEvery = Duration(seconds: 5);

  @override
  void initState() {
    super.initState();
    _liveSync = Timer.periodic(_pollEvery, (_) {
      if (!mounted) return;
      context.read<AppState>().refreshBills(silent: true);
    });
  }

  @override
  void dispose() {
    _liveSync?.cancel();
    super.dispose();
  }

  void _openOrders(BuildContext context, OrdersMode mode) {
    final title = mode == OrdersMode.pending ? 'Pending Bills' : 'Paid Bills';
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AdminDarkScaffold(
          title: title,
          body: OrdersScreen(mode: mode),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final money = NumberFormat('#,##0');
    final cur = state.dashboard?.currency ?? 'Rs.';

    final pendingTotal = state.pending.fold<double>(0, (sum, o) => sum + o.grandTotal);
    final paidTotal = state.paidTotal;
    final combinedTotal = pendingTotal + paidTotal;

    return AdminDarkScaffold(
      title: 'Pending / Paid Bills',
      body: RefreshIndicator(
        onRefresh: () => state.refreshBills(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          children: [
            if (state.error != null && state.pending.isEmpty && state.paid.isEmpty) ...[
              Text(state.error!, style: const TextStyle(color: Colors.redAccent)),
              const SizedBox(height: 12),
            ],
            _BillsSummaryCard(
              label: 'Pending Bills',
              countLabel: '${state.pending.length} bills',
              amount: '$cur ${money.format(pendingTotal)}',
              color: const Color(0xFFF59E0B),
              icon: Icons.hourglass_top_rounded,
              onTap: () => _openOrders(context, OrdersMode.pending),
            ),
            const SizedBox(height: 12),
            _BillsSummaryCard(
              label: 'Paid Bills',
              countLabel: '${state.paid.length} bills',
              amount: '$cur ${money.format(paidTotal)}',
              color: const Color(0xFF0D9488),
              icon: Icons.check_circle_outline,
              onTap: () => _openOrders(context, OrdersMode.paid),
            ),
            const SizedBox(height: 12),
            _BillsSummaryCard(
              label: 'Pending + Paid',
              countLabel: '${state.pending.length + state.paid.length} bills',
              amount: '$cur ${money.format(combinedTotal)}',
              color: const Color(0xFF6366F1),
              icon: Icons.account_balance_wallet_outlined,
            ),
          ],
        ),
      ),
    );
  }
}

class _BillsSummaryCard extends StatelessWidget {
  const _BillsSummaryCard({
    required this.label,
    required this.countLabel,
    required this.amount,
    required this.color,
    required this.icon,
    this.onTap,
  });

  final String label;
  final String countLabel;
  final String amount;
  final Color color;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final card = Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF151C2C),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.35)),
        boxShadow: [
          BoxShadow(color: color.withOpacity(0.12), blurRadius: 16, offset: const Offset(0, 6)),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: color.withOpacity(0.15),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 26),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
                const SizedBox(height: 2),
                Text(countLabel, style: const TextStyle(color: Colors.white54, fontSize: 13)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(amount, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 16)),
              if (onTap != null) ...[
                const SizedBox(height: 4),
                Icon(Icons.chevron_right, color: color.withOpacity(0.8), size: 20),
              ],
            ],
          ),
        ],
      ),
    );

    if (onTap == null) return card;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: card,
      ),
    );
  }
}
