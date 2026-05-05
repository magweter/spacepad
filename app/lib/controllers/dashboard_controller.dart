import 'dart:async';
import 'dart:io';

import 'package:get/get.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/exceptions/api_exception.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/models/display_data_model.dart';
import 'package:spacepad/models/event_status.dart';
import 'package:spacepad/services/display_service.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/pages/display_page.dart';
import 'package:spacepad/models/device_model.dart';
import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/models/display_settings_model.dart';
import 'package:spacepad/services/font_service.dart';
import 'package:flutter/material.dart';
import 'package:spacepad/components/custom_booking_modal.dart';

class DashboardController extends GetxController {
  final RxBool loading = RxBool(true);
  final RxList<EventModel> events = RxList();
  final Rx<DateTime> time = Rx<DateTime>(DateTime.now());
  final RxString displayId = RxString('');

  // Global variables for device, display, and settings
  DeviceModel? globalCurrentDevice;
  DisplayModel? globalDisplay;
  final Rx<DisplaySettingsModel?> globalSettings = Rx<DisplaySettingsModel?>(null);
  
  // Reactive font family for UI updates
  final RxString currentFontFamily = RxString('Inter');
  
  Timer? _clock;
  Timer? _dataTimer;
  
  // Track refresh state to prevent spamming
  final RxBool isRefreshing = RxBool(false);
  DateTime? _lastRefreshTime;
  static const int _refreshCooldownSeconds = 3;

  // Stale data indicator
  final RxBool isDataStale = RxBool(false);
  // ignore: unused_field
  DateTime? _lastSuccessfulFetchAt;

  @override
  void onInit() async {
    super.onInit();

    updateTime();
    
    // Check if display ID is set, redirect to display page if not
    final displayIdResult = AuthService.instance.getCurrentDisplayId();
    if (displayIdResult == null) {
      Get.offAll(() => const DisplayPage());
      return;
    } else {
      displayId.value = displayIdResult;
    }

    initializeTimers();
    await fetchDisplayData();
    
    // Preload fonts for better performance
    await FontService.instance.preloadFonts();

    loading.value = false;
  }

  void initializeTimers() {
    final int millisecondsToNextSecond = DateTime.now().millisecond;

    // Start a timer that aligns with the next second for data refresh (every 60 seconds)
    Future.delayed(Duration(milliseconds: millisecondsToNextSecond), () {
      _dataTimer = Timer.periodic(const Duration(seconds: 60), (timer) => fetchDisplayData());
    });

    _clock = Timer.periodic(const Duration(seconds: 1), (timer) => updateTime());
  }

  void startAdvertisementTimers() {
    _advertisementIntervalTimer?.cancel();
    // Do not cancel _advertisementDismissTimer here — it may be actively
    // counting down while the ad is visible, and fetchDisplayData() calls
    // this method every 60 seconds which would otherwise kill the timer.

    final settings = globalSettings.value;
    if (settings?.advertisementEnabled != true) {
      showAdvertisement.value = false;
      _advertisementDismissTimer?.cancel();
      return;
    }

    final adUrl = settings?.advertisementImageUrl;
    if (adUrl == null) return;

    final intervalMinutes = settings?.advertisementInterval ?? 5;

    _advertisementIntervalTimer = Timer.periodic(
      Duration(minutes: intervalMinutes),
      (timer) => _showAdvertisement(),
    );
  }

  void _showAdvertisement() {
    final settings = globalSettings.value;
    if (settings?.advertisementEnabled != true) return;

    final adUrl = settings?.advertisementImageUrl;
    if (adUrl == null) return;

    final durationSeconds = settings?.advertisementDuration ?? 15;

    showAdvertisement.value = true;
    _advertisementDismissTimer?.cancel();
    _advertisementDismissTimer = Timer(Duration(seconds: durationSeconds), () {
      showAdvertisement.value = false;
    });
  }

  void dismissAdvertisement() {
    showAdvertisement.value = false;
    _advertisementDismissTimer?.cancel();
  }

  void updateTime() {
    time.value = DateTime.now();
  }

  String get roomName {
    return globalDisplay?.name ?? 'meeting_room'.tr;
  }

  String get title {
    if (isReserved) {
      return currentEvent!.summary;
    }
    if (isCheckInActive) {
      return globalSettings.value?.textCheckin ?? 'check_in_now'.tr;
    }
    if (isTransitioning && !isReserved) {
      return globalSettings.value?.textTransitioning ?? 'to_be_reserved'.tr;
    }
    return globalSettings.value?.textAvailable ?? 'available'.tr;
  }

