import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'branding_service.dart';
import 'login_branding.dart';
import 'login_widgets.dart';

class PortalScreen extends StatefulWidget {
  const PortalScreen({super.key});

  @override
  State<PortalScreen> createState() => _PortalScreenState();
}

class _PortalScreenState extends State<PortalScreen> {
  LoginBranding _branding = LoginBranding.fallback;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    BrandingService().load().then((branding) {
      if (mounted) {
        setState(() {
          _branding = branding;
          _loaded = true;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
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
                    children: [
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
                          child: Column(
                            children: [
                              LoginBrandPanel(
                                branding: _branding,
                                wide: false,
                                steps: const [],
                                showContacts: false,
                              ),
                              _PortalActions(
                                branding: _branding,
                                onLogin: () => context.push('/login'),
                                onRegister: () => context.push('/register'),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Text(
                        'CBSE Sahodaya School Complex · Membership Portal',
                        style: TextStyle(fontSize: 11, color: Colors.white.withValues(alpha: 0.35)),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (!_loaded)
            const ColoredBox(
              color: Color(0x88041525),
              child: Center(child: CircularProgressIndicator(color: Color(0xFFFBBF24))),
            ),
        ],
      ),
    );
  }
}

class _PortalActions extends StatelessWidget {
  const _PortalActions({
    required this.branding,
    required this.onLogin,
    required this.onRegister,
  });

  final LoginBranding branding;
  final VoidCallback onLogin;
  final VoidCallback onRegister;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('GET STARTED', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 1, color: Color(0xFF1E5AA8))),
          const SizedBox(height: 8),
          const Text('Welcome', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Color(0xFF041525))),
          const SizedBox(height: 8),
          const Text(
            'Choose an option below to register your school or sign in to the admin portal.',
            style: TextStyle(fontSize: 14, color: Color(0xFF64748B), height: 1.6),
          ),
          const SizedBox(height: 24),
          if (branding.showRegister) ...[
            _PortalActionCard(
              title: 'School Registration',
              subtitle: 'Apply for Sahodaya membership',
              icon: Icons.apartment_outlined,
              primary: true,
              onTap: onRegister,
            ),
            const SizedBox(height: 12),
          ],
          _PortalActionCard(
            title: 'Admin Login',
            subtitle: 'Sign in for schools & Sahodaya admins',
            icon: Icons.vpn_key_outlined,
            primary: false,
            onTap: onLogin,
          ),
        ],
      ),
    );
  }
}

class _PortalActionCard extends StatelessWidget {
  const _PortalActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.primary,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final bool primary;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: primary ? const Color(0xFF0F3D7A) : const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: primary ? Colors.transparent : const Color(0xFFE2E8F0)),
            boxShadow: primary ? const [BoxShadow(color: Color(0x330F3D7A), blurRadius: 12, offset: Offset(0, 4))] : null,
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: primary ? const Color(0x26FBBF24) : const Color(0xFFE8F0FA),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: primary ? const Color(0xFFFBBF24) : const Color(0xFF1E5AA8)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: primary ? Colors.white : const Color(0xFF041525),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(
                        fontSize: 12,
                        color: primary ? const Color(0xB3FFFFFF) : const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: primary ? const Color(0xB3FFFFFF) : const Color(0xFF94A3B8)),
            ],
          ),
        ),
      ),
    );
  }
}
