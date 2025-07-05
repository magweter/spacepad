import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:spacepad/components/event_line.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'dart:io' show Platform;
import 'dart:math' show max;
import 'package:spacepad/services/auth_service.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  @override
  void initState() {
    super.initState();
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.landscapeLeft,
      DeviceOrientation.landscapeRight,
    ]);
  }

  @override
  void dispose() {
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
      DeviceOrientation.portraitDown,
      DeviceOrientation.landscapeLeft,
      DeviceOrientation.landscapeRight,
    ]);
    super.dispose();
  }

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
            color: controller.isTransitioning ?
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
                          controller.time.value,
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
                            spaceBetween: controller.meetingInfo != null ? (isPhone ? 5 : 10) : 0,
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
                                  if (controller.meetingInfo != null) Container(
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
                                        controller.meetingInfo!,
                                        style: TextStyle(
                                          color: TWColors.white,
                                          fontSize: isPhone ? 24 : 32,
                                          fontWeight: FontWeight.w400
                                        )
                                      ),
                                    ),
                                  ),
                                  Text(
                                    controller.subtitle,
                                    style: TextStyle(
                                      color: TWColors.gray_300,
                                      fontSize: isPhone ? 28 : 36,
                                      fontWeight: FontWeight.w400
                                    )
                                  ),
                                ]
                              ),
                              SizedBox(height: isPhone ? 10 : 20),
                              // Show booking interface if booking is enabled for this display
                              if (controller.shouldShowBooking)
                                Obx(() => controller.showBookingOptions.value
                                  // Show booking options in outlined box
                                  ? Container(
                                      decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(cornerRadius),
                                        border: Border.all(
                                          color: TWColors.gray_500.withValues(alpha: 0.3),
                                          width: 1,
                                        ),
                                        color: Colors.transparent,
                                      ),
                                      child: Padding(
                                        padding: EdgeInsets.all(isPhone ? 12 : 20),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            // Booking duration options
                                            Row(
                                              children: [
                                                for (var min in [15, 30, 60])
                                                  Padding(
                                                    padding: EdgeInsets.only(
                                                      right: min == 60 ? 0 : (isPhone ? 12 : 16),
                                                    ),
                                                    child: Container(
                                                      decoration: BoxDecoration(
                                                        borderRadius: BorderRadius.circular(cornerRadius * 0.3),
                                                        color: TWColors.gray_600.withValues(alpha: 0.3),
                                                      ),
                                                      child: Material(
                                                        color: Colors.transparent,
                                                        child: InkWell(
                                                          borderRadius: BorderRadius.circular(cornerRadius * 0.3),
                                                          onTap: () => controller.bookRoom(min),
                                                          child: Padding(
                                                            padding: EdgeInsets.symmetric(
                                                              vertical: isPhone ? 12 : 16,
                                                              horizontal: isPhone ? 16 : 24,
                                                            ),
                                                            child: Text(
                                                              '$min min',
                                                              style: TextStyle(
                                                                color: Colors.white,
                                                                fontSize: isPhone ? 18 : 26,
                                                                fontWeight: FontWeight.w600,
                                                              ),
                                                            ),
                                                          ),
                                                        ),
                                                      ),
                                                    ),
                                                  ),
                                              ],
                                            ),
                                            SizedBox(width: isPhone ? 16 : 24),
                                            // Cancel button
                                            Container(
                                              decoration: BoxDecoration(
                                                borderRadius: BorderRadius.circular(cornerRadius * 0.3),
                                                color: TWColors.gray_600.withValues(alpha: 0.3),
                                              ),
                                              child: Material(
                                                color: Colors.transparent,
                                                child: InkWell(
                                                  borderRadius: BorderRadius.circular(cornerRadius * 0.3),
                                                  onTap: () => controller.hideBookingOptions(),
                                                  child: Padding(
                                                    padding: EdgeInsets.symmetric(
                                                      vertical: isPhone ? 12 : 16,
                                                      horizontal: isPhone ? 16 : 24,
                                                    ),
                                                    child: Text(
                                                      'cancel'.tr,
                                                      style: TextStyle(
                                                        color: Colors.white,
                                                        fontSize: isPhone ? 18 : 26,
                                                        fontWeight: FontWeight.w600,
                                                      ),
                                                    ),
                                                  ),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    )
                                  // Show initial "Book now" button
                                  : Container(
                                      decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(cornerRadius),
                                        color: TWColors.gray_600.withValues(alpha: 0.3),
                                      ),
                                      margin: EdgeInsets.only(top: isPhone ? 10 : 20, bottom: isPhone ? 10 : 20),
                                      child: Material(
                                        color: Colors.transparent,
                                        child: InkWell(
                                          borderRadius: BorderRadius.circular(cornerRadius),
                                          onTap: () => controller.toggleBookingOptions(),
                                          child: Padding(
                                            padding: EdgeInsets.symmetric(
                                              vertical: isPhone ? 12 : 16,
                                              horizontal: isPhone ? 20 : 28,
                                            ),
                                            child: Text(
                                              'book_now'.tr,
                                              style: TextStyle(
                                                color: Colors.white,
                                                fontSize: isPhone ? 20 : 28,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                ),
                            ],
                          ),
                        ],
                      ),

                      if (controller.upcomingEvents.isNotEmpty) Align(
                          alignment: Alignment.bottomLeft,
                          child: Container(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(cornerRadius * 0.5),
                              color: TWColors.gray_600.withValues(alpha: 0.3),
                            ),
                            child: Padding(
                              padding: EdgeInsets.all(isPhone ? 10 : 20),
                              child: SpaceCol(
                                spaceBetween: isPhone ? 10 : 15,
                                children: [
                                  for (EventModel event in controller.upcomingEvents.take(1)) EventLine(event: event),
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