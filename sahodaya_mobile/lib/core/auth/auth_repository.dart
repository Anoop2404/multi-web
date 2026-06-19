import 'package:dio/dio.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../api/dio_errors.dart';
import 'auth_session.dart';
import 'secure_token_storage.dart';

class AuthRepository {
  AuthRepository({
    required ApiClient api,
    required SecureTokenStorage storage,
  })  : _api = api,
        _storage = storage;

  final ApiClient _api;
  final SecureTokenStorage _storage;

  Future<AuthSession> login({
    required String email,
    required String password,
    String deviceName = 'Sahodaya Mobile',
  }) async {
    try {
      final payload = await _api.postJson('/api/v1/auth/login', body: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      });
      final data = payload['data'] as Map<String, dynamic>? ?? payload;
      final token = data['token'] as String? ?? '';
      if (token.isEmpty) {
        throw ApiException('Login succeeded but no token was returned. Try again or contact support.');
      }
      final user = AuthUser.fromJson(data);
      if (user.role.isEmpty) {
        throw ApiException('This account is not authorized for the mobile app.');
      }
      if (user.tenantId.isEmpty) {
        throw ApiException('Your account is missing a school or Sahodaya assignment.');
      }
      await _storage.saveSession(
        token: token,
        tenantId: user.tenantId,
        role: user.role,
        tenantName: user.tenantName,
      );
      return AuthSession(token: token, user: user);
    } on DioException catch (error) {
      throw apiExceptionFromDio(error);
    }
  }

  Future<AuthSession?> restoreSession() async {
    final saved = await _storage.readSession();
    final token = saved['token'];
    final tenantId = saved['tenant_id'];
    final role = saved['role'];
    if (token == null || tenantId == null || role == null) {
      return null;
    }

    try {
      final payload = await _api.getJson('/api/v1/auth/me');
      final data = payload['data'] as Map<String, dynamic>? ?? payload;
      final user = AuthUser.fromJson(data);
      return AuthSession(token: token, user: user);
    } on DioException catch (error) {
      if (error.response?.statusCode == 401) {
        await _storage.clear();
      }
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await _api.postJson('/api/v1/auth/logout');
    } catch (_) {}
    await _storage.clear();
  }
}
