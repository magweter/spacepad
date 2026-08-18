import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/services/font_service.dart';
import 'package:tailwind_components/tailwind_components.dart';

/// The line under the meeting title: when it runs, who booked it, and how much of it is left.
///
/// One type size for the whole row. The time and the organiser are the same kind of fact, so
/// they get identical chips and identical text; the remaining time is the only thing that
/// steps back, in the weakest grey. Earlier versions used three sizes and three greys here,
/// which made the row read as three unrelated fragments instead of one line.
class MeetingMetaRow extends StatelessWidget {
  final dynamic controller;
  final bool isPhone;
  final double cornerRadius;

  /// Puts the remaining time on its own line, under the chips.
  ///
  /// True whenever something else claims horizontal room: a timeline panel beside the
  /// content, or a portrait display. On one line the organiser name and the remaining time
  /// compete for the same space and the name is the first to lose; on its own line the name
  /// gets the full width and the remaining time is still legible.
  final bool stackSubtitle;

  const MeetingMetaRow({
    super.key,
    required this.controller,
    required this.isPhone,
    required this.cornerRadius,
    required this.stackSubtitle,
  });

  /// Same scale as the rest of the dashboard, see DashboardPage._sp.
  double _sp(BuildContext context, double size) {
    final s = MediaQuery.of(context).size.shortestSide;
    return (size * (s / 800).clamp(0.5, 1.3)).roundToDouble();
  }

  /// The organiser, unless showing it adds nothing.
  ///
  /// Outlook and Google fall back to the organiser's name as the subject when a room is
  /// booked without one, so on a lot of displays the same name was simply printed twice.
  /// With the meeting title hidden for privacy the title reads "Reserved", which is not a
  /// duplicate, so the name stays.
  String? _organizer() {
    if (controller.showOrganizer != true) {
      return null;
    }

    final organizer = (controller.currentEvent?.organizerName as String?)?.trim();

    if (organizer == null || organizer.isEmpty) {
      return null;
    }

    final title = (controller.title as String).trim();

    return organizer.toLowerCase() == title.toLowerCase() ? null : organizer;
  }

  /// The remaining time. Two lines, because stacked it has the width to use them.
  Widget _subtitle(String subtitle, TextStyle style) {
    return Text(
      subtitle,
      style: style.copyWith(color: TWColors.gray_400),
      softWrap: true,
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final times = controller.meetingInfoTimes as Map<String, DateTime>?;
      final subtitle = (controller.subtitle as String).trim();
      final organizer = _organizer();
      final hasBackgroundImage = controller.globalSettings.value?.backgroundImageUrl != null;

      // The single size the whole row is set in.
      final fontSize = _sp(context, 32);

      final chipStyle = FontService.instance.getTextStyle(
        fontFamily: controller.currentFontFamily.value,
        fontSize: fontSize,
        fontWeight: FontWeight.w400,
        color: TWColors.white,
      );

      final chipPadding = EdgeInsets.fromLTRB(
        isPhone ? 10 : 15,
        isPhone ? 5 : 8,
        isPhone ? 10 : 15,
        isPhone ? 5 : 8,
      );

      final chips = SpaceRow(
        spaceBetween: isPhone ? 10 : 20,
        children: [
          if (times != null) FrostedPanel(
            borderRadius: cornerRadius,
            blurIntensity: 18,
            hasBackgroundImage: hasBackgroundImage,
            padding: chipPadding,
            child: Text(
              'meeting_info_title'.trParams({
                'start': formatTime(context, times['start'] ?? DateTime.now()),
                'end': formatTime(context, times['end'] ?? DateTime.now()),
              }),
              style: chipStyle,
            ),
          ),
          // A chip of its own rather than a separator on the line: it is the same kind of
          // fact as the time, so it gets the same chip, the same size and the same white.
          //
          // Three shares of the leftover space against one for the remaining time. Both
          // sat on flex 1 before, which capped the name at half the row and clipped names
          // that had room to spare, while "36 min left" left its own half half-empty.
          if (organizer != null) Flexible(
            flex: 3,
            child: FrostedPanel(
              borderRadius: cornerRadius,
              blurIntensity: 18,
              hasBackgroundImage: hasBackgroundImage,
              padding: chipPadding,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Dimmer than the name: it labels the chip, it is not part of the text.
                  Icon(
                    Icons.person_outline,
                    size: fontSize * 1,
                    color: TWColors.gray_300,
                  ),
                  SizedBox(width: isPhone ? 5 : 7),
                  Flexible(
                    child: Text(
                      organizer,
                      style: chipStyle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (!stackSubtitle && subtitle.isNotEmpty) Flexible(
            child: _subtitle(subtitle, chipStyle),
          ),
        ],
      );

      if (!stackSubtitle || subtitle.isEmpty) {
        return chips;
      }

      return SpaceCol(
        spaceBetween: isPhone ? 6 : 10,
        children: [
          chips,
          _subtitle(subtitle, chipStyle),
        ],
      );
    });
  }
}
