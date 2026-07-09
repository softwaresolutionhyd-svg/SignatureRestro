import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../providers/app_state.dart';
import '../services/session.dart';
import 'order_form_screen.dart';

class PendingScreen extends StatefulWidget {
  const PendingScreen({super.key});

  @override
  State<PendingScreen> createState() => _PendingScreenState();
}

class _PendingScreenState extends State<PendingScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppState>().refreshAll();
    });
  }

  Future<void> _openNewOrder() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const OrderFormScreen()),
    );
  }

  Future<void> _openEdit(PendingOrder order) async {
    if (!order.editable) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Yeh bill edit nahi ho sakti')),
      );
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => OrderFormScreen(orderId: order.id)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final session = context.watch<Session>();
    final currency = state.bootstrap?.currency ?? 'Rs.';
    final fmt = NumberFormat('#,##0.00');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Order Taker'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: state.loading ? null : () => state.refreshAll(),
            icon: const Icon(Icons.refresh),
          ),
          PopupMenuButton<String>(
            onSelected: (v) async {
              if (v == 'logout') {
                await session.logout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                enabled: false,
                child: Text(session.userName.isNotEmpty ? session.userName : session.userEmail),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(value: 'logout', child: Text('Logout')),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openNewOrder,
        icon: const Icon(Icons.add),
        label: const Text('Naya Order'),
      ),
      body: RefreshIndicator(
        onRefresh: () => state.refreshAll(),
        child: state.loading && state.pending.isEmpty
            ? ListView(children: const [SizedBox(height: 200), Center(child: CircularProgressIndicator())])
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
                children: [
                  if (state.error != null)
                    Card(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(state.error!),
                      ),
                    ),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.receipt_long),
                      title: const Text('POS Pending Bills'),
                      trailing: Chip(label: Text('${state.pending.length}')),
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (state.pending.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(32),
                      child: Center(child: Text('Koi pending bill nahi — naya order banayein.')),
                    )
                  else
                    ...state.pending.map((o) => _PendingCard(
                          order: o,
                          currency: currency,
                          fmt: fmt,
                          onTap: () => _openEdit(o),
                        )),
                ],
              ),
      ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  const _PendingCard({
    required this.order,
    required this.currency,
    required this.fmt,
    required this.onTap,
  });

  final PendingOrder order;
  final String currency;
  final NumberFormat fmt;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: order.editable ? onTap : null,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(order.orderNo, style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                  _TypeBadge(label: order.customerTypeLabel),
                  if (order.kitchenStatusLabel != null && order.kitchenStatusLabel!.isNotEmpty) ...[
                    const SizedBox(width: 6),
                    _KitchenStatusBadge(label: order.kitchenStatusLabel!),
                  ],
                  if (order.fromOrderTaker) ...[
                    const SizedBox(width: 6),
                    Chip(
                      visualDensity: VisualDensity.compact,
                      label: const Text('OT', style: TextStyle(fontSize: 11)),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 6),
              if (order.guestName != null && order.guestName!.isNotEmpty)
                Text('Guest: ${order.guestName}'),
              if (order.tableRoom != null && order.tableRoom!.isNotEmpty)
                Text('Table/Room: ${order.tableRoom}'),
              if (order.waiterName != null && order.waiterName!.isNotEmpty)
                Text('Waiter: ${order.waiterName}'),
              if (order.orderTime != null && order.orderTime!.isNotEmpty)
                Text('Order: ${order.orderTime}', style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
              if (order.serveAtLabel != null && order.serveAtLabel!.isNotEmpty)
                Text('Serve: ${order.serveAtLabel}', style: TextStyle(color: Colors.grey.shade700, fontSize: 13))
              else if (order.serveTime != null && order.serveTime!.isNotEmpty)
                Text('Serve: ${order.serveTime}', style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
              if (order.servedAt != null && order.servedAt!.isNotEmpty)
                Text('Served: ${order.servedAt}', style: TextStyle(color: Colors.green.shade700, fontSize: 13, fontWeight: FontWeight.w600)),
              Text('${order.itemsCount ?? order.items.length} items', style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
              const SizedBox(height: 10),
              Row(
                children: [
                  Text('$currency ${fmt.format(order.grandTotal)}',
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  const Spacer(),
                  if (order.editable)
                    FilledButton.tonalIcon(
                      onPressed: onTap,
                      icon: const Icon(Icons.add, size: 18),
                      label: const Text('Add items'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _KitchenStatusBadge extends StatelessWidget {
  const _KitchenStatusBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    Color bg = Colors.grey.shade300;
    if (label == 'Preparing') bg = Colors.orange.shade100;
    if (label == 'Complete') bg = Colors.green.shade100;
    if (label == 'Served') bg = Colors.blue.shade100;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
      child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    Color bg = Colors.grey.shade300;
    if (label == 'In-House') bg = Colors.blue.shade100;
    if (label == 'Mess Bill/Offices/Conf Room') bg = Colors.cyan.shade100;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
      child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}
