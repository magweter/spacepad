import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:spacepad/components/event_line.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'dart:math' show max;
import 'package:spacepad/components/action_panel.dart';
import 'package:spacepad/components/calendar_modal.dart';
import 'package:spacepad/components/authenticated_image.dart';
import 'package:spacepad/components/authenticated_background.dart';
import 'package:spacepad/services/font_service.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/components/admin_actions.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  bool _isPhone(BuildContext context) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    // Consider devices with shortestSide < 600 as phones only
    return shortestSide < 600;
  }

  bool _isPortrait(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return size.height > size.width;
  }

  double _getCornerRadius(BuildContext context) {
    // Get the top padding which includes the notch area
    final topPadding = MediaQuery.of(context).padding.top;
    // The corner radius is typically around 40-50% of the top padding
    // We'll use 45% as a good middle ground
    final cornerRadius = max(topPadding * 0.45, 10.0);
    return cornerRadius;
  }

  double _getContainerPadding(BuildContext context, DashboardController controller) {
    final size = MediaQuery.of(context).size;
    final shortestSide = size.shortestSide;
    final isPortrait = size.height > size.width;
    
    // Base padding on shortest side, increase for portrait
    final basePadding = shortestSide * 0.02; // 2% of shortest side
    final portraitMultiplier = isPortrait ? 1.2 : 1.1;
    
    // Adjust padding based on border thickness setting
    // Border thickness affects the visual border created by padding
    final borderThickness = controller.getBorderWidth();
    final borderMultiplier = borderThickness / 2.0; // Normalize to 2.0 (medium) as baseline
    
    return basePadding * portraitMultiplier * borderMultiplier;
  }

  EdgeInsets _getInnerPadding(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final shortestSide = size.shortestSide;
    final isPortrait = size.height > size.width;
    
    // Base padding on shortest side, increase for portrait
    final horizontalBase = shortestSide * 0.033; // ~3.3% of shortest side
    final verticalBase = shortestSide * 0.025; // ~2.5% of shortest side
    final portraitMultiplier = isPortrait ? 1.2 : 1.4;
    
    return EdgeInsets.fromLTRB(
      horizontalBase * portraitMultiplier,
      verticalBase * portraitMultiplier,
      horizontalBase * portraitMultiplier,
      verticalBase * portraitMultiplier,
    );
  }

  @override
  Widget build(BuildContext context) {
    DashboardController controller = Get.put(DashboardController());
    final isPhone = _isPhone(context);
    final isPortrait = _isPortrait(context);
    final cornerRadius = _getCornerRadius(context);

    if (kDebugMode) print('isPhone: $isPhone');
    if (kDebugMode) print('isPortrait: $isPortrait');
    if (kDebugMode) print('cornerRadius: $cornerRadius');

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
            padding: EdgeInsets.all(_getContainerPadding(context, controller)),
                child: AuthenticatedBackground(
                  imageUrl: controller.globalSettings.value?.backgroundImageUrl,
                  borderRadius: BorderRadius.circular(cornerRadius),
                child: Padding(
                  padding: _getInnerPadding(context),
                  child: Stack(
                    children: [
                      Align(
                        alignment: Alignment.topLeft,
                        child: Obx(() => Text(
                          formatTime(context, controller.time.value),
                          style: FontService.instance.getTextStyle(
                            fontFamily: controller.currentFontFamily.value,
                            fontSize: isPhone ? 20 : 28,
                            fontWeight: FontWeight.w500,
                            color: TWColors.white,
                          )
                        ))
                      ),
                      Align(
                        alignment: Alignment.topRight,
                        child: Obx(() {
                          final hideAdminActions = controller.globalSettings.value?.hideAdminActions ?? false;
                          final showTemporarily = controller.showAdminActionsTemporarily.value;
                          final shouldShowAdminActions = !hideAdminActions || showTemporarily;
                          return Row(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              // Admin actions component (refresh and logout buttons)
                              if (shouldShowAdminActions) AdminActions(
                                controller: controller,
                                isPhone: isPhone,
                              ),
                              if (shouldShowAdminActions) SizedBox(width: 15),
                              GestureDetector(
                                onLongPressStart: (details) {
                                  if (hideAdminActions) {
                                    controller.startLongPressTimer();
                                  }
                                },
                                onLongPressEnd: (details) {
                                  if (hideAdminActions) {
                                    controller.cancelLongPressTimer();
                                  }
                                },
                                child: Text(
                                  controller.roomName,
                                  style: FontService.instance.getTextStyle(
                                    fontFamily: controller.currentFontFamily.value,
                                    fontSize: isPhone ? 20 : 28,
                                    fontWeight: FontWeight.w500,
                                    color: TWColors.white,
                                  )
                                ),
                              ),
                            ],
                          );
                        }),
                      ),

                      SpaceCol(
                        spaceBetween: _getContainerPadding(context, controller) * 1.75, // Proportional to container padding
                        mainAxisSize: MainAxisSize.max,
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SpaceCol(
                            spaceBetween: controller.meetingInfoTimes != null ? (isPhone ? 5 : 10) : 0,
                            children: [
                              Obx(() {
                                final logoUrl = controller.globalSettings.value?.logoUrl;
                                if (logoUrl != null) {
                                  return Container(
                                    margin: EdgeInsets.only(bottom: isPhone ? 20 : 10),
                                    child: AuthenticatedImage(
                                      imageUrl: logoUrl,
                                      height: isPhone ? 24 : 36,
                                      fit: BoxFit.contain,
                                      placeholder: Container(
                                        height: isPhone ? 24 : 36,
                                        child: Center(
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            valueColor: AlwaysStoppedAnimation<Color>(TWColors.gray_300),
                                          ),
                                        ),
                                      ),
                                      errorWidget: SizedBox.shrink(), // Hide logo if it fails to load
                                    ),
                                  );
                                }
                                return SizedBox.shrink();
                              }),
                              Obx(() => Text(
                                controller.title,
                                style: FontService.instance.getTextStyle(
                                  fontFamily: controller.currentFontFamily.value,
                                  fontSize: isPhone ? 30 : 50,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                )
                              )),
                              SpaceRow(
                                spaceBetween: isPhone ? 10 : 20,
                                children: [
                                  if (controller.meetingInfoTimes != null) FrostedPanel(
                                    borderRadius: cornerRadius,
                                    blurIntensity: 18,
                                    padding: EdgeInsets.fromLTRB(
                                      isPhone ? 10 : 15,
                                      isPhone ? 5 : 8,
                                      isPhone ? 10 : 15,
                                      isPhone ? 5 : 8,
                                    ),
                                    child: Obx(() => Text(
                                      'meeting_info_title'.trParams({
                                        'start': formatTime(context, controller.meetingInfoTimes?['start'] ?? DateTime.now()),
                                        'end': formatTime(context, controller.meetingInfoTimes?['end'] ?? DateTime.now()),
                                      }),
                                      style: FontService.instance.getTextStyle(
                                        fontFamily: controller.currentFontFamily.value,
                                        fontSize: isPhone ? 24 : 32,
                                        fontWeight: FontWeight.w400,
                                        color: TWColors.white,
                                      )
                                    )),
                                  ),
                                  Flexible(
                                    child: Obx(() => Text(
                                      controller.subtitle,
                                      style: FontService.instance.getTextStyle(
                                        fontFamily: controller.currentFontFamily.value,
                                        fontSize: isPhone ? 28 : 36,
                                        fontWeight: FontWeight.w400,
                                        color: TWColors.gray_300,
                                      ),
                                      softWrap: true,
                                      overflow: TextOverflow.visible,
                                    )),
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
                        child: FrostedPanel(
                          borderRadius: cornerRadius,
                          blurIntensity: 18,
                          padding: EdgeInsets.all(isPhone ? 12 : 20),
                          child: SpaceRow(
                            spaceBetween: isPhone ? 10 : 20,
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
                                        color: TWColors.white,
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
                                              fontSize: isPhone ? 16 : 18,
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
                    ],
                  ),
                ),
              )
          ),
      ),
    );
  }
}