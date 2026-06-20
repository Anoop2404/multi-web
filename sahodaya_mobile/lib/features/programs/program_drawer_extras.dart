import 'package:flutter/material.dart';

import '../../core/widgets/sa_widgets.dart';
import 'program_placeholder_screen.dart';

class ProgramMenuItem {
  const ProgramMenuItem({
    required this.program,
    required this.label,
    required this.emoji,
    required this.registrationHint,
    required this.resultsHint,
    required this.icon,
  });

  final String program;
  final String label;
  final String emoji;
  final String registrationHint;
  final String resultsHint;
  final IconData icon;
}

const programMenuItems = [
  ProgramMenuItem(
    program: 'kalotsav',
    label: 'Kalotsav',
    emoji: '🎭',
    registrationHint: 'School registration for Kalotsav will open here when the event is announced.',
    resultsHint: 'Kalotsav results will be published here after the event.',
    icon: Icons.star_outline,
  ),
  ProgramMenuItem(
    program: 'sports-meet',
    label: 'Sports Meet',
    emoji: '🏅',
    registrationHint: 'Sports meet registration will open here when the schedule is published.',
    resultsHint: 'Sports meet results and standings will appear here after the event.',
    icon: Icons.emoji_events_outlined,
  ),
  ProgramMenuItem(
    program: 'kids-fest',
    label: 'Kids Fest',
    emoji: '🎨',
    registrationHint: 'Kids fest registration will open here when the event is announced.',
    resultsHint: 'Kids fest results will be published here after the event.',
    icon: Icons.palette_outlined,
  ),
];

List<Widget> buildProgramDrawerExtras(BuildContext context, {required bool schoolPortal}) {
  return [
    const SaDrawerSectionLabel('Programs'),
    for (final item in programMenuItems) ...[
      SaDrawerTile(
        icon: item.icon,
        label: item.label,
        selected: false,
        onTap: () {
          Navigator.pop(context);
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ProgramPlaceholderScreen(
                title: '${item.label} Registration',
                emoji: item.emoji,
                statusLabel: 'Registration not started',
                hint: schoolPortal
                    ? '${item.registrationHint} You will be notified when registration opens.'
                    : item.registrationHint,
                schoolPortal: schoolPortal,
              ),
            ),
          );
        },
      ),
      SaDrawerTile(
        icon: Icons.bar_chart_outlined,
        label: '${item.label} Results',
        selected: false,
        onTap: () {
          Navigator.pop(context);
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ProgramPlaceholderScreen(
                title: '${item.label} Results',
                emoji: item.emoji,
                statusLabel: 'No results published yet',
                hint: item.resultsHint,
                schoolPortal: schoolPortal,
              ),
            ),
          );
        },
      ),
    ],
  ];
}
