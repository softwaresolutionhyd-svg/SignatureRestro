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
      return Center(child: Text(state.error!, textAlign: TextAlign.center));
    }

    return RefreshIndicator(
      onRefresh: () => state.refreshExpenses(),
      child: state.expenses.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('Koi expense nahi.')),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: state.expenses.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final e = state.expenses[i];
                return Card(
                  child: ListTile(
                    title: Text(e.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text([e.date, if ((e.status ?? '').isNotEmpty) e.status].join(' · ')),
                    trailing: Text(
                      '$cur ${money.format(e.amount)}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
