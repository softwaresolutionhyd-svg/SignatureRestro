import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';

class LowStockScreen extends StatelessWidget {
  const LowStockScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    return Scaffold(
      appBar: AppBar(title: const Text('Low stock')),
      body: RefreshIndicator(
        onRefresh: () => state.refreshLowStock(),
        child: state.loading && state.lowStock.isEmpty
            ? ListView(children: const [SizedBox(height: 120), Center(child: CircularProgressIndicator())])
            : state.lowStock.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(child: Text('Low stock nahi.')),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(12),
                    itemCount: state.lowStock.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, i) {
                      final p = state.lowStock[i];
                      return Card(
                        child: ListTile(
                          title: Text(p.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                          subtitle: Text('Reorder ≤ ${p.reorderLevel} ${p.uom ?? ''}'),
                          trailing: Text(
                            '${p.qty}',
                            style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.orange),
                          ),
                        ),
                      );
                    },
                  ),
      ),
    );
  }
}
