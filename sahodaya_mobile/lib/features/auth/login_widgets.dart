import 'package:flutter/material.dart';

import '../../config/app_branding.dart';
import '../../core/widgets/tenant_logo.dart';
import 'login_branding.dart';

class LoginBackground extends StatelessWidget {
  const LoginBackground({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment(-0.9, -1),
              end: Alignment(0.9, 1),
              colors: [Color(0xFF041525), Color(0xFF0A2744), Color(0xFF0F3D7A)],
            ),
          ),
          child: CustomPaint(painter: _GridPainter()),
        ),
        const Positioned(top: -80, right: -60, child: _BlurOrb(size: 320, color: Color(0x1FEAB308))),
        const Positioned(bottom: -40, left: -40, child: _BlurOrb(size: 240, color: Color(0x242563EB))),
      ],
    );
  }
}

class LoginBrandPanel extends StatelessWidget {
  const LoginBrandPanel({
    super.key,
    required this.branding,
    required this.wide,
    required this.steps,
    this.showContacts = true,
  });

  final LoginBranding branding;
  final bool wide;
  final List<String> steps;
  final bool showContacts;

  @override
  Widget build(BuildContext context) {
    final subtitle = branding.tagline?.isNotEmpty == true
        ? branding.tagline!
        : 'Sign in to manage membership, schools, and registrations.';

    return Container(
      padding: EdgeInsets.symmetric(horizontal: wide ? 32 : 24, vertical: wide ? 40 : 28),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment(-0.8, -1),
          end: Alignment(0.8, 1),
          colors: [Color(0xFF0A2744), Color(0xFF0F3D7A), Color(0xFF1A4F8C)],
        ),
      ),
      child: Column(
        crossAxisAlignment: wide ? CrossAxisAlignment.start : CrossAxisAlignment.center,
        children: [
          TenantLogo(
            logoUrl: branding.resolvedLogoUrl,
            tenantName: branding.tenantName,
            size: wide ? 112 : 88,
            preferBundledAsset: branding.tenantName == AppBranding.appName,
          ),
          const SizedBox(height: 12),
          _Badge(label: branding.eyebrow, centered: !wide),
          const SizedBox(height: 10),
          if (branding.tenantName == AppBranding.appName)
            AppBrandTitle(
              textAlign: wide ? TextAlign.start : TextAlign.center,
              line1Size: wide ? 16 : 14,
              line2Size: wide ? 28 : 24,
            )
          else
            Text(
              branding.tenantName,
              textAlign: wide ? TextAlign.start : TextAlign.center,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: Colors.white, height: 1.25),
            ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: wide ? TextAlign.start : TextAlign.center,
            style: const TextStyle(fontSize: 14, color: Color(0x99FFFFFF), height: 1.55),
          ),
          if (branding.motto?.isNotEmpty == true && wide) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.only(left: 12),
              decoration: const BoxDecoration(border: Border(left: BorderSide(color: Color(0x73FBBF24), width: 2))),
              child: Text('"${branding.motto}"', style: const TextStyle(fontSize: 13, fontStyle: FontStyle.italic, color: Color(0xFFFBBF24))),
            ),
          ],
          if (wide && steps.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Divider(color: Color(0x1AFFFFFF)),
            const SizedBox(height: 16),
            ...List.generate(steps.length, (index) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _StepNumber(number: index + 1),
                      const SizedBox(width: 10),
                      Expanded(child: Text(steps[index], style: const TextStyle(fontSize: 13, color: Color(0xA6FFFFFF), height: 1.45))),
                    ],
                  ),
                )),
          ],
          if (showContacts && wide && (branding.phone?.isNotEmpty == true || branding.email?.isNotEmpty == true)) ...[
            const SizedBox(height: 8),
            const Divider(color: Color(0x1AFFFFFF)),
            const SizedBox(height: 12),
            if (branding.phone?.isNotEmpty == true) _ContactRow(icon: Icons.phone_outlined, text: branding.phone!),
            if (branding.email?.isNotEmpty == true) _ContactRow(icon: Icons.email_outlined, text: branding.email!),
          ],
        ],
      ),
    );
  }
}

class LoginLogo extends StatelessWidget {
  const LoginLogo({super.key, required this.logoUrl, required this.tenantName, required this.size});

  final String logoUrl;
  final String tenantName;
  final double size;

  @override
  Widget build(BuildContext context) {
    return TenantLogo(logoUrl: logoUrl, tenantName: tenantName, size: size);
  }
}

class _GridPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0x08FFFFFF)
      ..strokeWidth = 1;
    const step = 56.0;
    for (var x = 0.0; x < size.width; x += step) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (var y = 0.0; y < size.height; y += step) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _BlurOrb extends StatelessWidget {
  const _BlurOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color,
        boxShadow: [BoxShadow(color: color, blurRadius: 80, spreadRadius: 20)],
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.centered});

  final String label;
  final bool centered;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: centered ? Alignment.center : Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: const Color(0x1FEAB308),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: const Color(0x47EAB308)),
        ),
        child: Text(label.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 1.4, color: Color(0xFFFBBF24))),
      ),
    );
  }
}

class _StepNumber extends StatelessWidget {
  const _StepNumber({required this.number});

  final int number;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(shape: BoxShape.circle, color: const Color(0x26EAB308), border: Border.all(color: const Color(0x59EAB308))),
      alignment: Alignment.center,
      child: Text('$number', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFFFBBF24))),
    );
  }
}

class _ContactRow extends StatelessWidget {
  const _ContactRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(children: [
        Icon(icon, size: 14, color: const Color(0x80FFFFFF)),
        const SizedBox(width: 8),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 12, color: Color(0x80FFFFFF)))),
      ]),
    );
  }
}
