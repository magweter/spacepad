import 'package:spacepad/models/user_model.dart';
import 'package:spacepad/models/display_model.dart';

class DeviceModel {
  final String id;
  final String name;
  UserModel? user;
  DisplayModel? display;

  DeviceModel({required this.id, required this.name, required this.user, this.display});

  factory DeviceModel.fromJson(Map data) {
    return DeviceModel(
        id: data['id'],
        name: data['name'],
        user: data['user'] != null ? UserModel.fromJson(data['user']) : null,
        display: data['display'] != null ? DisplayModel.fromJson(data['display']) : null,
    );
  }
}