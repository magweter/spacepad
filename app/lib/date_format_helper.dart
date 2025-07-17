import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

String formatTime(BuildContext context, DateTime time) {
  final use24Hour = MediaQuery.of(context).alwaysUse24HourFormat;
  return use24Hour
      ? DateFormat.Hm().format(time)   // 24-hour format
      : DateFormat.jm().format(time);  // 12-hour format
} 