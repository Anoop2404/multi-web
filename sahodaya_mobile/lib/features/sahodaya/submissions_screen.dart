import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Text(_error!));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _submissions.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final item = _submissions[index];
          return ListTile(
            title: Text(item['school_name']?.toString() ?? item['school']?['name']?.toString() ?? 'School'),
            subtitle: Text(item['registration_status']?.toString() ?? ''),
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => SahodayaSubmissionDetailScreen(
                    submissionId: item['id'].toString(),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
