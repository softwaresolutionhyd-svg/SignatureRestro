import 'package:flutter_test/flutter_test.dart';
import 'package:order_taker_app/main.dart';

void main() {
  testWidgets('App loads login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const OrderTakerApp());
    await tester.pumpAndSettle(const Duration(seconds: 2));

    expect(find.text('Order Taker'), findsWidgets);
  });
}
