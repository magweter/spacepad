import 'dart:async';
import 'dart:io';

import 'package:get/get.dart';
import 'package:spacepad/components/toast.dart';
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
    } catch (e) {
      Toast.showError('could_not_load_data'.tr);
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
    } finally {
      isRefreshing.value = false;
    }
  }

  Future<void> bookRoom(int duration) async {
    try {
      final summary = 'reserved'.tr;
      await DisplayService.instance.book(displayId.value, duration, summary: summary);
      await fetchDisplayData();
      Toast.showSuccess('room_booked'.tr);
      
      // Cancel the booking options timer since user took action
      _bookingOptionsTimer?.cancel();
      showBookingOptions.value = false;
    } catch (e) {
      Toast.showError('could_not_book_room'.tr);
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

  Future<void> bookCustom(String title, DateTime startTime, DateTime endTime) async {
    try {
      await DisplayService.instance.bookCustom(displayId.value, title, startTime, endTime);
      await fetchDisplayData();
      Toast.showSuccess('room_booked'.tr);
      
      // Cancel the booking options timer since user took action
      _bookingOptionsTimer?.cancel();
      showBookingOptions.value = false;
    } catch (e) {
      Toast.showError('could_not_book_room'.tr);
    }
  }

  Future<void> cancelCurrentEvent() async {
    try {
      if (currentEvent != null) {
        await DisplayService.instance.cancelEvent(displayId.value, currentEvent!.id);
        await fetchDisplayData();
        Toast.showSuccess('event_cancelled'.tr);
      }
    } catch (e) {
      Toast.showError('could_not_cancel_event'.tr);
    }
  }

  // Check if booking should be displayed based on display settings
  bool get bookingEnabled {
    return globalSettings.value?.bookingEnabled ?? false;
  }

  bool get calendarEnabled {
    return globalSettings.value?.calendarEnabled ?? false;
  }

  // Track if booking options are shown
  final RxBool showBookingOptions = RxBool(false);
  
  // Timer for booking options timeout
  Timer? _bookingOptionsTimer;

  // Track if admin actions are temporarily visible
  final RxBool showAdminActionsTemporarily = RxBool(false);
  
  // Timer for admin actions timeout
  Timer? _adminActionsTimer;
  
  // Timer for long press detection (3 seconds)
  Timer? _longPressTimer;

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

  @override
  void dispose() {
    _clock?.cancel();
    _dataTimer?.cancel();
    _bookingOptionsTimer?.cancel();
    _adminActionsTimer?.cancel();
    _longPressTimer?.cancel();

    super.dispose();
  }
}