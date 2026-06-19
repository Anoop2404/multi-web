import 'package:dio/dio.dart';

import 'api_exception.dart';

ApiException apiExceptionFromDio(DioException error) {
  final data = error.response?.data;
  if (data is Map<String, dynamic>) {
    final validation = data['errors'];
    if (validation is Map<String, dynamic>) {
      final first = validation.values.first;
      final text = first is List && first.isNotEmpty ? first.first.toString() : data['message']?.toString();
      return ApiException(
        text ?? 'Request failed.',
        statusCode: error.response?.statusCode,
        errors: validation.cast<String, dynamic>(),
      );
    }
    if (data['message'] is String) {
      final message = data['message'] as String;
      if (message.contains('personal_access_tokens')) {
        return ApiException(
          'Mobile login is not set up on the server yet. Ask your admin to run: php artisan migrate',
          statusCode: error.response?.statusCode,
        );
      }
      return ApiException(message, statusCode: error.response?.statusCode);
    }
  }
  return ApiException(error.message ?? 'Network error.', statusCode: error.response?.statusCode);
}
