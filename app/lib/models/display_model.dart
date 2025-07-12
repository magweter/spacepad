import 'display_settings_model.dart';

class DisplayModel {
  String id;
  String name;
  DisplaySettingsModel settings;

  DisplayModel({
    required this.id,
    required this.name,
    required this.settings,
  });

  factory DisplayModel.fromJson(Map data) {
    return DisplayModel(
      id: data['id'],
      name: data['name'],
      settings: DisplaySettingsModel.fromJson(data['settings'] ?? {}),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'settings': settings.toJson(),
    };
  }
}