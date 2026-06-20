import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/sa_widgets.dart';
import 'sahodaya_api.dart';
import 'school_detail_screen.dart';

class SahodayaSchoolsScreen extends ConsumerStatefulWidget {
  const SahodayaSchoolsScreen({super.key, this.initialPaymentFilter});

  final String? initialPaymentFilter;

  @override
  ConsumerState<SahodayaSchoolsScreen> createState() => _SahodayaSchoolsScreenState();
}

class _SahodayaSchoolsScreenState extends ConsumerState<SahodayaSchoolsScreen> {
  final _search = TextEditingController();
  String? _statusFilter;
  String? _paymentFilter;
  List<Map<String, dynamic>> _schools = [];
  Map<String, dynamic>? _summary;
  String? _error;
  bool _loading = true;

  static const _filters = [
    (null, 'All'),
    ('approved', 'Approved'),
    ('pending', 'Pending'),
    ('rejected', 'Rejected'),
  ];

  static const _paymentFilters = [
    (null, 'All payments'),
    ('payment_not_done', 'Payment not done'),
    ('payment_pending', 'Payment pending'),
  ];

  @override
  void initState() {
    super.initState();
    _paymentFilter = widget.initialPaymentFilter;
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
      final results = await Future.wait([
        sahodayaGet(ref, '/schools', query: {
          'per_page': 100,
          if (_statusFilter != null) 'status': _statusFilter!,
          if (_paymentFilter != null) 'payment_status': _paymentFilter!,
          if (_search.text.trim().isNotEmpty) 'search': _search.text.trim(),
        }),
        sahodayaGet(ref, '/dashboard'),
      ]);
      final response = results[0];
      final dash = results[1];
      final data = response['data'];
      _schools = data is List ? data.cast<Map<String, dynamic>>() : [];
      _summary = (dash['data'] as Map<String, dynamic>?)?['stats'] as Map<String, dynamic>?;
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _filtered {
    final q = _search.text.trim().toLowerCase();
    if (q.isEmpty) return _schools;
    return _schools.where((school) {
      final name = school['name']?.toString().toLowerCase() ?? '';
      final prefix = school['school_prefix']?.toString().toLowerCase() ?? '';
      return name.contains(q) || prefix.contains(q);
    }).toList();
  }

  Future<void> _openSchool(Map<String, dynamic> school) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => SahodayaSchoolDetailScreen(
          schoolId: school['id'].toString(),
          preview: school,
        ),
      ),
    );
    if (changed == true) await _load();
  }

  void _setFilter(String? status) {
    if (_statusFilter == status) return;
    setState(() => _statusFilter = status);
    _load();
  }

  void _setPaymentFilter(String? status) {
    if (_paymentFilter == status) return;
    setState(() => _paymentFilter = status);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _schools.isEmpty && _error == null) return const SaLoadingView();
    if (_error != null && _schools.isEmpty) return SaErrorView(message: _error!, onRetry: _load);

    final schools = _filtered;

    return RefreshIndicator(
      color: AppColors.navyPrimary,
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _SchoolSummaryCounts(summary: _summary),
          const SizedBox(height: 12),
          SaSearchField(controller: _search, hint: 'Search schools...'),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: _filters.map((filter) {
                final selected = _statusFilter == filter.$1;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(filter.$2),
                    selected: selected,
                    onSelected: (_) => _setFilter(filter.$1),
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
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: _paymentFilters.map((filter) {
                final selected = _paymentFilter == filter.$1;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(filter.$2),
                    selected: selected,
                    onSelected: (_) => _setPaymentFilter(filter.$1),
                    selectedColor: const Color(0xFFFEF3C7),
                    checkmarkColor: const Color(0xFFB45309),
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      color: selected ? const Color(0xFFB45309) : const Color(0xFF64748B),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            '${schools.length} school${schools.length == 1 ? '' : 's'}',
            style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8), fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
          if (schools.isEmpty)
            const SaEmptyView(title: 'No schools found', subtitle: 'Try a different search or filter.')
          else
            ...schools.map((school) {
              final membershipStatus = school['membership_status']?.toString() ?? 'pending';
              final paymentStatus = school['payment_status']?.toString();
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SaEntityCard(
                  title: school['name']?.toString() ?? 'School',
                  subtitle: '${school['school_prefix'] ?? '-'} · ${school['student_count'] ?? 0} students',
                  status: membershipStatus,
                  leading: Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: AppColors.bgSky,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.borderBlue),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      (school['school_prefix']?.toString().isNotEmpty == true
                              ? school['school_prefix'].toString()[0]
                              : 'S')
                          .toUpperCase(),
                      style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.navyPrimary),
                    ),
                  ),
                  trailing: const Icon(Icons.chevron_right, color: Color(0xFF94A3B8)),
                  footer: paymentStatus != null && paymentStatus != 'none'
                      ? Row(
                          children: [
                            SaStatusChip(paymentStatus),
                            if (school['payment_amount'] != null) ...[
                              const SizedBox(width: 8),
                              Text(
                                '₹${NumberFormat.decimalPattern('en_IN').format(double.tryParse(school['payment_amount'].toString()) ?? 0)}',
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
                              ),
                            ],
                          ],
                        )
                      : null,
                  onTap: () => _openSchool(school),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _SchoolSummaryCounts extends StatelessWidget {
  const _SchoolSummaryCounts({this.summary});

  final Map<String, dynamic>? summary;

  @override
  Widget build(BuildContext context) {
    if (summary == null) return const SizedBox.shrink();

    final approved = summary!['approved_schools'] as int? ?? 0;
    final pending = summary!['pending_schools'] as int? ?? 0;
    final paymentNotDone = summary!['payment_not_done'] as int? ?? summary!['payment_due'] as int? ?? 0;
    final paymentPending = summary!['payments_pending_verification'] as int? ?? summary!['pending_payments'] as int? ?? 0;

    return SaCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _SummaryChip(label: 'Approved', count: approved, color: const Color(0xFF166534), bg: const Color(0xFFDCFCE7)),
          _SummaryChip(label: 'Pending', count: pending, color: const Color(0xFFB45309), bg: const Color(0xFFFEF3C7)),
          _SummaryChip(label: 'Payment not done', count: paymentNotDone, color: AppColors.navyPrimary, bg: AppColors.bgSky),
          _SummaryChip(label: 'Payment pending', count: paymentPending, color: const Color(0xFFB45309), bg: const Color(0xFFFFFBEB)),
        ],
      ),
    );
  }
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({required this.label, required this.count, required this.color, required this.bg});

  final String label;
  final int count;
  final Color color;
  final Color bg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Text(
        '$label · $count',
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}
