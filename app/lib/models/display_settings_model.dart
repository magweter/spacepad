class DisplaySettingsModel {
  bool checkInEnabled;
  bool bookingEnabled;

  DisplaySettingsModel({
    required this.checkInEnabled,
    required this.bookingEnabled,
  });

  factory DisplaySettingsModel.fromJson(Map data) {
    return DisplaySettingsModel(
      checkInEnabled: data['check_in_enabled'] ?? false,
      bookingEnabled: data['booking_enabled'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'check_in_enabled': checkInEnabled,
      'booking_enabled': bookingEnabled,
    };
  }
} 