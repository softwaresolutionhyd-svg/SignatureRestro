class ProductUom {
  ProductUom({required this.uom, required this.label, required this.factor});

  final String uom;
  final String label;
  final double factor;

  factory ProductUom.fromJson(Map<String, dynamic> json) {
    return ProductUom(
      uom: json['uom']?.toString() ?? '',
      label: json['label']?.toString() ?? json['uom']?.toString() ?? '',
      factor: _toDouble(json['factor']),
    );
  }
}

class Product {
  Product({
    required this.id,
    required this.name,
    required this.sku,
    required this.baseUom,
    required this.price,
    required this.forPos,
    required this.forPurchase,
    required this.uoms,
  });

  final int id;
  final String name;
  final String sku;
  final String baseUom;
  final double price;
  final bool forPos;
  final bool forPurchase;
  final List<ProductUom> uoms;

  bool allowedForCustomerType(String customerType) {
    if (customerType == 'mess_use' || customerType == 'booking') {
      return forPos;
    }
    return true;
  }

  factory Product.fromJson(Map<String, dynamic> json) {
    final uomsRaw = json['uoms'];
    final uoms = uomsRaw is List
        ? uomsRaw
            .whereType<Map>()
            .map((e) => ProductUom.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <ProductUom>[];

    return Product(
      id: _toInt(json['id']),
      name: json['name']?.toString() ?? '',
      sku: json['sku']?.toString() ?? '',
      baseUom: json['base_uom']?.toString() ?? '',
      price: _toDouble(json['price']),
      forPos: json['for_pos'] == true,
      forPurchase: json['for_purchase'] == true,
      uoms: uoms,
    );
  }
}

class NamedOption {
  NamedOption({required this.id, required this.name});

  final int id;
  final String name;

  factory NamedOption.fromJson(Map<String, dynamic> json) {
    return NamedOption(
      id: _toInt(json['id']),
      name: json['name']?.toString() ?? '',
    );
  }
}

class CheckedInRoom {
  CheckedInRoom({required this.roomNo, required this.guestName});

  final String roomNo;
  final String guestName;

  factory CheckedInRoom.fromJson(Map<String, dynamic> json) {
    return CheckedInRoom(
      roomNo: json['room_no']?.toString() ?? '',
      guestName: json['guest_name']?.toString() ?? '',
    );
  }
}

class ServeMealOption {
  ServeMealOption({required this.key, required this.label, required this.time});

  final String key;
  final String label;
  final String time;

  factory ServeMealOption.fromJson(Map<String, dynamic> json) {
    return ServeMealOption(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      time: json['time']?.toString() ?? '08:00',
    );
  }
}

class BootstrapData {
  BootstrapData({
    required this.currency,
    required this.tablesEnabled,
    required this.products,
    required this.tables,
    required this.waiters,
    required this.checkedInRooms,
    required this.customerTypes,
    required this.serveMeals,
  });

  final String currency;
  final bool tablesEnabled;
  final List<Product> products;
  final List<NamedOption> tables;
  final List<NamedOption> waiters;
  final List<CheckedInRoom> checkedInRooms;
  final List<Map<String, String>> customerTypes;
  final List<ServeMealOption> serveMeals;

  factory BootstrapData.fromJson(Map<String, dynamic> json) {
    List<T> mapList<T>(dynamic raw, T Function(Map<String, dynamic>) fn) {
      if (raw is! List) return [];
      return raw
          .whereType<Map>()
          .map((e) => fn(Map<String, dynamic>.from(e)))
          .toList();
    }

    final typesRaw = json['customer_types'];
    final types = typesRaw is List
        ? typesRaw.whereType<Map>().map((e) {
            final m = Map<String, dynamic>.from(e);
            return {
              'key': m['key']?.toString() ?? '',
              'label': m['label']?.toString() ?? '',
            };
          }).toList()
        : <Map<String, String>>[];

    return BootstrapData(
      currency: json['currency']?.toString() ?? 'Rs.',
      tablesEnabled: json['tables_enabled'] == true,
      products: mapList(json['products'], Product.fromJson),
      tables: mapList(json['tables'], NamedOption.fromJson),
      waiters: mapList(json['waiters'], NamedOption.fromJson),
      checkedInRooms: mapList(json['checked_in_rooms'], CheckedInRoom.fromJson),
      customerTypes: types,
      serveMeals: mapList(json['serve_meals'], ServeMealOption.fromJson),
    );
  }
}

class CartLine {
  CartLine({
    required this.productId,
    required this.name,
    required this.uom,
    required this.qty,
    required this.unitPrice,
    this.notes = '',
    this.kitchenServed = false,
    this.kitchenPending = true,
  });

  final int productId;
  final String name;
  String uom;
  double qty;
  double unitPrice;
  String notes;
  final bool kitchenServed;
  final bool kitchenPending;

  double get lineTotal => qty * unitPrice;

  bool get isLocked => kitchenServed;

  Map<String, dynamic> toPayload() => {
        'product_id': productId,
        'uom': uom,
        'qty': qty,
        'notes': notes.trim().isEmpty ? null : notes.trim(),
      };

