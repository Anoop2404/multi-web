import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';

class SchoolProfileScreen extends ConsumerStatefulWidget {
  const SchoolProfileScreen({super.key});

  @override
  ConsumerState<SchoolProfileScreen> createState() => _SchoolProfileScreenState();
}

class _SchoolProfileScreenState extends ConsumerState<SchoolProfileScreen> {
  Map<String, dynamic>? _data;
  final _profileFields = <String, TextEditingController>{};
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _loading = true;
  bool _savingProfile = false;
  bool _savingAccount = false;

  @override
  void dispose() {
    for (final c in _profileFields.values) {
      c.dispose();
    }
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await schoolGet(ref, '/registration/profile');
      _data = response['data'] as Map<String, dynamic>?;
      final profileData = (_data?['profile_data'] as Map?)?.cast<String, dynamic>() ?? {};
      final editable = (_data?['editable_fields'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      for (final field in editable) {
        final key = field['key'] as String;
        _profileFields.putIfAbsent(key, () => TextEditingController(text: profileData[key]?.toString() ?? ''));
      }
      final account = _data?['account'] as Map<String, dynamic>? ?? {};
      _nameController.text = account['name']?.toString() ?? '';
      _emailController.text = account['email']?.toString() ?? '';
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _saveProfile() async {
    setState(() => _savingProfile = true);
    try {
      final body = {for (final e in _profileFields.entries) e.key: e.value.text.trim()};
      await schoolPut(ref, '/registration/profile', body: body);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Registration details saved')));
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _saveAccount() async {
    setState(() => _savingAccount = true);
    try {
      final body = {
        'name': _nameController.text.trim(),
        'email': _emailController.text.trim(),
        if (_passwordController.text.isNotEmpty) 'password': _passwordController.text,
        if (_passwordController.text.isNotEmpty) 'password_confirmation': _passwordController.text,
      };
      await schoolPut(ref, '/registration/account', body: body);
      _passwordController.clear();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Account updated')));
      await _load();
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _savingAccount = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    final readOnly = (_data?['read_only_fields'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final editable = (_data?['editable_fields'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final account = _data?['account'] as Map<String, dynamic>? ?? {};

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const SaHeroBanner(
            eyebrow: 'School profile',
            title: 'Your registration details',
            subtitle: 'Update the information you submitted when joining this Sahodaya.',
          ),
          const SizedBox(height: 16),
          SaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SaSectionTitle('School identity'),
                ...readOnly.map(
                  (field) => SaDetailTile(
                    label: field['label'].toString(),
                    value: field['value']?.toString() ?? '—',
                    icon: Icons.badge_outlined,
                  ),
                ),
              ],
            ),
          ),
          if (editable.isNotEmpty) ...[
            const SizedBox(height: 16),
            SaCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SaSectionTitle('Registration Details'),
                  ...editable.map((field) {
                    final key = field['key'] as String;
                    final controller = _profileFields[key]!;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: TextFormField(
                        controller: controller,
                        decoration: InputDecoration(
                          labelText: '${field['label']}${field['required'] == true ? ' *' : ''}',
                        ),
                        maxLines: key == 'address' ? 3 : 1,
                        keyboardType: key.contains('phone') ? TextInputType.phone : TextInputType.text,
                      ),
                    );
                  }),
                  SaPrimaryButton(label: 'Save registration details', onPressed: _saveProfile, loading: _savingProfile),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          SaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SaSectionTitle('Login account'),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: account['email_verified'] == true ? const Color(0xFFF0FDF4) : const Color(0xFFFFFBEB),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: account['email_verified'] == true ? const Color(0xFFBBF7D0) : const Color(0xFFFDE68A),
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        account['email_verified'] == true ? Icons.verified_outlined : Icons.mark_email_unread_outlined,
                        size: 16,
                        color: account['email_verified'] == true ? const Color(0xFF15803D) : const Color(0xFFB45309),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          account['email_verified'] == true ? 'Email verified' : 'Email not verified — check your inbox',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: account['email_verified'] == true ? const Color(0xFF15803D) : const Color(0xFFB45309),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _nameController, decoration: const InputDecoration(labelText: 'Name')),
                const SizedBox(height: 12),
                TextFormField(controller: _emailController, decoration: const InputDecoration(labelText: 'Email'), keyboardType: TextInputType.emailAddress),
                const SizedBox(height: 12),
                TextFormField(controller: _passwordController, decoration: const InputDecoration(labelText: 'New password (optional)'), obscureText: true),
                const SizedBox(height: 16),
                SaPrimaryButton(label: 'Save account', onPressed: _saveAccount, loading: _savingAccount),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
