import 'package:get/get.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/services/event_service.dart';

class DashboardController extends GetxController {
  final RxBool loading = RxBool(false);
  final RxList<EventModel> events = RxList();

  @override
  void onInit() {
    super.onInit();

    getEvents();
  }

  bool get currentlyInMeeting {
    return currentMeeting != null;
  }

  EventModel? get currentMeeting {
    DateTime now = DateTime.now();

    return events.value.where((event) => (now.isAfter(event.start)) && (now.isBefore(event.end))).firstOrNull;
  }

  List<EventModel> getNextEvents() {
    List<EventModel> nextEvents = events.value.where((element) => element.start.isAfter(DateTime.now())).toList();

    nextEvents.sort((a, b) => a.start.compareTo(b.start));

    return nextEvents;
  }

  Future<void> getEvents() async {
    if (loading.value) return;

    loading.value = true;

    try {
      events.value = await EventService.instance.getEvents();
    } catch (e) {
      Toast.showError('Could not load events');
    }

    loading.value = false;
  }
}