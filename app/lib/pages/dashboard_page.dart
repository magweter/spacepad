import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:spacepad/components/action_button.dart';
import 'package:spacepad/components/event_line.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'dart:io' show Platform;
import 'dart:math' show max;
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/components/action_panel.dart';
import 'package:spacepad/components/calendar_modal.dart';
import 'package:spacepad/translations/translations.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  // @override
  // void initState() {
  //   super.initState();
  //   SystemChrome.setPreferredOrientations([
  //     DeviceOrientation.landscapeLeft,
  //     DeviceOrientation.landscapeRight,
  //   ]);
  // }
  //
  // @override
  // void dispose() {
  //   SystemChrome.setPreferredOrientations([
  //     DeviceOrientation.portraitUp,
  //     DeviceOrientation.portraitDown,
  //     DeviceOrientation.landscapeLeft,
  //     DeviceOrientation.landscapeRight,
  //   ]);
  //   super.dispose();
  // }

  bool _isPhone(BuildContext context) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    // Consider devices with shortestSide < 600 as phones only
    return shortestSide < 600;
  }

  double _getCornerRadius(BuildContext context) {
    // Get the top padding which includes the notch area
    final topPadding = MediaQuery.of(context).padding.top;
    // The corner radius is typically around 40-50% of the top padding
    // We'll use 45% as a good middle ground
    final cornerRadius = max(topPadding * 0.45, 10.0);
    return cornerRadius;
  }

  @override
  Widget build(BuildContext context) {
    DashboardController controller = Get.put(DashboardController());
    final isPhone = _isPhone(context);
    final cornerRadius = _getCornerRadius(context);

    return Scaffold(
      backgroundColor: AppTheme.black,
      body: Obx(() => controller.loading.value ?
          Center(
            child: Spinner(size: 40, thickness: 4, color: AppTheme.platinum),
          ) :
          Container(
            height: double.infinity,
            width: double.infinity,
            color: controller.isTransitioning || controller.isCheckInActive ?
              TWColors.amber_500 :
              (controller.isReserved ? TWColors.rose_600 : TWColors.green_600),
            padding: EdgeInsets.all(isPhone ? 8 : 16),
            child: Container(
                height: double.infinity,
                width: double.infinity,
                decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(cornerRadius),
                    color: Colors.black
                ),
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    isPhone ? 20 : 40,
                    isPhone ? 15 : 30,
                    isPhone ? 20 : 40,
                    isPhone ? 15 : 30,
                  ),
                  child: Stack(
                    children: [
                      Align(
                        alignment: Alignment.topLeft,
                        child: Text(
                          formatTime(context, controller.time.value),
                          style: TextStyle(
                            color: TWColors.gray_300,
                            fontSize: isPhone ? 20 : 28,
                            fontWeight: FontWeight.w900
                          )
                        )
                      ),
                      Align(
                        alignment: Alignment.topRight,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Opacity(
                              opacity: 0.4,
                              child: IconButton(
                                icon: const Icon(Icons.logout, size: 24, color: Colors.white),
                                onPressed: () {
                                  controller.switchRoom();
                                },
                                tooltip: 'switch_room'.tr,
                              ),
                            ),

                            SizedBox(width: 16),
                            Text(
                              controller.roomName,
                              style: TextStyle(
                                color: TWColors.gray_300,
                                fontSize: isPhone ? 20 : 28,
                                fontWeight: FontWeight.w700
                              )
                            ),
                          ],
                        ),
                      ),

                      SpaceCol(
                        spaceBetween: isPhone ? 20 : 40,
                        mainAxisSize: MainAxisSize.max,
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SpaceCol(
                            spaceBetween: controller.meetingInfoTimes != null ? (isPhone ? 5 : 10) : 0,
                            children: [
                              Text(
                                controller.title,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: isPhone ? 30 : 56,
                                  fontWeight: FontWeight.w900
                                )
                              ),
                              SpaceRow(
                                spaceBetween: isPhone ? 10 : 20,
                                children: [
                                  if (controller.meetingInfoTimes != null) Container(
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(cornerRadius * 0.5),
                                      color: TWColors.gray_600.withValues(alpha: 0.3),
                                    ),
                                    child: Padding(
                                      padding: EdgeInsets.fromLTRB(
                                        isPhone ? 10 : 20,
                                        isPhone ? 5 : 10,
                                        isPhone ? 10 : 20,
                                        isPhone ? 5 : 10,
                                      ),
                                      child: Text(
                                        'meeting_info_title'.trParams({
                                          'start': formatTime(context, controller.meetingInfoTimes!['start']!),
                                          'end': formatTime(context, controller.meetingInfoTimes!['end']!),
                                        }),
                                        style: TextStyle(
                                          color: TWColors.white,
                                          fontSize: isPhone ? 24 : 32,
                                          fontWeight: FontWeight.w400
                                        )
                                      ),
                                    ),
                                  ),
                                  Flexible(
                                    child: Text(
                                      controller.subtitle,
                                      style: TextStyle(
                                        color: TWColors.gray_300,
                                        fontSize: isPhone ? 28 : 36,
                                        fontWeight: FontWeight.w400
                                      ),
                                      softWrap: true,
                                      overflow: TextOverflow.visible,
                                    ),
                                  ),
                                ]
                              ),
                              if (controller.meetingInfoTimes == null) SizedBox(height: isPhone ? 5 : 10),
                              if (controller.bookingEnabled || controller.checkInEnabled) ActionPanel(
                                controller: controller,
                                isPhone: isPhone,
                                cornerRadius: cornerRadius,
                              ),
                            ],
                          ),
                        ],
                      ),

                      // Fixed Action Bar at Bottom
                      Align(
                        alignment: Alignment.bottomCenter,
                        child: Container(
                          width: double.infinity,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.only(
                              topLeft: Radius.circular(cornerRadius * 0.5),
                              topRight: Radius.circular(cornerRadius * 0.5),
                            ),
                            color: TWColors.gray_600.withValues(alpha: 0.3),
                          ),
                          child: Padding(
                            padding: EdgeInsets.all(isPhone ? 12 : 20),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                // Upcoming Events Section
                                Expanded(
                                  child: controller.upcomingEvents.isNotEmpty
                                    ? SpaceCol(
                                        spaceBetween: isPhone ? 8 : 12,
                                        children: [
                                          for (EventModel event in controller.upcomingEvents.take(1)) EventLine(event: event),
                                        ],
                                      )
                                    : Text(
                                        'no_upcoming_events'.tr,
                                        style: TextStyle(
                                          color: TWColors.gray_300,
                                          fontSize: isPhone ? 16 : 18,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                ),
                                
                                // Action Buttons Section
                                if (controller.calendarEnabled)
                                  Material(
                                    color: Colors.transparent,
                                    child: InkWell(
                                      hoverColor: Colors.transparent,
                                      splashColor: Colors.transparent,
                                      highlightColor: Colors.transparent,
                                      borderRadius: BorderRadius.circular(8),
                                      onTap: () {
                                        showDialog(
                                          context: context,
                                          builder: (context) => CalendarModal(
                                            events: controller.events,
                                            selectedDate: DateTime.now(),
                                          ),
                                        );
                                      },
                                      child: Padding(
                                        padding: EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(
                                              Icons.calendar_today_outlined,
                                              size: 24,
                                              color: Colors.white,
                                            ),
                                            SizedBox(width: 12),
                                            Text(
                                              'view_schedule'.tr,
                                              style: TextStyle(
                                                color: Colors.white,
                                                fontSize: isPhone ? 14 : 18,
                                                fontWeight: FontWeight.w500,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  )
                ),
              )
          ),
      ),
    );
  }
}