import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'sahodaya_api.dart';
import 'submission_detail_screen.dart';

class SahodayaSubmissionsScreen extends ConsumerStatefulWidget {
  const SahodayaSubmissionsScreen({super.key});

  @override
  ConsumerState<SahodayaSubmissionsScreen> createState() => _SahodayaSubmissionsScreenState();
}

class _SahodayaSubmissionsScreenState extends ConsumerState<SahodayaSubmissionsScreen> {
  List<Map<String, dynamic>> _submissions = [];
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
      final response = await sahodayaGet(ref, '/submissions');
      final data = response['data'];
      _submissions = data is List ? data.cast<Map<String, dynamic>>() : [];
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaLoadingView();
    if (_error != null) return SaErrorView(message: _error!, onRetry: _load);

    if (_submissions.isEmpty) {
      return RefreshIndicator(
        color: AppColors.navyPrimary,
        onRefresh: _load,
        child: ListView(
          children: const [
            SizedBox(height: 120),
            SaEmptyView(
              title: 'No submissions yet',
              subtitle: 'Annual registration submissions from schools will appear here.',
              icon: Icons.fact_check_outlined,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: AppColors.navyPrimary,
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _submissions.length,
        itemBuilder: (context, index) {
          final item = _submissions[index];
          final schoolName = item['school_name']?.toString() ?? item['school']?['name']?.toString() ?? 'School';
          final status = item['registration_status']?.toString() ?? 'draft';
          final year = item['academic_year']?.toString();

          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: SaEntityCard(
              title: schoolName,
              subtitle: year != null ? 'Academic year $year' : 'Annual registration',
              status: status,
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.bgSky,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.borderBlue),
                ),
                alignment: Alignment.center,
                child: const Icon(Icons.assignment_outlined, size: 20, color: AppColors.navyPrimary),
              ),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => SahodayaSubmissionDetailScreen(submissionId: item['id'].toString()),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
