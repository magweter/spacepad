import 'package:spacepad/models/event_model.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:tailwind_components/tailwind_components.dart';

class EventCard extends StatelessWidget {
  final EventModel event;

  const EventCard({super.key, required this.event});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Color.fromRGBO(45, 45, 45, 1),
                Color.fromRGBO(25, 25, 25, 1),
              ]
          ),
          boxShadow: const [
            BoxShadow(
                color: Colors.black38,
                spreadRadius: 2,
                blurRadius: 3,
                offset: Offset(0, 2)
            ),
          ]
      ),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: SpaceCol(
          spaceBetween: 10,
          children: [
            Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: TWColors.gray_100,
                  ),
                  height: 60,
                  width: 60,
                  child: const Center(
                    child: Icon(Icons.event_rounded, color: TWColors.pink_600, size: 32),
                  ),
                ),

                const SizedBox(width: 15),

                SpaceCol(
                  spaceBetween: 1,
                  children: [
                    Text(event.summary, style: TextStyle(fontSize: 21, fontFamily: 'Syne', fontWeight: FontWeight.w600, color: Colors.white)),
                    Text('${DateFormat('HH:mm').format(event.start)} - ${DateFormat('HH:mm').format(event.end)}', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: TWColors.pink_600)),
                  ],
                ),
              ],
            ),

            Divider(color: Colors.white.withOpacity(.4), height: 10),

            Row(
              children: [
                const Expanded(
                  child: SpaceRow(
                    spaceBetween: 5,
                    children: [
                      Icon(Icons.location_pin, size: 20, color: Colors.white),
                      Text('The Cube.', style: TextStyle(fontSize: 12, color: Colors.white, height: 1.3)),
                    ],
                  ),
                ),

                Expanded(
                  child: SpaceRow(
                    spaceBetween: 5,
                    children: [
                      const Icon(Icons.calendar_month, size: 20, color: Colors.white),

                      Text(DateFormat('EEEE, dd MMMM').format(event.start), style: TextStyle(fontSize: 12, color: Colors.white, height: 1.3)),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
