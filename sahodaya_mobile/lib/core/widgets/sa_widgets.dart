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
        fg = const Color(0xFF15803D);
        bg = const Color(0xFFDCFCE7);
      case 'rejected':
        fg = const Color(0xFFB91C1C);
        bg = const Color(0xFFFEE2E2);
      case 'pending':
      case 'submitted':
      case 'payment_submitted':
        fg = const Color(0xFFB45309);
        bg = const Color(0xFFFEF3C7);
      default:
        fg = const Color(0xFF475569);
        bg = const Color(0xFFF1F5F9);
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: fg.withValues(alpha: 0.25)),
      ),
      child: Text(
        status.replaceAll('_', ' '),
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: fg),
      ),
    );
  }
}

class SaLoadingView extends StatelessWidget {
  const SaLoadingView({super.key});

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: CircularProgressIndicator(color: AppColors.navyPrimary, strokeWidth: 2.5),
    );
  }
}

class SaErrorView extends StatelessWidget {
  const SaErrorView({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 40, color: Color(0xFFDC2626)),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF64748B), height: 1.5)),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              SaNavyButton(label: 'Try again', onPressed: onRetry),
            ],
          ],
        ),
      ),
    );
  }
}

class SaEmptyView extends StatelessWidget {
  const SaEmptyView({super.key, required this.title, this.subtitle, this.icon = Icons.inbox_outlined});

  final String title;
  final String? subtitle;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: const Color(0xFFCBD5E1)),
            const SizedBox(height: 12),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF334155))),
            if (subtitle != null) ...[
              const SizedBox(height: 6),
              Text(subtitle!, textAlign: TextAlign.center, style: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8))),
            ],
          ],
        ),
      ),
    );
  }
}

class SaHeroBanner extends StatelessWidget {
  const SaHeroBanner({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: AppTheme.sidebarGradient,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: Color(0x330F3D7A), blurRadius: 16, offset: Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            eyebrow.toUpperCase(),
            style: TextStyle(
              color: AppColors.accentGold.withValues(alpha: 0.9),
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 6),
          Text(title, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(subtitle, style: const TextStyle(color: Color(0xB3FFFFFF), fontSize: 13, height: 1.5)),
        ],
      ),
    );
  }
}

enum SaStatColor { blue, amber, navy, green }

class SaStatCard extends StatelessWidget {
  const SaStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.color = SaStatColor.blue,
    this.hint,
  });

  final String label;
  final String value;
  final String icon;
  final SaStatColor color;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    final palette = switch (color) {
      SaStatColor.blue => (const Color(0xFFEFF6FF), const Color(0xFFDBEAFE), const Color(0xFF1D4ED8)),
      SaStatColor.amber => (const Color(0xFFFFFBEB), const Color(0xFFFDE68A), const Color(0xFFB45309)),
      SaStatColor.navy => (const Color(0xFFF0F9FF), const Color(0xFFBAE6FD), const Color(0xFF0F3D7A)),
      SaStatColor.green => (const Color(0xFFF0FDF4), const Color(0xFFBBF7D0), const Color(0xFF15803D)),
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: palette.$2.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(color: palette.$1, borderRadius: BorderRadius.circular(8)),
                alignment: Alignment.center,
                child: Text(icon, style: const TextStyle(fontSize: 16)),
              ),
              const Spacer(),
              Text(
                value,
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: palette.$3),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
          if (hint != null) ...[
            const SizedBox(height: 2),
            Text(hint!, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
          ],
        ],
      ),
    );
  }
}

class SaActionBanner extends StatelessWidget {
  const SaActionBanner({
    super.key,
    required this.count,
    required this.label,
    required this.icon,
    this.onTap,
    this.color = SaStatColor.amber,
  });

  final int count;
  final String label;
  final String icon;
  final VoidCallback? onTap;
  final SaStatColor color;

  @override
  Widget build(BuildContext context) {
    final accent = color == SaStatColor.green ? const Color(0xFF15803D) : const Color(0xFFB45309);
    final bg = color == SaStatColor.green ? const Color(0xFFF0FDF4) : const Color(0xFFFFFBEB);

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: accent.withValues(alpha: 0.2)),
          ),
          child: Row(
            children: [
              Text(icon, style: const TextStyle(fontSize: 22)),
              const SizedBox(width: 12),
              Expanded(
                child: RichText(
                  text: TextSpan(
                    style: const TextStyle(fontSize: 13, color: Color(0xFF334155), height: 1.4),
                    children: [
                      TextSpan(text: '$count ', style: TextStyle(fontWeight: FontWeight.w800, color: accent)),
                      TextSpan(text: label),
                    ],
                  ),
                ),
              ),
              Icon(Icons.chevron_right, size: 18, color: accent.withValues(alpha: 0.7)),
            ],
          ),
        ),
      ),
    );
  }
}

class SaEntityCard extends StatelessWidget {
  const SaEntityCard({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.leading,
    this.status,
    this.onTap,
    this.footer,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget? leading;
  final String? status;
  final VoidCallback? onTap;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return SaCard(
      padding: const EdgeInsets.all(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (leading != null) ...[leading!, const SizedBox(width: 12)],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textDark)),
                      if (subtitle != null) ...[
                        const SizedBox(height: 4),
                        Text(subtitle!, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      ],
                    ],
                  ),
                ),
                if (status != null) SaStatusChip(status!),
                if (trailing != null) trailing!,
              ],
            ),
            if (footer != null) ...[const SizedBox(height: 12), footer!],
          ],
        ),
      ),
    );
  }
}

class SaDetailTile extends StatelessWidget {
  const SaDetailTile({super.key, required this.label, required this.value, this.icon});

  final String label;
  final String value;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          if (icon != null) ...[
            Icon(icon, size: 18, color: AppColors.navyLight),
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF94A3B8))),
                const SizedBox(height: 2),
                Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textDark)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SaSearchField extends StatelessWidget {
  const SaSearchField({super.key, required this.controller, this.hint = 'Search...'});

  final TextEditingController controller;
  final String hint;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      decoration: InputDecoration(
        hintText: hint,
        prefixIcon: const Icon(Icons.search, size: 20, color: Color(0xFF94A3B8)),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.navyLight, width: 1.5),
        ),
      ),
    );
  }
}
