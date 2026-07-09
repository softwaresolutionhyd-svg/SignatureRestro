import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({required this.baseUrl, required this.token});

  final String baseUrl;
  final String token;

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
    final res = await http.get(_uri(path), headers: _headers);
    return _decode(res);
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async {
    final res = await http.post(
      _uri(path),
      headers: _headers,
      body: body == null ? null : jsonEncode(body),
    );
    return _decode(res);
  }

  Future<Map<String, dynamic>> put(String path, Map<String, dynamic> body) async {
    final res = await http.put(
      _uri(path),
      headers: _headers,
      body: jsonEncode(body),
    );
    return _decode(res);
  }

  Map<String, dynamic> _decode(http.Response res) {
    Map<String, dynamic>? json;
    if (res.body.isNotEmpty) {
      final decoded = jsonDecode(res.body);
      if (decoded is Map<String, dynamic>) {
        json = decoded;
      }
    }

    if (res.statusCode >= 200 && res.statusCode < 300) {
      return json ?? {};
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
