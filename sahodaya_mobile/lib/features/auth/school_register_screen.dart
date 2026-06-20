import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/widgets/sa_widgets.dart';
import 'login_widgets.dart';
import 'school_register_api.dart';

class SchoolRegisterScreen extends StatefulWidget {
  const SchoolRegisterScreen({super.key});

  @override
  State<SchoolRegisterScreen> createState() => _SchoolRegisterScreenState();
}

class _SchoolRegisterScreenState extends State<SchoolRegisterScreen> {
  final _api = SchoolRegisterApi();
  final _values = <String, String>{};
  final _controllers = <String, TextEditingController>{};
  final _errors = <String, String>{};
  final _validationTimers = <String, Timer>{};
  final _validating = <String>{};

  static const _liveValidateFields = {'school_email', 'school_prefix', 'cbse_affiliation'};

  Map<String, dynamic>? _form;
  bool _loading = true;
  bool _submitting = false;
  int _step = 1;
  String? _loadError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final timer in _validationTimers.values) {
      timer.cancel();
    }
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController _controllerFor(String key) {
    return _controllers.putIfAbsent(key, () => TextEditingController(text: _values[key] ?? ''));
  }

  Future<void> _load() async {
    try {
      final data = await _api.loadForm();
      if (mounted) {
        setState(() {
          _form = data;
          _loading = false;
        });
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _loadError = error.message;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loadError = 'Could not load registration form.';
          _loading = false;
        });
      }
    }
  }

  Map<String, dynamic> get _fields => (_form?['fields'] as Map<String, dynamic>?) ?? {};

  Map<String, String> get _classOptions {
    final raw = _form?['highest_class_options'];
    if (raw is Map<String, dynamic>) {
      return raw.map((key, value) => MapEntry(key, value.toString()));
    }
    return const {};
  }

  bool get _twoStep => _form?['two_step'] as bool? ?? false;

  List<String> _enabledKeysForGroup(String group) {
    return _fields.entries
        .where((entry) {
          final field = entry.value as Map<String, dynamic>;
          return field['enabled'] == true && field['group'] == group && entry.key != 'school_name';
        })
        .map((entry) => entry.key)
        .toList();
  }

  bool get _hasSchoolStep => true;

  bool get _hasStep2 => _enabledKeysForGroup('principal').isNotEmpty || _enabledKeysForGroup('account').isNotEmpty;

  bool get _showSchoolStep => _hasSchoolStep && (!_twoStep || _step == 1);

  bool get _showPrincipalStep => _hasStep2 && (!_twoStep || _step == 2);

  Map<String, dynamic>? _field(String key) => _fields[key] as Map<String, dynamic>?;

  void _scheduleFieldValidation(String key) {
    if (!_liveValidateFields.contains(key)) return;

    _validationTimers[key]?.cancel();
    _validationTimers[key] = Timer(const Duration(milliseconds: 450), () => _validateField(key));
  }

  Future<void> _validateField(String key) async {
    if (!_liveValidateFields.contains(key)) return;

    final value = _values[key]?.trim() ?? '';
    if (value.isEmpty) {
      if (mounted) {
        setState(() {
          _errors.remove(key);
          _validating.remove(key);
        });
      }
      return;
    }

    setState(() => _validating.add(key));

    try {
      await _api.validateField(key, value);
      if (mounted) {
        setState(() {
          _errors.remove(key);
          _validating.remove(key);
        });
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      final message = _errorMessageFor(error, key);
      setState(() {
        if (message != null) {
          _errors[key] = message;
        } else {
          _errors.remove(key);
        }
        _validating.remove(key);
      });
    }
  }

  String? _errorMessageFor(ApiException error, String key) {
    final fieldError = error.errors?[key];
    if (fieldError is List && fieldError.isNotEmpty) {
      return fieldError.first.toString();
    }
    if (fieldError is String && fieldError.isNotEmpty) {
      return fieldError;
    }
    return error.statusCode == 422 ? error.message : null;
  }

  Future<bool> _validateLiveFields(Iterable<String> keys) async {
    var valid = true;
    for (final key in keys) {
      if (!_liveValidateFields.contains(key)) continue;
      final value = _values[key]?.trim() ?? '';
      if (value.isEmpty) continue;
      await _validateField(key);
      if (_errors.containsKey(key)) valid = false;
    }
    return valid;
  }

  Future<void> _submit() async {
    setState(() => _errors.clear());
    final body = <String, dynamic>{'school_name': _values['school_name']?.trim()};
    for (final key in _fields.keys) {
      final field = _field(key);
      if (field == null || field['enabled'] != true || key == 'password_confirmation') continue;
      final value = _values[key]?.trim();
      if (value != null && value.isNotEmpty) {
        body[key] = value;
      }
    }

    setState(() => _submitting = true);
    try {
      final message = await _api.submit(body);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Application Submitted'),
          content: Text(message),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('OK')),
          ],
        ),
      );
      if (mounted) context.go('/login');
    } on ApiException catch (error) {
      if (mounted) {
        final validation = error.errors;
        if (validation != null) {
          setState(() {
            _errors.clear();
            for (final entry in validation.entries) {
              final value = entry.value;
              final text = value is List && value.isNotEmpty ? value.first.toString() : error.message;
              _errors[entry.key] = text;
            }
          });
          final firstErrorStep = _errors.keys.any((key) => _field(key)?['group'] == 'school') ? 1 : 2;
          setState(() => _step = _twoStep ? firstErrorStep : 1);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
        }
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  bool _validateStep(int step) {
    _errors.removeWhere((key, _) => _liveValidateFields.contains(key));
    var valid = true;

    void requireField(String key) {
      final field = _field(key);
      if (field == null || field['enabled'] != true) return;
      if (field['required'] == true && (_values[key]?.trim().isEmpty ?? true)) {
        _errors[key] = '${field['label']} is required.';
        valid = false;
      }
    }

    if (step == 1) {
      requireField('school_name');
      for (final key in _enabledKeysForGroup('school')) {
        requireField(key);
      }
    } else {
      for (final key in [..._enabledKeysForGroup('principal'), ..._enabledKeysForGroup('account')]) {
        requireField(key);
      }
    }

    setState(() {});
    return valid;
  }

  Future<void> _nextStep() async {
    if (!_validateStep(1)) return;
    final keys = ['school_name', ..._enabledKeysForGroup('school')];
    if (!await _validateLiveFields(keys)) return;
    setState(() => _step = 2);
  }

  Future<void> _register() async {
    final step = _twoStep ? 2 : 1;
    if (!_validateStep(step)) return;
    final keys = step == 1
        ? ['school_name', ..._enabledKeysForGroup('school')]
        : [..._enabledKeysForGroup('principal'), ..._enabledKeysForGroup('account')];
    if (!await _validateLiveFields(keys)) return;
    await _submit();
  }

  @override
  Widget build(BuildContext context) {
    final tenantName = _form?['tenant_name']?.toString() ?? 'School Registration';
    final logoUrl = _form?['logo_url']?.toString();

    return Scaffold(
      body: Stack(
        children: [
          const LoginBackground(),
          SafeArea(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFFFBBF24)))
                : _loadError != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_loadError!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)),
                              const SizedBox(height: 16),
                              TextButton(onPressed: () => context.go('/portal'), child: const Text('Back to portal')),
                            ],
                          ),
                        ),
                      )
                    : SingleChildScrollView(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            InkWell(
                              onTap: () => context.go('/portal'),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.chevron_left, size: 18, color: Color(0x8CFFFFFF)),
                                  SizedBox(width: 4),
                                  Text('Back to portal', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0x8CFFFFFF))),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),
                            DecoratedBox(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(24),
                                boxShadow: const [BoxShadow(color: Color(0x8C000000), blurRadius: 80, offset: Offset(0, 32))],
                                border: Border.all(color: Color(0x14FFFFFF)),
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(24),
                                child: Column(
                                  children: [
                                    if (logoUrl != null)
                                      Padding(
                                        padding: const EdgeInsets.fromLTRB(24, 24, 24, 0),
                                        child: LoginLogo(logoUrl: logoUrl, tenantName: tenantName, size: 72),
                                      ),
                                    Container(
                                      color: Colors.white,
                                      padding: const EdgeInsets.all(24),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.stretch,
                                        children: [
                                          Text(tenantName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF041525))),
                                          const SizedBox(height: 4),
                                          const Text('School Registration', style: TextStyle(fontSize: 14, color: Color(0xFF64748B))),
                                          if (_twoStep) ...[
                                            const SizedBox(height: 16),
                                            _StepIndicator(step: _step),
                                          ],
                                          const SizedBox(height: 20),
                                          if (_showSchoolStep) _buildSchoolStep(),
                                          if (_showPrincipalStep) _buildPrincipalStep(),
                                          const SizedBox(height: 20),
                                          _buildActions(),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildSchoolStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildField('school_name'),
        ..._enabledKeysForGroup('school').map(_buildField),
      ],
    );
  }

  Widget _buildPrincipalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ..._enabledKeysForGroup('principal').map(_buildField),
        ..._enabledKeysForGroup('account').map(_buildField),
      ],
    );
  }

  Widget _buildField(String key) {
    final field = _field(key);
    if (field == null || field['enabled'] != true) return const SizedBox.shrink();

    final label = field['label']?.toString() ?? key;
    final required = field['required'] == true;
    final hint = field['placeholder']?.toString();
    final isValidating = _validating.contains(key);
    final hasError = _errors[key] != null;

    if (key == 'highest_class') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$label${required ? ' *' : ''}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155))),
            const SizedBox(height: 6),
            DropdownButtonFormField<String>(
              value: _values[key]?.isNotEmpty == true ? _values[key] : null,
              decoration: _inputDecoration(hint ?? 'Select class', error: hasError),
              items: _classOptions.entries
                  .map((entry) => DropdownMenuItem(value: entry.key, child: Text(entry.value)))
                  .toList(),
              onChanged: (value) => setState(() => _values[key] = value ?? ''),
            ),
            if (hasError) ...[
              const SizedBox(height: 6),
              Text(_errors[key]!, style: const TextStyle(fontSize: 12, color: Color(0xFFDC2626))),
            ],
          ],
        ),
      );
    }

    final obscure = key == 'password';
    final keyboard = key.contains('email')
        ? TextInputType.emailAddress
        : key.contains('phone')
            ? TextInputType.phone
            : key == 'website'
                ? TextInputType.url
                : TextInputType.text;

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label${required ? ' *' : ''}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155))),
          const SizedBox(height: 6),
          TextField(
            controller: _controllerFor(key),
            onChanged: (value) {
              _values[key] = value;
              if (_errors.containsKey(key)) {
                setState(() => _errors.remove(key));
              }
              _scheduleFieldValidation(key);
            },
            onEditingComplete: () => _validateField(key),
            obscureText: obscure,
            keyboardType: keyboard,
            textCapitalization: key == 'school_prefix' ? TextCapitalization.characters : TextCapitalization.none,
            decoration: _inputDecoration(hint ?? '', error: hasError).copyWith(
              suffixIcon: isValidating
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                    )
                  : null,
            ),
          ),
          if (hasError) ...[
            const SizedBox(height: 6),
            Text(_errors[key]!, style: const TextStyle(fontSize: 12, color: Color(0xFFDC2626))),
          ],
        ],
      ),
    );
  }

  Widget _buildActions() {
    if (_twoStep && _step == 1) {
      return Row(
        children: [
          TextButton(onPressed: () => context.go('/login'), child: const Text('Sign in')),
          const Spacer(),
          SaPrimaryButton(label: 'Continue', onPressed: _nextStep),
        ],
      );
    }

    return Row(
      children: [
        if (_twoStep)
          TextButton(onPressed: () => setState(() => _step = 1), child: const Text('Back'))
        else
          TextButton(onPressed: () => context.go('/login'), child: const Text('Sign in')),
        const Spacer(),
        SaPrimaryButton(
          label: 'Register',
          loading: _submitting,
          onPressed: () {
            if (_twoStep && _step == 1) {
              _nextStep();
              return;
            }
            _register();
          },
        ),
      ],
    );
  }

  InputDecoration _inputDecoration(String hint, {bool error = false}) {
    return InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: error ? const Color(0xFFF87171) : const Color(0xFFE2E8F0), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: error ? const Color(0xFFF87171) : const Color(0xFF1E5AA8), width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFF87171), width: 1.5),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFF87171), width: 1.5),
      ),
    );
  }
}

class _StepIndicator extends StatelessWidget {
  const _StepIndicator({required this.step});

  final int step;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _chip('1 School', active: step == 1, done: step > 1),
        Expanded(child: Container(height: 2, color: step > 1 ? const Color(0xFF1E5AA8) : const Color(0xFFE2E8F0))),
        _chip('2 Principal', active: step == 2, done: false),
      ],
    );
  }

  Widget _chip(String label, {required bool active, required bool done}) {
    final color = active || done ? const Color(0xFF1E5AA8) : const Color(0xFF94A3B8);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        CircleAvatar(
          radius: 12,
          backgroundColor: active || done ? const Color(0xFF1E5AA8) : const Color(0xFFE2E8F0),
          child: Text(label[0], style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: active || done ? Colors.white : const Color(0xFF64748B))),
        ),
        const SizedBox(width: 6),
        Text(label.substring(2), style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
      ],
    );
  }
}
