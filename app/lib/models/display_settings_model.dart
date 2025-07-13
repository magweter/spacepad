class DisplaySettingsModel {
  bool checkInEnabled;
  bool bookingEnabled;
  int checkInGracePeriod;
  int checkInMinutes;
  bool calendarEnabled;

  DisplaySettingsModel({
    required this.checkInEnabled,
    required this.bookingEnabled,
    required this.checkInGracePeriod,
    required this.checkInMinutes,
    required this.calendarEnabled,
  });

  factory DisplaySettingsModel.fromJson(Map data) {
    return DisplaySettingsModel(
      checkInEnabled: data['check_in_enabled'] ?? false,
      bookingEnabled: data['booking_enabled'] ?? false,
      checkInGracePeriod: data['check_in_grace_period'] ?? 5,
      checkInMinutes: data['check_in_minutes'] ?? 15,
      calendarEnabled: data['calendar_enabled'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'check_in_enabled': checkInEnabled,
      'booking_enabled': bookingEnabled,
      'check_in_grace_period': checkInGracePeriod,
      'check_in_minutes': checkInMinutes,
      'calendar_enabled': calendarEnabled,
    };
  }
} 