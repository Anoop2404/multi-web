import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import '../../core/auth/auth_providers.dart';
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

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authControllerProvider).session!;
    final pages = const [
      SahodayaDashboardScreen(),
      SahodayaSchoolsScreen(),
      SahodayaPaymentsScreen(),
      SahodayaSubmissionsScreen(),
    ];
    final labels = ['Dashboard', 'Schools', 'Payments', 'Submissions'];

    return Container(
      decoration: BoxDecoration(gradient: AppTheme.mainBackground),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(
          title: Text(session.user.tenantName ?? labels[_index]),
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
              SaDrawerHeader(roleLabel: 'Sahodaya', tenantName: session.user.tenantName ?? 'Sahodaya'),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                  children: [
                    for (var i = 0; i < labels.length; i++)
                      SaDrawerTile(
                        icon: [Icons.dashboard_outlined, Icons.school_outlined, Icons.payments_outlined, Icons.fact_check_outlined][i],
                        label: labels[i],
                        selected: _index == i,
                        onTap: () {
                          Navigator.pop(context);
                          setState(() => _index = i);
                        },
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
        body: pages[_index],
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (value) => setState(() => _index = value),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
            NavigationDestination(icon: Icon(Icons.school_outlined), label: 'Schools'),
            NavigationDestination(icon: Icon(Icons.payments_outlined), label: 'Payments'),
            NavigationDestination(icon: Icon(Icons.fact_check_outlined), label: 'Submissions'),
          ],
        ),
      ),
    );
  }
}
