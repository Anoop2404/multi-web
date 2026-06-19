import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/widgets/sa_widgets.dart';
import 'registration_counts_screen.dart';
import 'registration_submission_students_screen.dart';
import 'registration_teachers_screen.dart';
import 'school_api.dart';
import 'setup_code_screen.dart';

class SchoolRegistrationScreen extends ConsumerStatefulWidget {
  const SchoolRegistrationScreen({super.key});

  @override
  ConsumerState<SchoolRegistrationScreen> createState() => _SchoolRegistrationScreenState();
}

class _SchoolRegistrationScreenState extends ConsumerState<SchoolRegistrationScreen> {
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
      final response = await schoolGet(ref, '/registration');
      setState(() => _data = response['data'] as Map<String, dynamic>?);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _begin() async {
    try {
      await schoolPost(ref, '/registration/begin');
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _openWizard(Widget screen) async {
    final changed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => screen));
    if (changed == true) await _load();
  }

  Future<void> _uploadPayment() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(source: ImageSource.gallery);
    if (file == null) return;

    try {
      final formData = FormData.fromMap({
        'payment_proof': await MultipartFile.fromFile(file.path),
      });
      await schoolMultipart(ref, '/registration/payment', formData: formData);
      await _load();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Payment proof uploaded')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Text(_error!));

    final registration = _data?['registration'] as Map<String, dynamic>?;
    final profile = _data?['profile'] as Map<String, dynamic>?;
    final canBegin = _data?['can_begin'] == true;
    final status = registration?['registration_status']?.toString() ?? 'not_started';
    final submission = registration?['submission'] as Map<String, dynamic>?;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SaCard(
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Academic year ${_data?['academic_year'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 4),
                      Text('Status: $status', style: const TextStyle(fontSize: 13, color: Colors.grey)),
                    ],
                  ),
                ),
                SaStatusChip(status),
              ],
            ),
          ),
          if (canBegin) ...[
            const SizedBox(height: 12),
            SaNavyButton(label: 'Begin annual registration', onPressed: _begin),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SchoolSetupCodeScreen())),
              child: const Text('Set school code first'),
            ),
          ],
          if (registration != null && profile != null) ...[
            const SizedBox(height: 16),
            const SaSectionTitle('Wizard steps'),
            if (profile['student_data_mode'] == 'counts_only')
              _TrackCard(
                title: 'Student counts',
                status: submission?['counts_status']?.toString() ?? 'pending',
                onOpen: () => _openWizard(const SchoolRegistrationCountsScreen()),
              )
            else
              _TrackCard(
                title: 'Student records',
                status: submission?['full_records_status']?.toString() ?? 'pending',
                onOpen: () => _openWizard(const SchoolRegistrationSubmissionStudentsScreen()),
              ),
            if (profile['teacher_registration_enabled'] == true)
              _TrackCard(
                title: 'Teachers',
                status: submission?['teacher_status']?.toString() ?? 'pending',
                onOpen: () => _openWizard(const SchoolRegistrationTeachersScreen()),
              ),
            if (status == 'payment_pending' || status == 'payment_rejected') ...[
              const SizedBox(height: 16),
              SaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Membership fee: ₹${registration['membership_fee_amount'] ?? '-'}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    if (profile['payment_details_text'] != null) ...[
                      const SizedBox(height: 8),
                      Text(profile['payment_details_text'].toString(), style: const TextStyle(fontSize: 13, color: Colors.grey)),
                    ],
                    const SizedBox(height: 12),
                    SaPrimaryButton(label: 'Upload payment proof', onPressed: _uploadPayment),
                  ],
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _TrackCard extends StatelessWidget {
  const _TrackCard({required this.title, required this.status, required this.onOpen});

  final String title;
  final String status;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final canEdit = status == 'pending' || status == 'rejected';
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SaCard(
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Row(
            children: [
              const Text('Status: '),
              SaStatusChip(status),
            ],
          ),
          trailing: canEdit
              ? TextButton(onPressed: onOpen, child: const Text('Open'))
              : const Icon(Icons.check_circle, color: Colors.green),
          onTap: canEdit ? onOpen : null,
        ),
      ),
    );
  }
}
