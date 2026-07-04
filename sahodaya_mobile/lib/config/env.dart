import 'tenant_config.dart';

class AppEnv {
  const AppEnv._();

  static const String apiBaseUrl = TenantConfig.apiBaseUrl;
  static const String appName = TenantConfig.appName;
  static const String tenantSlug = TenantConfig.slug;
}
