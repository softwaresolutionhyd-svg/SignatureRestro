import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// LAN pe self-signed HTTPS (mkcert) allow — production public hosts pe nahi.
bool _isPrivateLanHost(String host) {
  final h = host.toLowerCase();
  if (h == 'localhost' || h == '127.0.0.1' || h == '::1') return true;
  if (h.endsWith('.local') || h.endsWith('.test') || h.endsWith('.restro')) return true;
  final ip = InternetAddress.tryParse(h);
  if (ip == null || ip.type != InternetAddressType.IPv4) return false;
  final parts = ip.address.split('.').map(int.parse).toList();
  if (parts[0] == 10) return true;
  if (parts[0] == 192 && parts[1] == 168) return true;
  if (parts[0] == 172 && parts[1] >= 16 && parts[1] <= 31) return true;
  return false;
}

http.Client createLanHttpClient() {
  final io = HttpClient()
    ..connectionTimeout = const Duration(seconds: 8)
    ..idleTimeout = const Duration(seconds: 15)
    ..badCertificateCallback = (X509Certificate cert, String host, int port) {
      return _isPrivateLanHost(host);
    };
  return IOClient(io);
}

class ApiClient {
  ApiClient({required this.baseUrl, required this.token, http.Client? client})
      : _client = client ?? createLanHttpClient();

  final String baseUrl;
  final String token;
  final http.Client _client;

  static const Duration _timeout = Duration(seconds: 12);

  Uri _uri(String path) {
    final normalized = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$baseUrl$normalized');
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token.isNotEmpty) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> get(String path) async {
    try {
      final res = await _client.get(_uri(path), headers: _headers).timeout(_timeout);
      return _decode(res);
    } on SocketException {
      throw ApiException('Server se connect nahi hua. WiFi + Server URL (IP:port) check karein.');
    } on HttpException {
      throw ApiException('Server se connect nahi hua. URL / port galat ho sakta hai.');
    } on HandshakeException {
      throw ApiException('HTTPS certificate fail. http://IP:8080 try karein ya LAN CA install karein.');
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException('Network error: $e');
    }
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async {
    try {
      final res = await _client
          .post(
            _uri(path),
            headers: _headers,
            body: body == null ? null : jsonEncode(body),
          )
          .timeout(_timeout);
      return _decode(res);
    } on SocketException {
      throw ApiException('Server se connect nahi hua. WiFi + Server URL (IP:port) check karein.');
    } on HandshakeException {
      throw ApiException('HTTPS certificate fail. http://IP:8080 try karein.');
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException('Network error: $e');
    }
  }

  Future<Map<String, dynamic>> put(String path, Map<String, dynamic> body) async {
    try {
      final res = await _client
          .put(
            _uri(path),
            headers: _headers,
            body: jsonEncode(body),
          )
          .timeout(_timeout);
      return _decode(res);
    } on SocketException {
      throw ApiException('Server se connect nahi hua. WiFi + Server URL (IP:port) check karein.');
    } on HandshakeException {
      throw ApiException('HTTPS certificate fail. http://IP:8080 try karein.');
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException('Network error: $e');
    }
  }

  Map<String, dynamic> _decode(http.Response res) {
    Map<String, dynamic>? json;
    if (res.body.isNotEmpty) {
      try {
        final decoded = jsonDecode(res.body);
        if (decoded is Map<String, dynamic>) {
          json = decoded;
        }
      } catch (_) {
        // non-JSON body (e.g. Softwaresolution HTML on wrong port)
      }
    }

    if (res.statusCode >= 200 && res.statusCode < 300) {
      return json ?? {};
    }

    if (res.statusCode == 404 || (res.body.contains('<html') && json == null)) {
      throw ApiException(
        'Yeh URL pe Signature nahi mila (galat IP/port). Softwaresolution :80 ho sakta hai — Signature ke liye :8080 try karein.',
        statusCode: res.statusCode,
      );
    }

    final message = json?['message']?.toString() ??
        _firstValidationError(json?['errors']) ??
        'Request failed (${res.statusCode})';

    throw ApiException(message, statusCode: res.statusCode);
  }

  String? _firstValidationError(dynamic errors) {
    if (errors is! Map) return null;
    for (final value in errors.values) {
      if (value is List && value.isNotEmpty) {
        return value.first.toString();
      }
      if (value != null) return value.toString();
    }
    return null;
  }
}