  /// Returns the start and end DateTime of the current event, or null if not reserved.
  Map<String, DateTime>? get meetingInfoTimes {
    if (!isReserved) {
      return null;
    }
    return {
      'start': currentEvent!.start,
      'end': currentEvent!.end,
    };
  }

  String get subtitle {
    if (isReserved && !isCheckInActive) {
      final currentEventEnd = currentEvent!.end;
      final totalMinutesLeft = currentEventEnd.difference(DateTime.now()).inMinutes;
      final hoursLeft = (totalMinutesLeft / 60).floor();
      final minutesLeft = (totalMinutesLeft - (hoursLeft * 60)).floor() + 1;

      return totalMinutesLeft < 60 ?
        'x_minutes_left'.trParams({'minutes': minutesLeft.toString()}) :
        'x_hours_x_minutes_left'.trParams({'hours': hoursLeft.toString(), 'minutes': minutesLeft.toString()});
    }

    if (isCheckInActive && currentEvent != null) {
      final totalMinutesLeft = currentEvent!.start.add(Duration(minutes: checkInGracePeriod)).difference(DateTime.now()).inMinutes;
      final hoursLeft = (totalMinutesLeft / 60).floor();
      final minutesLeft = (totalMinutesLeft - (hoursLeft * 60)).floor() + 1;

      return 'check_in_within_x_minutes'.trParams({'minutes': minutesLeft.toString()});
    }

    if (isCheckInActive && upcomingEvents.isNotEmpty) {
      final upcomingMeeting = upcomingEvents.first;
      final totalMinutesLeft = upcomingMeeting.start.difference(DateTime.now()).inMinutes;
      final hoursLeft = (totalMinutesLeft / 60).floor();
      final minutesLeft = (totalMinutesLeft - (hoursLeft * 60)).floor() + 1;

      return 'x_starts_in_x_minutes'.trParams({'meeting': upcomingMeeting.summary, 'minutes': minutesLeft.toString()});
    }

    if (upcomingEvents.isNotEmpty) {
      final upcomingStart = upcomingEvents.first.start;
      final totalMinutesLeft = upcomingStart.difference(DateTime.now()).inMinutes;
      final hoursLeft = (totalMinutesLeft / 60).floor();
      final minutesLeft = (totalMinutesLeft - (hoursLeft * 60)).floor() + 1;

      return totalMinutesLeft < 60 ?
        'for_x_minutes'.trParams({'minutes': minutesLeft.toString()}) :
        'for_x_hours_x_minutes'.trParams({'hours': hoursLeft.toString(), 'minutes': minutesLeft.toString()});
    }

    return 'till_end_of_day'.tr;
  }

  bool get isReserved {
    return currentEvent != null;
  }

  bool get isTransitioning {
    if (checkInEnabled) {
      return false;
    }

    if (isReserved) {
      final currentEventEnd = currentEvent!.end;
      final minutesLeft = currentEventEnd.difference(DateTime.now()).inMinutes;

      return minutesLeft < 10;
    }

    if (upcomingEvents.isNotEmpty) {
      final upcomingStart = upcomingEvents.first.start;
      final minutesLeft = upcomingStart.difference(DateTime.now()).inMinutes;

      return minutesLeft < 10;
    }

    return false;
  }

  // Returns true if there is an event with checkInRequired and we are within its check-in window (before/after start)
  bool get isCheckInActive {
    if (!checkInEnabled) {
      return false;
    }

    return checkInEvent != null;
  }

  EventModel? get checkInEvent {
    final now = DateTime.now();
    return events.firstWhereOrNull((e) {
      if (e.checkInRequired != true) return false;

      final start = e.start;
      final windowStart = start.subtract(Duration(minutes: checkInMinutes));
      final windowEnd = start.add(Duration(minutes: checkInGracePeriod));

      return now.isAfter(windowStart) && now.isBefore(windowEnd);
    });
  }

  EventModel? get currentEvent {
    DateTime now = DateTime.now();
    return events.where((e) => now.isAfter(e.start) && now.isBefore(e.end)).firstOrNull;
  }

  List<EventModel> get upcomingEvents {
    List<EventModel> nextEvents = events.where((e) => e.start.isAfter(DateTime.now())).toList();

    nextEvents.sort((a, b) => a.start.compareTo(b.start));

    return nextEvents;
  }

