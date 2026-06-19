import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text(_error!));
    }

    final setup = _data?['setup'] as Map<String, dynamic>? ?? {};
    final stats = _data?['stats'] as Map<String, dynamic>? ?? {};
    final membership = _data?['membership_complete'] as Map<String, dynamic>?;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Setup checklist', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  _CheckItem(
                    done: setup['has_school_code'] == true,
                    label: 'School code configured',
                  ),
                  _CheckItem(
                    done: setup['has_classes'] == true,
                    label: 'Classes provisioned (${setup['class_count'] ?? 0})',
                  ),
                  _CheckItem(
                    done: (setup['student_count'] as int? ?? 0) > 0,
                    label: 'Students added (${setup['student_count'] ?? 0})',
                  ),
                  _CheckItem(
                    done: setup['has_registration'] == true,
                    label: 'Annual registration started',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _StatCard(
                  label: 'Active students',
                  value: '${stats['active_students'] ?? 0}',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _StatCard(
                  label: 'Classes',
                  value: '${stats['classes'] ?? 0}',
                ),
              ),
            ],
          ),
          if (membership != null) ...[
            const SizedBox(height: 12),
            Card(
              color: Colors.green.shade50,
              child: ListTile(
                leading: const Icon(Icons.verified, color: Colors.green),
                title: const Text('Membership complete'),
                subtitle: Text('${membership['academic_year']} · ${membership['reg_no']}'),
              ),
            ),
          ],
          if (setup['registration_status'] != null) ...[
            const SizedBox(height: 12),
            Card(
              child: ListTile(
                title: const Text('Registration status'),
                subtitle: Text(setup['registration_status'].toString()),
              ),
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
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(done ? Icons.check_circle : Icons.radio_button_unchecked, color: done ? Colors.green : null),
          const SizedBox(width: 8),
          Expanded(child: Text(label)),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label),
            const SizedBox(height: 8),
            Text(value, style: Theme.of(context).textTheme.headlineSmall),
          ],
        ),
      ),
    );
  }
}
