import 'dart:io';
import 'package:http/http.dart' as http;

class ServerService {
  static final ServerService _instance = ServerService._internal();
  factory ServerService() => _instance;
  ServerService._internal();

  Future<bool> isServerReachable(String url) async {
    try {
      final response = await http.get(
        Uri.parse('$url/health'),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 5));

      return response.statusCode == 200;
    } on HandshakeException {
      rethrow;
    } catch (e) {
      return false;
    }
  }
}