import 'package:http/http.dart' as http;

class ServerService {
  static final ServerService _instance = ServerService._internal();
  factory ServerService() => _instance;
  ServerService._internal();

  /// Checks if a server is reachable by making a GET request to its health endpoint
  /// Returns true if the server responds with a 200 status code within 5 seconds
  Future<bool> isServerReachable(String url) async {
    try {
      final response = await http.get(
        Uri.parse('$url/health'),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 5));
      
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }
} 