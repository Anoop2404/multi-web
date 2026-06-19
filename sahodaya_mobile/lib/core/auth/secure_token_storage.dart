import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureTokenStorage {
  SecureTokenStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'auth_token';
  static const _tenantIdKey = 'tenant_id';
  static const _roleKey = 'role';
  static const _tenantNameKey = 'tenant_name';

  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveSession({
    required String token,
    required String tenantId,
    required String role,
    String? tenantName,
  }) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _tenantIdKey, value: tenantId);
    await _storage.write(key: _roleKey, value: role);
    if (tenantName != null) {
      await _storage.write(key: _tenantNameKey, value: tenantName);
    }
  }

  Future<Map<String, String?>> readSession() async {
    return {
      'token': await _storage.read(key: _tokenKey),
      'tenant_id': await _storage.read(key: _tenantIdKey),
      'role': await _storage.read(key: _roleKey),
      'tenant_name': await _storage.read(key: _tenantNameKey),
    };
  }

  Future<void> clear() => _storage.deleteAll();
}
