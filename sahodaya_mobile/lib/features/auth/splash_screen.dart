import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/app_branding.dart';
import '../../core/theme/app_theme.dart';
import '../../core/auth/auth_providers.dart';
import '../../core/widgets/app_brand_logo.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(authControllerProvider.notifier).bootstrap());
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(gradient: AppTheme.sidebarGradient),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const AppBrandLogo(size: 96),
              const SizedBox(height: 16),
              const AppBrandTitle(
                textAlign: TextAlign.center,
                line1Size: 13,
                line2Size: 22,
              ),
              const SizedBox(height: 24),
              const CircularProgressIndicator(color: AppColors.accentGold),
            ],
          ),
        ),
      ),
    );
  }
}
