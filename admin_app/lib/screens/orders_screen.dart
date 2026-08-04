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
      return Center(child: Text(state.error!, textAlign: TextAlign.center));
    }

    return RefreshIndicator(
      onRefresh: () => mode == OrdersMode.pending ? state.refreshPending() : state.refreshPaid(),
      child: orders.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('Koi bill nahi mili.')),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: orders.length + (mode == OrdersMode.paid ? 1 : 0),
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                if (mode == OrdersMode.paid && i == 0) {
                  return Card(
                    color: const Color(0xFFECFDF5),
                    child: ListTile(
                      title: const Text('Aaj ka total'),
                      trailing: Text(
                        '$cur ${money.format(state.paidTotal)}',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                    ),
                  );
                }
                final o = orders[mode == OrdersMode.paid ? i - 1 : i];
                return Card(
                  child: ListTile(
                    title: Text(o.orderNo, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(o.subtitle),
                    trailing: Text(
                      '$cur ${money.format(o.grandTotal)}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
