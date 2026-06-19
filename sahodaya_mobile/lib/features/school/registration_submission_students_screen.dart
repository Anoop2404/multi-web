import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolRegistrationSubmissionStudentsScreen extends ConsumerStatefulWidget {
  const SchoolRegistrationSubmissionStudentsScreen({super.key});

  @override
  ConsumerState<SchoolRegistrationSubmissionStudentsScreen> createState() =>
      _SchoolRegistrationSubmissionStudentsScreenState();
}

class _SchoolRegistrationSubmissionStudentsScreenState extends ConsumerState<SchoolRegistrationSubmissionStudentsScreen> {
  Map<String, dynamic>? _data;
  final _nameController = TextEditingController();
  final _sectionController = TextEditingController();
  final _guardianController = TextEditingController();
  int? _classId;
  bool _loading = true;

  @override
  void dispose() {
    _nameController.dispose();
    _sectionController.dispose();
    _guardianController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await schoolGet(ref, '/registration/submission-students');
      _data = response['data'] as Map<String, dynamic>?;
      final classes = (_data?['classes'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      _classId ??= classes.firstOrNull?['id'] as int?;
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _add() async {
    if (_classId == null || _nameController.text.trim().isEmpty) return;
    try {
      await schoolPost(ref, '/registration/submission-students', body: {
        'name': _nameController.text.trim(),
        'school_class_id': _classId,
        if (_sectionController.text.isNotEmpty) 'section': _sectionController.text.trim(),
        if (_guardianController.text.isNotEmpty) 'guardian_name': _guardianController.text.trim(),
      });
      _nameController.clear();
      _sectionController.clear();
      _guardianController.clear();
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _remove(int id) async {
    try {
      await schoolDelete(ref, '/registration/submission-students/$id');
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _submit() async {
    try {
      await schoolPost(ref, '/registration/submit-track', body: {'track': 'full_records'});
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const SaPageScaffold(title: 'Submission Students', body: Center(child: CircularProgressIndicator()));
    }

    final classes = (_data?['classes'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final students = (_data?['students'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final status = (_data?['submission'] as Map?)?['full_records_status']?.toString() ?? 'pending';
    final canEdit = status == 'pending' || status == 'rejected';

    return SaPageScaffold(
      title: 'Submission Students',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [const Text('Status: ', style: TextStyle(color: Colors.grey)), SaStatusChip(status)]),
          const SizedBox(height: 16),
          if (canEdit && classes.isNotEmpty)
            SaCard(
              child: Column(
                children: [
                  TextFormField(controller: _nameController, decoration: const InputDecoration(labelText: 'Name *')),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _classId,
                    decoration: const InputDecoration(labelText: 'Class *'),
                    items: classes
                        .map((c) => DropdownMenuItem<int>(value: c['id'] as int, child: Text('Class ${c['name']}')))
                        .toList(),
                    onChanged: (v) => setState(() => _classId = v),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(controller: _sectionController, decoration: const InputDecoration(labelText: 'Section')),
                  const SizedBox(height: 12),
                  TextFormField(controller: _guardianController, decoration: const InputDecoration(labelText: 'Guardian name')),
                  const SizedBox(height: 12),
                  SaPrimaryButton(label: 'Add student', onPressed: _add),
                ],
              ),
            ),
          const SizedBox(height: 16),
          SaCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final student in students)
                  ListTile(
                    title: Text(student['name']?.toString() ?? ''),
                    subtitle: Text('${student['school_class']?['name'] ?? student['class']} · ${student['section'] ?? '—'}'),
                    trailing: canEdit
                        ? IconButton(icon: const Icon(Icons.delete_outline, color: Colors.red), onPressed: () => _remove(student['id'] as int))
                        : null,
                  ),
                if (students.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('No students added yet'))),
              ],
            ),
          ),
          if (canEdit) ...[
            const SizedBox(height: 16),
            SaNavyButton(label: 'Submit for review', onPressed: _submit),
          ],
        ],
      ),
    );
  }
}

extension _FirstOrNull<E> on List<E> {
  E? get firstOrNull => isEmpty ? null : first;
}
