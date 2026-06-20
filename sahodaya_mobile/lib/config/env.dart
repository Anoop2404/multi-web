import 'app_branding.dart';

class AppEnv {
  const AppEnv._();

  /// Production API — used for local dev and release builds.
  static const String apiBaseUrl = 'https://malappuramcentralsahodaya.org';

  static const String appName = AppBranding.appName;
}
