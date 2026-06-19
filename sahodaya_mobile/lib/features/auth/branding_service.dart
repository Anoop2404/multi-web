import 'package:dio/dio.dart';

import '../../config/env.dart';
import 'login_branding.dart';

class BrandingService {
  Future<LoginBranding> load() async {
    try {
      final response = await Dio().get<Map<String, dynamic>>(
        '${AppEnv.apiBaseUrl}/api/v1/auth/login-branding',
        options: Options(headers: {'Accept': 'application/json'}),
      );
      final data = response.data?['data'] as Map<String, dynamic>?;
      if (data != null) {
        return LoginBranding.fromJson(data).withFallbacks();
      }
    } catch (_) {}
    return LoginBranding.fallback;
  }
}
