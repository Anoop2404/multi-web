import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'sahodaya_api.dart';
import 'school_detail_screen.dart';

class SahodayaSchoolsScreen extends ConsumerStatefulWidget {
  const SahodayaSchoolsScreen({super.key});

  @override
  ConsumerState<SahodayaSchoolsScreen> createState() => _SahodayaSchoolsScreenState();
}

class _SahodayaSchoolsScreenState extends ConsumerState<SahodayaSchoolsScreen> {
  List<Map<String, dynamic>> _schools = [];
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
      final response = await sahodayaGet(ref, '/schools');
      final data = response['data'];
      _schools = data is List ? data.cast<Map<String, dynamic>>() : [];
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
        itemCount: _schools.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final school = _schools[index];
          return ListTile(
            title: Text(school['name']?.toString() ?? ''),
            subtitle: Text(
              '${school['school_prefix'] ?? '-'} · ${school['membership_status'] ?? ''} · ${school['student_count'] ?? 0} students',
            ),
            onTap: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => SahodayaSchoolDetailScreen(schoolId: school['id'].toString()),
                ),
              );
              await _load();
            },
          );
        },
      ),
    );
  }
}
