import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class SaCard extends StatelessWidget {
  const SaCard({super.key, required this.child, this.padding = const EdgeInsets.all(16)});

  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(padding: padding, child: child),
    );
  }
}

class SaInfoBanner extends StatelessWidget {
  const SaInfoBanner({super.key, required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.infoBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.infoBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1E3A8A))),
          const SizedBox(height: 8),
          Text(body, style: const TextStyle(fontSize: 13, color: Color(0xFF1E40AF), height: 1.5)),
        ],
      ),
    );
  }
}

class SaSectionTitle extends StatelessWidget {
  const SaSectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        text,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: const Color(0xFF1F2937),
            ),
      ),
    );
  }
}

class SaPrimaryButton extends StatelessWidget {
  const SaPrimaryButton({super.key, required this.label, required this.onPressed, this.loading = false});

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: loading ? null : onPressed,
      child: loading
          ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
          : Text(label),
    );
  }
}

class SaSubmitButton extends StatelessWidget {
  const SaSubmitButton({super.key, required this.label, required this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(backgroundColor: AppColors.purple600),
      child: Text(label),
    );
  }
}

class SaNavyButton extends StatelessWidget {
  const SaNavyButton({super.key, required this.label, required this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(backgroundColor: AppColors.navyPrimary),
      child: Text(label),
    );
  }
}

class SaPageScaffold extends StatelessWidget {
  const SaPageScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.leading,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(gradient: AppTheme.mainBackground),
      child: Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(
          title: Text(title),
          leading: leading,
          actions: actions,
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(1),
            child: Divider(height: 1, color: AppColors.borderBlue),
          ),
        ),
        body: body,
      ),
    );
  }
}

class SaDrawerHeader extends StatelessWidget {
  const SaDrawerHeader({super.key, required this.roleLabel, required this.tenantName, this.subtitle});

  final String roleLabel;
  final String tenantName;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 48, 20, 20),
      decoration: BoxDecoration(gradient: AppTheme.sidebarGradient),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.accentGold.withValues(alpha: 0.45), width: 2),
            ),
            child: Center(
              child: Text(
                tenantName.isNotEmpty ? tenantName[0].toUpperCase() : 'S',
                style: const TextStyle(color: AppColors.accentGold, fontWeight: FontWeight.bold, fontSize: 18),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            roleLabel.toUpperCase(),
            style: TextStyle(
              color: AppColors.accentGold.withValues(alpha: 0.75),
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.4,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            tenantName,
            style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(subtitle!, style: const TextStyle(color: Colors.white54, fontSize: 10, fontFamily: 'monospace')),
          ],
        ],
      ),
    );
  }
}

class SaDrawerTile extends StatelessWidget {
  const SaDrawerTile({
    super.key,
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: selected ? Colors.white : Colors.white60, size: 20),
      title: Text(
        label,
        style: TextStyle(
          color: selected ? Colors.white : Colors.white60,
          fontWeight: selected ? FontWeight.w600 : FontWeight.normal,
          fontSize: 14,
        ),
      ),
      selected: selected,
      selectedTileColor: Colors.white.withValues(alpha: 0.12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      onTap: onTap,
    );
  }
}

class SaStatusChip extends StatelessWidget {
  const SaStatusChip(this.status, {super.key});

  final String status;

  @override
  Widget build(BuildContext context) {
    Color fg;
    Color bg;
    switch (status) {
      case 'approved':
      case 'verified':
      case 'completed':
        fg = Colors.green.shade700;
        bg = Colors.green.shade50;
      case 'rejected':
        fg = Colors.red.shade700;
        bg = Colors.red.shade50;
      case 'pending':
      case 'submitted':
        fg = Colors.amber.shade900;
        bg = Colors.amber.shade50;
      default:
        fg = Colors.grey.shade700;
        bg = Colors.grey.shade100;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: fg.withValues(alpha: 0.3)),
      ),
      child: Text(status, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}
