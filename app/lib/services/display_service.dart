import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/services/api_service.dart';

class DisplayService {
  DisplayService._();
  static final DisplayService instance = DisplayService._();

  Future<List<DisplayModel>> getDisplays() async {
    Map body = await ApiService.get('displays');

    List data = body['data'] as List;

    return data.map((e) => DisplayModel.fromJson(e)).toList();
  }
}