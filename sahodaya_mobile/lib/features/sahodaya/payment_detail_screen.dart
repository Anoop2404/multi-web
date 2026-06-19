import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/authenticated_document.dart';
import '../../core/widgets/sa_widgets.dart';
import 'sahodaya_api.dart';

class SahodayaPaymentDetailScreen extends ConsumerStatefulWidget {
  const SahodayaPaymentDetailScreen({super.key, required this.payment});

  final Map<String, dynamic> payment;

  @override
  ConsumerState<SahodayaPaymentDetailScreen> createState() => _SahodayaPaymentDetailScreenState();
}

class _SahodayaPaymentDetailScreenState extends ConsumerState<SahodayaPaymentDetailScreen> {
  bool _acting = false;
  late Map<String, dynamic> _payment;

  @override
  void initState() {
    super.initState();
    _payment = Map<String, dynamic>.from(widget.payment);
  }

  int get _paymentId => _payment['id'] as int;

  bool get _canVerify => _payment['status']?.toString() == 'submitted';

  String _formatDate(String? value) {
    if (value == null || value.isEmpty) return '—';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;
    return DateFormat('d MMM yyyy, h:mm a').format(parsed.toLocal());
  }

  Future<void> _verify(String action, {String? reason}) async {
    setState(() => _acting = true);
    try {
      final response = await sahodayaPost(ref, '/payments/$_paymentId/verify', body: {
        'action': action,
        if (reason != null) 'reason': reason,
      });
      final data = response['data'] as Map<String, dynamic>?;
      if (data != null) {
        setState(() => _payment = data);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(action == 'verify' ? 'Payment verified' : 'Payment rejected')),
        );
        if (_payment['status']?.toString() != 'submitted') {
          Navigator.pop(context, true);
        }
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _acting = false);
    }
  }

  Future<void> _confirmVerify() async {
    final schoolName = _payment['school']?['name']?.toString() ?? 'this school';
    final amount = _payment['amount'] ?? 0;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Verify payment?'),
        content: Text('Approve payment of ₹$amount from $schoolName? This completes annual registration.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFF15803D)),
            child: const Text('Verify'),
          ),
        ],
      ),
    );
    if (confirmed == true) await _verify('verify');
  }

  Future<void> _reject() async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Reject payment'),
          content: TextField(
            controller: controller,
            decoration: const InputDecoration(labelText: 'Reason for rejection *'),
            maxLines: 3,
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFFB91C1C)),
              child: const Text('Reject'),
            ),
          ],
        );
      },
    );
    if (reason == null || reason.isEmpty) return;
    await _verify('reject', reason: reason);
  }

  @override
  Widget build(BuildContext context) {
    final school = _payment['school'] as Map<String, dynamic>?;
    final schoolName = school?['name']?.toString() ?? 'School';
    final status = _payment['status']?.toString() ?? 'submitted';
    final proofPath = '${sahodayaBase(ref)}/payments/$_paymentId/proof';

    return SaPageScaffold(
      title: 'Payment review',
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                SaHeroBanner(
                  eyebrow: _payment['academic_year']?.toString() ?? 'Annual registration',
                  title: schoolName,
                  subtitle: 'Submitted ${_formatDate(_payment['created_at']?.toString())}',
                ),
                const SizedBox(height: 16),
                SaCard(
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '₹${_payment['amount'] ?? 0}',
                              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: AppColors.navyPrimary),
                            ),
                          ),
                          SaStatusChip(status),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SaDetailTile(label: 'School code', value: school?['school_prefix']?.toString() ?? '—', icon: Icons.badge_outlined),
                      SaDetailTile(
                        label: 'Payment method',
                        value: (_payment['payment_method']?.toString() ?? '—').replaceAll('_', ' '),
                        icon: Icons.account_balance_wallet_outlined,
                      ),
                      SaDetailTile(label: 'Transaction ref', value: _payment['transaction_ref']?.toString() ?? '—', icon: Icons.tag_outlined),
                      SaDetailTile(label: 'Submitted', value: _formatDate(_payment['created_at']?.toString()), icon: Icons.schedule_outlined),
                      if (_payment['verified_at'] != null)
                        SaDetailTile(label: 'Verified', value: _formatDate(_payment['verified_at']?.toString()), icon: Icons.verified_outlined),
                    ],
                  ),
                ),
                if (_payment['rejection_reason'] != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFECACA)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Rejection reason', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFFB91C1C))),
                        const SizedBox(height: 6),
                        Text(_payment['rejection_reason'].toString(), style: const TextStyle(fontSize: 13, color: Color(0xFF7F1D1D), height: 1.5)),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                const SaSectionTitle('Payment proof'),
                const SizedBox(height: 8),
                AuthenticatedDocument(path: proofPath, height: 360),
              ],
            ),
          ),
          if (_canVerify)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _acting ? null : _reject,
                      icon: const Icon(Icons.close, size: 18),
                      label: const Text('Reject'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFFB91C1C),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _acting ? null : _confirmVerify,
                      icon: _acting
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.check, size: 18),
                      label: const Text('Verify'),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF15803D),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
