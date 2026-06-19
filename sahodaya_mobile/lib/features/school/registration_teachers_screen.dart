import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolRegistrationTeachersScreen extends ConsumerStatefulWidget {
  const SchoolRegistrationTeachersScreen({super.key});

  @override
  ConsumerState<SchoolRegistrationTeachersScreen> createState() => _SchoolRegistrationTeachersScreenState();
}

class _SchoolRegistrationTeachersScreenState extends ConsumerState<SchoolRegistrationTeachersScreen> {
  Map<String, dynamic>? _data;
  final _nameController = TextEditingController();
  final _subjectController = TextEditingController();
  int? _typeId;
  bool _loading = true;

  @override
  void dispose() {
    _nameController.dispose();
    _subjectController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await schoolGet(ref, '/registration/teachers');
      _data = response['data'] as Map<String, dynamic>?;
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
    if (_nameController.text.trim().isEmpty) return;
    try {
      await schoolPost(ref, '/registration/teachers', body: {
        'name': _nameController.text.trim(),
        if (_subjectController.text.isNotEmpty) 'subject': _subjectController.text.trim(),
        if (_typeId != null) 'teaching_type_id': _typeId,
      });
      _nameController.clear();
      _subjectController.clear();
      _typeId = null;
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _remove(int id) async {
    try {
      await schoolDelete(ref, '/registration/teachers/$id');
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _submit() async {
    try {
      await schoolPost(ref, '/registration/submit-track', body: {'track': 'teachers'});
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaPageScaffold(title: 'Submission Teachers', body: SaLoadingView());

    final teachers = (_data?['teachers'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final types = (_data?['teaching_types'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final status = (_data?['submission'] as Map?)?['teacher_status']?.toString() ?? 'pending';
    final canEdit = status == 'pending' || status == 'rejected';

    return SaPageScaffold(
      title: 'Submission Teachers',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [const Text('Status: ', style: TextStyle(color: Colors.grey)), SaStatusChip(status)]),
          const SizedBox(height: 16),
          if (canEdit)
            SaCard(
              child: Column(
                children: [
                  TextFormField(controller: _nameController, decoration: const InputDecoration(labelText: 'Name *')),
                  const SizedBox(height: 12),
                  TextFormField(controller: _subjectController, decoration: const InputDecoration(labelText: 'Subject')),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _typeId,
                    decoration: const InputDecoration(labelText: 'Teaching type'),
                    items: [
                      const DropdownMenuItem<int>(child: Text('—')),
                      ...types.map((t) => DropdownMenuItem<int>(value: t['id'] as int, child: Text(t['label']?.toString() ?? ''))),
                    ],
                    onChanged: (v) => setState(() => _typeId = v),
                  ),
                  const SizedBox(height: 12),
                  SaPrimaryButton(label: 'Add teacher', onPressed: _add),
                ],
              ),
            ),
          const SizedBox(height: 16),
          if (teachers.isEmpty)
            const SaEmptyView(title: 'No teachers added yet', subtitle: 'Add teachers above, then submit for review.', icon: Icons.co_present_outlined)
          else
            ...teachers.map(
              (teacher) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: SaEntityCard(
                  title: teacher['name']?.toString() ?? 'Teacher',
                  subtitle: '${teacher['subject'] ?? '—'} · ${teacher['teaching_type']?['label'] ?? '—'}',
                  trailing: canEdit
                      ? IconButton(
                          icon: const Icon(Icons.delete_outline, color: Color(0xFFDC2626)),
                          onPressed: () => _remove(teacher['id'] as int),
                        )
                      : null,
                ),
              ),
            ),
          if (canEdit) ...[
            const SizedBox(height: 16),
            SaSubmitButton(label: 'Submit teachers & continue', onPressed: _submit),
          ],
        ],
      ),
    );
  }
}
