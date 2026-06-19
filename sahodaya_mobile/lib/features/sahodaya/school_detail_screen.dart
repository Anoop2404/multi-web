import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'sahodaya_api.dart';

class SahodayaSchoolDetailScreen extends ConsumerStatefulWidget {
  const SahodayaSchoolDetailScreen({super.key, required this.schoolId});

  final String schoolId;

  @override
  ConsumerState<SahodayaSchoolDetailScreen> createState() => _SahodayaSchoolDetailScreenState();
}

class _SahodayaSchoolDetailScreenState extends ConsumerState<SahodayaSchoolDetailScreen> {
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
      final response = await sahodayaGet(ref, '/schools/${widget.schoolId}');
      setState(() => _data = response['data'] as Map<String, dynamic>?);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _reject() async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Reject school'),
          content: TextField(
            controller: controller,
            decoration: const InputDecoration(labelText: 'Reason'),
            maxLines: 3,
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              child: const Text('Reject'),
            ),
          ],
        );
      },
    );
    if (reason == null || reason.isEmpty) return;

    try {
      await sahodayaPost(ref, '/schools/${widget.schoolId}/reject', body: {'reason': reason});
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final school = _data?['school'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(
        title: Text(school?['name']?.toString() ?? 'School'),
        actions: [
          if (school?['membership_status'] != 'rejected')
            TextButton(onPressed: _reject, child: const Text('Reject')),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Card(
                      child: ListTile(
                        title: Text(school?['name']?.toString() ?? ''),
                        subtitle: Text(
                          'Code: ${school?['school_prefix'] ?? '-'} · Status: ${school?['membership_status'] ?? ''}',
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text('Students: ${school?['student_count'] ?? 0}'),
                    Text('Classes: ${school?['classes_count'] ?? 0}'),
                    if (_data?['registration'] != null) ...[
                      const SizedBox(height: 12),
                      Text('Registration: ${(_data!['registration'] as Map)['registration_status']}'),
                    ],
                  ],
                ),
    );
  }
}
