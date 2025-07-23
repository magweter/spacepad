class DisplaySettingsModel {
  bool checkInEnabled;
  bool bookingEnabled;
  int checkInGracePeriod;
  int checkInMinutes;
  bool calendarEnabled;
  String? textAvailable;
  String? textTransitioning;
  String? textReserved;
  String? textCheckin;
  bool showMeetingTitle;

  DisplaySettingsModel({
    required this.checkInEnabled,
    required this.bookingEnabled,
    required this.checkInGracePeriod,
    required this.checkInMinutes,
    required this.calendarEnabled,
    this.textAvailable,
    this.textTransitioning,
    this.textReserved,
    this.textCheckin,
    required this.showMeetingTitle,
  });

  factory DisplaySettingsModel.fromJson(Map data) {
    return DisplaySettingsModel(
      checkInEnabled: data['check_in_enabled'] ?? false,
      bookingEnabled: data['booking_enabled'] ?? false,
      checkInGracePeriod: data['check_in_grace_period'] ?? 5,
      checkInMinutes: data['check_in_minutes'] ?? 15,
      calendarEnabled: data['calendar_enabled'] ?? false,
      textAvailable: data['text_available'],
      textTransitioning: data['text_transitioning'],
      textReserved: data['text_reserved'],
      textCheckin: data['text_checkin'],
      showMeetingTitle: data['show_meeting_title'] ?? true,
    );
  }

  Map<String, dynamic>? toJson() {
    return {
      'check_in_enabled': checkInEnabled,
      'booking_enabled': bookingEnabled,
      'check_in_grace_period': checkInGracePeriod,
      'check_in_minutes': checkInMinutes,
      'calendar_enabled': calendarEnabled,
      'text_available': textAvailable,
      'text_transitioning': textTransitioning,
      'text_reserved': textReserved,
      'text_checkin': textCheckin,
      'show_meeting_title': showMeetingTitle,
    };
  }
} 