import 'display_model.dart';
import 'event_model.dart';

class DisplayDataModel {
  DisplayModel display;
  List<EventModel> events;

  DisplayDataModel({
    required this.display,
    required this.events,
  });

  factory DisplayDataModel.fromJson(Map<String, dynamic> data) {
    return DisplayDataModel(
      display: DisplayModel.fromJson(data['display']),
      events: (data['events'] as List)
          .map((e) => EventModel.fromJson(e))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'display': display.toJson(),
      'events': events.map((e) => e.toJson()).toList(),
    };
  }
}