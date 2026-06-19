import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import '../../core/auth/auth_providers.dart';
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

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authControllerProvider).session!;
    final pages = [
      const SchoolDashboardScreen(),
      const SchoolStudentsScreen(),
      const SchoolRegistrationScreen(),
      const SchoolProfileScreen(),
    ];
    final titles = ['Dashboard', 'Students', 'Annual Registration', 'Registration Details'];

    return Container(
      decoration: BoxDecoration(gradient: AppTheme.mainBackground),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(
          title: Text(session.user.tenantName ?? titles[_index]),
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(1),
            child: Divider(height: 1, color: AppColors.borderBlue),
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.logout),
              onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            ),
          ],
        ),
        drawer: Drawer(
          child: Column(
            children: [
              SaDrawerHeader(
                roleLabel: 'School',
                tenantName: session.user.tenantName ?? 'School',
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                  children: [
                    SaDrawerTile(
                      icon: Icons.dashboard_outlined,
                      label: 'Dashboard',
                      selected: _index == 0,
                      onTap: () => _select(0),
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
                      onTap: () => _select(1),
                    ),
                    const Padding(
                      padding: EdgeInsets.fromLTRB(12, 16, 12, 4),
                      child: Text(
                        'MEMBERSHIP',
                        style: TextStyle(color: Color(0xBFFBBF24), fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 1.2),
                      ),
                    ),
                    SaDrawerTile(
                      icon: Icons.person_outline,
                      label: 'Registration Details',
                      selected: _index == 3,
                      onTap: () => _select(3),
                    ),
                    SaDrawerTile(
                      icon: Icons.assignment_outlined,
                      label: 'Annual Registration',
                      selected: _index == 2,
                      onTap: () => _select(2),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        body: pages[_index],
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index > 2 ? 2 : _index,
          onDestinationSelected: (value) => setState(() => _index = value),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
            NavigationDestination(icon: Icon(Icons.people_outline), label: 'Students'),
            NavigationDestination(icon: Icon(Icons.assignment_outlined), label: 'Registration'),
          ],
        ),
      ),
    );
  }

  void _select(int index) {
    Navigator.pop(context);
    setState(() => _index = index);
  }
}
