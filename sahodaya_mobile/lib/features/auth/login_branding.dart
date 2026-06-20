import '../../config/app_branding.dart';
import '../../config/env.dart';

class LoginBranding {
  const LoginBranding({
    required this.tenantName,
    required this.eyebrow,
    this.logoUrl,
    this.tagline,
    this.motto,
    this.phone,
    this.email,
    this.portalUrl,
    this.registerUrl,
    this.showRegister = true,
  });

  final String? logoUrl;
  final String tenantName;
  final String eyebrow;
  final String? tagline;
  final String? motto;
  final String? phone;
  final String? email;
  final String? portalUrl;
  final String? registerUrl;
  final bool showRegister;

  static String get fallbackLogoUrl => '${AppEnv.apiBaseUrl}/images/tenants/malappuram-logo.png';

  String get resolvedLogoUrl => logoUrl ?? fallbackLogoUrl;

  String get resolvedRegisterUrl => registerUrl ?? '${AppEnv.apiBaseUrl}/school-register';

  factory LoginBranding.fromJson(Map<String, dynamic> json) {
    return LoginBranding(
      logoUrl: json['logo_url'] as String?,
      tenantName: json['tenant_name'] as String? ?? 'Admin Portal',
      eyebrow: json['eyebrow'] as String? ?? 'CBSE Sahodaya School Complex',
      tagline: json['tagline'] as String?,
      motto: json['motto'] as String?,
      phone: json['phone'] as String?,
      email: json['email'] as String?,
      portalUrl: json['portal_url'] as String?,
      registerUrl: json['register_url'] as String?,
      showRegister: json['show_register'] as bool? ?? true,
    );
  }

  LoginBranding withFallbacks() {
    final base = LoginBranding.fallback;
    return LoginBranding(
      logoUrl: logoUrl ?? base.logoUrl,
      tenantName: tenantName.isNotEmpty ? tenantName : base.tenantName,
      eyebrow: eyebrow.isNotEmpty ? eyebrow : base.eyebrow,
      tagline: tagline ?? base.tagline,
      motto: motto ?? base.motto,
      phone: phone ?? base.phone,
      email: email ?? base.email,
      portalUrl: portalUrl ?? base.portalUrl,
      registerUrl: registerUrl ?? base.registerUrl,
      showRegister: showRegister,
    );
  }

  static LoginBranding get fallback => LoginBranding(
        tenantName: AppBranding.appName,
        eyebrow: AppBranding.eyebrow,
        tagline: 'Uniting CBSE schools for academic excellence, cultural programs, and collaborative growth.',
        motto: 'Caring and Sharing',
        logoUrl: fallbackLogoUrl,
        portalUrl: AppEnv.apiBaseUrl,
        registerUrl: '${AppEnv.apiBaseUrl}/school-register',
        showRegister: true,
      );
}
