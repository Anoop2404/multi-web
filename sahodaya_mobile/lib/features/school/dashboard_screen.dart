import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/auth_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolDashboardScreen extends ConsumerStatefulWidget {
  const SchoolDashboardScreen({super.key});

  @override
  ConsumerState<SchoolDashboardScreen> createState() => _SchoolDashboardScreenState();
}

class _SchoolDashboardScreenState extends ConsumerState<SchoolDashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await schoolGet(ref, '/dashboard');
      setState(() => _data = response['data'] as Map<String, dynamic>?);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaLoadingView();
    if (_error != null) return SaErrorView(message: _error!, onRetry: _load);

    final session = ref.watch(authControllerProvider).session!;
    final setup = _data?['setup'] as Map<String, dynamic>? ?? {};
    final stats = _data?['stats'] as Map<String, dynamic>? ?? {};
    final membership = _data?['membership_complete'] as Map<String, dynamic>?;

    final checks = [
      (setup['has_school_code'] == true, 'School code configured'),
      (setup['has_classes'] == true, 'Classes provisioned (${setup['class_count'] ?? 0})'),
      ((setup['student_count'] as int? ?? 0) > 0, 'Students added (${setup['student_count'] ?? 0})'),
      (setup['has_registration'] == true, 'Annual registration started'),
    ];

    return RefreshIndicator(
      color: AppColors.navyPrimary,
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SaHeroBanner(
            eyebrow: 'School Portal',
            title: session.user.tenantName ?? 'Dashboard',
            subtitle: 'Complete setup steps and submit your annual registration.',
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: SaStatCard(
                  label: 'Active students',
                  value: '${stats['active_students'] ?? 0}',
                  icon: '👨‍🎓',
                  color: SaStatColor.blue,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: SaStatCard(
                  label: 'Classes',
                  value: '${stats['classes'] ?? 0}',
                  icon: '🏫',
                  color: SaStatColor.navy,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const SaSectionTitle('Setup checklist'),
          SaCard(
            child: Column(
              children: [
                for (var i = 0; i < checks.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  _CheckItem(done: checks[i].$1, label: checks[i].$2),
                ],
              ],
            ),
          ),
          if (membership != null) ...[
            const SizedBox(height: 12),
            SaCard(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCFCE7),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.verified, color: Color(0xFF15803D)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Membership complete', style: TextStyle(fontWeight: FontWeight.w700)),
                        Text(
                          '${membership['academic_year']} · ${membership['reg_no']}',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (setup['registration_status'] != null) ...[
            const SizedBox(height: 12),
            SaEntityCard(
              title: 'Registration status',
              subtitle: setup['registration_status'].toString().replaceAll('_', ' '),
              status: setup['registration_status'].toString(),
              leading: const Icon(Icons.assignment_outlined, color: AppColors.navyPrimary),
            ),
          ],
        ],
      ),
    );
  }
}

class _CheckItem extends StatelessWidget {
  const _CheckItem({required this.done, required this.label});

  final bool done;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Icon(
            done ? Icons.check_circle_rounded : Icons.radio_button_unchecked,
            color: done ? const Color(0xFF15803D) : const Color(0xFFCBD5E1),
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: done ? FontWeight.w600 : FontWeight.w500,
                color: done ? const Color(0xFF334155) : const Color(0xFF94A3B8),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
