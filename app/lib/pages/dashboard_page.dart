import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:spacepad/components/event_line.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/date_format_helper.dart';
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
import 'package:spacepad/components/meeting_meta_row.dart';
import 'package:spacepad/components/status_title.dart';

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

  /// Proportionally scale [size] (designed for a ~750 px shortest side) to
  /// the actual screen size so text and icons shrink/grow smoothly when the
  /// window is resized rather than jumping between two fixed breakpoints.
  ///
  /// The reference used to be 800, which left a 1024x600 panel at 0.75 and its text a notch
  /// smaller than it needed to be at reading distance.
  double _sp(BuildContext context, double size) {
    final s = MediaQuery.of(context).size.shortestSide;
    return (size * (s / 750).clamp(0.5, 1.3)).roundToDouble();
  }

  /// A timeline panel's width: the base on a full-size display, 24pt less on a smaller one.
  ///
  /// A fixed step rather than a proportional one, because the panel always has to hold a whole
  /// day: what changes on a smaller display is that the content beside it needs those 24pt more
  /// than the panel does. The threshold is 90% of the scale reference in [_sp], so a display
  /// sitting at or near its design size keeps the full width.
  double _panelWidth(BuildContext context, double base) {
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    return shortestSide < 750 * 0.9 ? base - 24 : base;
  }

  /// Half the shrink of [_sp], for the timeline panel.
  ///
  /// The panel holds a whole day whatever the display measures, so it cannot give up width as
  /// fast as the type it is set in without the hour rows crowding together. On a 1024x600
  /// panel this takes 10% off where the type scale would take 20%; on a full-size display it
  /// leaves the panel where it was.
  double _panelSize(BuildContext context, double size) {
    final s = MediaQuery.of(context).size.shortestSide;
    final factor = (s / 750).clamp(0.5, 1.3);
    return (size * (1 + factor) / 2).roundToDouble();
  }

  /// The one type size the bottom bar is set in: the upcoming-event line, the empty-state
  /// text and the View schedule button. They sit on the same row, so they scale together, also
  /// with the text size chosen for the bottom toolbar.
  /// [EventLine] repeats this because it is a widget of its own.
  double _barFontSize(BuildContext context, bool isPhone, double scale) {
    return ((isPhone ? 16 : _sp(context, 20)) * scale).roundToDouble();
  }

  /// The calendar glyph beside "View schedule", kept in step with [_barFontSize] instead of
  /// staying at a fixed 24 while the label around it shrank.
  double _barIconSize(BuildContext context, bool isPhone, double scale) {
    return ((isPhone ? 20 : _sp(context, 24)) * scale).roundToDouble();
  }

  /// The space kept free between the header and the content, and between the content and the
  /// bottom bar. The title gives up lines before it eats into this (see [StatusTitle]).
  double _safeMargin(BuildContext context, bool isPhone) {
    return isPhone ? 12 : _sp(context, 24);
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

  /// The room name in the header corner, and the long-press target that reveals the admin
  /// actions when they are hidden.
  ///
  /// That long press is the only way back to the admin actions on a display that hides them.
  Widget _buildRoomName(BuildContext context, DashboardController controller, bool hideAdminActions) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onLongPressStart: (_) { if (hideAdminActions) controller.startLongPressTimer(); },
      onLongPressEnd: (_) { if (hideAdminActions) controller.cancelLongPressTimer(); },
      child: Text(
        controller.roomName,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: FontService.instance.getTextStyle(
          fontFamily: controller.currentFontFamily.value,
          fontSize: _sp(context, 28) * controller.roomNameScale,
          fontWeight: FontWeight.w500,
          color: TWColors.white,
        ),
      ),
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

  /// The top row: clock, stale-data warning, admin actions and the room name.
  ///
  /// [timelineToggle] adds the icon that opens and closes the side panel, which only the
  /// side-panel layout has.
  Widget _buildHeader(BuildContext context, DashboardController controller, bool isPhone, {bool timelineToggle = false}) {
    // The stale-data warning floats centred over the row instead of taking a slot in it, so it
    // never decides where the room name goes.
    return Stack(
      alignment: Alignment.center,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Obx(() => Text(
              formatTime(context, controller.time.value),
              style: FontService.instance.getTextStyle(
                fontFamily: controller.currentFontFamily.value,
                fontSize: _sp(context, 28) * controller.clockScale,
                fontWeight: FontWeight.w500,
                color: TWColors.white,
              ),
            )),
            const SizedBox(width: 16),
            // All the width the clock leaves, with the room name pinned to its right edge. The
            // name shortens itself only when even that is not enough: a long name at a large
            // text size beside a side panel.
            Expanded(
              child: Align(
                alignment: Alignment.centerRight,
                child: Obx(() {
                  final hide = controller.globalSettings.value?.hideAdminActions ?? false;
                  final showTemp = controller.showAdminActionsTemporarily.value;
                  final show = !hide || showTemp;
                  return Row(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      if (show) AdminActions(controller: controller),
                      if (show) const SizedBox(width: 15),
                      if (timelineToggle && show) ...[
                        Opacity(
                          opacity: 0.6,
                          child: GestureDetector(
                            onTap: () => setState(() => _timelineOpen = !_timelineOpen),
                            child: Icon(
                              _timelineOpen ? Icons.calendar_today : Icons.calendar_today_outlined,
                              color: Colors.white,
                              size: _sp(context, 24),
                            ),
                          ),
                        ),
                        const SizedBox(width: 15),
                      ],
                      Flexible(child: _buildRoomName(context, controller, hide)),
                    ],
                  );
                }),
              ),
            ),
          ],
        ),
        Obx(() => controller.isDataStale.value
            ? _buildStaleIndicator(context, isPhone, controller)
            : const SizedBox.shrink()),
      ],
    );
  }

  /// Logo, title, meeting details and the action buttons, centred in the height between the
  /// header and the bottom bar.
  ///
  /// The title is the one flexible child: everything else is laid out first and the title gets
  /// what is left, up to three lines. So a long meeting subject shortens itself instead of
  /// pushing the logo and the buttons into the header and the bottom bar.
  Widget _buildMainContent(
    BuildContext context,
    DashboardController controller,
    bool isPhone,
    double cornerRadius,
    bool isPortrait, {
    required bool panelBeside,
  }) {
    final space = controller.meetingInfoTimes != null ? (isPhone ? 5.0 : 10.0) : 0.0;

    return Column(
      mainAxisSize: MainAxisSize.max,
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Obx(() {
          final logoUrl = controller.globalSettings.value?.logoUrl;
          if (logoUrl == null) {
            return const SizedBox.shrink();
          }
          return Container(
            margin: EdgeInsets.only(bottom: isPhone ? 20 : 10),
            child: AuthenticatedImage(
              imageUrl: logoUrl,
              height: isPhone ? 24 : 36,
              fit: BoxFit.contain,
              placeholder: SizedBox(
                height: isPhone ? 24 : 36,
                child: Center(
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(TWColors.gray_300),
                  ),
                ),
              ),
              errorWidget: const SizedBox.shrink(),
            ),
          );
        }),
        SizedBox(height: space),
        Flexible(
          child: Obx(() {
            // Set a little tighter than the font's own line height, so a title of two or three
            // lines reads as one block instead of three loose rows.
            final size = _sp(context, 44) * controller.titleScale;
            return StatusTitle(
              text: controller.title,
              style: FontService.instance.getTextStyle(
                fontFamily: controller.currentFontFamily.value,
                fontSize: size,
                fontWeight: FontWeight.w700,
                color: Colors.white,
                height: 1.15,
                letterSpacing: -0.02 * size,
              ),
            );
          }),
        ),
        // More room under the title than above it: the details belong to the title, but at a
        // tight line height they otherwise read as a fourth line of it.
        SizedBox(height: space == 0 ? 0 : (isPhone ? 10.0 : _sp(context, 20))),
        MeetingMetaRow(
          controller: controller,
          isPhone: isPhone,
          cornerRadius: cornerRadius,
          panelBeside: panelBeside,
          isPortrait: isPortrait,
        ),
        if (controller.meetingInfoTimes == null) SizedBox(height: isPhone ? 5 : 10),
        if (controller.bookingEnabled || controller.checkInEnabled || controller.extendEnabled) ...[
          SizedBox(height: space),
          ActionPanel(
            controller: controller,
            isPhone: isPhone,
            cornerRadius: cornerRadius,
          ),
        ],
      ],
    );
  }

  /// The frosted bar along the bottom: the next meeting and the View schedule button.
  Widget _buildBottomBar(BuildContext context, DashboardController controller, bool isPhone, double cornerRadius) {
    final scale = controller.toolbarScale;
    final fontSize = _barFontSize(context, isPhone, scale);

    final iconSize = _barIconSize(context, isPhone, scale);
    final labelStyle = TextStyle(color: Colors.white, fontSize: fontSize, fontWeight: FontWeight.w500);
    final gap = isPhone ? 10.0 : _sp(context, 20);

    return FrostedPanel(
      key: const ValueKey('bottom-toolbar'),
      borderRadius: cornerRadius,
      blurIntensity: 18,
      padding: EdgeInsets.all(isPhone ? 12 : _sp(context, 20)),
      child: LayoutBuilder(builder: (context, constraints) {
        // The View schedule button keeps its label only while that leaves the next meeting
        // most of the bar. At a large text size in a narrow toolbar it becomes just its icon.
        final labelWidth = (TextPainter(
          text: TextSpan(text: 'view_schedule'.tr, style: labelStyle),
          textDirection: Directionality.of(context),
          textScaler: MediaQuery.textScalerOf(context),
          maxLines: 1,
        )..layout()).width;
        final showLabel = iconSize + 12 + labelWidth + 8 <= constraints.maxWidth * 0.4;

        return Row(
          children: [
            Expanded(
              child: controller.upcomingEvents.isNotEmpty
                ? EventLine(event: controller.upcomingEvents.first, scale: scale)
                : Text(
                    'no_upcoming_events'.tr,
                    style: TextStyle(color: TWColors.white, fontSize: fontSize, fontWeight: FontWeight.w500),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
            ),
            if (controller.calendarEnabled) ...[
              SizedBox(width: gap),
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
                        Icon(Icons.calendar_today_outlined, size: iconSize, color: Colors.white),
                        if (showLabel) ...[
                          const SizedBox(width: 12),
                          Text('view_schedule'.tr, style: labelStyle),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ],
        );
      }),
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
      body: Obx(() {
        if (controller.loading.value) {
          return Center(
            child: Spinner(size: 40, thickness: 4, color: AppTheme.platinum),
          );
        }

        final timelineMode = controller.timelineWidgetMode; // 'none' | 'side_panel' | 'inline' | 'full_panel'
        final timelineEnabled = timelineMode != 'none';
        final timelineWidth = isPhone ? 220.0 : _panelWidth(context, 300);
        final inlineTimelineWidth = isPhone ? 260.0 : _panelWidth(context, 324);
        final inlineTimelineMaxHeight = isPhone ? 330.0 : 484.0;
        final gap = isPhone ? 10.0 : 16.0;
        final portraitTimelineHeight = isPhone ? 200.0 : _panelSize(context, 260).clamp(200.0, 260.0);

        final safeMargin = _safeMargin(context, isPhone);

        final mainStack = Stack(
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _buildHeader(context, controller, isPhone, timelineToggle: timelineEnabled),
                SizedBox(height: safeMargin),
                Expanded(
                  child: _buildMainContent(
                    context, controller, isPhone, cornerRadius, isPortrait,
                    // mainStack serves both 'none' and 'side_panel'. Only the latter puts a panel
                    // beside the content, and only while it is open.
                    panelBeside: timelineMode == 'side_panel' && _timelineOpen,
                  ),
                ),
                SizedBox(height: safeMargin),
                _buildBottomBar(context, controller, isPhone, cornerRadius),
              ],
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
          final inlineContentWidget = _buildMainContent(
            context, controller, isPhone, cornerRadius, isPortrait,
            // The timeline sits beside the content in landscape and under it in portrait,
            // so this row never has the full width of the display.
            panelBeside: true,
          );

          final inlineChild = Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _buildHeader(context, controller, isPhone),
              SizedBox(height: safeMargin),
              // Middle: portrait → content above, timeline below; landscape → side by side
              Expanded(
                child: isPortrait
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(child: inlineContentWidget),
                        SizedBox(height: gap),
                        SizedBox(
                          height: portraitTimelineHeight,
                          child: DayTimelineWidget(
                            controller: controller,
                            isPhone: isPhone,
                            cornerRadius: cornerRadius,
                            frosted: true,
                          ),
                        ),
                      ],
                    )
                  : Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(child: inlineContentWidget),
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
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
              ),
              SizedBox(height: safeMargin),
              _buildBottomBar(context, controller, isPhone, cornerRadius),
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
        // Full-height timeline on the right (landscape) or below content (portrait);
        // main content (header + status + bottom bar) in a Column.
        if (timelineMode == 'full_panel') {

          final fullPanelHeaderRow = _buildHeader(context, controller, isPhone);

          final fullPanelContentWidget = _buildMainContent(
            context, controller, isPhone, cornerRadius, isPortrait,
            // Same as inline: the timeline always takes either the right side or the bottom.
            panelBeside: true,
          );

          final fullPanelBottomBar = _buildBottomBar(context, controller, isPhone, cornerRadius);

          final fullPanelTimeline = DayTimelineWidget(
            controller: controller,
            isPhone: isPhone,
            cornerRadius: cornerRadius,
            frosted: true,
          );

          final Widget fullPanelChild = isPortrait
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  fullPanelHeaderRow,
                  SizedBox(height: safeMargin),
                  Expanded(child: fullPanelContentWidget),
                  SizedBox(height: safeMargin),
                  SizedBox(height: portraitTimelineHeight, child: fullPanelTimeline),
                  SizedBox(height: gap),
                  fullPanelBottomBar,
                ],
              )
            : Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        fullPanelHeaderRow,
                        SizedBox(height: safeMargin),
                        Expanded(child: fullPanelContentWidget),
                        SizedBox(height: safeMargin),
                        fullPanelBottomBar,
                      ],
                    ),
                  ),
                  SizedBox(width: gap),
                  SizedBox(width: inlineTimelineWidth, child: fullPanelTimeline),
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
                        frosted: true,
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
