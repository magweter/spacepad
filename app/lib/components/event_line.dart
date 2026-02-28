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

  @override
  Widget build(BuildContext context) {
    final isPhone = _isPhone(context);

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
              fontSize: isPhone ? 16 : 18,
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
                fontSize: isPhone ? 16 : 18,
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
