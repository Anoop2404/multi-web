import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolRegistrationCountsScreen extends ConsumerStatefulWidget {
  const SchoolRegistrationCountsScreen({super.key});

  @override
  ConsumerState<SchoolRegistrationCountsScreen> createState() => _SchoolRegistrationCountsScreenState();
}

class _SchoolRegistrationCountsScreenState extends ConsumerState<SchoolRegistrationCountsScreen> {
  Map<String, dynamic>? _data;
  final _rows = <int, Map<String, int>>{};
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await schoolGet(ref, '/registration/counts');
      _data = response['data'] as Map<String, dynamic>?;
      final categories = (_data?['categories'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      final counts = _data?['counts'] as Map?;
      _rows.clear();
      for (final cat in categories) {
        final id = cat['id'] as int;
        final existing = counts?[id.toString()] ?? counts?[id];
        _rows[id] = {
          'male_count': (existing?['male_count'] as int?) ?? 0,
          'female_count': (existing?['female_count'] as int?) ?? 0,
          'total_count': (existing?['total_count'] as int?) ?? 0,
        };
      }
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await schoolPost(ref, '/registration/counts', body: {
        'counts': _rows.entries
            .map((e) => {
                  'class_category_id': e.key,
                  'male_count': e.value['male_count'],
                  'female_count': e.value['female_count'],
                  'total_count': e.value['total_count'],
                })
            .toList(),
      });
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Counts saved')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _submit() async {
    try {
      await schoolPost(ref, '/registration/submit-track', body: {'track': 'counts'});
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaPageScaffold(title: 'Student Counts', body: Center(child: CircularProgressIndicator()));

    final categories = (_data?['categories'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final status = (_data?['submission'] as Map?)?['counts_status']?.toString() ?? 'pending';
    final canEdit = status == 'pending' || status == 'rejected';

    return SaPageScaffold(
      title: 'Student Counts',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              const Text('Status: ', style: TextStyle(color: Colors.grey)),
              SaStatusChip(status),
            ],
          ),
          const SizedBox(height: 16),
          SaCard(
            child: Column(
              children: [
                for (final cat in categories) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(cat['label']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(child: _countField('Male', cat['id'] as int, 'male_count', canEdit)),
                            const SizedBox(width: 8),
                            Expanded(child: _countField('Female', cat['id'] as int, 'female_count', canEdit)),
                            const SizedBox(width: 8),
                            Expanded(child: _countField('Total', cat['id'] as int, 'total_count', canEdit)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Divider(),
                ],
                if (canEdit) ...[
                  const SizedBox(height: 8),
                  SaPrimaryButton(label: 'Save counts', onPressed: _save, loading: _saving),
                ],
              ],
            ),
          ),
          if (canEdit) ...[
            const SizedBox(height: 16),
            SaSubmitButton(label: 'Submit counts & continue', onPressed: _submit),
          ],
        ],
      ),
    );
  }

  Widget _countField(String label, int catId, String key, bool enabled) {
    return TextFormField(
      enabled: enabled,
      initialValue: '${_rows[catId]?[key] ?? 0}',
      decoration: InputDecoration(labelText: label),
      keyboardType: TextInputType.number,
      onChanged: (v) => _rows[catId]![key] = int.tryParse(v) ?? 0,
    );
  }
}
