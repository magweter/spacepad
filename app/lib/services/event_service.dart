import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/services/api_service.dart';

class EventService {
  EventService._();
  static final EventService instance = EventService._();

  Future<List<EventModel>> getEvents() async {
    Map body = await ApiService.get('events');

    List data = body['data'] as List;

    return data.map((e) => EventModel.fromJson(e)).toList();
  }

  Future<void> bookRoom(int duration, {String? summary}) async {
    await ApiService.post('events/book', {
      'duration': duration,
      if (summary != null) 'summary': summary,
    });
  }

  Future<void> cancelEvent(String eventId) async {
    await ApiService.delete('events/$eventId');
  }
}