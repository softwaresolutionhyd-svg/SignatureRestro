import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';

class ExpensesScreen extends StatelessWidget {
  const ExpensesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final money = NumberFormat('#,##0');
    final cur = state.dashboard?.currency ?? 'Rs.';

    if (state.loading && state.expenses.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.expenses.isEmpty) {
      return Center(
        child: Text(state.error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.redAccent)),
      );
    }

    return RefreshIndicator(
      onRefresh: () => state.refreshExpenses(),
      child: state.expenses.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('Koi expense nahi.', style: TextStyle(color: Colors.white54))),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: state.expenses.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final e = state.expenses[i];
                return Card(
                  color: const Color(0xFF151C2C),
                  child: ListTile(
                    title: Text(e.title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    subtitle: Text(
                      [e.date, if ((e.status ?? '').isNotEmpty) e.status].join(' · '),
                      style: const TextStyle(color: Colors.white60),
                    ),
                    trailing: Text(
                      '$cur ${money.format(e.amount)}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
