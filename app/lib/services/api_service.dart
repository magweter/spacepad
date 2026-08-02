import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:http/http.dart';
import 'package:get/get.dart' as GetX;

import 'package:http/http.dart' as http;
import 'package:spacepad/exceptions/api_exception.dart';
import 'package:spacepad/services/auth_service.dart';

import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  ApiService._();

  static Future<bool> setBaseUrl(String apiUrl) async {
    var sharedPrefs = await SharedPreferences.getInstance();
    return sharedPrefs.setString('api_url', apiUrl);
  }

  static Future<bool> resetToServerBaseUrl() async {
    var apiUrl = dotenv.env['API_URL'] ?? 'https://app.spacepad.io';
    return await setBaseUrl(apiUrl);
  }

  static Future<String> getBaseUrl() async {
    var sharedPrefs = await SharedPreferences.getInstance();
    var apiUrl = sharedPrefs.getString('api_url');
    return '$apiUrl/api/';
  }

  static Future get(String endpoint) async {
    var baseUrl = await getBaseUrl();
    if (kDebugMode) print('GET: $baseUrl$endpoint');

    try {
      Response response = await http.get(Uri.parse('$baseUrl$endpoint'), headers: _getHeaders());

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      }

      throw ApiException.fromResponse(response);
    } on ApiException catch (e) {
      if (kDebugMode) print('${e.code}: ${e.message}');

      if (e.code == 401) {
        await AuthService.instance.signOut();
        return;
      }

      rethrow;
    }
  }

  static Future post(String endpoint, Map body) async {
    var baseUrl = await getBaseUrl();
    if (kDebugMode) print('POST: $baseUrl$endpoint');

    try {
      Response response = await http.post(
          Uri.parse('$baseUrl$endpoint'),
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
    var baseUrl = await getBaseUrl();
    if (kDebugMode) print('PUT: $baseUrl$endpoint');

    try {
      Response response = await http.put(
          Uri.parse('$baseUrl$endpoint'),
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
    var baseUrl = await getBaseUrl();
    if (kDebugMode) print('DELETE: $baseUrl$endpoint');

    try {
      Response response = await http.delete(
          Uri.parse('$baseUrl$endpoint'),
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
    final DateTime now = DateTime.now();

    Map<String, String> headers = {
      'Content-Type' : 'application/json',
      'Accept' : 'application/json',
      'Accept-Language' : GetX.Get.locale?.languageCode ?? 'en',
      // Tell the backend which calendar day this tablet is on. The server runs in its own
      // timezone (usually UTC) and cannot know ours, so without this it has to guess the day
      // boundary — which either cut off part of our evening or leaked tomorrow's bookings into
      // the payload. Sent on every request so each endpoint can answer for the right day.
      'X-Local-Date' : _localDate(now),
      'X-Utc-Offset' : now.timeZoneOffset.inMinutes.toString(),
    };

    if (AuthService.instance.getAuthToken() != null) {
      headers['Authorization'] = 'Bearer ${AuthService.instance.getAuthToken()}';
    }

    return headers;
  }

  /// The device's calendar date as YYYY-MM-DD, in its own timezone.
  static String _localDate(DateTime now) {
    final String month = now.month.toString().padLeft(2, '0');
    final String day = now.day.toString().padLeft(2, '0');

    return '${now.year}-$month-$day';
  }
}