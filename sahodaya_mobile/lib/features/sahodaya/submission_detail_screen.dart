import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
    final submission = _data?['submission'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(title: const Text('Submission review')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (submission != null) ...[
                      Text('Counts: ${submission['counts_status']}'),
                      Text('Records: ${submission['full_records_status']}'),
                      Text('Teachers: ${submission['teacher_status']}'),
                    ],
                    const SizedBox(height: 16),
                    Text('Students', style: Theme.of(context).textTheme.titleMedium),
                    ...((_data?['students'] as List?) ?? []).map(
                      (student) => ListTile(
                        title: Text(student['name']?.toString() ?? ''),
                        subtitle: Text(student['class']?.toString() ?? ''),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text('Teachers', style: Theme.of(context).textTheme.titleMedium),
                    ...((_data?['teachers'] as List?) ?? []).map(
                      (teacher) => ListTile(
                        title: Text(teacher['name']?.toString() ?? ''),
                        subtitle: Text(teacher['subject']?.toString() ?? ''),
                      ),
                    ),
                  ],
                ),
    );
  }
}
