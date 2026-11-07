import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/models/event_model.dart';

String formatTime(BuildContext context, DateTime time) {
  final use24Hour = MediaQuery.of(context).alwaysUse24HourFormat;
  return use24Hour
      ? DateFormat.Hm().format(time)   // 24-hour format
      : DateFormat.jm().format(time);  // 12-hour format
}

/// "09:00 - 10:00", or "All day" for an event that fills the day: its times are midnights, and
/// "00:00 - 00:00" reads as a mistake.
String formatEventTimes(BuildContext context, EventModel event, {String separator = ' - '}) {
  if (event.isAllDay) {
    return 'all_day'.tr;
  }

  return '${formatTime(context, event.start)}$separator${formatTime(context, event.end)}';
}
