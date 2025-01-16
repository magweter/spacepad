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
    time.value = DateFormat('HH:mm').format(DateTime.now());
  }

  String get title {
    return AuthService.instance.currentDevice.value?.display?.name ?? 'Meetingruimte';
  }

  String get subtitle {
    if (isReserved) {
      final currentEventEnd = currentEvent!.end;
      final minutesLeft = currentEventEnd.difference(DateTime.now()).inMinutes;

      return minutesLeft <= 60 ?
        'Gereserveerd voor $minutesLeft minuten tot ${DateFormat('HH:mm').format(currentEventEnd)} uur' :
        'Gereserveerd tot ${DateFormat('HH:mm').format(currentEventEnd)} uur';
    }

    if (upcomingEvents.isNotEmpty) {
      final upcomingStart = upcomingEvents.first.start;
      final minutesLeft = upcomingStart.difference(DateTime.now()).inMinutes;

      return minutesLeft <= 60 ?
        'Beschikbaar voor $minutesLeft minuten tot ${DateFormat('HH:mm').format(upcomingStart)} uur' :
        'Beschikbaar tot ${DateFormat('HH:mm').format(upcomingStart)} uur';
    }

    return 'Beschikbaar de hele dag';
  }

  bool get isReserved {
    return currentEvent != null;
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
      Toast.showError('Could not load events');
    }
  }

  @override
  void dispose() {
    _clock?.cancel();
    _timer?.cancel();

    super.dispose();
  }
}