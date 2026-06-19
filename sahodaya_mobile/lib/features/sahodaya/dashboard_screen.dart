import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'sahodaya_api.dart';

class SahodayaDashboardScreen extends ConsumerStatefulWidget {
  const SahodayaDashboardScreen({super.key});

  @override
  ConsumerState<SahodayaDashboardScreen> createState() => _SahodayaDashboardScreenState();
}

class _SahodayaDashboardScreenState extends ConsumerState<SahodayaDashboardScreen> {
  Map<String, dynamic>? _stats;
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
      final response = await sahodayaGet(ref, '/dashboard');
      _stats = (response['data'] as Map<String, dynamic>?)?['stats'] as Map<String, dynamic>?;
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
      child: GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        children: [
          _StatTile(label: 'Approved schools', value: '${_stats?['approved_schools'] ?? 0}'),
          _StatTile(label: 'Pending schools', value: '${_stats?['pending_schools'] ?? 0}'),
          _StatTile(label: 'Pending payments', value: '${_stats?['pending_payments'] ?? 0}'),
          _StatTile(label: 'Total students', value: '${_stats?['total_students'] ?? 0}'),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value});

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
            const Spacer(),
            Text(value, style: Theme.of(context).textTheme.headlineSmall),
          ],
        ),
      ),
    );
  }
}
