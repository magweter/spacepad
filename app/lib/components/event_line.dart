import 'package:get/get.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:flutter/material.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:tailwind_components/tailwind_components.dart';

class EventLine extends StatelessWidget {
  const EventLine({super.key, required this.event});

  final EventModel event;

  bool _isPhone(BuildContext context) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    return shortestSide < 600;
  }

  /// Same size as the View schedule button it shares the bottom bar with, so the two do not
  /// drift apart on a narrower display. Mirrors DashboardPage._barFontSize.
  double _fontSize(BuildContext context, bool isPhone) {
    if (isPhone) {
      return 16;
    }

    final s = MediaQuery.of(context).size.shortestSide;
    return (20 * (s / 750).clamp(0.5, 1.3)).roundToDouble();
  }

  @override
  Widget build(BuildContext context) {
    final isPhone = _isPhone(context);
    final fontSize = _fontSize(context, isPhone);

    return SizedBox(
      width: double.infinity,
      child: SpaceRow(
        spaceBetween: isPhone ? 5 : 10,
        mainAxisSize: MainAxisSize.max,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text(
            '${'next'.tr}:',
            style: TextStyle(
              fontSize: fontSize,
              fontWeight: FontWeight.bold,
              color: Colors.white
            )
          ),
          Expanded(
            child: Text(
              'next_event_title'.trParams({
                'start': formatTime(context, event.start),
                'end': formatTime(context, event.end),
                'summary': event.summary,
              }),
              style: TextStyle(
                fontSize: fontSize,
                fontWeight: FontWeight.w400,
                color: Colors.white
              ),
              overflow: TextOverflow.ellipsis,
              maxLines: 1,
            ),
          ),
        ],
      ),
    );
  }
}
