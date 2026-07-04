import 'package:flutter/material.dart';

import 'tenant_config.dart';

class AppBranding {
  const AppBranding._();

  static const String logoAsset = TenantConfig.logoAsset;
  static const String appName = TenantConfig.appName;
  static const String nameLine1 = TenantConfig.nameLine1;
  static const String nameLine2 = TenantConfig.nameLine2;
  static const String eyebrow = TenantConfig.eyebrow;
}

class AppBrandTitle extends StatelessWidget {
  const AppBrandTitle({
    super.key,
    this.textAlign = TextAlign.start,
    this.line1Color = Colors.white,
    this.line2Color = Colors.white,
    this.line1Size = 14,
    this.line2Size = 20,
  });

  final TextAlign textAlign;
  final Color line1Color;
  final Color line2Color;
  final double line1Size;
  final double line2Size;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: textAlign == TextAlign.center ? CrossAxisAlignment.center : CrossAxisAlignment.start,
      children: [
        Text(
          AppBranding.nameLine1.toUpperCase(),
          textAlign: textAlign,
          style: TextStyle(
            fontSize: line1Size,
            fontWeight: FontWeight.w600,
            color: line1Color,
            letterSpacing: 0.6,
            height: 1.2,
          ),
        ),
        Text(
          AppBranding.nameLine2.toUpperCase(),
          textAlign: textAlign,
          style: TextStyle(
            fontSize: line2Size,
            fontWeight: FontWeight.w800,
            color: line2Color,
            letterSpacing: 0.8,
            height: 1.15,
          ),
        ),
      ],
    );
  }
}