  Future<void> fetchDisplayData() async {
    try {
      DisplayDataModel displayData = await DisplayService.instance.getDisplayData(displayId.value);

      // Update global device, display, and settings
      if (AuthService.instance.currentDevice.value != null) {
        globalCurrentDevice = AuthService.instance.currentDevice.value;
        globalCurrentDevice!.display = displayData.display;
        globalDisplay = globalCurrentDevice!.display;
        globalSettings.value = globalDisplay?.settings;

        // Update reactive font family to trigger UI rebuild
        final newFontFamily = globalSettings.value?.fontFamily ?? 'Inter';
        if (currentFontFamily.value != newFontFamily) {
          currentFontFamily.value = newFontFamily;

          // Reload the font when settings change
          await FontService.instance.reloadFont(newFontFamily);
        }

        // (Re-)start advertisement timers when settings are refreshed
        startAdvertisementTimers();

        AuthService.instance.currentDevice.refresh();
      }

      // Update events
      events.value = displayData.events
          .where((e) => e.status != EventStatus.cancelled)
          .map((e) {
            e.summary = getDisplayableSummary(e);
            return e;
          })
          .toList();

      isDataStale.value = false;
      _lastSuccessfulFetchAt = DateTime.now();
    } catch (e) {
      isDataStale.value = true;
    }
  }

  void switchRoom() {
    _clock?.cancel();
    _dataTimer?.cancel();
    
    Get.offAll(() => const DisplayPage());
  }

  // Manually refresh display data with cooldown to prevent spamming
  Future<void> refreshDisplayData() async {
    // Check if we're already refreshing
    if (isRefreshing.value) {
      return;
    }
    
    // Check cooldown period
    if (_lastRefreshTime != null) {
      final secondsSinceLastRefresh = DateTime.now().difference(_lastRefreshTime!).inSeconds;
      if (secondsSinceLastRefresh < _refreshCooldownSeconds) {
        return;
      }
    }
    
    isRefreshing.value = true;
    _lastRefreshTime = DateTime.now();

    try {
      await fetchDisplayData();
      Toast.showSuccess('display_data_refreshed'.tr);
    } catch (e) {
      if (_isConnectivityError(e)) {
        Toast.showError('no_internet_connection'.tr);
      } else if (e is ApiException) {
        Toast.showError('could_not_load_data'.tr);
      } else {
        Toast.showError('could_not_load_data'.tr);
      }
    } finally {
      isRefreshing.value = false;
    }
  }

  Future<void> bookRoom(int duration) async {
    if (isBooking.value) return; // Prevent multiple simultaneous bookings
    
    try {
      isBooking.value = true;
      bookingDuration.value = duration; // Track which button was clicked
      final summary = 'reserved'.tr;
      await DisplayService.instance.book(displayId.value, duration, summary: summary);
      await fetchDisplayData();
      Toast.showSuccess('room_booked'.tr);
      
      // Cancel the booking options timer since user took action
      _bookingOptionsTimer?.cancel();
      showBookingOptions.value = false;
    } catch (e) {
      if (e is ApiException && e.message != null) {
        Toast.showError(e.message!);
      } else if (_isConnectivityError(e)) {
        Toast.showError('no_internet_connection'.tr);
      } else {
        Toast.showError('could_not_book_room'.tr);
      }
    } finally {
      isBooking.value = false;
      bookingDuration.value = null; // Clear the tracked duration
    }
  }

  void showCustomBookingModal(BuildContext context, bool isPhone, double cornerRadius) {
    showDialog(
      context: context,
      builder: (context) => CustomBookingModal(
        controller: this,
        isPhone: isPhone,
        cornerRadius: cornerRadius,
      ),
    );
  }

  Future<void> bookCustom(String title, DateTime startTime, DateTime endTime, {String? description, List<String>? attendees}) async {
    isBooking.value = true;
    try {
      await DisplayService.instance.bookCustom(displayId.value, title, startTime, endTime, description: description, attendees: attendees);
      await fetchDisplayData();
      Toast.showSuccess('room_booked'.tr);

      // Cancel the booking options timer since user took action
      _bookingOptionsTimer?.cancel();
      showBookingOptions.value = false;
    } catch (e) {
      if (e is ApiException && e.message != null) {
        Toast.showError(e.message!);
      } else if (_isConnectivityError(e)) {
        Toast.showError('no_internet_connection'.tr);
      } else {
        Toast.showError('could_not_book_room'.tr);
      }
    } finally {
      isBooking.value = false;
      bookingDuration.value = null; // Clear the tracked duration
    }
  }

