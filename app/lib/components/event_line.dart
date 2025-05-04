import 'package:get/get.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'dart:io' show Platform;

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
              fontSize: isPhone ? 18 : 24,
              fontWeight: FontWeight.bold,
              color: Colors.white
            )
          ),
          Text(
            'next_event_title'.trParams({
              'start': DateFormat.Hm().format(event.start),
              'end': DateFormat.Hm().format(event.end),
              'summary': event.summary,
            }),
            style: TextStyle(
              fontSize: isPhone ? 18 : 24,
              fontWeight: FontWeight.w400,
              color: Colors.white
            )
          ),
        ],
      ),
    );
  }
}
