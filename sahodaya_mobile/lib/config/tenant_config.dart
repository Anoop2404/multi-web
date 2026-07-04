/// Build-time tenant configuration via `--dart-define-from-file=tenants/<slug>.env.json`.
///
/// Default values target Malappuram Central Sahodaya (local dev without defines).
class TenantConfig {
  const TenantConfig._();

  static const String slug = String.fromEnvironment(
    'TENANT_SLUG',
    defaultValue: 'malappuram',
  );

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://malappuramcentralsahodaya.org',
  );

  static const String appName = String.fromEnvironment(
    'APP_NAME',
    defaultValue: 'Malappuram Central Sahodaya',
  );

  static const String nameLine1 = String.fromEnvironment(
    'NAME_LINE_1',
    defaultValue: 'Malappuram Central',
  );

  static const String nameLine2 = String.fromEnvironment(
    'NAME_LINE_2',
    defaultValue: 'Sahodaya',
  );

  static const String eyebrow = String.fromEnvironment(
    'EYEBROW',
    defaultValue: 'CBSE Sahodaya School Complex',
  );

  static const String logoAsset = String.fromEnvironment(
    'LOGO_ASSET',
    defaultValue: 'assets/tenants/malappuram/logo.png',
  );

  static const String androidApplicationId = String.fromEnvironment(
    'ANDROID_APPLICATION_ID',
    defaultValue: 'org.malappuramcentralsahodaya.mobile',
  );

  static const String iosBundleId = String.fromEnvironment(
    'IOS_BUNDLE_ID',
    defaultValue: 'org.malappuramcentralsahodaya.mobile',
  );

  static String defaultLogoUrl() =>
      '$apiBaseUrl/images/tenants/$slug-logo.png';
}
