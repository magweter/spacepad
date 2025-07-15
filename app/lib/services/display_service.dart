import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/models/display_data_model.dart';
import 'package:spacepad/services/api_service.dart';

class DisplayService {
  DisplayService._();
  static final DisplayService instance = DisplayService._();

  bool? _newRouteAvailable;

  Future<List<DisplayModel>> getDisplays() async {
    Map body = await ApiService.get('displays');

    List data = body['data'] as List;

    return data.map((e) => DisplayModel.fromJson(e)).toList();
  }

  Future<void> book(String displayId, int duration, {String? summary}) async {
    await ApiService.post('displays/$displayId/book', {
      'duration': duration,
      if (summary != null) 'summary': summary,
    });
  }

  Future<DisplayDataModel> getDisplayData(String displayId) async {
    // If we already know which route to use, use it
    if (_newRouteAvailable == false) {
      return _getDisplayDataOld(displayId);
    }

    try {
      final data = await _getDisplayDataNew(displayId);
      _newRouteAvailable = true;
      return data;
    } catch (e) {
      if (_isRouteNotFoundError(e)) {
        _newRouteAvailable = false;
        return _getDisplayDataOld(displayId);
      }
      rethrow;
    }
  }

  Future<DisplayDataModel> _getDisplayDataNew(String displayId) async {
    Map body = await ApiService.get('displays/$displayId/data');
    Map<String, dynamic> data = Map<String, dynamic>.from(body['data']);
    return DisplayDataModel.fromJson(data);
  }

  Future<DisplayDataModel> _getDisplayDataOld(String displayId) async {
    Map body = await ApiService.get('events');
    List data = body['data'] as List;
    return DisplayDataModel.fromEventsJson(data);
  }

  bool _isRouteNotFoundError(dynamic e) {
    // Check if the error is a 404 or similar
    return e.toString().contains('404');
  }

  Future<void> cancelEvent(String displayId, String eventId) async {
    await ApiService.delete('displays/$displayId/events/$eventId');
  }

  Future<void> checkInToEvent(String displayId, String eventId) async {
    await ApiService.post('displays/$displayId/events/$eventId/check-in', {});
  }
}