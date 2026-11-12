import 'package:get/get.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:flutter/material.dart';
import 'package:spacepad/date_format_helper.dart';

class EventLine extends StatelessWidget {
  const EventLine({super.key, required this.event, this.scale = 1.0});

  final EventModel event;

  /// The text size step chosen for the bottom toolbar, see DashboardController.toolbarScale.
  final double scale;

  bool _isPhone(BuildContext context) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    return shortestSide < 600;
  }

  /// Same size as the View schedule button it shares the bottom bar with, so the two do not
  /// drift apart on a narrower display. Mirrors DashboardPage._barFontSize.
  double _fontSize(BuildContext context, bool isPhone) {
    if (isPhone) {
      return (16 * scale).roundToDouble();
    }

    final s = MediaQuery.of(context).size.shortestSide;
    return (20 * (s / 750).clamp(0.5, 1.3) * scale).roundToDouble();
  }

  @override
  Widget build(BuildContext context) {
    final isPhone = _isPhone(context);
    final fontSize = _fontSize(context, isPhone);

    // One line of text, not a label beside an expanding text: at a large size in a narrow
    // toolbar the label alone can be wider than the space, and a single line shortens itself
    // with an ellipsis where a row of two would overflow.
    return SizedBox(
      width: double.infinity,
      child: Text.rich(
        TextSpan(
          children: [
            TextSpan(
              text: '${'next'.tr}: ',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            TextSpan(
              text: 'next_event_title'.trParams({
                'start': formatTime(context, event.start),
                'end': formatTime(context, event.end),
                'summary': event.summary,
              }),
            ),
          ],
        ),
        style: TextStyle(
          fontSize: fontSize,
          fontWeight: FontWeight.w400,
          color: Colors.white,
        ),
        overflow: TextOverflow.ellipsis,
        maxLines: 1,
      ),
    );
  }
}
