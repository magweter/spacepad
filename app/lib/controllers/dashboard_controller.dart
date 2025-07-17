import 'dart:async';

import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/models/display_data_model.dart';
import 'package:spacepad/models/event_status.dart';
import 'package:spacepad/services/display_service.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/pages/display_page.dart';

class DashboardController extends GetxController {
  final RxBool loading = RxBool(true);
  final RxList<EventModel> events = RxList();
  final Rx<DateTime> time = Rx<DateTime>(DateTime.now());
  final RxString displayId = RxString('');
  
  Timer? _clock;
  Timer? _dataTimer;

  @override
  void onInit() {
    super.onInit();

    updateTime();
    
    // Check if display ID is set, redirect to display page if not
    final displayIdResult = AuthService.instance.getCurrentDisplayId();
    if (displayIdResult == null) {
      Get.offAll(() => const DisplayPage());
    } else {
      displayId.value = displayIdResult;
    }

    initializeTimers();
    fetchDisplayData();

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
    return AuthService.instance.currentDevice.value?.display?.name ?? 'meeting_room'.tr;
  }

  String get title {
    if (isReserved) {
      return currentEvent!.summary;
    }

    if (isCheckInActive) {
      return 'check_in_now'.tr;
    }

    if (isTransitioning && !isReserved) {
      return 'to_be_reserved'.tr;
    }

    return 'available'.tr;
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
      
      // Update events
      events.value = displayData.events.where((e) => e.status != EventStatus.cancelled).toList();
      
      // Update display settings in the current device
      if (AuthService.instance.currentDevice.value != null) {
        AuthService.instance.currentDevice.value!.display = displayData.display;
        AuthService.instance.currentDevice.refresh();
      }
    } catch (e) {
      Toast.showError('could_not_load_data'.tr);
    }
  }

  void switchRoom() {
    _clock?.cancel();
    _dataTimer?.cancel();
    
    Get.offAll(() => const DisplayPage());
  }

  Future<void> bookRoom(int duration, String summary) async {
    try {
      await DisplayService.instance.book(displayId.value, duration, summary: summary);
      await fetchDisplayData();
      Toast.showSuccess('room_booked'.tr);
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
    return AuthService.instance.currentDevice.value?.display?.settings.bookingEnabled ?? false;
  }

  bool get calendarEnabled {
    return AuthService.instance.currentDevice.value?.display?.settings.calendarEnabled ?? false;
  }

  // Track if booking options are shown
  final RxBool showBookingOptions = RxBool(false);

  // Show booking options
  void toggleBookingOptions() {
    showBookingOptions.value = true;
  }

  // Hide booking options
  void hideBookingOptions() {
    showBookingOptions.value = false;
  }

  int get checkInGracePeriod {
    return AuthService.instance.currentDevice.value?.display?.settings.checkInGracePeriod ?? 5;
  }

  bool get checkInEnabled {
    return AuthService.instance.currentDevice.value?.display?.settings.checkInEnabled ?? false;
  }

  int get checkInMinutes {
    return AuthService.instance.currentDevice.value?.display?.settings.checkInMinutes ?? 15;
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

  @override
  void dispose() {
    _clock?.cancel();
    _dataTimer?.cancel();

    super.dispose();
  }
}