  Future<List<EventModel>> getEventsForDate(DateTime date) async {
    return DisplayService.instance.getEventsForDate(displayId.value, date);
  }


  Future<void> cancelCurrentEvent() async {
    if (isCancelling.value) return; // Prevent multiple simultaneous cancellations
    
    try {
      isCancelling.value = true;
      if (currentEvent != null) {
        await DisplayService.instance.cancelEvent(displayId.value, currentEvent!.id);
        await fetchDisplayData();
        Toast.showSuccess('event_cancelled'.tr);
      }
    } catch (e) {
      if (e is ApiException && e.message != null) {
        Toast.showError(e.message!);
      } else if (_isConnectivityError(e)) {
        Toast.showError('no_internet_connection'.tr);
      } else {
        Toast.showError('could_not_cancel_event'.tr);
      }
    } finally {
      isCancelling.value = false;
    }
  }

  // Check if booking should be displayed based on display settings
  bool get bookingEnabled {
    return globalSettings.value?.bookingEnabled ?? false;
  }

  // Check if custom booking is available (server capability)
  bool get hasCustomBooking {
    return globalSettings.value?.hasCustomBooking ?? false;
  }

  // Check if current event can be cancelled based on cancel permission setting
  bool get canCancelCurrentEvent {
    // Early return: cannot cancel if there's no current event
    if (currentEvent == null) {
      return false;
    }
    
    final cancelPermission = globalSettings.value?.cancelPermission ?? 'all';
    
    if (cancelPermission == 'none') {
      return false;
    }
    
    if (cancelPermission == 'tablet_only') {
      // Only allow cancelling if the event was booked via tablet
      // currentEvent is guaranteed to be non-null at this point
      return currentEvent!.isTabletBooking;
    }
    
    // Default: 'all' - allow cancelling any event
    // currentEvent is guaranteed to be non-null at this point
    return true;
  }

  // Get border width based on border thickness setting
  double getBorderWidth() {
    final borderThickness = globalSettings.value?.borderThickness ?? 'medium';
    switch (borderThickness) {
      case 'small':
        return 1.33;
      case 'large':
        return 2.67;
      case 'medium':
      default:
        return 2.0;
    }
  }

  bool get extendEnabled {
    return globalSettings.value?.extendEnabled ?? false;
  }

  List<int> get availableExtendDurations {
    if (currentEvent == null) return [];
    final base = [15, 30, 60];
    if (upcomingEvents.isNotEmpty) {
      final minutesUntilNext = upcomingEvents.first.start.difference(currentEvent!.end).inMinutes;
      return base.where((min) => min <= minutesUntilNext).toList();
    }
    return base;
  }

  void toggleExtendOptions() {
    showExtendOptions.value = true;
    _extendOptionsTimer?.cancel();
    _extendOptionsTimer = Timer(const Duration(seconds: 30), () {
      showExtendOptions.value = false;
    });
  }

  void hideExtendOptions() {
    showExtendOptions.value = false;
    _extendOptionsTimer?.cancel();
  }

  Future<void> extendCurrentEvent(int minutes) async {
    if (isExtending.value || currentEvent == null) return;

    try {
      isExtending.value = true;
      extendDuration.value = minutes;
      final newEnd = currentEvent!.end.add(Duration(minutes: minutes));
      await DisplayService.instance.extendEvent(displayId.value, currentEvent!.id, newEnd);
      await fetchDisplayData();
      Toast.showSuccess('event_extended'.tr);
      _extendOptionsTimer?.cancel();
      showExtendOptions.value = false;
    } catch (e) {
      if (e is ApiException && e.message != null) {
        Toast.showError(e.message!);
      } else if (_isConnectivityError(e)) {
        Toast.showError('no_internet_connection'.tr);
      } else {
        Toast.showError('could_not_extend_event'.tr);
      }
    } finally {
      isExtending.value = false;
      extendDuration.value = null;
    }
  }

  bool get calendarEnabled {
    return globalSettings.value?.calendarEnabled ?? false;
  }

  String get timelineWidgetMode {
    return globalSettings.value?.timelineWidgetMode ?? 'none';
  }

  bool get timelineWidgetEnabled {
    return timelineWidgetMode != 'none';
  }

  // Track if booking options are shown
  final RxBool showBookingOptions = RxBool(false);
  
