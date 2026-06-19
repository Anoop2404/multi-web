import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolSetupCodeScreen extends ConsumerStatefulWidget {
  const SchoolSetupCodeScreen({super.key});

  @override
  ConsumerState<SchoolSetupCodeScreen> createState() => _SchoolSetupCodeScreenState();
}

class _SchoolSetupCodeScreenState extends ConsumerState<SchoolSetupCodeScreen> {
  final _controller = TextEditingController();
  Map<String, dynamic>? _data;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await schoolGet(ref, '/setup/code');
      _data = response['data'] as Map<String, dynamic>?;
      _controller.text = _data?['school_code']?.toString() ?? '';
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await schoolPost(ref, '/setup/code', body: {'school_prefix': _controller.text.trim().toUpperCase()});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('School code saved')));
        Navigator.pop(context, true);
      }
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaPageScaffold(title: 'School Code', body: Center(child: CircularProgressIndicator()));

    final locked = _data?['code_locked'] == true;
    final code = _data?['school_code']?.toString();
    final suggested = _data?['suggested_code']?.toString();
    final example = _data?['reg_no_example']?.toString();

    return SaPageScaffold(
      title: 'School Code',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SaInfoBanner(
            title: 'What is the school code?',
            body: 'A short unique code for your school within this Sahodaya (e.g. AMU). '
                'Used in membership and student registration numbers.'
                '${example != null ? '\nExample: $example' : ''}',
          ),
          const SizedBox(height: 16),
          SaCard(
            child: locked && code != null
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('YOUR SCHOOL CODE', style: TextStyle(fontSize: 10, color: Colors.grey)),
                      const SizedBox(height: 8),
                      Text(code, style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, fontFamily: 'monospace')),
                      const SizedBox(height: 8),
                      const Text('Locked after first student registration number is issued.', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: _controller,
                        decoration: const InputDecoration(labelText: 'School code *'),
                        textCapitalization: TextCapitalization.characters,
                        onChanged: (v) => _controller.text = v.toUpperCase().replaceAll(RegExp(r'[^A-Z0-9]'), ''),
                      ),
                      if (suggested != null && _controller.text.isEmpty) ...[
                        const SizedBox(height: 8),
                        TextButton(
                          onPressed: () => setState(() => _controller.text = suggested),
                          child: Text('Use suggested: $suggested'),
                        ),
                      ],
                      const SizedBox(height: 16),
                      SaPrimaryButton(label: 'Save school code', onPressed: _save, loading: _saving),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
