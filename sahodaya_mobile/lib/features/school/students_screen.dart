import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/authenticated_image.dart';
import '../../core/widgets/sa_widgets.dart';
import 'school_api.dart';
import 'student_form_screen.dart';

class SchoolStudentsScreen extends ConsumerStatefulWidget {
  const SchoolStudentsScreen({super.key});

  @override
  ConsumerState<SchoolStudentsScreen> createState() => _SchoolStudentsScreenState();
}

class _SchoolStudentsScreenState extends ConsumerState<SchoolStudentsScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _students = [];
  List<Map<String, dynamic>> _classes = [];
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
    _search.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await schoolGet(ref, '/students');
      _students = (response['data'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      final meta = response['meta'] as Map<String, dynamic>?;
      _classes = (meta?['classes'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    } catch (error) {
      _error = error.toString();
    } finally {
      setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _filtered {
    final q = _search.text.trim().toLowerCase();
    if (q.isEmpty) return _students;
    return _students.where((student) {
      final name = student['name']?.toString().toLowerCase() ?? '';
      final admission = student['admission_number']?.toString().toLowerCase() ?? '';
      final className = student['school_class']?['name']?.toString().toLowerCase() ?? '';
      return name.contains(q) || admission.contains(q) || className.contains(q);
    }).toList();
  }

  Future<void> _importCsv() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['csv', 'txt'],
    );
    if (result == null || result.files.single.path == null) return;

    try {
      final formData = FormData.fromMap({
        'file': await MultipartFile.fromFile(result.files.single.path!),
      });
      await schoolMultipart(ref, '/students/import', formData: formData);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Import completed')),
        );
      }
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())),
        );
      }
    }
  }

  Future<void> _uploadPhoto(int studentId) async {
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.gallery, maxWidth: 1200);
    if (image == null) return;

    try {
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(image.path),
      });
      await schoolMultipart(ref, '/students/$studentId/photo', formData: formData);
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())),
        );
      }
    }
  }

  Future<void> _openForm({Map<String, dynamic>? student}) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => SchoolStudentFormScreen(student: student, classes: _classes),
      ),
    );
    if (saved == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SaLoadingView();
    if (_error != null) return SaErrorView(message: _error!, onRetry: _load);

    final students = _filtered;

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FloatingActionButton.small(
            heroTag: 'import',
            backgroundColor: Colors.white,
            foregroundColor: AppColors.navyPrimary,
            onPressed: _importCsv,
            child: const Icon(Icons.upload_file),
          ),
          const SizedBox(height: 8),
          FloatingActionButton(
            heroTag: 'add',
            backgroundColor: AppColors.navyPrimary,
            onPressed: () => _openForm(),
            child: const Icon(Icons.add),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: AppColors.navyPrimary,
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SaSearchField(controller: _search, hint: 'Search students...'),
            const SizedBox(height: 8),
            Text(
              '${_students.length} student${_students.length == 1 ? '' : 's'}',
              style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8), fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            if (students.isEmpty)
              const SaEmptyView(
                title: 'No students yet',
                subtitle: 'Tap + to add a student or use the upload button to import a CSV.',
                icon: Icons.school_outlined,
              )
            else
              ...students.map((student) {
                final id = student['id'] as int;
                final className = student['school_class']?['name']?.toString() ?? '';
                final admission = student['admission_number']?.toString() ?? '';
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: SaEntityCard(
                    title: student['name']?.toString() ?? 'Student',
                    subtitle: [if (admission.isNotEmpty) admission, if (className.isNotEmpty) className].join(' · '),
                    leading: CircleAvatar(
                      radius: 20,
                      backgroundColor: AppColors.bgSky,
                      child: ClipOval(
                        child: AuthenticatedImage(
                          url: schoolPhotoUrl(ref, id),
                          width: 40,
                          height: 40,
                        ),
                      ),
                    ),
                    trailing: IconButton(
                      icon: const Icon(Icons.photo_camera_outlined, size: 20, color: AppColors.navyLight),
                      onPressed: () => _uploadPhoto(id),
                    ),
                    onTap: () => _openForm(student: student),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}
