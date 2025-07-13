import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/models/display_data_model.dart';
import 'package:spacepad/services/api_service.dart';

class DisplayService {
  DisplayService._();
  static final DisplayService instance = DisplayService._();

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
    Map body = await ApiService.get('displays/$displayId/data');

    Map<String, dynamic> data = Map<String, dynamic>.from(body['data']);

    return DisplayDataModel.fromJson(data);
  }

  Future<void> cancelEvent(String displayId, String eventId) async {
    await ApiService.delete('displays/$displayId/events/$eventId');
  }

  Future<void> checkInToEvent(String displayId, String eventId) async {
    await ApiService.post('displays/$displayId/events/$eventId/check-in', {});
  }
}