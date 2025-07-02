import 'dart:async';

import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/services/event_service.dart';
import 'package:spacepad/services/auth_service.dart';

class DashboardController extends GetxController {
  final RxBool loading = RxBool(true);
  final RxList<EventModel> events = RxList();
  final RxString time = RxString('');
  
  Timer? _clock;
  Timer? _timer;

  @override
  void onInit() {
    super.onInit();

    updateTime();
    initializeTimers();

    fetchEvents();

    loading.value = false;
  }

  void initializeTimers() {
    final int millisecondsToNextSecond = DateTime.now().millisecond;

    // Start a timer that aligns with the next second
    Future.delayed(Duration(milliseconds: millisecondsToNextSecond), () {
      _timer = Timer.periodic(const Duration(seconds: 60), (timer) => fetchEvents());
    });

    _clock = Timer.periodic(const Duration(seconds: 1), (timer) => updateTime());
  }

  void updateTime() {
    time.value = DateFormat.jm().format(DateTime.now());
  }

  String get roomName {
    return AuthService.instance.currentDevice.value?.display?.name ?? 'meeting_room'.tr;
  }

  String get title {
    if (isTransitioning && !isReserved) {
      return 'to_be_reserved'.tr;
    }

    if (isReserved) {
      return currentEvent!.summary;
    }

    return 'available'.tr;
  }

  String? get meetingInfo {
    if (!isReserved) {
      return null;
    }

    final currentEventStart = currentEvent!.start;
    final currentEventEnd = currentEvent!.end;

    return 'meeting_info_title'.trParams({
      'start': DateFormat.jm().format(currentEventStart),
      'end': DateFormat.jm().format(currentEventEnd)
    });
  }

  String get subtitle {
    if (isReserved) {
      final currentEventEnd = currentEvent!.end;
      final totalMinutesLeft = currentEventEnd.difference(DateTime.now()).inMinutes;
      final hoursLeft = (totalMinutesLeft / 60).floor();
      final minutesLeft = (totalMinutesLeft - (hoursLeft * 60)).floor() + 1;

      return totalMinutesLeft < 60 ?
        'x_minutes_left'.trParams({'minutes': minutesLeft.toString()}) :
        'x_hours_x_minutes_left'.trParams({'hours': hoursLeft.toString(), 'minutes': minutesLeft.toString()});
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

  EventModel? get currentEvent {
    DateTime now = DateTime.now();
    return events.value.where((e) => now.isAfter(e.start) && now.isBefore(e.end)).firstOrNull;
  }

  List<EventModel> get upcomingEvents {
    List<EventModel> nextEvents = events.value.where((e) => e.start.isAfter(DateTime.now())).toList();

    nextEvents.sort((a, b) => a.start.compareTo(b.start));

    return nextEvents;
  }

  Future<void> fetchEvents() async {
    try {
      events.value = await EventService.instance.getEvents();
    } catch (e) {
      Toast.showError('could_not_load_events'.tr);
    }
  }

  void logout() {
    _clock?.cancel();
    _timer?.cancel();
    AuthService.instance.signOut();
  }

  Future<void> bookRoom(int duration, {String? summary}) async {
    try {
      await EventService.instance.bookRoom(duration, summary: summary);
      await fetchEvents();
      Toast.showSuccess('Room booked!');
    } catch (e) {
      Toast.showError('Could not book room');
    }
  }

  @override
  void dispose() {
    _clock?.cancel();
    _timer?.cancel();

    super.dispose();
  }
}