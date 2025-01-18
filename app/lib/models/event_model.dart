class EventModel {
  String id;
  String summary;
  String? location;
  String? description;
  DateTime start;
  DateTime end;
  String? timezone;
  bool? isAllDay;

  EventModel({
    required this.id,
    required this.summary,
    this.location,
    this.description,
    required this.start,
    required this.end,
    this.timezone,
    this.isAllDay,
  });

  factory EventModel.fromJson(Map<String, dynamic> data) {
    return EventModel(
      id: data['id'],
      summary: data['summary'],
      location: data['location'],
      description: data['description'],
      start: DateTime.parse(data['start']).toLocal(),
      end: DateTime.parse(data['end']).toLocal(),
      timezone: data['timezone'],
      isAllDay: data['isAllDay'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'summary': summary,
      'location': location,
      'description': description,
      'start': start.toIso8601String(),
      'end': end.toIso8601String(),
      'timezone': timezone,
      'isAllDay': isAllDay,
    };
  }
}
