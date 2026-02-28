import 'event_status.dart';

class EventModel {
  String id;
  EventStatus status;
  String summary;
  String? location;
  String? description;
  DateTime start;
  DateTime end;
  String? timezone;
  bool isCheckedIn;
  bool checkInRequired;
  String? source;
  bool isTabletBooking;

  EventModel({
    required this.id,
    required this.status,
    required this.summary,
    this.location,
    this.description,
    required this.start,
    required this.end,
    this.timezone,
    this.isCheckedIn = false,
    this.checkInRequired = false,
    this.source,
    this.isTabletBooking = false,
  });

  factory EventModel.fromJson(Map<String, dynamic> data) {
    return EventModel(
      id: data['id'],
      status: eventStatusFromString(data['status']),
      summary: data['summary'],
      location: data['location'],
      description: data['description'],
      start: DateTime.parse(data['start']).toLocal(),
      end: DateTime.parse(data['end']).toLocal(),
      timezone: data['timezone'],
      isCheckedIn: data['checkedInAt'] != null,
      checkInRequired: data['checkInRequired'] ?? false,
      source: data['source'],
      isTabletBooking: data['isTabletBooking'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'status': eventStatusToString(status),
      'summary': summary,
      'location': location,
      'description': description,
      'start': start.toIso8601String(),
      'end': end.toIso8601String(),
      'timezone': timezone,
      'isCheckedIn': isCheckedIn,
      'checkInRequired': checkInRequired,
    };
  }
}
