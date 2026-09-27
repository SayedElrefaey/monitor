import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  const ApiException(this.message, {this.statusCode});
  @override
  String toString() => message;
}

class ApiService {
  static const String baseUrl = 'https://monitor.oxserver.net/api';
  static const FlutterSecureStorage _secure = FlutterSecureStorage();

  Future<String?> _token() => _secure.read(key: 'jwt_token');

  Future<bool> hasToken() async => (await _token())?.isNotEmpty == true;

  Map<String, String> _headers(String token) => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> _decode(http.Response response) async {
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (_) {
      throw ApiException('استجابة غير صالحة من الخادم', statusCode: response.statusCode);
    }
    if (data is! Map<String, dynamic>) {
      throw ApiException('استجابة غير صالحة من الخادم', statusCode: response.statusCode);
    }
    if (response.statusCode == 401) {
      throw const ApiException('SESSION_EXPIRED', statusCode: 401);
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        (data['message'] ?? data['error'] ?? 'حدث خطأ في الخادم').toString(),
        statusCode: response.statusCode,
      );
    }
    return data;
  }

  Future<void> login(String username, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login.php'),
      headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({'username': username, 'password': password}),
    ).timeout(const Duration(seconds: 15));

    final data = await _decode(response);
    final token = data['token'];
    if (data['status'] != 'ok' || token is! String || token.isEmpty) {
      throw ApiException((data['message'] ?? 'فشل تسجيل الدخول').toString());
    }
    await _secure.write(key: 'jwt_token', value: token);
    await _secure.write(key: 'username', value: username);
  }

  Future<Map<String, dynamic>> dashboard() async {
    final token = await _token();
    if (token == null || token.isEmpty) throw const ApiException('SESSION_EXPIRED', statusCode: 401);
    final response = await http.get(
      Uri.parse('$baseUrl/dashboard.php'),
      headers: _headers(token),
    ).timeout(const Duration(seconds: 15));
    return _decode(response);
  }

  Future<List<Map<String, dynamic>>> servers() async {
    final token = await _token();
    if (token == null || token.isEmpty) throw const ApiException('SESSION_EXPIRED', statusCode: 401);
    final response = await http.get(
      Uri.parse('$baseUrl/servers.php'),
      headers: _headers(token),
    ).timeout(const Duration(seconds: 15));
    final data = await _decode(response);
    final list = data['servers'];
    if (list is! List) return <Map<String, dynamic>>[];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<List<Map<String, dynamic>>> alerts() async {
    final token = await _token();
    if (token == null || token.isEmpty) throw const ApiException('SESSION_EXPIRED', statusCode: 401);
    final response = await http.get(
      Uri.parse('$baseUrl/alerts.php'),
      headers: _headers(token),
    ).timeout(const Duration(seconds: 15));
    final data = await _decode(response);
    final list = data['alerts'];
    if (list is! List) return <Map<String, dynamic>>[];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<String?> username() => _secure.read(key: 'username');

  Future<void> logout() async {
    await _secure.delete(key: 'jwt_token');
    await _secure.delete(key: 'username');
  }
}
