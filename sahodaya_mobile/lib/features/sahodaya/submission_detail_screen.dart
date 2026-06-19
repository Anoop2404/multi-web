import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'sahodaya_api.dart';

class SahodayaSubmissionDetailScreen extends ConsumerStatefulWidget {
  const SahodayaSubmissionDetailScreen({super.key, required this.submissionId});

  final String submissionId;

  @override
  ConsumerState<SahodayaSubmissionDetailScreen> createState() => _SahodayaSubmissionDetailScreenState();
}

class _SahodayaSubmissionDetailScreenState extends ConsumerState<SahodayaSubmissionDetailScreen> {
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
      final response = await sahodayaGet(ref, '/submissions/${widget.submissionId}');
      setState(() => _data = response['data'] as Map<String, dynamic>?);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final school = _data?['school'] as Map<String, dynamic>?;
    final schoolName = school?['name']?.toString() ?? 'Submission';
    final year = _data?['academic_year']?.toString() ?? '';
    final regStatus = _data?['registration_status']?.toString();
    final students = (_data?['students'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final teachers = (_data?['teachers'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final studentTotal = _data?['student_total'];

    return SaPageScaffold(
      title: 'Submission review',
      body: _loading
          ? const SaLoadingView()
          : _error != null
              ? SaErrorView(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  color: AppColors.navyPrimary,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      SaHeroBanner(
                        eyebrow: year.isNotEmpty ? 'Academic year $year' : 'Annual registration',
                        title: schoolName,
                        subtitle: regStatus != null
                            ? 'Overall status: ${regStatus.replaceAll('_', ' ')}'
                            : 'Review student and teacher submission tracks.',
                      ),
                      const SizedBox(height: 16),
                      const SaSectionTitle('Track status'),
                      SaCard(
                        child: Column(
                          children: [
                            _StatusRow(label: 'Student counts', status: _data?['counts_status']?.toString() ?? '—'),
                            const Divider(height: 1),
                            _StatusRow(label: 'Student records', status: _data?['full_records_status']?.toString() ?? '—'),
                            const Divider(height: 1),
                            _StatusRow(label: 'Teachers', status: _data?['teacher_status']?.toString() ?? '—'),
                          ],
                        ),
                      ),
                      if (studentTotal != null) ...[
                        const SizedBox(height: 16),
                        SaStatCard(
                          label: 'Total students reported',
                          value: '$studentTotal',
                          icon: '👨‍🎓',
                          color: SaStatColor.navy,
                        ),
                      ],
                      const SizedBox(height: 20),
                      const SaSectionTitle('Students'),
                      if (students.isEmpty)
                        const SaEmptyView(
                          title: 'No student records',
                          subtitle: 'This school may have submitted counts only.',
                          icon: Icons.people_outline,
                        )
                      else
                        ...students.map(
                          (student) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: SaEntityCard(
                              title: student['name']?.toString() ?? 'Student',
                              subtitle: [
                                student['class']?.toString(),
                                student['section']?.toString(),
                              ].where((v) => v != null && v.isNotEmpty).join(' · '),
                              leading: Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: AppColors.bgSky,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                alignment: Alignment.center,
                                child: const Icon(Icons.person_outline, size: 18, color: AppColors.navyPrimary),
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 20),
                      const SaSectionTitle('Teachers'),
                      if (teachers.isEmpty)
                        const SaEmptyView(
                          title: 'No teachers listed',
                          subtitle: 'Teacher registration may not be required for this school.',
                          icon: Icons.co_present_outlined,
                        )
                      else
                        ...teachers.map(
                          (teacher) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: SaEntityCard(
                              title: teacher['name']?.toString() ?? 'Teacher',
                              subtitle: teacher['subject']?.toString() ?? teacher['teaching_type']?['label']?.toString(),
                              leading: Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: AppColors.bgSky,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                alignment: Alignment.center,
                                child: const Icon(Icons.co_present_outlined, size: 18, color: AppColors.navyPrimary),
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}

class _StatusRow extends StatelessWidget {
  const _StatusRow({required this.label, required this.status});

  final String label;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.textDark))),
          if (status != '—') SaStatusChip(status) else Text(status, style: const TextStyle(color: Color(0xFF94A3B8))),
        ],
      ),
    );
  }
}
