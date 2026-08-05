class DashboardData {
  DashboardData({
    required this.currency,
    required this.todaySales,
    required this.todayBills,
    required this.todayExpenses,
    required this.monthSales,
    required this.monthExpenses,
    required this.pendingOrders,
    required this.lowStock,
    this.sessionOpenedAt,
  });

  final String currency;
  final double todaySales;
  final int todayBills;
  final double todayExpenses;
  final double monthSales;
  final double monthExpenses;
  final int pendingOrders;
  final int lowStock;
  final String? sessionOpenedAt;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final today = Map<String, dynamic>.from(json['today'] as Map? ?? {});
    final month = Map<String, dynamic>.from(json['month'] as Map? ?? {});
    final session = json['session'];
    return DashboardData(
      currency: json['currency']?.toString() ?? 'Rs.',
      todaySales: _d(today['sales']),
      todayBills: _i(today['bills']),
      todayExpenses: _d(today['expenses']),
      monthSales: _d(month['sales']),
      monthExpenses: _d(month['expenses']),
      pendingOrders: _i(json['pending_orders']),
      lowStock: _i(json['low_stock']),
      sessionOpenedAt: session is Map ? session['opened_at']?.toString() : null,
    );
  }
}

class AdminOrder {
  AdminOrder({
    required this.id,
    required this.orderNo,
    required this.status,
    required this.grandTotal,
    this.table,
    this.guestName,
    this.serviceType,
    this.time,
    this.itemsQty,
  });

  final int id;
  final String orderNo;
  final String status;
  final double grandTotal;
  final String? table;
  final String? guestName;
  final String? serviceType;
  final String? time;
  final double? itemsQty;

  factory AdminOrder.fromJson(Map<String, dynamic> json) {
    return AdminOrder(
      id: _i(json['id']),
      orderNo: json['order_no']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      grandTotal: _d(json['grand_total']),
      table: json['table']?.toString(),
      guestName: json['guest_name']?.toString(),
      serviceType: json['service_type']?.toString(),
      time: json['time']?.toString(),
      itemsQty: json['items_qty'] == null ? null : _d(json['items_qty']),
    );
  }

  String get subtitle {
    final parts = <String>[];
    if ((table ?? '').isNotEmpty) parts.add(table!);
    if ((guestName ?? '').isNotEmpty) parts.add(guestName!);
    if ((serviceType ?? '').isNotEmpty) parts.add(serviceType!);
    if ((time ?? '').isNotEmpty) parts.add(time!);
    return parts.join(' · ');
  }
}

class KitchenVoidItem {
  KitchenVoidItem({
    required this.id,
    required this.item,
    required this.qty,
    required this.reason,
    required this.by,
    required this.at,
    this.orderNo,
  });

  final int id;
  final String item;
  final double qty;
  final String reason;
  final String by;
  final String at;
  final String? orderNo;

  factory KitchenVoidItem.fromJson(Map<String, dynamic> json) {
    return KitchenVoidItem(
      id: _i(json['id']),
      item: json['item']?.toString() ?? 'Item',
      qty: _d(json['qty']),
      reason: json['reason']?.toString() ?? '',
      by: json['by']?.toString() ?? '—',
      at: json['at']?.toString() ?? '',
      orderNo: json['order_no']?.toString(),
    );
  }
}

class ExpenseItem {
  ExpenseItem({
    required this.id,
    required this.title,
    required this.amount,
    required this.date,
    this.status,
  });

  final int id;
  final String title;
  final double amount;
  final String date;
  final String? status;

  factory ExpenseItem.fromJson(Map<String, dynamic> json) {
    return ExpenseItem(
      id: _i(json['id']),
      title: json['title']?.toString() ?? 'Expense',
      amount: _d(json['amount']),
      date: json['date']?.toString() ?? '',
      status: json['status']?.toString(),
    );
  }
}

class LowStockItem {
  LowStockItem({
    required this.id,
    required this.name,
    required this.qty,
    required this.reorderLevel,
    this.uom,
    this.sku,
  });

  final int id;
  final String name;
  final double qty;
  final double reorderLevel;
  final String? uom;
  final String? sku;

