import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/auth_providers.dart';
import '../../core/widgets/sa_admin_shell.dart';
import '../../core/widgets/sa_widgets.dart';
import '../programs/program_drawer_extras.dart';
import 'dashboard_screen.dart';
import 'profile_screen.dart';
import 'registration_screen.dart';
import 'school_api.dart';
import 'setup_code_screen.dart';
import 'students_screen.dart';

class SchoolShell extends ConsumerStatefulWidget {
  const SchoolShell({super.key});

  @override
  ConsumerState<SchoolShell> createState() => _SchoolShellState();
}

class _SchoolShellState extends ConsumerState<SchoolShell> {
  int _index = 0;
  bool? _hasSchoolCode;

  static const _labels = ['Dashboard', 'Students', 'Registration'];
  static const _icons = [Icons.dashboard_outlined, Icons.people_outline, Icons.assignment_outlined];

  @override
  void initState() {
    super.initState();
    _loadSetup();
  }

  Future<void> _loadSetup() async {
    try {
      final response = await schoolGet(ref, '/dashboard');
      final setup = (response['data'] as Map?)?['setup'] as Map?;
      if (mounted) setState(() => _hasSchoolCode = setup?['has_school_code'] == true);
    } catch (_) {
      if (mounted) setState(() => _hasSchoolCode = true);
    }
  }

  void _goTo(int index) {
    Navigator.pop(context);
    setState(() => _index = index);
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authControllerProvider).session!;

    return SaAdminShell(
      roleLabel: 'School',
      tenantName: session.user.tenantName ?? 'School',
      logoUrl: session.user.logoUrl,
      pageTitle: _index == 3 ? 'Registration Details' : _labels[_index.clamp(0, 2)],
      labels: _labels,
      icons: _icons,
      selectedIndex: _index,
      navSelectedIndex: _index > 2 ? 2 : _index,
      onIndexChanged: (value) => setState(() => _index = value),
      navCount: 3,
      drawerLeadingExtras: const [SaDrawerSectionLabel('Membership')],
      drawerNavChildren: [
        SaDrawerTile(
          icon: Icons.dashboard_outlined,
          label: 'Dashboard',
          selected: _index == 0,
          onTap: () => _goTo(0),
        ),
        if (_hasSchoolCode == false)
          SaDrawerTile(
            icon: Icons.tag,
            label: 'School Code',
            selected: false,
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (_) => const SchoolSetupCodeScreen()));
            },
          ),
        SaDrawerTile(
          icon: Icons.people_outline,
          label: 'Students',
          selected: _index == 1,
          onTap: () => _goTo(1),
        ),
        SaDrawerTile(
          icon: Icons.person_outline,
          label: 'Registration Details',
          selected: _index == 3,
          onTap: () => _goTo(3),
        ),
        SaDrawerTile(
          icon: Icons.assignment_outlined,
          label: 'Annual Registration',
          selected: _index == 2,
          onTap: () => _goTo(2),
        ),
      ],
      drawerTrailingExtras: buildProgramDrawerExtras(context, schoolPortal: true),
      pages: const [
        SchoolDashboardScreen(),
        SchoolStudentsScreen(),
        SchoolRegistrationScreen(),
        SchoolProfileScreen(),
      ],
    );
  }
}
