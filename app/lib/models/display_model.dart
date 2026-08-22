import 'display_settings_model.dart';

class DisplayModel {
  String id;

  /// The room name, printed in the header corner of the dashboard.
  String name;

  /// The "Display name" from the portal: dashboard-only and unique per display.
  ///
  /// Null against a backend that predates the field. Room names are deliberately shared in
  /// setups that put a building name there, so anything that has to tell displays apart —
  /// the setup wizard — needs this one instead.
  String? dashboardName;

  DisplaySettingsModel settings;

  DisplayModel({
    required this.id,
    required this.name,
    this.dashboardName,
    required this.settings,
  });

  factory DisplayModel.fromJson(Map data) {
    return DisplayModel(
      id: data['id'],
      name: data['name'],
      dashboardName: data['dashboard_name'],
      settings: DisplaySettingsModel.fromJson(data['settings'] ?? {}),
    );
  }

  /// The name to pick this display out of a list by, falling back to the room name.
  String get pickerName => (dashboardName?.trim().isNotEmpty ?? false) ? dashboardName!.trim() : name;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'dashboard_name': dashboardName,
      'settings': settings.toJson(),
    };
  }
}