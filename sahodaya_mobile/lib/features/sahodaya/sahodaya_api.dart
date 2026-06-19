import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/env.dart';
import '../../core/api/dio_errors.dart';
import '../../core/auth/auth_providers.dart';

String sahodayaBase(WidgetRef ref) {
  final tenantId = ref.read(authControllerProvider).session!.user.tenantId;
  return '/api/v1/sahodaya/$tenantId';
}

Future<Map<String, dynamic>> sahodayaGet(WidgetRef ref, String path, {Map<String, dynamic>? query}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.getJson('${sahodayaBase(ref)}$path', query: query);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

Future<Map<String, dynamic>> sahodayaPost(WidgetRef ref, String path, {Map<String, dynamic>? body}) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.postJson('${sahodayaBase(ref)}$path', body: body);
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}

String sahodayaPaymentProofUrl(WidgetRef ref, int paymentId) {
  final tenantId = ref.read(authControllerProvider).session!.user.tenantId;
  return '${AppEnv.apiBaseUrl}/api/v1/sahodaya/$tenantId/payments/$paymentId/proof';
}

Future<List<int>> sahodayaDownload(WidgetRef ref, String path) async {
  final api = ref.read(apiClientProvider);
  try {
    return await api.downloadBytes('${sahodayaBase(ref)}$path');
  } on DioException catch (error) {
    throw apiExceptionFromDio(error);
  }
}
