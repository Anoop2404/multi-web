import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/widgets/authenticated_image.dart';
import 'school_api.dart';
import 'student_form_screen.dart';

class SchoolStudentsScreen extends ConsumerStatefulWidget {
  const SchoolStudentsScreen({super.key});

  @override
  ConsumerState<SchoolStudentsScreen> createState() => _SchoolStudentsScreenState();
}

class _SchoolStudentsScreenState extends ConsumerState<SchoolStudentsScreen> {
  List<Map<String, dynamic>> _students = [];
  List<Map<String, dynamic>> _classes = [];
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
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Text(_error!));

    return Scaffold(
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FloatingActionButton.small(
            heroTag: 'import',
            onPressed: _importCsv,
            child: const Icon(Icons.upload_file),
          ),
          const SizedBox(height: 8),
          FloatingActionButton(
            heroTag: 'add',
            onPressed: () => _openForm(),
            child: const Icon(Icons.add),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _students.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('No students yet')),
                ],
              )
            : ListView.separated(
                itemCount: _students.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  final student = _students[index];
                  final id = student['id'] as int;
                  final className = student['school_class']?['name']?.toString() ?? '';
                  return ListTile(
                    leading: CircleAvatar(
                      child: ClipOval(
                        child: AuthenticatedImage(
                          url: schoolPhotoUrl(ref, id),
                          width: 40,
                          height: 40,
                        ),
                      ),
                    ),
                    title: Text(student['name']?.toString() ?? ''),
                    subtitle: Text('${student['admission_number'] ?? ''} · $className'),
                    onTap: () => _openForm(student: student),
                    trailing: IconButton(
                      icon: const Icon(Icons.photo_camera_outlined),
                      onPressed: () => _uploadPhoto(id),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
