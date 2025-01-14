import 'dart:convert';
import 'package:http/http.dart';

class ApiException implements Exception {
  final int code;
  final String? message;
  final Map? errors;

  ApiException({required this.code, this.message, this.errors});

  static ApiException fromResponse(Response response) {
    return ApiException(
        code: response.statusCode,
        message: jsonDecode(response.body)['message'],
        errors: _mapErrors(jsonDecode(response.body)['errors'])
    );
  }

  static Map? _mapErrors(Map? errors) {
    return errors?.map((key, value) {
      return MapEntry(key, value?.isNotEmpty ? value.first : '');
    });
  }

  @override
  String toString() => 'ApiException: $code - $message';
}