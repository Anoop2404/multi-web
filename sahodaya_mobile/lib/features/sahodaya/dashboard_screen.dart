import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/auth/auth_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'sahodaya_api.dart';

class SahodayaDashboardScreen extends ConsumerStatefulWidget {
  const SahodayaDashboardScreen({super.key, this.onGoToSchools, this.onGoToPayments, this.onGoToPaymentDue});

  final VoidCallback? onGoToSchools;
  final VoidCallback? onGoToPayments;
  final VoidCallback? onGoToPaymentDue;

  @override
  ConsumerState<SahodayaDashboardScreen> createState() => _SahodayaDashboardScreenState();
}

class _SahodayaDashboardScreenState extends ConsumerState<SahodayaDashboardScreen> {
  Map<String, dynamic>? _stats;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await sahodayaGet(ref, '/dashboard');
      _stats = (response['data'] as Map<String, dynamic>?)?['stats'] as Map<String, dynamic>?;
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaLoadingView();
    if (_error != null) return SaErrorView(message: _error!, onRetry: _load);

    final session = ref.watch(authControllerProvider).session!;
    final approved = _stats?['approved_schools'] as int? ?? 0;
    final pendingSchools = _stats?['pending_schools'] as int? ?? 0;
    final pendingPayments = _stats?['pending_payments'] as int? ?? 0;
    final paymentDue = _stats?['payment_due'] as int? ?? 0;
    final students = _stats?['total_students'] as int? ?? 0;
    final pendingAmount = _stats?['pending_amount'];
    final approvedAmount = _stats?['approved_amount'];
    final paymentDueAmount = _stats?['payment_due_amount'];

    String formatRupee(dynamic value) {
      final amount = double.tryParse(value?.toString() ?? '') ?? 0;
      return '₹${NumberFormat.decimalPattern('en_IN').format(amount)}';
    }

    return RefreshIndicator(
      color: AppColors.navyPrimary,
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SaHeroBanner(
            eyebrow: 'Sahodaya Complex',
            title: session.user.tenantName ?? 'Dashboard',
            subtitle: '$approved approved member${approved == 1 ? '' : 's'}'
                '${pendingSchools > 0 ? ' · $pendingSchools pending schools' : ''}',
          ),
          const SizedBox(height: 16),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.15,
            children: [
              SaStatCard(label: 'Approved Members', value: '$approved', icon: '🏫', color: SaStatColor.blue),
              SaStatCard(label: 'Pending Schools', value: '$pendingSchools', icon: '⏳', color: SaStatColor.amber),
              SaStatCard(label: 'Active Students', value: '$students', icon: '👨‍🎓', color: SaStatColor.navy, hint: 'Approved members only'),
              SaStatCard(label: 'Pending approval fees', value: formatRupee(pendingAmount), icon: '💳', color: SaStatColor.amber, hint: '$pendingPayments awaiting verify'),
              SaStatCard(label: 'Approved fees', value: formatRupee(approvedAmount), icon: '✅', color: SaStatColor.green),
              SaStatCard(label: 'Payment not done', value: formatRupee(paymentDueAmount), icon: '🧾', color: SaStatColor.navy, hint: '$paymentDue schools'),
            ],
          ),
          if (pendingSchools > 0 || pendingPayments > 0 || paymentDue > 0) ...[
            const SizedBox(height: 20),
            const SaSectionTitle('Needs attention'),
            const SizedBox(height: 8),
            if (pendingSchools > 0)
              SaActionBanner(
                count: pendingSchools,
                label: 'schools awaiting membership approval',
                icon: '⏳',
                onTap: widget.onGoToSchools,
              ),
            if (pendingSchools > 0 && (paymentDue > 0 || pendingPayments > 0)) const SizedBox(height: 8),
            if (paymentDue > 0)
              SaActionBanner(
                count: paymentDue,
                label: 'schools registered but payment not done',
                icon: '🧾',
                onTap: widget.onGoToPaymentDue,
              ),
            if (paymentDue > 0 && pendingPayments > 0) const SizedBox(height: 8),
            if (pendingPayments > 0)
              SaActionBanner(
                count: pendingPayments,
                label: 'payments pending verification',
                icon: '💳',
                color: SaStatColor.green,
                onTap: widget.onGoToPayments,
              ),
          ],
        ],
      ),
    );
  }
}
