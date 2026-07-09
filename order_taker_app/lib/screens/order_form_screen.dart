import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../providers/app_state.dart';
import '../services/api_client.dart';
import '../widgets/qty_stepper.dart';

class OrderFormScreen extends StatefulWidget {
  const OrderFormScreen({super.key, this.orderId});

  final int? orderId;

  @override
  State<OrderFormScreen> createState() => _OrderFormScreenState();
}

class _OrderFormScreenState extends State<OrderFormScreen> {
  String _customerType = 'mess_use';
  String _guestName = '';
  String _roomNo = '';
  String _waiterName = '';
  String _serveTime = '';
  String _serveDate = '';
  String _serveMeal = '';
  int? _tableId;
  List<CartLine> _cart = [];
  String _productQuery = '';
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  bool _editMode = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final state = context.read<AppState>();
      if (state.bootstrap == null) {
        await state.refreshAll();
      }
      if (widget.orderId == null && _serveDate.isEmpty) {
        _serveDate = DateFormat('yyyy-MM-dd').format(DateTime.now());
      }
      if (widget.orderId != null) {
        final detail = await state.loadOrder(widget.orderId!);
        _editMode = true;
        _customerType = detail.customerType;
        _guestName = detail.guestName ?? '';
        _roomNo = detail.roomNo ?? '';
        _waiterName = detail.waiterName ?? '';
        _serveTime = detail.serveTime ?? '';
        _serveDate = detail.serveDate ?? DateFormat('yyyy-MM-dd').format(DateTime.now());
        _serveMeal = detail.serveMeal ?? '';
        _tableId = detail.tableId;
        _cart = List.from(detail.cart);
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  BootstrapData? get _boot => context.read<AppState>().bootstrap;

  List<Product> get _filteredProducts {
    final boot = _boot;
    if (boot == null) return [];
    final q = _productQuery.trim().toLowerCase();
    return boot.products.where((p) {
      if (!p.allowedForCustomerType(_customerType)) return false;
      if (q.isEmpty) return true;
      return p.name.toLowerCase().contains(q) || p.sku.toLowerCase().contains(q);
    }).take(80).toList();
  }

  double get _grandTotal => _cart.fold(0, (sum, line) => sum + line.lineTotal);

  void _addProduct(Product product) {
    final defaultUom = product.uoms.isNotEmpty ? product.uoms.first.uom : product.baseUom;
    final factor = product.uoms.isNotEmpty ? product.uoms.first.factor : 1.0;
    final unitPrice = product.price * factor;

    final existing = _cart.indexWhere(
      (c) => c.productId == product.id && c.uom == defaultUom && !c.isLocked,
    );
    setState(() {
      if (existing >= 0) {
        _cart[existing].qty += 1;
      } else {
        _cart.add(CartLine(
          productId: product.id,
          name: product.name,
          uom: defaultUom,
          qty: 1,
          unitPrice: unitPrice,
        ));
      }
    });
  }

  void _removeLine(int index) {
    if (_cart[index].isLocked) return;
    setState(() => _cart.removeAt(index));
  }

  Future<void> _submit() async {
    if (_cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Kam az kam aik product add karein')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final msg = await context.read<AppState>().submitOrder(
            orderId: widget.orderId,
            customerType: _customerType,
            guestName: _guestName,
            roomNo: _roomNo,
            waiterName: _waiterName,
            serveTime: _serveTime,
            serveDate: _serveDate,
            serveMeal: _serveMeal,
            tableId: _tableId,
            cart: _cart,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final boot = _boot;
    final currency = boot?.currency ?? 'Rs.';
    final fmt = NumberFormat('#,##0.00');

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.orderId == null ? 'Naya Order' : 'Add Items'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : Column(
                  children: [
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          if (!_editMode) ...[
                            _CustomerTypeSection(
                              customerType: _customerType,
                              types: boot?.customerTypes ?? [],
                              onChanged: (v) => setState(() {
                                _customerType = v;
                                _cart.removeWhere((c) => !c.isLocked);
                              }),
                            ),
                            const SizedBox(height: 12),
                            _GuestFields(
                              customerType: _customerType,
                              guestName: _guestName,
                              roomNo: _roomNo,
                              waiterName: _waiterName,
                              serveTime: _serveTime,
                              serveDate: _serveDate,
                              serveMeal: _serveMeal,
                              tableId: _tableId,
                              boot: boot,
                              onGuestName: (v) => setState(() => _guestName = v),
                              onRoomNo: (v) => setState(() => _roomNo = v),
                              onWaiterName: (v) => setState(() => _waiterName = v),
                              onServeTime: (v) => setState(() => _serveTime = v),
                              onServeDate: (v) => setState(() => _serveDate = v),
                              onServeMeal: (v) => setState(() => _serveMeal = v),
                              onTableId: (v) => setState(() => _tableId = v),
                            ),
                            const SizedBox(height: 16),
                          ] else
                            Card(
                              child: ListTile(
                                title: Text('Order #${widget.orderId}'),
                                subtitle: Text('Guest meta locked — sirf items add/change'),
                              ),
                            ),
                          TextField(
                            decoration: const InputDecoration(
                              labelText: 'Product search',
                              prefixIcon: Icon(Icons.search),
                            ),
                            onChanged: (v) => setState(() => _productQuery = v),
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 120,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: _filteredProducts.length,
                              separatorBuilder: (_, __) => const SizedBox(width: 8),
                              itemBuilder: (context, i) {
                                final p = _filteredProducts[i];
                                return ActionChip(
                                  label: SizedBox(
                                    width: 110,
                                    child: Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis),
                                  ),
                                  onPressed: () => _addProduct(p),
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text('Cart', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          if (_cart.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 24),
                              child: Center(child: Text('Cart khali hai')),
                            )
                          else
                            ...List.generate(_cart.length, (i) {
                              final line = _cart[i];
                              return Card(
                                margin: const EdgeInsets.only(bottom: 8),
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Text(line.name, style: const TextStyle(fontWeight: FontWeight.bold)),
                                          ),
                                          if (line.isLocked)
                                            Chip(
                                              label: const Text('Served', style: TextStyle(fontSize: 11)),
                                              backgroundColor: Colors.green.shade100,
                                              visualDensity: VisualDensity.compact,
                                            )
                                          else
                                            IconButton(
                                              icon: const Icon(Icons.delete_outline, color: Colors.red),
                                              onPressed: () => _removeLine(i),
                                            ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Row(
                                        children: [
                                          if (!line.isLocked)
                                            QtyStepper(
                                              qty: line.qty,
                                              onChanged: (v) => setState(() => line.qty = v),
                                            )
                                          else
                                            Text('Qty: ${line.qty}'),
                                          const Spacer(),
                                          Text('$currency ${fmt.format(line.lineTotal)}'),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      TextFormField(
                                        key: ValueKey('notes-$i-${line.productId}'),
                                        enabled: !line.isLocked,
                                        decoration: const InputDecoration(labelText: 'Notes'),
                                        initialValue: line.notes,
                                        onChanged: (v) => line.notes = v,
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            }),
                        ],
                      ),
                    ),
                    SafeArea(
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surface,
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 8, offset: const Offset(0, -2))],
                        ),
                        child: Row(
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text('Total'),
                                Text(
                                  '$currency ${fmt.format(_grandTotal)}',
                                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            const Spacer(),
                            FilledButton.icon(
                              onPressed: _submitting ? null : _submit,
                              icon: _submitting
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Icon(Icons.send),
                              label: Text(_submitting ? 'Saving...' : 'Send to Kitchen & POS'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _CustomerTypeSection extends StatelessWidget {
  const _CustomerTypeSection({
    required this.customerType,
    required this.types,
    required this.onChanged,
  });

  final String customerType;
  final List<Map<String, String>> types;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final options = types.isNotEmpty
        ? types
        : [
            {'key': 'mess_use', 'label': 'Walk-In'},
            {'key': 'booking', 'label': 'In-House'},
            {'key': 'ast_offr', 'label': 'Mess Bill/Offices/Conf Room'},
          ];

    return SegmentedButton<String>(
      segments: options
          .map((t) => ButtonSegment(
                value: t['key']!,
                label: Text(t['label']!, style: const TextStyle(fontSize: 12)),
              ))
          .toList(),
      selected: {customerType},
      onSelectionChanged: (s) => onChanged(s.first),
    );
  }
}

class _GuestFields extends StatelessWidget {
  const _GuestFields({
    required this.customerType,
    required this.guestName,
    required this.roomNo,
    required this.waiterName,
    required this.serveTime,
    required this.serveDate,
    required this.serveMeal,
    required this.tableId,
    required this.boot,
    required this.onGuestName,
    required this.onRoomNo,
    required this.onWaiterName,
    required this.onServeTime,
    required this.onServeDate,
    required this.onServeMeal,
    required this.onTableId,
  });

  final String customerType;
  final String guestName;
  final String roomNo;
  final String waiterName;
  final String serveTime;
  final String serveDate;
  final String serveMeal;
  final int? tableId;
  final BootstrapData? boot;
  final ValueChanged<String> onGuestName;
  final ValueChanged<String> onRoomNo;
  final ValueChanged<String> onWaiterName;
  final ValueChanged<String> onServeTime;
  final ValueChanged<String> onServeDate;
  final ValueChanged<String> onServeMeal;
  final ValueChanged<int?> onTableId;

  String _localDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  void _applyServeMeal(String mealKey) {
    final meals = boot?.serveMeals ?? [];
    ServeMealOption? meal;
    for (final m in meals) {
      if (m.key == mealKey) {
        meal = m;
        break;
      }
    }
    if (meal == null) return;

    final now = DateTime.now();
    final parts = meal.time.split(':');
    final hour = int.tryParse(parts[0]) ?? 8;
    final minute = int.tryParse(parts.length > 1 ? parts[1] : '0') ?? 0;
    var slot = DateTime(now.year, now.month, now.day, hour, minute);
    if (!now.isBefore(slot)) {
      slot = slot.add(const Duration(days: 1));
    }

    onServeDate(_localDate(slot));
    onServeTime(meal.time);
  }

  Future<void> _pickDate(BuildContext context) async {
    final initial = DateTime.tryParse(serveDate) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );
    if (picked != null) {
      onServeDate(_localDate(picked));
    }
  }

  Future<void> _pickTime(BuildContext context) async {
    final parts = serveTime.split(':');
    final initial = TimeOfDay(
      hour: parts.length >= 2 ? int.tryParse(parts[0]) ?? 8 : 8,
      minute: parts.length >= 2 ? int.tryParse(parts[1]) ?? 0 : 0,
    );
    final picked = await showTimePicker(context: context, initialTime: initial);
    if (picked != null) {
      onServeTime('${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}');
    }
  }

  Widget _serveScheduleSection(BuildContext context) {
    final meals = boot?.serveMeals ?? const <ServeMealOption>[];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        DropdownButtonFormField<String>(
          value: serveMeal.isEmpty ? null : serveMeal,
          decoration: const InputDecoration(labelText: 'Meal / Serve for'),
          items: [
            const DropdownMenuItem(value: null, child: Text('— Meal choose karein —')),
            ...meals.map(
              (m) => DropdownMenuItem(value: m.key, child: Text(m.label)),
            ),
          ],
          onChanged: (value) {
            if (value == null || value.isEmpty) {
              onServeMeal('');
              return;
            }
            onServeMeal(value);
            _applyServeMeal(value);
          },
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: InkWell(
                onTap: () => _pickDate(context),
                child: InputDecorator(
                  decoration: const InputDecoration(labelText: 'Serve date'),
                  child: Text(serveDate.isEmpty ? '—' : serveDate),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: InkWell(
                onTap: () => _pickTime(context),
                child: InputDecorator(
                  decoration: const InputDecoration(labelText: 'Serve time'),
                  child: Text(serveTime.isEmpty ? '—' : serveTime),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          'Meal select karte hi agla serve slot auto set ho jata hai — date/time change bhi kar sakte hain.',
          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    if (customerType == 'booking') {
      final rooms = boot?.checkedInRooms ?? [];
      return DropdownButtonFormField<String>(
        value: roomNo.isEmpty ? null : roomNo,
        decoration: const InputDecoration(labelText: 'Checked-in Room'),
        items: rooms
            .map((r) => DropdownMenuItem(
                  value: r.roomNo,
                  child: Text('${r.roomNo} — ${r.guestName}'),
                ))
            .toList(),
        onChanged: (v) => onRoomNo(v ?? ''),
      );
    }

    if (customerType == 'ast_offr') {
      return TextFormField(
        initialValue: guestName,
        decoration: const InputDecoration(labelText: 'Officer / Guest Name'),
        onChanged: onGuestName,
      );
    }

    return Column(
      children: [
        TextFormField(
          initialValue: guestName,
          decoration: const InputDecoration(labelText: 'Guest Name'),
          onChanged: onGuestName,
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          value: waiterName.isEmpty ? null : waiterName,
          decoration: const InputDecoration(labelText: 'Waiter'),
          items: (boot?.waiters ?? [])
              .map((w) => DropdownMenuItem(value: w.name, child: Text(w.name)))
              .toList(),
          onChanged: (v) => onWaiterName(v ?? ''),
        ),
        if (boot?.tablesEnabled == true) ...[
          const SizedBox(height: 10),
          DropdownButtonFormField<int>(
            value: tableId,
            decoration: const InputDecoration(labelText: 'Table (optional)'),
            items: [
              const DropdownMenuItem<int>(value: null, child: Text('— None —')),
              ...(boot?.tables ?? [])
                  .map((t) => DropdownMenuItem(value: t.id, child: Text(t.name))),
            ],
            onChanged: onTableId,
          ),
        ],
        const SizedBox(height: 12),
        _serveScheduleSection(context),
      ],
    );
  }
}
