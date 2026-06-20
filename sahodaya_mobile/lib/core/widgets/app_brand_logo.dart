import 'package:flutter/material.dart';

import '../../config/app_branding.dart';
import '../theme/app_theme.dart';
import 'tenant_logo.dart';

class AppBrandLogo extends StatelessWidget {
  const AppBrandLogo({super.key, required this.size, this.borderColor});

  final double size;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    return TenantLogo(
      logoUrl: '',
      tenantName: AppBranding.appName,
      size: size,
      preferBundledAsset: true,
      borderColor: borderColor ?? AppColors.accentGold.withValues(alpha: 0.5),
    );
  }
}