  factory LowStockItem.fromJson(Map<String, dynamic> json) {
    return LowStockItem(
      id: _i(json['id']),
      name: json['name']?.toString() ?? '',
      qty: _d(json['qty']),
      reorderLevel: _d(json['reorder_level']),
      uom: json['uom']?.toString(),
      sku: json['sku']?.toString(),
    );
  }
}

class AttendanceRow {
  AttendanceRow({
    required this.id,
    required this.name,
    required this.status,
    this.employeeNo,
    this.clockIn,
    this.clockOut,
  });

  final int id;
  final String name;
  final String status;
  final String? employeeNo;
  final String? clockIn;
  final String? clockOut;

  factory AttendanceRow.fromJson(Map<String, dynamic> json) {
    return AttendanceRow(
      id: _i(json['id']),
      name: json['name']?.toString() ?? '',
      status: json['status']?.toString() ?? 'absent',
      employeeNo: json['employee_no']?.toString(),
      clockIn: json['clock_in']?.toString(),
      clockOut: json['clock_out']?.toString(),
    );
  }

  bool get isPresent {
    final s = status.toLowerCase();
    return s == 'present' || s == 'p' || s == 'holiday' || s == 'h' || s == 'half';
  }
}

class AnalyticsOverview {
  AnalyticsOverview({
    required this.currency,
    required this.snapshotDate,
    required this.sessionOpen,
    required this.sessionSale,
    required this.sessionPaidBills,
    required this.sessionPending,
    required this.sessionCash,
    required this.sessionCard,
    required this.sessionBank,
    required this.todaySales,
    required this.todayPaidBills,
    required this.monthIncome,
    required this.monthIncomeGrowthPct,
    required this.restaurantProfit,
    required this.purchases,
    required this.expenses,
    required this.outstandingCredit,
    required this.activeEmployees,
    required this.productsTotal,
    required this.outOfStock,
    required this.lowStock,
    this.sessionNos,
    this.cashiers,
  });

  final String currency;
  final String snapshotDate;
  final bool sessionOpen;
  final double sessionSale;
  final int sessionPaidBills;
  final int sessionPending;
  final double sessionCash;
  final double sessionCard;
  final double sessionBank;
  final String? sessionNos;
  final String? cashiers;
  final double todaySales;
  final int todayPaidBills;
  final double monthIncome;
  final double monthIncomeGrowthPct;
  final double restaurantProfit;
  final double purchases;
  final double expenses;
  final double outstandingCredit;
  final int activeEmployees;
  final int productsTotal;
  final int outOfStock;
  final int lowStock;

  factory AnalyticsOverview.fromJson(Map<String, dynamic> json) {
    final session = Map<String, dynamic>.from(json['session'] as Map? ?? {});
    final today = Map<String, dynamic>.from(json['today'] as Map? ?? {});
    final month = Map<String, dynamic>.from(json['month'] as Map? ?? {});
    final products = Map<String, dynamic>.from(json['products'] as Map? ?? {});
    return AnalyticsOverview(
      currency: json['currency']?.toString() ?? 'Rs.',
      snapshotDate: json['snapshot_date']?.toString() ?? '',
      sessionOpen: session['open'] == true,
      sessionSale: _d(session['sale']),
      sessionPaidBills: _i(session['paid_bills']),
      sessionPending: _i(session['pending']),
      sessionCash: _d(session['cash']),
      sessionCard: _d(session['card']),
      sessionBank: _d(session['bank']),
      sessionNos: session['session_nos']?.toString(),
      cashiers: session['cashiers']?.toString(),
      todaySales: _d(today['sales']),
      todayPaidBills: _i(today['paid_bills']),
      monthIncome: _d(month['income']),
      monthIncomeGrowthPct: _d(month['income_growth_pct']),
      restaurantProfit: _d(month['restaurant_profit']),
      purchases: _d(month['purchases']),
      expenses: _d(month['expenses']),
      outstandingCredit: _d(json['outstanding_credit']),
      activeEmployees: _i(json['active_employees']),
      productsTotal: _i(products['total']),
      outOfStock: _i(products['out_of_stock']),
      lowStock: _i(products['low_stock']),
    );
  }
}

double _d(dynamic v) {
  if (v is num) return v.toDouble();
  return double.tryParse(v?.toString() ?? '') ?? 0;
}

int _i(dynamic v) {
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse(v?.toString() ?? '') ?? 0;
}
