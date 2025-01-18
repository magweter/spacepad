import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:http/http.dart';
import 'package:get/get.dart' as GetX;

import 'package:http/http.dart' as http;
import 'package:spacepad/exceptions/api_exception.dart';
import 'package:spacepad/services/auth_service.dart';


class ApiService {
  ApiService._();

  static Future get(String endpoint) async {
    if (kDebugMode) print('GET: ${dotenv.env['APP_URL']!}$endpoint');

    try {
      Response response = await http.get(Uri.parse('${dotenv.env['APP_URL']!}$endpoint'), headers: _getHeaders());

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      }

      throw ApiException.fromResponse(response);
    } on ApiException catch (e) {
      if (kDebugMode) print('${e.code}: ${e.message}');

      if (e.code == 401 || e.code == 403) {
        AuthService.instance.signOut();

        return;
      }

      rethrow;
    }
  }

  static Future post(String endpoint, Map body) async {
    if (kDebugMode) print('POST: ${dotenv.env['APP_URL']!}$endpoint');

    try {
      Response response = await http.post(
          Uri.parse('${dotenv.env['APP_URL']!}$endpoint'),
          headers: _getHeaders(),
          body: jsonEncode(body)
      );

      if ([200, 201, 202, 204].contains(response.statusCode)) {
        return jsonDecode(response.body);
      }

      throw ApiException.fromResponse(response);
    } on ApiException catch (e) {
      if (kDebugMode) print('${e.code}: ${e.message}');
      rethrow;
    }
  }

  static Future put(String endpoint, Map body) async {
    if (kDebugMode) print('PUT: ${dotenv.env['APP_URL']!}$endpoint');

    try {
      Response response = await http.put(
          Uri.parse('${dotenv.env['APP_URL']!}$endpoint'),
          headers: _getHeaders(),
          body: jsonEncode(body)
      );

      if ([200, 201, 202, 204].contains(response.statusCode)) {
        return jsonDecode(response.body);
      }

      throw ApiException.fromResponse(response);
    } on ApiException catch (e) {
      if (kDebugMode) print('${e.code}: ${e.message}');
      rethrow;
    }
  }

  static Future delete(String endpoint, [Map? body]) async {
    if (kDebugMode) print('DELETE: ${dotenv.env['APP_URL']!}$endpoint');

    try {
      Response response = await http.delete(
          Uri.parse('${dotenv.env['APP_URL']!}$endpoint'),
          headers: _getHeaders(),
          body: jsonEncode(body)
      );

      if (response.statusCode == 204) {
        return;
      }

      if ([200, 201, 202].contains(response.statusCode)) {
        return jsonDecode(response.body);
      }

      throw ApiException.fromResponse(response);
    } on ApiException catch (e) {
      if (kDebugMode) print('${e.code}: ${e.message}');
      rethrow;
    }
  }

  static Map<String, String>? _getHeaders() {
    Map<String, String> headers = {
      'Content-Type' : 'application/json',
      'Accept' : 'application/json',
      'Accept-Language' : GetX.Get.locale?.languageCode ?? 'en'
    };

    if (AuthService.instance.getAuthToken() != null) {
      headers['Authorization'] = 'Bearer ${AuthService.instance.getAuthToken()}';
    }

    return headers;
  }
}