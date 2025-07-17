import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/theme.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:tailwind_components/tailwind_components.dart';

class CalendarModal extends StatelessWidget {
  final List<EventModel> events;
  final DateTime selectedDate;

  const CalendarModal({
    super.key,
    required this.events,
    required this.selectedDate,
  });

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Center(
        child: SizedBox(
          width: 800, // Make modal narrower
          child: ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha((0.08 * 255).toInt()),
                  // More visible frosted background
                  borderRadius: BorderRadius.circular(20),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 0, vertical: 0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Title and close icon
                    Padding(
                      padding: const EdgeInsets.fromLTRB(26, 20, 26, 20),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              'todays_schedule'.tr,
                              style: TextStyle(
                                color: AppTheme.platinum,
                                fontSize: 22,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          IconButton(
                            onPressed: () => Navigator.of(context).pop(),
                            icon: Icon(
                              Icons.close,
                              color: AppTheme.platinum,
                              size: 28,
                            ),
                            splashRadius: 22,
                          ),
                        ],
                      ),
                    ),
                    // Removed date header
                    // Events list
                    Flexible(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                        child: events.isEmpty
                            ? SizedBox(
                                height: 200,
                                child: Center(
                                  child: Text(
                                    'no_events_today'.tr,
                                    style: TextStyle(
                                      color: AppTheme.platinum,
                                      fontSize: 16,
                                    ),
                                  ),
                                ),
                              )
                            : ListView.builder(
                                shrinkWrap: true,
                                itemCount: events.length,
                                itemBuilder: (context, index) {
                                  final event = events[index];
                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 18),
                                    decoration: BoxDecoration(
                                      color: TWColors.gray_800,
                                      borderRadius: BorderRadius.circular(14),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black
                                              .withAlpha((0.1 * 255).toInt()),
                                          blurRadius: 8,
                                          offset: const Offset(0, 2),
                                        ),
                                      ],
                                    ),
                                    child: Padding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 18, vertical: 16),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Icon(
                                                Icons.schedule,
                                                color: AppTheme.orange,
                                                size: 18,
                                              ),
                                              const SizedBox(width: 8),
                                              Text(
                                                '${DateFormat('HH:mm').format(event.start)} - ${DateFormat('HH:mm').format(event.end)}',
                                                style: TextStyle(
                                                  color: AppTheme.platinum,
                                                  fontSize: 15,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 8),
                                          Text(
                                            event.summary,
                                            style: TextStyle(
                                              color: Colors.white,
                                              fontSize: 17,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          if ((event.location ?? '')
                                              .trim()
                                              .isNotEmpty) ...[
                                            const SizedBox(height: 4),
                                            Text(
                                              event.location!,
                                              style: TextStyle(
                                                color: AppTheme.platinum,
                                                fontSize: 13,
                                                fontWeight: FontWeight.w400,
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
