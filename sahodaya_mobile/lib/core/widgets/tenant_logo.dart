import 'package:flutter/material.dart';

import '../../config/env.dart';
import '../theme/app_theme.dart';

class TenantLogo extends StatelessWidget {
  const TenantLogo({
    super.key,
    required this.logoUrl,
    required this.tenantName,
    required this.size,
    this.fit = BoxFit.cover,
    this.borderColor,
  });

  final String logoUrl;
  final String tenantName;
  final double size;
  final BoxFit fit;
  final Color? borderColor;

  static String resolveUrl(String? logoUrl) {
    if (logoUrl == null || logoUrl.isEmpty) {
      return '${AppEnv.apiBaseUrl}/images/tenants/malappuram-logo.png';
    }
    if (logoUrl.startsWith('http')) return logoUrl;
    return '${AppEnv.apiBaseUrl}${logoUrl.startsWith('/') ? logoUrl : '/$logoUrl'}';
  }

  @override
  Widget build(BuildContext context) {
    final initial = tenantName.isNotEmpty ? tenantName[0].toUpperCase() : 'S';
    final resolved = resolveUrl(logoUrl);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: borderColor ?? const Color(0x59FFFFFF), width: 2),
        boxShadow: size >= 64
            ? const [BoxShadow(color: Color(0x4D000000), blurRadius: 32, offset: Offset(0, 12))]
            : null,
      ),
      clipBehavior: Clip.antiAlias,
      child: Transform.scale(
        scale: 1.18,
        child: Image.network(
          resolved,
          fit: fit,
          loadingBuilder: (context, child, progress) {
            if (progress == null) return child;
            return const Center(child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.accentGold));
          },
          errorBuilder: (_, __, ___) => _AvatarFallback(initial: initial, fontSize: size * 0.4),
        ),
      ),
    );
  }
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.initial, required this.fontSize});

  final String initial;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(colors: [Color(0xFF0F3D7A), Color(0xFF1E5AA8)]),
      ),
      child: Center(
        child: Text(
          initial,
          style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.w800, color: AppColors.accentGold),
        ),
      ),
    );
  }
}
