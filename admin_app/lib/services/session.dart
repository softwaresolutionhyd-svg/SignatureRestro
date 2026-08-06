import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'fcm_push_service.dart';
import 'order_notifications.dart';

class Session extends ChangeNotifier {
  static const _keyBaseUrl = 'admin_base_url';
  static const _keyToken = 'admin_token';
  static const _keyUserId = 'admin_user_id';
  static const _keyUserName = 'admin_user_name';
  static const _keyUserEmail = 'admin_user_email';
  static const _keyUserRole = 'admin_user_role';

  String _baseUrl = '';
  String _token = '';
  int? _userId;
  String _userName = '';
  String _userEmail = '';
  String _userRole = '';
  bool loaded = false;

  String get baseUrl => _baseUrl;
  String get token => _token;
  int? get userId => _userId;
  String get userName => _userName;
  String get userEmail => _userEmail;
  String get userRole => _userRole;
  bool get isLoggedIn => _token.isNotEmpty && _baseUrl.isNotEmpty;

  ApiClient get client => ApiClient(baseUrl: _baseUrl, token: _token);

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = (prefs.getString(_keyBaseUrl) ?? '').trim();
    _token = prefs.getString(_keyToken) ?? '';
    _userId = prefs.getInt(_keyUserId);
    _userName = prefs.getString(_keyUserName) ?? '';
    _userEmail = prefs.getString(_keyUserEmail) ?? '';
    _userRole = prefs.getString(_keyUserRole) ?? '';
    loaded = true;
    notifyListeners();
  }

  Future<void> saveBaseUrl(String url) async {
    _baseUrl = url.trim().replaceAll(RegExp(r'/+$'), '');
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyBaseUrl, _baseUrl);
    notifyListeners();
  }

  Future<void> login({
    required String token,
    required String name,
    required String email,
    required String role,
    int? userId,
  }) async {
    _token = token;
    _userId = userId;
    _userName = name;
    _userEmail = email;
    _userRole = role;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    if (userId != null) {
      await prefs.setInt(_keyUserId, userId);
    } else {
      await prefs.remove(_keyUserId);
    }
    await prefs.setString(_keyUserName, name);
    await prefs.setString(_keyUserEmail, email);
    await prefs.setString(_keyUserRole, role);
    notifyListeners();
  }

  Future<void> logout() async {
    final fcm = FcmPushService.instance.token;
    final deviceId = await FcmPushService.instance.deviceId();
    try {
      if (_token.isNotEmpty) {
        await client.post('/api/logout', {
          'app': 'admin',
          if (fcm != null && fcm.isNotEmpty) 'fcm_token': fcm,
          'device_id': deviceId,
        });
      }
    } catch (_) {}
    // Local cleanup (also best-effort DELETE /device-tokens).
    await OrderNotificationService.instance.stop();
    _token = '';
    _userId = null;
    _userName = '';
    _userEmail = '';
    _userRole = '';
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUserId);
    await prefs.remove(_keyUserName);
    await prefs.remove(_keyUserEmail);
    await prefs.remove(_keyUserRole);
    await prefs.setBool('admin_bg_seeded', false);
    notifyListeners();
  }
}
