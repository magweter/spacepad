class DisplaySettingsModel {
  bool checkInEnabled;
  bool bookingEnabled;
  bool hasCustomBooking;
  int checkInGracePeriod;
  int checkInMinutes;
  bool calendarEnabled;
  bool hideAdminActions;
  String? textAvailable;
  String? textTransitioning;
  String? textReserved;
  String? textCheckin;
  bool showMeetingTitle;
  String? logoUrl;
  String? backgroundImageUrl;
  String fontFamily;
  String cancelPermission;
  String borderThickness;

  DisplaySettingsModel({
    required this.checkInEnabled,
    required this.bookingEnabled,
    required this.hasCustomBooking,
    required this.checkInGracePeriod,
    required this.checkInMinutes,
    required this.calendarEnabled,
    required this.hideAdminActions,
    this.textAvailable,
    this.textTransitioning,
    this.textReserved,
    this.textCheckin,
    required this.showMeetingTitle,
    this.logoUrl,
    this.backgroundImageUrl,
    required this.fontFamily,
    this.cancelPermission = 'all',
    this.borderThickness = 'medium',
  });

  factory DisplaySettingsModel.fromJson(Map data) {
    return DisplaySettingsModel(
      checkInEnabled: data['check_in_enabled'] ?? false,
      bookingEnabled: data['booking_enabled'] ?? false,
      hasCustomBooking: data['has_custom_booking'] ?? false,
      checkInGracePeriod: data['check_in_grace_period'] ?? 5,
      checkInMinutes: data['check_in_minutes'] ?? 15,
      calendarEnabled: data['calendar_enabled'] ?? false,
      hideAdminActions: data['hide_admin_actions'] ?? false,
      textAvailable: data['text_available'],
      textTransitioning: data['text_transitioning'],
      textReserved: data['text_reserved'],
      textCheckin: data['text_checkin'],
      showMeetingTitle: data['show_meeting_title'] ?? true,
      logoUrl: data['logo_url'],
      backgroundImageUrl: data['background_image_url'],
      fontFamily: data['font_family'] ?? 'Inter',
      cancelPermission: data['cancel_permission'] ?? 'all',
      borderThickness: data['border_thickness'] ?? 'medium',
    );
  }

  Map<String, dynamic>? toJson() {
    return {
      'check_in_enabled': checkInEnabled,
      'booking_enabled': bookingEnabled,
      'has_custom_booking': hasCustomBooking,
      'check_in_grace_period': checkInGracePeriod,
      'check_in_minutes': checkInMinutes,
      'calendar_enabled': calendarEnabled,
      'hide_admin_actions': hideAdminActions,
      'text_available': textAvailable,
      'text_transitioning': textTransitioning,
      'text_reserved': textReserved,
      'text_checkin': textCheckin,
      'show_meeting_title': showMeetingTitle,
      'logo_url': logoUrl,
      'background_image_url': backgroundImageUrl,
      'font_family': fontFamily,
    };
  }
} 