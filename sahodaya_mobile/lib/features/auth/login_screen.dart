import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../config/env.dart';
import '../../core/api/api_exception.dart';
import '../../core/auth/auth_providers.dart';
import 'branding_service.dart';
import 'login_branding.dart';
import 'login_widgets.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  LoginBranding _branding = LoginBranding.fallback;
  String? _emailError;
  bool _loading = false;
  bool _brandingLoaded = false;

  static const _steps = [
    'Sign in with your admin email',
    'Manage schools & annual registration',
    'Review submissions and payments',
  ];

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await BrandingService().load();
    if (mounted) {
      setState(() {
        _branding = branding;
        _brandingLoaded = true;
      });
    }
  }

  Future<void> _submit() async {
    setState(() {
      _emailError = null;
      _loading = true;
    });
    try {
      await ref.read(authControllerProvider.notifier).login(
            _emailController.text.trim(),
            _passwordController.text,
          );
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _emailError = error.message);
      }
    } catch (error) {
      if (mounted) {
        setState(() => _emailError = 'Could not reach ${AppEnv.apiBaseUrl}. Check your connection and try again.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 640;

    return Scaffold(
      body: Stack(
        children: [
          const LoginBackground(),
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 928),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _BackLink(onTap: () => context.go('/portal')),
                      const SizedBox(height: 20),
                      DecoratedBox(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: const [
                            BoxShadow(color: Color(0x8C000000), blurRadius: 80, offset: Offset(0, 32)),
                          ],
                          border: Border.all(color: Color(0x14FFFFFF)),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(24),
                          child: wide
                              ? IntrinsicHeight(
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.stretch,
                                    children: [
                                      Expanded(flex: 95, child: LoginBrandPanel(branding: _branding, wide: true, steps: _steps)),
                                      Expanded(
                                        flex: 105,
                                        child: _FormPanel(
                                          branding: _branding,
                                          emailController: _emailController,
                                          passwordController: _passwordController,
                                          emailError: _emailError,
                                          loading: _loading,
                                          onSubmit: _submit,
                                        ),
                                      ),
                                    ],
                                  ),
                                )
                              : Column(
                                  children: [
                                    LoginBrandPanel(branding: _branding, wide: false, steps: _steps),
                                    _FormPanel(
                                      branding: _branding,
                                      emailController: _emailController,
                                      passwordController: _passwordController,
                                      emailError: _emailError,
                                      loading: _loading,
                                      onSubmit: _submit,
                                    ),
                                  ],
                                ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Center(
                        child: Text(
                          kDebugMode
                              ? 'CBSE Sahodaya · ${AppEnv.apiBaseUrl}'
                              : 'CBSE Sahodaya School Complex · Membership Portal',
                          style: const TextStyle(fontSize: 11, color: Color(0x59FFFFFF)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (!_brandingLoaded)
            const ColoredBox(
              color: Color(0x88041525),
              child: Center(child: CircularProgressIndicator(color: Color(0xFFFBBF24))),
            ),
        ],
      ),
    );
  }
}

class _BackLink extends StatelessWidget {
  const _BackLink({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.chevron_left, size: 18, color: Color(0x8CFFFFFF)),
          SizedBox(width: 4),
          Text('Back to portal', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0x8CFFFFFF))),
        ],
      ),
    );
  }
}

class _FormPanel extends StatelessWidget {
  const _FormPanel({
    required this.branding,
    required this.emailController,
    required this.passwordController,
    required this.emailError,
    required this.loading,
    required this.onSubmit,
  });

  final LoginBranding branding;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final String? emailError;
  final bool loading;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 40),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('ADMIN ACCESS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 1, color: Color(0xFF1E5AA8))),
          const SizedBox(height: 8),
          const Text('Sign In', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Color(0xFF041525))),
          const SizedBox(height: 8),
          const Text('Enter your credentials to access the admin dashboard.', style: TextStyle(fontSize: 14, color: Color(0xFF64748B), height: 1.6)),
          const SizedBox(height: 24),
          const Text('Email', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155))),
          const SizedBox(height: 6),
          TextField(
            controller: emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: _inputDecoration('you@school.edu', error: emailError != null),
          ),
          if (emailError != null) ...[
            const SizedBox(height: 6),
            Text(emailError!, style: const TextStyle(fontSize: 12, color: Color(0xFFDC2626))),
          ],
          const SizedBox(height: 18),
          const Text('Password', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155))),
          const SizedBox(height: 6),
          TextField(
            controller: passwordController,
            obscureText: true,
            decoration: _inputDecoration('••••••••'),
            onSubmitted: (_) => onSubmit(),
          ),
          const SizedBox(height: 20),
          DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              gradient: const LinearGradient(colors: [Color(0xFF0F3D7A), Color(0xFF1E5AA8)]),
              boxShadow: const [BoxShadow(color: Color(0x660F3D7A), blurRadius: 14, offset: Offset(0, 4))],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: loading ? null : onSubmit,
                borderRadius: BorderRadius.circular(12),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: Center(
                    child: loading
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Sign In', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white)),
                  ),
                ),
              ),
            ),
          ),
          if (branding.showRegister) ...[
            const SizedBox(height: 18),
            Center(
              child: TextButton(
                onPressed: () => context.push('/register'),
                child: const Text(
                  'New school? Apply for membership',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF1E5AA8)),
                ),
              ),
            ),
          ],
        ],
      ),
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
        borderSide: const BorderSide(color: Color(0xFF1E5AA8), width: 1.5),
      ),
    );
  }
}
