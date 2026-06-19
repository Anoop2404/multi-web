import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'payment_detail_screen.dart';
import 'sahodaya_api.dart';

class SahodayaSchoolDetailScreen extends ConsumerStatefulWidget {
  const SahodayaSchoolDetailScreen({
    super.key,
    required this.schoolId,
    this.preview,
  });

  final String schoolId;
  final Map<String, dynamic>? preview;

  @override
  ConsumerState<SahodayaSchoolDetailScreen> createState() => _SahodayaSchoolDetailScreenState();
}

class _SahodayaSchoolDetailScreenState extends ConsumerState<SahodayaSchoolDetailScreen> {
  Map<String, dynamic>? _data;
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
      final response = await sahodayaGet(ref, '/schools/${widget.schoolId}');
      setState(() => _data = response['data'] as Map<String, dynamic>?);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  String _formatDate(String? value) {
    if (value == null || value.isEmpty) return '—';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;
    return DateFormat('d MMM yyyy').format(parsed.toLocal());
  }

  String _formatRupee(dynamic value) {
    final amount = double.tryParse(value?.toString() ?? '') ?? 0;
    return '₹${NumberFormat.decimalPattern('en_IN').format(amount)}';
  }

  Future<void> _reject() async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Reject school'),
          content: TextField(
            controller: controller,
            decoration: const InputDecoration(labelText: 'Reason'),
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

    try {
      await sahodayaPost(ref, '/schools/${widget.schoolId}/reject', body: {'reason': reason});
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _openPayment(Map<String, dynamic> payment) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => SahodayaPaymentDetailScreen(payment: payment)),
    );
    if (changed == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final school = (_data?['school'] as Map<String, dynamic>?) ?? widget.preview;
    final detailFields = (_data?['detail_fields'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final registration = _data?['registration'] as Map<String, dynamic>?;
    final payments = (_data?['recent_payments'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final year = _data?['academic_year']?.toString() ?? '';
    final status = school?['membership_status']?.toString() ?? 'pending';

    return SaPageScaffold(
      title: school?['name']?.toString() ?? 'School details',
      actions: [
        if (!_loading && school != null && status != 'rejected')
          TextButton(
            onPressed: _reject,
            child: const Text('Reject', style: TextStyle(color: Color(0xFFB91C1C))),
          ),
      ],
      body: _loading && _data == null
          ? const SaLoadingView()
          : _error != null && _data == null
              ? SaErrorView(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  color: AppColors.navyPrimary,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      SaHeroBanner(
                        eyebrow: school?['school_prefix']?.toString() ?? 'Member school',
                        title: school?['name']?.toString() ?? 'School',
                        subtitle: 'Registered ${_formatDate(school?['created_at']?.toString())}',
                      ),
                      const SizedBox(height: 12),
                      if (status == 'pending')
                        const SaInfoBanner(
                          title: 'Pending approval',
                          body: 'Membership is approved when you verify their payment on the Payments screen.',
                        ),
                      if (status == 'pending') const SizedBox(height: 12),
                      Row(
                        children: [
                          SaStatusChip(status),
                          const Spacer(),
                          Text(
                            '${school?['student_count'] ?? 0} students · ${school?['classes_count'] ?? 0} classes',
                            style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (detailFields.isNotEmpty) ...[
                        const SaSectionTitle('Application details'),
                        SaCard(
                          child: Column(
                            children: [
                              for (var i = 0; i < detailFields.length; i++) ...[
                                if (i > 0) const Divider(height: 1),
                                SaDetailTile(
                                  label: detailFields[i]['label']?.toString() ?? '',
                                  value: detailFields[i]['value']?.toString() ?? '—',
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],
                      const SaSectionTitle('Portal access'),
                      SaCard(
                        child: SaDetailTile(
                          label: 'Login account',
                          value: school?['has_login'] == true
                              ? (school?['login_email']?.toString() ?? 'Created')
                              : 'Not created',
                          icon: Icons.login_outlined,
                        ),
                      ),
                      if (registration != null) ...[
                        const SizedBox(height: 16),
                        const SaSectionTitle('Annual registration'),
                        SaCard(
                          child: Column(
                            children: [
                              SaDetailTile(
                                label: 'Academic year',
                                value: year.isNotEmpty ? year : registration['academic_year']?.toString() ?? '—',
                                icon: Icons.calendar_today_outlined,
                              ),
                              const Divider(height: 1),
                              SaDetailTile(
                                label: 'Membership no.',
                                value: registration['reg_no']?.toString() ?? '—',
                                icon: Icons.badge_outlined,
                              ),
                              const Divider(height: 1),
                              SaDetailTile(
                                label: 'Status',
                                value: (registration['registration_status']?.toString() ?? '—').replaceAll('_', ' '),
                                icon: Icons.assignment_outlined,
                              ),
                              if (registration['membership_fee_amount'] != null) ...[
                                const Divider(height: 1),
                                SaDetailTile(
                                  label: 'Membership fee',
                                  value: _formatRupee(registration['membership_fee_amount']),
                                  icon: Icons.payments_outlined,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                      if (payments.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        const SaSectionTitle('Payment history'),
                        ...payments.map(
                          (payment) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: SaEntityCard(
                              title: '${payment['academic_year'] ?? year} — ${_formatRupee(payment['amount'])}',
                              subtitle: _formatDate(payment['created_at']?.toString()),
                              status: payment['status']?.toString(),
                              leading: Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: AppColors.bgSky,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                alignment: Alignment.center,
                                child: const Text('₹', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.navyPrimary)),
                              ),
                              trailing: payment['status']?.toString() == 'submitted'
                                  ? const Icon(Icons.chevron_right, color: Color(0xFF94A3B8))
                                  : null,
                              onTap: payment['status']?.toString() == 'submitted' ? () => _openPayment(payment) : null,
                            ),
                          ),
                        ),
                      ],
                      if (detailFields.isEmpty && registration == null && payments.isEmpty)
                        const SaEmptyView(
                          title: 'Limited details available',
                          subtitle: 'This school has not submitted full application or registration data yet.',
                          icon: Icons.school_outlined,
                        ),
                    ],
                  ),
                ),
    );
  }
}
