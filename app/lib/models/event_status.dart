enum EventStatus { confirmed, tentative, cancelled }

EventStatus eventStatusFromString(String? value) {
  switch (value) {
    case 'tentative':
      return EventStatus.tentative;
    case 'cancelled':
      return EventStatus.cancelled;
    case 'confirmed':
    default:
      return EventStatus.confirmed;
  }
}

String eventStatusToString(EventStatus status) {
  switch (status) {
    case EventStatus.tentative:
      return 'tentative';
    case EventStatus.cancelled:
      return 'cancelled';
    case EventStatus.confirmed:
    default:
      return 'confirmed';
  }
} 