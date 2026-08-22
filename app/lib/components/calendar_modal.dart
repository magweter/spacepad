import 'package:flutter/material.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/theme.dart';
import 'package:get/get.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'package:spacepad/components/frosted_panel.dart';

/// The corners in this modal, from the outside in: the panel, the meeting cards, the labels on
/// them. Rounded rectangles rather than pills, so the labels are the same shape as the chips on
/// the display behind the modal and sit square inside the card that holds them.
const double _panelRadius = 20;
const double _cardRadius = 14;
const double _labelRadius = 10;

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
    final controller = Get.find<DashboardController>();
    final showOrganizer = controller.showOrganizer;
    final showMeetingLocation = controller.showMeetingLocation;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Center(
        child: SizedBox(
          width: 800, // Make modal narrower
          child: FrostedPanel(
            borderRadius: _panelRadius,
            blurIntensity: 18,
            padding: const EdgeInsets.symmetric(horizontal: 0, vertical: 0),
            child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Title and close icon.
                    //
                    // The close button carries its own 48pt tap target, so the header only needs
                    // padding around it, not padding the size of a second row.
                    Padding(
                      padding: const EdgeInsets.fromLTRB(26, 12, 14, 4),
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
                        // No bottom padding: every card already carries an 18pt margin under it.
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
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
                                  final organizer = _organizerFor(event, showOrganizer);

                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 18),
                                    decoration: BoxDecoration(
                                      color: TWColors.gray_800,
                                      borderRadius: BorderRadius.circular(_cardRadius),
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
                                          // Time and organiser on one line: both answer "which
                                          // meeting is this", so they lead the card together and
                                          // the title underneath gets the full width to itself.
                                          Row(
                                            children: [
                                              Icon(
                                                Icons.schedule,
                                                color: AppTheme.orange,
                                                size: 18,
                                              ),
                                              const SizedBox(width: 8),
                                              Text(
                                                '${formatTime(context, event.start)} - ${formatTime(context, event.end)}',
                                                style: TextStyle(
                                                  color: AppTheme.platinum,
                                                  fontSize: 15,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                              if (organizer != null) ...[
                                                const SizedBox(width: 12),
                                                Flexible(
                                                  child: _MetaLabel(
                                                    icon: Icons.person_outline,
                                                    text: organizer,
                                                  ),
                                                ),
                                              ],
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
                                          if (showMeetingLocation)
                                            _EventLocationLine(event: event),
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
        );
  }
}

/// The organiser, unless showing it adds nothing.
///
/// Outlook and Google fall back to the organiser's name as the subject when a room is booked
/// without one, so printing both would put the same name on the card twice.
String? _organizerFor(EventModel event, bool showOrganizer) {
  if (!showOrganizer) {
    return null;
  }

  final organizer = (event.organizerName ?? '').trim();

  if (organizer.isEmpty ||
      organizer.toLowerCase() == event.summary.trim().toLowerCase()) {
    return null;
  }

  return organizer;
}

/// Where the meeting is, as the calendar has it.
///
/// One line for both facts, because that is how the providers deliver it: Google and Microsoft
/// write the booked room and the address the organiser typed onto the same location field, so it
/// can read as a full street and postcode followed by a room name. Off by default, and left out
/// entirely rather than kept as an empty strip when the event carries no location at all.
///
/// Plain text, not a label: it is the longest value on the card and the least often looked at, so
/// it should not read as loudly as the organiser beside the time.
class _EventLocationLine extends StatelessWidget {
  final EventModel event;

  const _EventLocationLine({required this.event});

  @override
  Widget build(BuildContext context) {
    final location = (event.location ?? '').trim();

    if (location.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Text(
        location,
        style: TextStyle(
          color: AppTheme.platinum,
          fontSize: 13,
          fontWeight: FontWeight.w400,
        ),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
    );
  }
}

/// One labelled fact on a card: an icon that says which fact it is, and the value.
///
/// Same shape and weight as the chips on the display behind the modal, at card scale.
class _MetaLabel extends StatelessWidget {
  final IconData icon;
  final String text;

  const _MetaLabel({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(_labelRadius),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 13,
            color: AppTheme.platinum,
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              text,
              style: TextStyle(
                color: AppTheme.platinum,
                fontSize: 12,
                fontWeight: FontWeight.w400,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
