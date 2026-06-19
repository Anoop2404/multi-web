import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolStudentFormScreen extends ConsumerStatefulWidget {
  const SchoolStudentFormScreen({
    super.key,
    this.student,
    required this.classes,
  });

  final Map<String, dynamic>? student;
  final List<Map<String, dynamic>> classes;

  @override
  ConsumerState<SchoolStudentFormScreen> createState() => _SchoolStudentFormScreenState();
}

class _SchoolStudentFormScreenState extends ConsumerState<SchoolStudentFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  String _gender = 'male';
  int? _classId;
  bool _saving = false;

  bool get _editing => widget.student != null;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.student?['name']?.toString() ?? '');
    _emailController = TextEditingController(text: widget.student?['parent_email']?.toString() ?? '');
    _gender = widget.student?['gender']?.toString() ?? 'male';
    _classId = widget.student?['school_class_id'] as int? ?? widget.classes.firstOrNull?['id'] as int?;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _classId == null) return;
    setState(() => _saving = true);
    try {
      final body = {
        'name': _nameController.text.trim(),
        'gender': _gender,
        'school_class_id': _classId,
        if (_emailController.text.isNotEmpty) 'parent_email': _emailController.text.trim(),
      };
      if (_editing) {
        await schoolPut(ref, '/students/${widget.student!['id']}', body: body);
      } else {
        await schoolPost(ref, '/students', body: body);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete() async {
    if (!_editing) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete student?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _saving = true);
    try {
      await schoolDelete(ref, '/students/${widget.student!['id']}');
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SaPageScaffold(
      title: _editing ? 'Edit student' : 'Add student',
      actions: [
        if (_editing)
          IconButton(
            icon: const Icon(Icons.delete_outline),
            onPressed: _saving ? null : _delete,
          ),
      ],
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              SaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SaSectionTitle('Student details'),
                    TextFormField(
                      controller: _nameController,
                      decoration: const InputDecoration(labelText: 'Name *'),
                      validator: (v) => v == null || v.isEmpty ? 'Required' : null,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<int>(
                      value: _classId,
                      decoration: const InputDecoration(labelText: 'Class *'),
                      items: widget.classes
                          .map(
                            (item) => DropdownMenuItem<int>(
                              value: item['id'] as int,
                              child: Text(item['name']?.toString() ?? ''),
                            ),
                          )
                          .toList(),
                      onChanged: (value) => setState(() => _classId = value),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      value: _gender,
                      decoration: const InputDecoration(labelText: 'Gender'),
                      items: const [
                        DropdownMenuItem(value: 'male', child: Text('Male')),
                        DropdownMenuItem(value: 'female', child: Text('Female')),
                        DropdownMenuItem(value: 'other', child: Text('Other')),
                      ],
                      onChanged: (value) => setState(() => _gender = value ?? 'male'),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(labelText: 'Parent email (optional)'),
                      keyboardType: TextInputType.emailAddress,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              SaPrimaryButton(
                label: _editing ? 'Save changes' : 'Add student',
                onPressed: _save,
                loading: _saving,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

extension _FirstOrNull<E> on List<E> {
  E? get firstOrNull => isEmpty ? null : first;
}
