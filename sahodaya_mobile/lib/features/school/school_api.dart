import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/env.dart';
import '../../core/api/dio_errors.dart';
import '../../core/auth/auth_providers.dart';

String schoolBase(WidgetRef ref) {
  final tenantId = ref.read(authControllerProvider).session!.user.tenantId;
  return '/api/v1/school/$tenantId';
}

Future<Map<String, dynamic>> schoolGet(WidgetRef ref, String path, {Map<String, dynamic>? query}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.getJson('${schoolBase(ref)}$path', query: query);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

Future<Map<String, dynamic>> schoolPost(WidgetRef ref, String path, {Map<String, dynamic>? body}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.postJson('${schoolBase(ref)}$path', body: body);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

Future<Map<String, dynamic>> schoolPut(WidgetRef ref, String path, {Map<String, dynamic>? body}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.putJson('${schoolBase(ref)}$path', body: body);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

Future<Map<String, dynamic>> schoolDelete(WidgetRef ref, String path) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.deleteJson('${schoolBase(ref)}$path');
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

Future<Map<String, dynamic>> schoolMultipart(
  WidgetRef ref,
  String path, {
  required FormData formData,
}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.postMultipart('${schoolBase(ref)}$path', formData: formData);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

String schoolPhotoUrl(WidgetRef ref, int studentId) {
  final tenantId = ref.read(authControllerProvider).session!.user.tenantId;
  return '${AppEnv.apiBaseUrl}/api/v1/school/$tenantId/students/$studentId/photo';
}
