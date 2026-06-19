import 'package:dio/dio.dart';

import '../../config/env.dart';
import '../../core/api/dio_errors.dart';

class SchoolRegisterApi {
  SchoolRegisterApi({Dio? dio}) : _dio = dio ?? Dio(
        BaseOptions(
          baseUrl: AppEnv.apiBaseUrl,
          headers: {'Accept': 'application/json'},
          connectTimeout: const Duration(seconds: 20),
          receiveTimeout: const Duration(seconds: 30),
        ),
      );

  final Dio _dio;

  Future<Map<String, dynamic>> loadForm() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('/api/v1/public/school-register');
      return response.data?['data'] as Map<String, dynamic>? ?? {};
    } on DioException catch (error) {
      throw apiExceptionFromDio(error);
    }
  }

  Future<String> submit(Map<String, dynamic> body) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>('/api/v1/public/school-register', data: body);
      return response.data?['message'] as String? ?? 'Application submitted.';
    } on DioException catch (error) {
      throw apiExceptionFromDio(error);
    }
  }
}
