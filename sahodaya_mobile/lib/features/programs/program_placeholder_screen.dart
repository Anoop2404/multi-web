import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class ProgramPlaceholderScreen extends StatelessWidget {
  const ProgramPlaceholderScreen({
    super.key,
    required this.title,
    required this.emoji,
    required this.statusLabel,
    required this.hint,
    this.schoolPortal = false,
  });

  final String title;
  final String emoji;
  final String statusLabel;
  final String hint;
  final bool schoolPortal;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(gradient: AppTheme.mainBackground),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(title: Text(title)),
        body: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.borderBlue),
              ),
              child: Column(
                children: [
                  Text(emoji, style: const TextStyle(fontSize: 48)),
                  const SizedBox(height: 12),
                  const Text(
                    'COMING SOON',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.4,
                      color: Color(0xFFB45309),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.navyPrimary),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    hint,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 14, color: Color(0xFF64748B), height: 1.5),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFCBD5E1), style: BorderStyle.solid),
              ),
              child: Column(
                children: [
                  Text(
                    statusLabel,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    schoolPortal
                        ? 'Placeholder until Sahodaya enables this event.'
                        : 'Placeholder until this feature is enabled.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
