import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';

enum OrdersMode { pending, paid }

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({super.key, required this.mode});

  final OrdersMode mode;

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final orders = mode == OrdersMode.pending ? state.pending : state.paid;
    final money = NumberFormat('#,##0');
    final cur = state.dashboard?.currency ?? 'Rs.';

    if (state.loading && orders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && orders.isEmpty) {
      return Center(
        child: Text(state.error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.redAccent)),
      );
    }

    return RefreshIndicator(
      onRefresh: () => state.refreshBills(),
      child: orders.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('Koi bill nahi mili.', style: TextStyle(color: Colors.white54))),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: orders.length + (mode == OrdersMode.paid ? 1 : 0),
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                if (mode == OrdersMode.paid && i == 0) {
                  return Card(
                    color: const Color(0xFF0F3D36),
                    child: ListTile(
                      title: const Text('Aaj ka total', style: TextStyle(color: Colors.white70)),
                      trailing: Text(
                        '$cur ${money.format(state.paidTotal)}',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                    ),
                  );
                }
                final o = orders[mode == OrdersMode.paid ? i - 1 : i];
                return Card(
                  color: const Color(0xFF151C2C),
                  child: ListTile(
                    title: Text(o.orderNo, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    subtitle: Text(o.subtitle, style: const TextStyle(color: Colors.white60)),
                    trailing: Text(
                      '$cur ${money.format(o.grandTotal)}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
