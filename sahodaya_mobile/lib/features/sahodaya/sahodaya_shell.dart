import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/auth_providers.dart';
import '../../core/widgets/sa_admin_shell.dart';
import '../../core/widgets/sa_widgets.dart';
import '../../config/app_branding.dart';
import '../programs/program_drawer_extras.dart';
import 'dashboard_screen.dart';
import 'payments_screen.dart';
import 'schools_screen.dart';
import 'submissions_screen.dart';

class SahodayaShell extends ConsumerStatefulWidget {
  const SahodayaShell({super.key});

  @override
  ConsumerState<SahodayaShell> createState() => _SahodayaShellState();
}

class _SahodayaShellState extends ConsumerState<SahodayaShell> {
  int _index = 0;
  String? _paymentsInitialStatus;
  String? _schoolsPaymentFilter;

  static const _labels = ['Dashboard', 'Schools', 'Payments', 'Submissions'];
  static const _icons = [
    Icons.dashboard_outlined,
    Icons.school_outlined,
    Icons.payments_outlined,
    Icons.fact_check_outlined,
  ];

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authControllerProvider).session!;

    return SaAdminShell(
      roleLabel: 'Sahodaya',
      tenantName: session.user.tenantName ?? AppBranding.appName,
      logoUrl: session.user.logoUrl,
      pageTitle: _labels[_index],
      labels: _labels,
      icons: _icons,
      selectedIndex: _index,
      onIndexChanged: (value) => setState(() => _index = value),
      drawerLeadingExtras: const [SaDrawerSectionLabel('Membership')],
      drawerTrailingExtras: buildProgramDrawerExtras(context, schoolPortal: false),
      pages: [
        SahodayaDashboardScreen(
          onGoToSchools: () => setState(() => _index = 1),
          onGoToPayments: () => setState(() {
            _paymentsInitialStatus = 'submitted';
            _index = 2;
          }),
          onGoToPaymentDue: () => setState(() {
            _schoolsPaymentFilter = 'payment_not_done';
            _index = 1;
          }),
        ),
        SahodayaSchoolsScreen(
          key: ValueKey(_schoolsPaymentFilter ?? 'all'),
          initialPaymentFilter: _schoolsPaymentFilter,
        ),
        SahodayaPaymentsScreen(key: ValueKey(_paymentsInitialStatus ?? 'submitted'), initialStatus: _paymentsInitialStatus ?? 'submitted'),
        const SahodayaSubmissionsScreen(),
      ],
    );
  }
}
