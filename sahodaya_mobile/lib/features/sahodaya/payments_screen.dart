import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/widgets/authenticated_image.dart';
import 'sahodaya_api.dart';

class SahodayaPaymentsScreen extends ConsumerStatefulWidget {
  const SahodayaPaymentsScreen({super.key});

  @override
  ConsumerState<SahodayaPaymentsScreen> createState() => _SahodayaPaymentsScreenState();
}

class _SahodayaPaymentsScreenState extends ConsumerState<SahodayaPaymentsScreen> {
  List<Map<String, dynamic>> _payments = [];
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
      final response = await sahodayaGet(ref, '/payments', query: {'status': 'submitted'});
      final data = response['data'];
      _payments = data is List ? data.cast<Map<String, dynamic>>() : [];
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _verify(int paymentId, String action, {String? reason}) async {
    try {
      await sahodayaPost(ref, '/payments/$paymentId/verify', body: {
        'action': action,
        if (reason != null) 'reason': reason,
      });
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _reject(int paymentId) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (context) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Reject payment'),
          content: TextField(
            controller: controller,
            decoration: const InputDecoration(labelText: 'Reason'),
            maxLines: 3,
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              child: const Text('Reject'),
            ),
          ],
        );
      },
    );
    if (reason == null || reason.isEmpty) return;
    await _verify(paymentId, 'reject', reason: reason);
  }

  void _showProof(int paymentId) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: AuthenticatedImage(
            url: sahodayaPaymentProofUrl(ref, paymentId),
            height: 400,
            fit: BoxFit.contain,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Text(_error!));

    return RefreshIndicator(
      onRefresh: _load,
      child: _payments.isEmpty
          ? ListView(
              children: const [
                SizedBox(height: 120),
                Center(child: Text('No pending payments')),
              ],
            )
          : ListView.separated(
              itemCount: _payments.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final payment = _payments[index];
                final id = payment['id'] as int;
                final schoolName = payment['school']?['name']?.toString() ?? 'School';
                return ListTile(
                  title: Text(schoolName),
                  subtitle: Text('₹${payment['amount'] ?? 0} · ${payment['status']}'),
                  onTap: () => _showProof(id),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.check_circle_outline, color: Colors.green),
                        onPressed: () => _verify(id, 'verify'),
                      ),
                      IconButton(
                        icon: const Icon(Icons.cancel_outlined, color: Colors.red),
                        onPressed: () => _reject(id),
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