  // Loading states for actions
  final RxBool isBooking = RxBool(false);
  final Rx<int?> bookingDuration = Rx<int?>(null); // Track which duration button was clicked
  final RxBool isCancelling = RxBool(false);
  final RxBool isExtending = RxBool(false);
  final Rx<int?> extendDuration = Rx<int?>(null);
  final RxBool showExtendOptions = RxBool(false);
  Timer? _extendOptionsTimer;
  
  // Timer for booking options timeout
  Timer? _bookingOptionsTimer;

  // Track if admin actions are temporarily visible
  final RxBool showAdminActionsTemporarily = RxBool(false);

  // Timer for admin actions timeout
  Timer? _adminActionsTimer;

  // Timer for long press detection (3 seconds)
  Timer? _longPressTimer;

  // Advertisement state
  final RxBool showAdvertisement = RxBool(false);
  Timer? _advertisementIntervalTimer;
  Timer? _advertisementDismissTimer;

  // Show booking options with 30-second timeout
  void toggleBookingOptions() {
    showBookingOptions.value = true;
    
    // Cancel any existing timer
    _bookingOptionsTimer?.cancel();
    
    // Set a 30-second timeout to automatically hide booking options
    _bookingOptionsTimer = Timer(const Duration(seconds: 30), () {
      showBookingOptions.value = false;
    });
  }

  // Hide booking options
  void hideBookingOptions() {
    showBookingOptions.value = false;
    _bookingOptionsTimer?.cancel();
  }
  // Start long press timer (3 seconds)
  void startLongPressTimer() {
    // Cancel any existing timer
    _longPressTimer?.cancel();
    
    // Set a 3-second timer to trigger reveal
    _longPressTimer = Timer(const Duration(seconds: 3), () {
      revealAdminActionsTemporarily();
    });
  }

  // Cancel long press timer
  void cancelLongPressTimer() {
    _longPressTimer?.cancel();
  }

  // Show admin actions temporarily (30 seconds)
  void revealAdminActionsTemporarily() {
    showAdminActionsTemporarily.value = true;
    
    // Show notification with duration
    Toast.showSuccess('admin_actions_enabled'.trParams({'seconds': '30'}));
    
    // Cancel any existing timer
    _adminActionsTimer?.cancel();
    
    // Set a 30-second timeout to automatically hide admin actions
    _adminActionsTimer = Timer(const Duration(seconds: 30), () {
      showAdminActionsTemporarily.value = false;
    });
  }

  int get checkInGracePeriod {
    return globalSettings.value?.checkInGracePeriod ?? 5;
  }

  bool get checkInEnabled {
    return globalSettings.value?.checkInEnabled ?? false;
  }

  int get checkInMinutes {
    return globalSettings.value?.checkInMinutes ?? 15;
  }

  void checkIn() async {
    try {
      await DisplayService.instance.checkInToEvent(displayId.value, checkInEvent!.id);
      await fetchDisplayData();
      Toast.showSuccess('checked_in'.tr);
    } catch (e) {
      Toast.showError('could_not_check_in'.tr);
    }
  }

  List<int> get availableBookingDurations {
    final base = [15, 30, 60];
    if (isCheckInActive) {
      return base.where((min) => min <= checkInGracePeriod).toList();
    }
    if (upcomingEvents.isNotEmpty) {
      final nextEvent = upcomingEvents.first;
      final minutesUntilNext = nextEvent.start.difference(DateTime.now()).inMinutes;
      return base.where((min) => min <= minutesUntilNext).toList();
    }
    return base;
  }

  /// Returns the summary to display for an event, respecting showMeetingTitle
  String getDisplayableSummary(EventModel event) {
    if (globalSettings.value?.showMeetingTitle == false) {
      return getReservedText();
    }
    return event.summary;
  }

  String getReservedText() {
    return globalSettings.value?.textReserved ?? 'reserved'.tr;
  }

  bool _isConnectivityError(dynamic e) {
    final s = e.toString().toLowerCase();
    return e is SocketException ||
        s.contains('socketexception') ||
        s.contains('failed host lookup') ||
        s.contains('network is unreachable') ||
        s.contains('connection refused') ||
        s.contains('no address associated');
  }

  @override
  void dispose() {
    _clock?.cancel();
    _dataTimer?.cancel();
    _bookingOptionsTimer?.cancel();
    _adminActionsTimer?.cancel();
    _longPressTimer?.cancel();
    _advertisementIntervalTimer?.cancel();
    _advertisementDismissTimer?.cancel();
    _extendOptionsTimer?.cancel();

    super.dispose();
  }
}