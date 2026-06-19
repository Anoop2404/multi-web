import 'package:flutter/material.dart';

class AppColors {
  static const navyDark = Color(0xFF041525);
  static const navyMid = Color(0xFF0A2744);
  static const navyPrimary = Color(0xFF0F3D7A);
  static const navyLight = Color(0xFF1E5AA8);
  static const accentGold = Color(0xFFFBBF24);
  static const bgSky = Color(0xFFF0F9FF);
  static const bgMain = Color(0xFFF8FAFC);
  static const borderBlue = Color(0xFFDBEAFE);
  static const textDark = Color(0xFF041525);
  static const blue600 = Color(0xFF2563EB);
  static const purple600 = Color(0xFF9333EA);
  static const infoBg = Color(0xFFEFF6FF);
  static const infoBorder = Color(0xFFDBEAFE);
}

class AppTheme {
  static ThemeData get light {
    final base = ThemeData(
      useMaterial3: true,
      fontFamily: 'Roboto',
      scaffoldBackgroundColor: AppColors.bgSky,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.navyPrimary,
        primary: AppColors.navyPrimary,
        secondary: AppColors.accentGold,
        surface: Colors.white,
        onPrimary: Colors.white,
        onSurface: AppColors.textDark,
      ),
    );

    return base.copyWith(
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: TextStyle(
          color: AppColors.textDark,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Color(0xFFF3F4F6)),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.navyPrimary, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF6B7280)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.blue600,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: AppColors.bgSky,
        labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: AppColors.navyPrimary);
          }
          return const IconThemeData(color: Color(0xFF9CA3AF));
        }),
      ),
      drawerTheme: const DrawerThemeData(
        backgroundColor: AppColors.navyDark,
        surfaceTintColor: Colors.transparent,
      ),
    );
  }

  static LinearGradient get sidebarGradient => const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [AppColors.navyDark, AppColors.navyMid, AppColors.navyPrimary],
      );

  static LinearGradient get mainBackground => const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [AppColors.bgSky, AppColors.bgMain],
      );
}
