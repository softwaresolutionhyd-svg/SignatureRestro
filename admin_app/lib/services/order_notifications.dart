import 'api_client.dart';
import 'fcm_push_service.dart';

/// Compatibility façade — POS alerts are FCM push only (no polling / FGS).
class OrderNotificationService {
  OrderNotificationService._();

  static final OrderNotificationService instance = OrderNotificationService._();

  Future<void> init() => FcmPushService.instance.init();

  Future<void> start(ApiClient client, {bool isNewLogin = false}) =>
      FcmPushService.instance.start(client, isNewLogin: isNewLogin);

  Future<void> stop({bool keepLifecycle = false}) => FcmPushService.instance.stop();
}
