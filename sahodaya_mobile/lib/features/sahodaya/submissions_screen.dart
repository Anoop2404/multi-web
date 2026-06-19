import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/authenticated_document.dart';
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
          final fileCount = item['submitted_file_count'] as int? ?? 0;
          final paymentProofs = item['payment_proof_count'] as int? ?? 0;
          final studentImages = item['student_image_count'] as int? ?? 0;

          String? fileHint;
          if (fileCount > 0) {
            final parts = <String>[];
            if (paymentProofs > 0) parts.add('$paymentProofs payment');
            if (studentImages > 0) parts.add('$studentImages photo${studentImages == 1 ? '' : 's'}');
            fileHint = parts.join(' · ');
          }

          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: SaEntityCard(
              title: schoolName,
              subtitle: [
                if (year != null) 'Academic year $year',
                if (fileHint != null) fileHint,
              ].join('\n'),
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
                child: Icon(
                  fileCount > 0 ? Icons.folder_copy_outlined : Icons.assignment_outlined,
                  size: 20,
                  color: AppColors.navyPrimary,
                ),
              ),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => SahodayaSubmissionDetailScreen(submissionId: item['id'].toString()),
                  ),
                );
              },
              footer: fileCount > 0
                  ? _SubmissionPreviewStrip(
                      files: (item['preview_files'] as List?)?.cast<Map<String, dynamic>>() ?? [],
                    )
                  : null,
            ),
          );
        },
      ),
    );
  }
}

class _SubmissionPreviewStrip extends StatelessWidget {
  const _SubmissionPreviewStrip({required this.files});

  final List<Map<String, dynamic>> files;

  @override
  Widget build(BuildContext context) {
    if (files.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: SizedBox(
        height: 56,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: files.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final file = files[index];
            final path = file['path']?.toString();
            final type = file['type']?.toString() ?? 'file';
            if (path == null) return const SizedBox.shrink();

            return ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: SizedBox(
                width: 56,
                height: 56,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    AuthenticatedDocument(path: path, height: 56, fit: BoxFit.cover),
                    if (type == 'payment')
                      Positioned(
                        right: 2,
                        bottom: 2,
                        child: Container(
                          padding: const EdgeInsets.all(2),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.9),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Icon(Icons.receipt_long, size: 12, color: AppColors.navyPrimary),
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