  factory CartLine.fromJson(Map<String, dynamic> json) {
    return CartLine(
      productId: _toInt(json['product_id']),
      name: json['name']?.toString() ?? '',
      uom: json['uom']?.toString() ?? '',
      qty: _toDouble(json['qty']),
      unitPrice: _toDouble(json['unit_price']),
      notes: json['notes']?.toString() ?? '',
      kitchenServed: json['kitchen_served'] == true,
      kitchenPending: json['kitchen_pending'] != false,
    );
  }
}

class PendingOrder {
  PendingOrder({
    required this.id,
    required this.orderNo,
    required this.fromOrderTaker,
    required this.customerType,
    required this.customerTypeLabel,
    required this.guestName,
    required this.tableRoom,
    required this.waiterName,
    required this.grandTotal,
    required this.items,
    required this.editable,
    this.itemsCount,
    this.serveTime,
    this.serveDate,
    this.serveMeal,
    this.serveAtLabel,
    this.orderTime,
    this.servedAt,
    this.kitchenStatusLabel,
    this.kitchenStatusBadge,
  });

  final int id;
  final String orderNo;
  final bool fromOrderTaker;
  final String customerType;
  final String customerTypeLabel;
  final String? guestName;
  final String? tableRoom;
  final String? waiterName;
  final double grandTotal;
  final List<Map<String, dynamic>> items;
  final bool editable;
  final int? itemsCount;
  final String? serveTime;
  final String? serveDate;
  final String? serveMeal;
  final String? serveAtLabel;
  final String? orderTime;
  final String? servedAt;
  final String? kitchenStatusLabel;
  final String? kitchenStatusBadge;

  factory PendingOrder.fromJson(Map<String, dynamic> json) {
    final itemsRaw = json['items'];
    final items = itemsRaw is List
        ? itemsRaw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
        : <Map<String, dynamic>>[];

    return PendingOrder(
      id: _toInt(json['id']),
      orderNo: json['order_no']?.toString() ?? '',
      fromOrderTaker: json['from_order_taker'] == true,
      customerType: json['customer_type']?.toString() ?? 'mess_use',
      customerTypeLabel: json['customer_type_label']?.toString() ?? '',
      guestName: json['guest_name']?.toString(),
      tableRoom: json['table_room']?.toString(),
      waiterName: json['waiter_name']?.toString(),
      grandTotal: _toDouble(json['grand_total']),
      items: items,
      editable: json['editable'] == true,
      itemsCount: json['items_count'] == null ? items.length : _toInt(json['items_count']),
      serveTime: json['serve_time']?.toString(),
      serveDate: json['serve_date']?.toString(),
      serveMeal: json['serve_meal']?.toString(),
      serveAtLabel: json['serve_at_label']?.toString(),
      orderTime: json['order_time']?.toString(),
      servedAt: json['served_at']?.toString(),
      kitchenStatusLabel: json['kitchen_status_label']?.toString(),
      kitchenStatusBadge: json['kitchen_status_badge']?.toString(),
    );
  }
}

class OrderDetail extends PendingOrder {
  OrderDetail({
    required super.id,
    required super.orderNo,
    required super.fromOrderTaker,
    required super.customerType,
    required super.customerTypeLabel,
    required super.guestName,
    required super.tableRoom,
    required super.waiterName,
    required super.grandTotal,
    required super.items,
    required super.editable,
    super.serveTime,
    super.serveDate,
    super.serveMeal,
    super.serveAtLabel,
    super.orderTime,
    super.servedAt,
    super.kitchenStatusLabel,
    super.kitchenStatusBadge,
    required this.tableId,
    required this.roomNo,
    required this.subtotal,
    required this.taxTotal,
    required this.cart,
  });

  final int? tableId;
  final String? roomNo;
  final double subtotal;
  final double taxTotal;
  final List<CartLine> cart;

  factory OrderDetail.fromJson(Map<String, dynamic> json) {
    final summary = PendingOrder.fromJson(json);
    final cartRaw = json['cart'];
    final cart = cartRaw is List
        ? cartRaw
            .whereType<Map>()
            .map((e) => CartLine.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <CartLine>[];

    return OrderDetail(
      id: summary.id,
      orderNo: summary.orderNo,
      fromOrderTaker: summary.fromOrderTaker,
      customerType: summary.customerType,
      customerTypeLabel: summary.customerTypeLabel,
      guestName: summary.guestName,
      tableRoom: summary.tableRoom,
      waiterName: summary.waiterName,
      grandTotal: summary.grandTotal,
      items: summary.items,
      editable: summary.editable,
      itemsCount: summary.itemsCount,
      serveTime: summary.serveTime,
      serveDate: summary.serveDate,
      serveMeal: summary.serveMeal,
      serveAtLabel: summary.serveAtLabel,
      orderTime: summary.orderTime,
      servedAt: summary.servedAt,
      kitchenStatusLabel: summary.kitchenStatusLabel,
      kitchenStatusBadge: summary.kitchenStatusBadge,
      tableId: json['table_id'] == null ? null : _toInt(json['table_id']),
      roomNo: json['room_no']?.toString(),
      subtotal: _toDouble(json['subtotal']),
      taxTotal: _toDouble(json['tax_total']),
      cart: cart,
    );
  }
}

int _toInt(dynamic v) {
  if (v is int) return v;
  return int.tryParse(v?.toString() ?? '') ?? 0;
}

double _toDouble(dynamic v) {
  if (v is num) return v.toDouble();
  return double.tryParse(v?.toString() ?? '') ?? 0;
}
