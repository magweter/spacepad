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
import 'package:spacepad/components/day_timeline_widget.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  bool _timelineOpen = true;

  bool _isPhone(BuildContext context) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    return shortestSide < 600;
  }

  bool _isPortrait(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return size.height > size.width;
  }

  double _getCornerRadius(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;
    final cornerRadius = max(topPadding * 0.45, 10.0);
    return cornerRadius;
  }

  double _getContainerPadding(BuildContext context, DashboardController controller) {
    final size = MediaQuery.of(context).size;
    final shortestSide = size.shortestSide;
    final isPortrait = size.height > size.width;
    final basePadding = shortestSide * 0.02;
    final portraitMultiplier = isPortrait ? 1.2 : 1.1;
    final borderThickness = controller.getBorderWidth();
    final borderMultiplier = borderThickness / 2.0;
    return basePadding * portraitMultiplier * borderMultiplier;
  }

  EdgeInsets _getInnerPadding(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final shortestSide = size.shortestSide;
    final isPortrait = size.height > size.width;
    final horizontalBase = shortestSide * 0.033;
    final verticalBase = shortestSide * 0.025;
    final portraitMultiplier = isPortrait ? 1.2 : 1.4;
    return EdgeInsets.fromLTRB(
      horizontalBase * portraitMultiplier,
      verticalBase * portraitMultiplier,
      horizontalBase * portraitMultiplier,
      verticalBase * portraitMultiplier,
    );
  }

  Widget _buildStaleIndicator(BuildContext context, bool isPhone, DashboardController controller) {
    return Obx(() {
      final offline = controller.isOffline.value;
      final serverUnreachable = controller.isServerUnreachable.value;
      return GestureDetector(
        onTap: () => controller.refreshDisplayData(),
        child: Container(
          margin: EdgeInsets.only(top: isPhone ? 2.0 : 4.0),
          padding: EdgeInsets.symmetric(horizontal: isPhone ? 8 : 10, vertical: isPhone ? 4 : 5),
          decoration: BoxDecoration(
            color: const Color(0xFFFBBF24).withOpacity(0.15),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFFBBF24).withOpacity(0.5), width: 1),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                offline ? Icons.wifi_off_rounded : (serverUnreachable ? Icons.cloud_off_rounded : Icons.sync_problem_rounded),
                size: isPhone ? 13 : 15, color: const Color(0xFFFBBF24),
              ),
              const SizedBox(width: 5),
              Text(
                offline ? 'no_internet_connection'.tr : (serverUnreachable ? 'server_unreachable'.tr : 'data_may_be_outdated'.tr),
                style: TextStyle(
                  color: const Color(0xFFFBBF24),
                  fontSize: isPhone ? 12 : 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(width: 5),
              Icon(Icons.refresh_rounded, size: isPhone ? 13 : 15, color: const Color(0xFFFBBF24)),
            ],
          ),
        ),
      );
    });
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
      body: Obx(() {
        if (controller.loading.value) {
          return Center(
            child: Spinner(size: 40, thickness: 4, color: AppTheme.platinum),
          );
        }

        final timelineMode = controller.timelineWidgetMode; // 'none' | 'side_panel' | 'inline'
        final timelineEnabled = timelineMode != 'none';
        final timelineWidth = isPhone ? 220.0 : 300.0;
        final inlineTimelineWidth = isPhone ? 220.0 : 300.0;
        final inlineTimelineMaxHeight = isPhone ? 330.0 : 484.0;
        final gap = isPhone ? 10.0 : 16.0;

        final mainStack = Stack(
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
                ),
              )),
            ),
            Align(
              alignment: Alignment.topCenter,
              child: Obx(() => controller.isDataStale.value
                  ? _buildStaleIndicator(context, isPhone, controller)
                  : const SizedBox.shrink()),
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
                    if (shouldShowAdminActions) AdminActions(
                      controller: controller,
                      isPhone: isPhone,
                    ),
                    if (shouldShowAdminActions) const SizedBox(width: 15),
                    if (timelineEnabled) ...[
                      Opacity(
                        opacity: 0.6,
                        child: GestureDetector(
                          onTap: () => setState(() => _timelineOpen = !_timelineOpen),
                          child: Icon(
                            _timelineOpen
                                ? Icons.calendar_today
                                : Icons.calendar_today_outlined,
                            color: Colors.white,
                            size: isPhone ? 20 : 24,
                          ),
                        ),
                      ),
                      const SizedBox(width: 15),
                    ],
                    GestureDetector(
                      onLongPressStart: (details) {
                        if (hideAdminActions) controller.startLongPressTimer();
                      },
                      onLongPressEnd: (details) {
                        if (hideAdminActions) controller.cancelLongPressTimer();
                      },
                      child: Text(
                        controller.roomName,
                        style: FontService.instance.getTextStyle(
                          fontFamily: controller.currentFontFamily.value,
                          fontSize: isPhone ? 20 : 28,
                          fontWeight: FontWeight.w500,
                          color: TWColors.white,
                        ),
                      ),
                    ),
                  ],
                );
              }),
            ),

            SpaceCol(
              spaceBetween: _getContainerPadding(context, controller) * 1.75,
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
                            errorWidget: SizedBox.shrink(),
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
                      ),
                    )),
                    SpaceRow(
                      spaceBetween: isPhone ? 10 : 20,
                      children: [
                        if (controller.meetingInfoTimes != null) FrostedPanel(
                          borderRadius: cornerRadius,
                          blurIntensity: 18,
                          hasBackgroundImage: controller.globalSettings.value?.backgroundImageUrl != null,
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
                            ),
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
                      ],
                    ),
                    if (controller.meetingInfoTimes == null) SizedBox(height: isPhone ? 5 : 10),
                    Obx(() {
                      final organizer = controller.currentEvent?.organizerName;
                      if (!controller.showOrganizer || organizer == null || organizer.isEmpty) {
                        return const SizedBox.shrink();
                      }
                      return Padding(
                        padding: EdgeInsets.only(top: isPhone ? 4 : 8),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.person_outline, size: isPhone ? 14 : 16, color: TWColors.gray_300),
                            SizedBox(width: isPhone ? 4 : 6),
                            Text(
                              organizer,
                              style: FontService.instance.getTextStyle(
                                fontFamily: controller.currentFontFamily.value,
                                fontSize: isPhone ? 16 : 20,
                                fontWeight: FontWeight.w400,
                                color: TWColors.gray_300,
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                    if (controller.bookingEnabled || controller.checkInEnabled || controller.extendEnabled) ActionPanel(
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
                hasBackgroundImage: controller.globalSettings.value?.backgroundImageUrl != null,
                padding: EdgeInsets.all(isPhone ? 12 : 20),
                child: SpaceRow(
                  spaceBetween: isPhone ? 10 : 20,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
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
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(
                                  Icons.calendar_today_outlined,
                                  size: 24,
                                  color: Colors.white,
                                ),
                                const SizedBox(width: 12),
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

            // Advertisement overlay — hidden while timeline is open
            if (!timelineEnabled || !_timelineOpen) Obx(() {
              final settings = controller.globalSettings.value;
              final adEnabled = settings?.advertisementEnabled ?? false;
              final adUrl = settings?.advertisementImageUrl;
              if (!adEnabled || adUrl == null || !controller.showAdvertisement.value) {
                return const SizedBox.shrink();
              }
              return Align(
                alignment: Alignment.centerRight,
                child: FractionallySizedBox(
                  widthFactor: 0.5,
                  heightFactor: 1.0,
                  child: Stack(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(cornerRadius),
                        child: AuthenticatedImage(
                          imageUrl: adUrl,
                          fit: BoxFit.contain,
                          onError: controller.dismissAdvertisement,
                          placeholder: const SizedBox.shrink(),
                          errorWidget: const SizedBox.shrink(),
                        ),
                      ),
                      Positioned(
                        top: 12,
                        right: 12,
                        child: Semantics(
                          label: 'dismiss_advertisement'.tr,
                          button: true,
                          child: GestureDetector(
                            onTap: controller.dismissAdvertisement,
                            child: Container(
                              decoration: const BoxDecoration(
                                color: Colors.black54,
                                shape: BoxShape.circle,
                              ),
                              padding: const EdgeInsets.all(6),
                              child: const Icon(
                                Icons.close,
                                color: Colors.white,
                                size: 20,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
          ],
        );

        // The Row sits at the Scaffold (black) level so the colored border only
        // wraps the main panel. The timeline panel is outside the colored Container.
        final mainPanel = Container(
          height: double.infinity,
          width: double.infinity,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: controller.isTransitioning || controller.isCheckInActive
                ? TWColors.amber_500
                : (controller.isReserved ? TWColors.rose_600 : TWColors.green_600),
            borderRadius: BorderRadius.circular(cornerRadius),
          ),
          padding: EdgeInsets.all(_getContainerPadding(context, controller)),
          child: AuthenticatedBackground(
            imageUrl: controller.globalSettings.value?.backgroundImageUrl,
            borderRadius: BorderRadius.circular(cornerRadius),
            child: Padding(
              padding: _getInnerPadding(context),
              child: mainStack,
            ),
          ),
        );

        // ── none ──────────────────────────────────────────────────────────────
        if (timelineMode == 'none') return mainPanel;

        // ── inline ────────────────────────────────────────────────────────────
        // Timeline lives inside the main panel: header row at the top,
        // content + timeline side by side, action bar at the bottom.
        if (timelineMode == 'inline') {
          final inlineChild = Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header: clock left, stale indicator center, admin actions + room name right
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Obx(() => Text(
                    formatTime(context, controller.time.value),
                    style: FontService.instance.getTextStyle(
                      fontFamily: controller.currentFontFamily.value,
                      fontSize: isPhone ? 20 : 28,
                      fontWeight: FontWeight.w500,
                      color: TWColors.white,
                    ),
                  )),
                  const Spacer(),
                  Obx(() => controller.isDataStale.value
                      ? Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          child: _buildStaleIndicator(context, isPhone, controller),
                        )
                      : const SizedBox.shrink()),
                  const Spacer(),
                  Obx(() {
                    final hide = controller.globalSettings.value?.hideAdminActions ?? false;
                    final showTemp = controller.showAdminActionsTemporarily.value;
                    final show = !hide || showTemp;
                    return Row(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        if (show) AdminActions(controller: controller, isPhone: isPhone),
                        if (show) const SizedBox(width: 15),
                        GestureDetector(
                          onLongPressStart: (_) { if (hide) controller.startLongPressTimer(); },
                          onLongPressEnd: (_) { if (hide) controller.cancelLongPressTimer(); },
                          child: Text(
                            controller.roomName,
                            style: FontService.instance.getTextStyle(
                              fontFamily: controller.currentFontFamily.value,
                              fontSize: isPhone ? 20 : 28,
                              fontWeight: FontWeight.w500,
                              color: TWColors.white,
                            ),
                          ),
                        ),
                      ],
                    );
                  }),
                ],
              ),
              SizedBox(height: gap),
              // Middle: status content on the left, timeline on the right
              Expanded(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: SpaceCol(
                        spaceBetween: _getContainerPadding(context, controller) * 1.75,
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
                                        child: Center(child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(TWColors.gray_300))),
                                      ),
                                      errorWidget: SizedBox.shrink(),
                                    ),
                                  );
                                }
                                return SizedBox.shrink();
                              }),
                              Obx(() => Text(controller.title,
                                style: FontService.instance.getTextStyle(
                                  fontFamily: controller.currentFontFamily.value,
                                  fontSize: isPhone ? 30 : 50,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              )),
                              SpaceRow(
                                spaceBetween: isPhone ? 10 : 20,
                                children: [
                                  if (controller.meetingInfoTimes != null) FrostedPanel(
                                    borderRadius: cornerRadius,
                                    blurIntensity: 18,
                                    hasBackgroundImage: controller.globalSettings.value?.backgroundImageUrl != null,
                                    padding: EdgeInsets.fromLTRB(isPhone ? 10 : 15, isPhone ? 5 : 8, isPhone ? 10 : 15, isPhone ? 5 : 8),
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
                                      ),
                                    )),
                                  ),
                                  Flexible(
                                    child: Obx(() => Text(controller.subtitle,
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
                                ],
                              ),
                              if (controller.meetingInfoTimes == null) SizedBox(height: isPhone ? 5 : 10),
                              Obx(() {
                                final organizer = controller.currentEvent?.organizerName;
                                if (!controller.showOrganizer || organizer == null || organizer.isEmpty) {
                                  return const SizedBox.shrink();
                                }
                                return Padding(
                                  padding: EdgeInsets.only(top: isPhone ? 4 : 8),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(Icons.person_outline, size: isPhone ? 14 : 16, color: TWColors.gray_300),
                                      SizedBox(width: isPhone ? 4 : 6),
                                      Text(
                                        organizer,
                                        style: FontService.instance.getTextStyle(
                                          fontFamily: controller.currentFontFamily.value,
                                          fontSize: isPhone ? 16 : 20,
                                          fontWeight: FontWeight.w400,
                                          color: TWColors.gray_300,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                              if (controller.bookingEnabled || controller.checkInEnabled || controller.extendEnabled) ActionPanel(
                                controller: controller,
                                isPhone: isPhone,
                                cornerRadius: cornerRadius,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    SizedBox(width: gap),
                    Align(
                      alignment: Alignment.center,
                      child: ConstrainedBox(
                        constraints: BoxConstraints(maxHeight: inlineTimelineMaxHeight),
                        child: SizedBox(
                          width: inlineTimelineWidth,
                          child: DayTimelineWidget(
                            controller: controller,
                            isPhone: isPhone,
                            cornerRadius: cornerRadius,
                            frosted: true,
                            hasBackgroundImage: controller.globalSettings.value?.backgroundImageUrl != null,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(height: gap),
              // Bottom bar (not in Align — Column handles vertical layout)
              FrostedPanel(
                borderRadius: cornerRadius,
                blurIntensity: 18,
                hasBackgroundImage: controller.globalSettings.value?.backgroundImageUrl != null,
                padding: EdgeInsets.all(isPhone ? 12 : 20),
                child: SpaceRow(
                  spaceBetween: isPhone ? 10 : 20,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: controller.upcomingEvents.isNotEmpty
                        ? SpaceCol(
                            spaceBetween: isPhone ? 8 : 12,
                            children: [
                              for (EventModel event in controller.upcomingEvents.take(1)) EventLine(event: event),
                            ],
                          )
                        : Text('no_upcoming_events'.tr,
                            style: TextStyle(color: TWColors.white, fontSize: isPhone ? 16 : 18, fontWeight: FontWeight.w500)),
                    ),
                    if (controller.calendarEnabled)
                      Material(
                        color: Colors.transparent,
                        child: InkWell(
                          hoverColor: Colors.transparent,
                          splashColor: Colors.transparent,
                          highlightColor: Colors.transparent,
                          borderRadius: BorderRadius.circular(8),
                          onTap: () => showDialog(
                            context: context,
                            builder: (context) => CalendarModal(events: controller.events, selectedDate: DateTime.now()),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.calendar_today_outlined, size: 24, color: Colors.white),
                                const SizedBox(width: 12),
                                Text('view_schedule'.tr,
                                  style: TextStyle(color: Colors.white, fontSize: isPhone ? 16 : 18, fontWeight: FontWeight.w500)),
                              ],
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          );

          return Container(
            height: double.infinity,
            width: double.infinity,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: controller.isTransitioning || controller.isCheckInActive
                  ? TWColors.amber_500
                  : (controller.isReserved ? TWColors.rose_600 : TWColors.green_600),
              borderRadius: BorderRadius.circular(cornerRadius),
            ),
            padding: EdgeInsets.all(_getContainerPadding(context, controller)),
            child: AuthenticatedBackground(
              imageUrl: controller.globalSettings.value?.backgroundImageUrl,
              borderRadius: BorderRadius.circular(cornerRadius),
              child: Padding(
                padding: _getInnerPadding(context),
                child: inlineChild,
              ),
            ),
          );
        }

        // ── full_panel ────────────────────────────────────────────────────────
        // Full-height timeline on the right; main content (header + status + bottom
        // bar) in a Column on the left. Same as inline but timeline is not height-
        // constrained — it stretches to the full panel height.
        if (timelineMode == 'full_panel') {
          final hasBackground = controller.globalSettings.value?.backgroundImageUrl != null;

          final fullPanelChild = Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Header
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Obx(() => Text(
                          formatTime(context, controller.time.value),
                          style: FontService.instance.getTextStyle(
                            fontFamily: controller.currentFontFamily.value,
                            fontSize: isPhone ? 20 : 28,
                            fontWeight: FontWeight.w500,
                            color: TWColors.white,
                          ),
                        )),
                        const Spacer(),
                        Obx(() => controller.isDataStale.value
                            ? Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 8),
                                child: _buildStaleIndicator(context, isPhone, controller),
                              )
                            : const SizedBox.shrink()),
                        const Spacer(),
                        Obx(() {
                          final hide = controller.globalSettings.value?.hideAdminActions ?? false;
                          final showTemp = controller.showAdminActionsTemporarily.value;
                          final show = !hide || showTemp;
                          return Row(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              if (show) AdminActions(controller: controller, isPhone: isPhone),
                              if (show) const SizedBox(width: 15),
                              GestureDetector(
                                onLongPressStart: (_) { if (hide) controller.startLongPressTimer(); },
                                onLongPressEnd: (_) { if (hide) controller.cancelLongPressTimer(); },
                                child: Text(
                                  controller.roomName,
                                  style: FontService.instance.getTextStyle(
                                    fontFamily: controller.currentFontFamily.value,
                                    fontSize: isPhone ? 20 : 28,
                                    fontWeight: FontWeight.w500,
                                    color: TWColors.white,
                                  ),
                                ),
                              ),
                            ],
                          );
                        }),
                      ],
                    ),
                    SizedBox(height: gap),
                    // Status content
                    Expanded(
                      child: SpaceCol(
                        spaceBetween: _getContainerPadding(context, controller) * 1.75,
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
                                        child: Center(child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(TWColors.gray_300))),
                                      ),
                                      errorWidget: const SizedBox.shrink(),
                                    ),
                                  );
                                }
                                return const SizedBox.shrink();
                              }),
                              Obx(() => Text(controller.title,
                                style: FontService.instance.getTextStyle(
                                  fontFamily: controller.currentFontFamily.value,
                                  fontSize: isPhone ? 30 : 50,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              )),
                              SpaceRow(
                                spaceBetween: isPhone ? 10 : 20,
                                children: [
                                  if (controller.meetingInfoTimes != null) FrostedPanel(
                                    borderRadius: cornerRadius,
                                    blurIntensity: 18,
                                    hasBackgroundImage: hasBackground,
                                    padding: EdgeInsets.fromLTRB(isPhone ? 10 : 15, isPhone ? 5 : 8, isPhone ? 10 : 15, isPhone ? 5 : 8),
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
                                      ),
                                    )),
                                  ),
                                  Flexible(
                                    child: Obx(() => Text(controller.subtitle,
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
                                ],
                              ),
                              if (controller.meetingInfoTimes == null) SizedBox(height: isPhone ? 5 : 10),
                              Obx(() {
                                final organizer = controller.currentEvent?.organizerName;
                                if (!controller.showOrganizer || organizer == null || organizer.isEmpty) {
                                  return const SizedBox.shrink();
                                }
                                return Padding(
                                  padding: EdgeInsets.only(top: isPhone ? 4 : 8),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(Icons.person_outline, size: isPhone ? 14 : 16, color: TWColors.gray_300),
                                      SizedBox(width: isPhone ? 4 : 6),
                                      Text(
                                        organizer,
                                        style: FontService.instance.getTextStyle(
                                          fontFamily: controller.currentFontFamily.value,
                                          fontSize: isPhone ? 16 : 20,
                                          fontWeight: FontWeight.w400,
                                          color: TWColors.gray_300,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                              if (controller.bookingEnabled || controller.checkInEnabled || controller.extendEnabled) ActionPanel(
                                controller: controller,
                                isPhone: isPhone,
                                cornerRadius: cornerRadius,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: gap),
                    // Bottom bar
                    FrostedPanel(
                      borderRadius: cornerRadius,
                      blurIntensity: 18,
                      hasBackgroundImage: hasBackground,
                      padding: EdgeInsets.all(isPhone ? 12 : 20),
                      child: SpaceRow(
                        spaceBetween: isPhone ? 10 : 20,
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: controller.upcomingEvents.isNotEmpty
                              ? SpaceCol(
                                  spaceBetween: isPhone ? 8 : 12,
                                  children: [
                                    for (EventModel event in controller.upcomingEvents.take(1)) EventLine(event: event),
                                  ],
                                )
                              : Text('no_upcoming_events'.tr,
                                  style: TextStyle(color: TWColors.white, fontSize: isPhone ? 16 : 18, fontWeight: FontWeight.w500)),
                          ),
                          if (controller.calendarEnabled)
                            Material(
                              color: Colors.transparent,
                              child: InkWell(
                                hoverColor: Colors.transparent,
                                splashColor: Colors.transparent,
                                highlightColor: Colors.transparent,
                                borderRadius: BorderRadius.circular(8),
                                onTap: () => showDialog(
                                  context: context,
                                  builder: (context) => CalendarModal(events: controller.events, selectedDate: DateTime.now()),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.calendar_today_outlined, size: 24, color: Colors.white),
                                      const SizedBox(width: 12),
                                      Text('view_schedule'.tr,
                                        style: TextStyle(color: Colors.white, fontSize: isPhone ? 16 : 18, fontWeight: FontWeight.w500)),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(width: gap),
              // Right: full-height timeline — no height constraint, stretches with the Row
              SizedBox(
                width: inlineTimelineWidth,
                child: DayTimelineWidget(
                  controller: controller,
                  isPhone: isPhone,
                  cornerRadius: cornerRadius,
                  frosted: true,
                  hasBackgroundImage: hasBackground,
                ),
              ),
            ],
          );

          return Container(
            height: double.infinity,
            width: double.infinity,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: controller.isTransitioning || controller.isCheckInActive
                  ? TWColors.amber_500
                  : (controller.isReserved ? TWColors.rose_600 : TWColors.green_600),
              borderRadius: BorderRadius.circular(cornerRadius),
            ),
            padding: EdgeInsets.all(_getContainerPadding(context, controller)),
            child: AuthenticatedBackground(
              imageUrl: controller.globalSettings.value?.backgroundImageUrl,
              borderRadius: BorderRadius.circular(cornerRadius),
              child: Padding(
                padding: _getInnerPadding(context),
                child: fullPanelChild,
              ),
            ),
          );
        }

        // ── side_panel ────────────────────────────────────────────────────────
        return Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(child: mainPanel),

            // Timeline panel — outside the colored Container so the border only
            // wraps the main panel. ClipRect + AnimatedContainer animate width
            // from 0 → timelineWidth + gap. OverflowBox keeps the child at its
            // natural width while the container clips it during the transition.
            ClipRect(
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                curve: Curves.easeOut,
                width: _timelineOpen ? timelineWidth + gap : 0,
                child: OverflowBox(
                  maxWidth: timelineWidth + gap,
                  alignment: Alignment.centerRight,
                  child: SizedBox(
                    width: timelineWidth + gap,
                    child: Padding(
                      padding: EdgeInsets.only(left: gap),
                      child: DayTimelineWidget(
                        controller: controller,
                        isPhone: isPhone,
                        cornerRadius: cornerRadius,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      }),
    );
  }
}
