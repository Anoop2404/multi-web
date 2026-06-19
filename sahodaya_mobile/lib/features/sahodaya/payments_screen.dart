import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'payment_detail_screen.dart';
import 'sahodaya_api.dart';

class SahodayaPaymentsScreen extends ConsumerStatefulWidget {
  const SahodayaPaymentsScreen({super.key, this.initialStatus});

  final String? initialStatus;

  @override
  ConsumerState<SahodayaPaymentsScreen> createState() => _SahodayaPaymentsScreenState();
}

class _SahodayaPaymentsScreenState extends ConsumerState<SahodayaPaymentsScreen> {
  final _search = TextEditingController();
  String _status = 'submitted';
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic>? _meta;
  String? _error;
  bool _loading = true;

  static const _tabs = [
    ('submitted', 'Payment pending'),
    ('payment-due', 'Payment not done'),
    ('verified', 'Verified'),
    ('rejected', 'Rejected'),
    ('all', 'All'),
  ];

  bool get _isPaymentDue => _status == 'payment-due';

  @override
  void initState() {
    super.initState();
    _status = widget.initialStatus ?? 'submitted';
    _load();
    _search.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _search.removeListener(_onSearchChanged);
    _search.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    Future.delayed(const Duration(milliseconds: 350), () {
      if (!mounted || _search.text == _lastSearch) return;
      _lastSearch = _search.text;
      _load();
    });
  }

  String _lastSearch = '';

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await sahodayaGet(ref, '/payments', query: {
        'status': _status,
        if (_search.text.trim().isNotEmpty) 'search': _search.text.trim(),
      });
      final data = response['data'];
      _items = data is List ? data.cast<Map<String, dynamic>>() : [];
      _meta = response['meta'] as Map<String, dynamic>?;
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  void _switchStatus(String status) {
    if (_status == status) return;
    setState(() => _status = status);
    _load();
  }

  int _countFor(String key) {
    final counts = _meta?['status_counts'] as Map<String, dynamic>?;
    return counts?[key] as int? ?? 0;
  }

  Map<String, dynamic> get _summary => (_meta?['summary'] as Map<String, dynamic>?) ?? {};

  String _formatRupee(dynamic value) {
    final amount = double.tryParse(value?.toString() ?? '') ?? 0;
    return '₹${NumberFormat.decimalPattern('en_IN').format(amount)}';
  }

