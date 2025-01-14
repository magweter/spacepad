import 'package:spacepad/services/api_service.dart';

class DeviceService {
  DeviceService._();
  static final DeviceService instance = DeviceService._();

  Future<void> changeDisplay(String displayId) async {
    await ApiService.put('devices/display', {
      "display_id" : displayId,
    });
  }
}