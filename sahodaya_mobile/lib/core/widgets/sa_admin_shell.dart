import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_providers.dart';
import '../theme/app_theme.dart';
import 'sa_widgets.dart';

Future<void> confirmSignOut(BuildContext context, WidgetRef ref) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Sign out?'),
      content: const Text('Are you sure you want to sign out?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sign out')),
      ],
    ),
  );

  if (confirmed == true && context.mounted) {
    await ref.read(authControllerProvider.notifier).logout();
  }
}

class SaAdminShell extends ConsumerWidget {
  const SaAdminShell({
    super.key,
    required this.roleLabel,
    required this.tenantName,
    required this.pageTitle,
    required this.pages,
    required this.labels,
    required this.icons,
    required this.selectedIndex,
    required this.onIndexChanged,
    this.drawerExtras,
    this.navCount,
    this.navSelectedIndex,
  });

  final String roleLabel;
  final String tenantName;
  final String pageTitle;
  final List<Widget> pages;
  final List<String> labels;
  final List<IconData> icons;
  final int selectedIndex;
  final ValueChanged<int> onIndexChanged;
  final List<Widget>? drawerExtras;
  final int? navCount;
  final int? navSelectedIndex;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final displayNavCount = navCount ?? labels.length;
    final bottomIndex = navSelectedIndex ?? selectedIndex.clamp(0, displayNavCount - 1);

    return Container(
      decoration: BoxDecoration(gradient: AppTheme.mainBackground),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                pageTitle,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              ),
              Text(
                tenantName,
                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
              ),
            ],
          ),
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(1),
            child: Divider(height: 1, color: AppColors.borderBlue),
          ),
          actions: [
            Container(
              margin: const EdgeInsets.only(right: 4),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.bgSky,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AppColors.borderBlue),
              ),
              child: Text(
                roleLabel.toUpperCase(),
                style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: AppColors.navyPrimary, letterSpacing: 0.8),
              ),
            ),
            IconButton(
              tooltip: 'Sign out',
              icon: const Icon(Icons.logout_rounded, size: 20),
              onPressed: () => confirmSignOut(context, ref),
            ),
          ],
        ),
        drawer: Drawer(
          child: Column(
            children: [
              SaDrawerHeader(roleLabel: roleLabel, tenantName: tenantName),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                  children: [
                    if (drawerExtras != null) ...drawerExtras!,
                    for (var i = 0; i < labels.length; i++)
                      SaDrawerTile(
                        icon: icons[i],
                        label: labels[i],
                        selected: selectedIndex == i,
                        onTap: () {
                          Navigator.pop(context);
                          onIndexChanged(i);
                        },
                      ),
                  ],
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.logout_rounded, color: Color(0xFFFCA5A5), size: 20),
                title: const Text(
                  'Sign out',
                  style: TextStyle(color: Color(0xFFFCA5A5), fontWeight: FontWeight.w600, fontSize: 14),
                ),
                onTap: () {
                  Navigator.pop(context);
                  confirmSignOut(context, ref);
                },
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
        body: pages[selectedIndex],
        bottomNavigationBar: NavigationBar(
          selectedIndex: bottomIndex,
          onDestinationSelected: onIndexChanged,
          height: 64,
          labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
          indicatorColor: AppColors.bgSky,
          destinations: [
            for (var i = 0; i < displayNavCount; i++)
              NavigationDestination(
                icon: Icon(icons[i]),
                selectedIcon: Icon(icons[i], color: AppColors.navyPrimary),
                label: labels[i],
              ),
          ],
        ),
      ),
    );
  }
}
