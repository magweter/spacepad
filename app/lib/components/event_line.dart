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
      width: 800,
      child: SpaceRow(
        spaceBetween: 15,
        mainAxisSize: MainAxisSize.max,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text('${DateFormat('HH:mm').format(event.start)} - ${DateFormat('HH:mm').format(event.end)}', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w400, color: Colors.white)),
          Text(event.summary, style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white)),
        ],
      ),
    );
  }
}
