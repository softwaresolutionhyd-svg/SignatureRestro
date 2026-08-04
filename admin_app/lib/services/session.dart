import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

class Session extends ChangeNotifier {
  static const _keyBaseUrl = 'admin_base_url';
  static const _keyToken = 'admin_token';
  static const _keyUserName = 'admin_user_name';
  static const _keyUserEmail = 'admin_user_email';
  static const _keyUserRole = 'admin_user_role';

  String _baseUrl = '';
  String _token = '';
  String _userName = '';
  String _userEmail = '';
  String _userRole = '';
  bool loaded = false;

  String get baseUrl => _baseUrl;
  String get token => _token;
  String get userName => _userName;
  String get userEmail => _userEmail;
  String get userRole => _userRole;
  bool get isLoggedIn => _token.isNotEmpty && _baseUrl.isNotEmpty;

  ApiClient get client => ApiClient(baseUrl: _baseUrl, token: _token);

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = (prefs.getString(_keyBaseUrl) ?? '').trim();
    _token = prefs.getString(_keyToken) ?? '';
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
  }) async {
    _token = token;
    _userName = name;
    _userEmail = email;
    _userRole = role;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    await prefs.setString(_keyUserName, name);
    await prefs.setString(_keyUserEmail, email);
    await prefs.setString(_keyUserRole, role);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      if (_token.isNotEmpty) {
        await client.post('/api/logout');
      }
    } catch (_) {}
    _token = '';
    _userName = '';
    _userEmail = '';
    _userRole = '';
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUserName);
    await prefs.remove(_keyUserEmail);
    await prefs.remove(_keyUserRole);
    notifyListeners();
  }
}
