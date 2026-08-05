import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';

class KitchenVoidsScreen extends StatelessWidget {
  const KitchenVoidsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    if (state.loading && state.voids.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.voids.isEmpty) {
      return Center(
        child: Text(state.error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.redAccent)),
      );
    }

    return RefreshIndicator(
      onRefresh: () => state.refreshVoids(),
      child: state.voids.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('Koi kitchen cancel nahi.', style: TextStyle(color: Colors.white54))),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: state.voids.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final v = state.voids[i];
                return Card(
                  color: const Color(0xFF151C2C),
                  child: ListTile(
                    title: Text(
                      '${v.qty}× ${v.item}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      [
                        if ((v.orderNo ?? '').isNotEmpty) 'Bill ${v.orderNo}',
                        v.reason,
                        '${v.by} · ${v.at}',
                      ].where((s) => s.trim().isNotEmpty).join('\n'),
                      style: const TextStyle(color: Colors.white60),
                    ),
                    isThreeLine: true,
                  ),
                );
              },
            ),
    );
  }
}