  Future<void> _openPayment(Map<String, dynamic> payment) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => SahodayaPaymentDetailScreen(payment: payment)),
    );
    if (changed == true) await _load();
  }

  String _formatSubmitted(String? value) {
    if (value == null || value.isEmpty) return '';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return '';
    return 'Submitted ${parsed.toLocal().toString().split(' ').first}';
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _items.isEmpty && _error == null) return const SaLoadingView();
    if (_error != null && _items.isEmpty) return SaErrorView(message: _error!, onRetry: _load);

    return RefreshIndicator(
      color: AppColors.navyPrimary,
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.35,
            children: [
              SaStatCard(
                label: 'Payment pending',
                value: _formatRupee(_summary['payments_pending_verification_amount'] ?? _summary['pending_amount']),
                icon: '⏳',
                color: SaStatColor.amber,
                hint: '${_summary['payments_pending_verification'] ?? _summary['pending'] ?? _countFor('submitted')} payments',
              ),
              SaStatCard(
                label: 'Approved fees',
                value: _formatRupee(_summary['approved_amount'] ?? _summary['collected']),
                icon: '✅',
                color: SaStatColor.green,
                hint: '${_summary['verified'] ?? _countFor('verified')} verified',
              ),
              SaStatCard(
                label: 'Payment not done',
                value: _formatRupee(_summary['payment_not_done_amount'] ?? _summary['payment_due_amount']),
                icon: '🧾',
                color: SaStatColor.navy,
                hint: '${_summary['payment_not_done'] ?? _summary['payment_due'] ?? _countFor('payment-due')} schools',
              ),
              SaStatCard(
                label: 'Rejected fees',
                value: _formatRupee(_summary['rejected_amount']),
                icon: '✖️',
                color: SaStatColor.amber,
                hint: '${_summary['rejected'] ?? _countFor('rejected')} rejected',
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_status == 'submitted')
            const SaInfoBanner(
              title: 'Payment pending',
              body: 'Proof uploaded — tap a school to preview and verify or reject.',
            ),
          if (_status == 'payment-due')
            const SaInfoBanner(
              title: 'Payment not done',
              body: 'These schools have not uploaded membership payment proof yet.',
            ),
          if (_status == 'submitted') const SizedBox(height: 12),
          if (_status == 'payment-due') const SizedBox(height: 12),
          SaSearchField(controller: _search, hint: 'Search schools...'),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: _tabs.map((tab) {
                final selected = _status == tab.$1;
                final count = _countFor(tab.$1);
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(count > 0 ? '${tab.$2} ($count)' : tab.$2),
                    selected: selected,
                    onSelected: (_) => _switchStatus(tab.$1),
                    selectedColor: AppColors.navyPrimary.withValues(alpha: 0.15),
                    checkmarkColor: AppColors.navyPrimary,
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      color: selected ? AppColors.navyPrimary : const Color(0xFF64748B),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 12),
          if (_isPaymentDue)
            const SaInfoBanner(
              title: 'Payment not done',
              body: 'These schools completed registration but have not uploaded payment proof yet.',
            ),
          if (_isPaymentDue) const SizedBox(height: 12),
          if (_items.isEmpty)
            SaEmptyView(
              title: _isPaymentDue ? 'No schools awaiting payment' : 'No payments found',
              subtitle: _isPaymentDue
                  ? 'Schools will appear here after they register and reach the payment step.'
                  : 'Try another filter or pull to refresh.',
              icon: _isPaymentDue ? Icons.receipt_long_outlined : Icons.payments_outlined,
            )
          else if (_isPaymentDue)
            ..._items.map(_buildPaymentDueCard)
          else
            ..._items.map(_buildPaymentCard),
        ],
      ),
    );
  }

  Widget _buildPaymentDueCard(Map<String, dynamic> registration) {
    final school = registration['school'] as Map<String, dynamic>?;
    final schoolName = school?['name']?.toString() ?? 'School';
    final amount = registration['membership_fee_amount'];
    final status = registration['registration_status']?.toString() ?? 'payment_pending';
    final year = registration['academic_year']?.toString() ?? '';
    final regNo = registration['reg_no']?.toString();

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: SaEntityCard(
        title: schoolName,
        subtitle: [
          if (year.isNotEmpty) year,
          if (regNo != null && regNo.isNotEmpty) regNo,
          if (school?['school_prefix'] != null) school!['school_prefix'].toString(),
        ].join(' · '),
        status: status,
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: const Color(0xFFFFFBEB),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFFDE68A)),
          ),
          alignment: Alignment.center,
          child: const Icon(Icons.receipt_long_outlined, size: 20, color: Color(0xFFB45309)),
        ),
        footer: amount != null
            ? Text(
                'Fee due: ₹$amount',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.navyPrimary),
              )
            : const Text(
                'Awaiting payment upload from school',
                style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
              ),
      ),
    );
  }

  Widget _buildPaymentCard(Map<String, dynamic> payment) {
    final schoolName = payment['school']?['name']?.toString() ?? 'School';
    final amount = payment['amount'] ?? 0;
    final status = payment['status']?.toString() ?? 'submitted';
    final submitted = _formatSubmitted(payment['created_at']?.toString());
    final canVerify = status == 'submitted';

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: SaEntityCard(
        title: schoolName,
        subtitle: submitted.isNotEmpty ? '$submitted · Tap to review proof' : 'Tap to review payment proof',
        status: status,
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: const Color(0xFFF0FDF4),
            borderRadius: BorderRadius.circular(10),
          ),
          alignment: Alignment.center,
          child: const Text('₹', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF15803D))),
        ),
        trailing: const Icon(Icons.chevron_right, color: Color(0xFF94A3B8)),
        onTap: () => _openPayment(payment),
        footer: Row(
          children: [
            Text(
              '₹$amount',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.navyPrimary),
            ),
            const Spacer(),
            if (canVerify)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFBEB),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFFFDE68A)),
                ),
                child: const Text(
                  'Needs review',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFFB45309)),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
