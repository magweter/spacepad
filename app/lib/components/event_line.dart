import 'package:get/get.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:tailwind_components/tailwind_components.dart';

class EventLine extends StatelessWidget {
  final EventModel event;

  const EventLine({super.key, required this.event});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: SpaceRow(
        spaceBetween: 10,
        mainAxisSize: MainAxisSize.max,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text('${'next'.tr}:', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white)),
          Text('next_event_title'.trParams({
            'start': DateFormat.Hm().format(event.start),
            'end': DateFormat.Hm().format(event.end),
            'summary': event.summary,
          }), style: TextStyle(fontSize: 24, fontWeight: FontWeight.w400, color: Colors.white)),
        ],
      ),
    );
  }
}
