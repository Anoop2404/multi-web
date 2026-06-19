import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';

import '../auth/auth_providers.dart';
import '../theme/app_theme.dart';

enum DocumentKind { image, pdf, unknown }

DocumentKind detectDocumentKind(Uint8List bytes, {String? contentType}) {
  if (contentType != null) {
    if (contentType.startsWith('image/')) return DocumentKind.image;
    if (contentType.contains('pdf')) return DocumentKind.pdf;
  }
  if (bytes.length >= 4) {
    if (bytes[0] == 0x25 && bytes[1] == 0x50 && bytes[2] == 0x44 && bytes[3] == 0x46) {
      return DocumentKind.pdf;
    }
    if (bytes[0] == 0xFF && bytes[1] == 0xD8) return DocumentKind.image;
    if (bytes[0] == 0x89 && bytes[1] == 0x50 && bytes[2] == 0x4E && bytes[3] == 0x47) {
      return DocumentKind.image;
    }
  }
  return DocumentKind.unknown;
}

class AuthenticatedDocument extends ConsumerStatefulWidget {
  const AuthenticatedDocument({
    super.key,
    required this.path,
    this.height = 320,
  });

  final String path;
  final double height;

  @override
  ConsumerState<AuthenticatedDocument> createState() => _AuthenticatedDocumentState();
}

class _AuthenticatedDocumentState extends ConsumerState<AuthenticatedDocument> {
  Uint8List? _bytes;
  DocumentKind? _kind;
  bool _loading = true;
  bool _failed = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(covariant AuthenticatedDocument oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
      _error = null;
    });
    try {
      final result = await ref.read(apiClientProvider).download(widget.path);
      if (!mounted) return;
      if (result.bytes.isEmpty) {
        setState(() {
          _failed = true;
          _error = 'Document is empty';
          _loading = false;
        });
        return;
      }
      setState(() {
        _bytes = result.bytes;
        _kind = detectDocumentKind(result.bytes, contentType: result.contentType);
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _failed = true;
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _openExternally() async {
    if (_bytes == null) return;
    final ext = _kind == DocumentKind.pdf ? 'pdf' : 'jpg';
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/payment_proof_${DateTime.now().millisecondsSinceEpoch}.$ext');
    await file.writeAsBytes(_bytes!);
    await OpenFile.open(file.path);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Container(
        height: widget.height,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        alignment: Alignment.center,
        child: const CircularProgressIndicator(color: AppColors.navyPrimary, strokeWidth: 2),
      );
    }

    if (_failed || _bytes == null) {
      return Container(
        height: widget.height,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF2F2),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFFECACA)),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, color: Color(0xFFB91C1C), size: 36),
            const SizedBox(height: 8),
            Text(
              _error ?? 'Could not load payment proof',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
            ),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    if (_kind == DocumentKind.image) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE2E8F0)),
            borderRadius: BorderRadius.circular(12),
          ),
          child: InteractiveViewer(
            minScale: 0.5,
            maxScale: 4,
            child: Image.memory(_bytes!, fit: BoxFit.contain, width: double.infinity),
          ),
        ),
      );
    }

    final isPdf = _kind == DocumentKind.pdf;
    return Container(
      height: widget.height,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            isPdf ? Icons.picture_as_pdf : Icons.insert_drive_file_outlined,
            size: 56,
            color: isPdf ? const Color(0xFFB91C1C) : AppColors.navyLight,
          ),
          const SizedBox(height: 12),
          Text(
            isPdf ? 'PDF payment proof uploaded' : 'Payment proof uploaded',
            style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.textDark),
          ),
          const SizedBox(height: 6),
          const Text(
            'Tap below to open the document on your device.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _openExternally,
            icon: const Icon(Icons.open_in_new, size: 18),
            label: const Text('Open document'),
            style: FilledButton.styleFrom(backgroundColor: AppColors.navyPrimary),
          ),
        ],
      ),
    );
  }
}
