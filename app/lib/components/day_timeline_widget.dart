import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/services/display_service.dart';
import 'package:tailwind_components/tailwind_components.dart';

// Soft blue used for event blocks (less harsh than orange on a dark background).
const _kEventColor = Color(0xFF4A86C8);

class DayTimelineWidget extends StatefulWidget {
  final DashboardController controller;
  final bool isPhone;
  final double cornerRadius;
  final bool frosted;

  const DayTimelineWidget({
    super.key,
    required this.controller,
    required this.isPhone,
    required this.cornerRadius,
    this.frosted = false,
  });

  @override
  State<DayTimelineWidget> createState() => _DayTimelineWidgetState();
}

class _DayTimelineWidgetState extends State<DayTimelineWidget> {
  DateTime _selectedDate = DateTime.now();
  List<EventModel> _otherDayEvents = [];
  bool _loading = false;

  // Auto-scroll state
  late ScrollController _scrollController;
  bool _userHasScrolled = false;
  Timer? _autoScrollTimer;

  // Full 24-hour timeline with fixed pixel height per hour.
  static const int _startHour = 0;
  static const int _endHour = 24;
  static const double _hourHeight = 64.0; // px per hour
  static const double _timelinePadding = 28.0; // extra space above 00:00 and below final 00:00

  double get _totalHeight => (_endHour - _startHour) * _hourHeight + _timelinePadding * 2;

  // ─── Lifecycle ────────────────────────────────────────────────────────────

  @override
  void initState() {
    super.initState();
    _scrollController = ScrollController();
    // Scroll after first frame so the viewport dimensions are known.
    WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToCurrentTime());
    // Keep current time centred once per minute (unless the user has scrolled).
    _autoScrollTimer = Timer.periodic(const Duration(minutes: 1), (_) {
      if (!_userHasScrolled) _scrollToCurrentTime();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _autoScrollTimer?.cancel();
    super.dispose();
  }

  // ─── Auto-scroll ──────────────────────────────────────────────────────────

  void _scrollToCurrentTime() {
    if (!_isToday(_selectedDate)) return;
    if (_userHasScrolled) return;
    if (!_scrollController.hasClients) return;

    final now = DateTime.now();
    final offsetY = _minutesToOffset(now.hour * 60 + now.minute);
    final viewport = _scrollController.position.viewportDimension;
    final maxExtent = _scrollController.position.maxScrollExtent;
    final target = (offsetY - viewport / 2).clamp(0.0, maxExtent);

    _scrollController.animateTo(
      target,
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeOut,
    );
  }

  // ─── Date helpers ─────────────────────────────────────────────────────────

  bool _isToday(DateTime date) {
    final now = DateTime.now();
    return date.year == now.year && date.month == now.month && date.day == now.day;
  }

  bool _isTomorrow(DateTime date) {
    final t = DateTime.now().add(const Duration(days: 1));
    return date.year == t.year && date.month == t.month && date.day == t.day;
  }

  bool _isYesterday(DateTime date) {
    final y = DateTime.now().subtract(const Duration(days: 1));
    return date.year == y.year && date.month == y.month && date.day == y.day;
  }

  String _formatSelectedDate() {
    if (_isToday(_selectedDate)) return 'timeline_today'.tr;
    if (_isTomorrow(_selectedDate)) return 'timeline_tomorrow'.tr;
    if (_isYesterday(_selectedDate)) return 'timeline_yesterday'.tr;
    return DateFormat('E d MMM', Get.locale?.toString()).format(_selectedDate);
  }

  // ─── Navigation ───────────────────────────────────────────────────────────

  Future<void> _loadEventsForDate(DateTime date) async {
    if (_isToday(date)) {
      setState(() {
        _selectedDate = date;
        _userHasScrolled = false;
      });
      // Scroll back to current time after frame
      WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToCurrentTime());
      return;
    }
    setState(() {
      _selectedDate = date;
      _loading = true;
    });
    try {
      final events = await DisplayService.instance.getEventsForDate(
        widget.controller.displayId.value,
        date,
      );
      if (mounted) setState(() { _otherDayEvents = events; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _prevDay() => _loadEventsForDate(_selectedDate.subtract(const Duration(days: 1)));
  void _nextDay() => _loadEventsForDate(_selectedDate.add(const Duration(days: 1)));

  Future<void> _pickDate() async {
    // Use GetX's root overlay context so the picker is not affected by
    // local widget-tree rebuilds (e.g. the outer Obx refreshing every 60 s).
    final ctx = Get.overlayContext ?? context;
    final picked = await showDatePicker(
      context: ctx,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) => Theme(
        data: ThemeData.dark().copyWith(
          colorScheme: const ColorScheme.dark(
            primary: _kEventColor,
            surface: Color(0xFF1E1E1E),
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) _loadEventsForDate(picked);
  }

  // ─── Position helper ──────────────────────────────────────────────────────

  double _minutesToOffset(int minutes) {
    final clamped = (minutes - _startHour * 60).clamp(0, (_endHour - _startHour) * 60);
    return clamped / 60 * _hourHeight + _timelinePadding;
  }

  // ─── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final fontSize = widget.isPhone ? 15.0 : 17.0;

    return FrostedPanel(
      borderRadius: widget.cornerRadius,
      blurIntensity: widget.frosted ? 18 : 0,
      backgroundColor: widget.frosted ? const Color(0x14FFFFFF) : const Color(0xFF1C1C1C),
      padding: EdgeInsets.all(widget.isPhone ? 10 : 14),
      child: Column(
        children: [
          _buildHeader(fontSize),
          SizedBox(height: widget.isPhone ? 8 : 10),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: _kEventColor, strokeWidth: 2))
                : _isToday(_selectedDate)
                    ? Obx(() {
                        final len = widget.controller.events.length;
                        final events = len > 0
                            ? List<EventModel>.from(widget.controller.events)
                            : <EventModel>[];
                        return _buildScrollableTimeline(events, fontSize);
                      })
                    : _buildScrollableTimeline(_otherDayEvents, fontSize),
          ),
        ],
      ),
    );
  }

  // ─── Header ───────────────────────────────────────────────────────────────

  Widget _buildHeader(double fontSize) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        _NavButton(icon: Icons.chevron_left, onTap: _prevDay),
        Expanded(
          child: GestureDetector(
            onTap: _pickDate,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Flexible(
                  child: Text(
                    _formatSelectedDate(),
                    style: TextStyle(color: Colors.white, fontSize: fontSize, fontWeight: FontWeight.w600),
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(width: 4),
                Icon(Icons.calendar_today_outlined, color: TWColors.gray_300, size: fontSize),
              ],
            ),
          ),
        ),
        _NavButton(icon: Icons.chevron_right, onTap: _nextDay),
      ],
    );
  }

  // ─── Scrollable timeline ──────────────────────────────────────────────────

  Widget _buildScrollableTimeline(List<EventModel> events, double fontSize) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final labelWidth = widget.isPhone ? 38.0 : 44.0;
        final eventAreaWidth = constraints.maxWidth - labelWidth - 6;

        return Stack(
          children: [
            NotificationListener<ScrollNotification>(
              onNotification: (notification) {
                // Only mark as user-scrolled when drag starts (not programmatic scroll).
                if (notification is ScrollStartNotification &&
                    notification.dragDetails != null) {
                  _userHasScrolled = true;
                }
                return false;
              },
              child: SingleChildScrollView(
                controller: _scrollController,
                physics: const ClampingScrollPhysics(),
                child: SizedBox(
                  height: _totalHeight,
                  child: Stack(
                    clipBehavior: Clip.hardEdge,
                    children: [
                      ..._buildHourRows(labelWidth, eventAreaWidth, fontSize),
                      if (_isToday(_selectedDate))
                        _buildCurrentTimeIndicator(labelWidth, eventAreaWidth),
                      ..._buildEventBlocks(events, labelWidth, eventAreaWidth, fontSize),
                    ],
                  ),
                ),
              ),
            ),
            if (events.isEmpty)
              Center(
                child: Text(
                  'timeline_no_events'.tr,
                  style: TextStyle(color: TWColors.gray_500, fontSize: fontSize - 1),
                ),
              ),
          ],
        );
      },
    );
  }

  // ─── Hour grid ────────────────────────────────────────────────────────────

  List<Widget> _buildHourRows(double labelWidth, double eventAreaWidth, double fontSize) {
    final widgets = <Widget>[];
    for (int h = _startHour; h <= _endHour; h++) {
      final offsetY = _minutesToOffset(h * 60);
      widgets.add(
        Positioned(
          top: offsetY,
          left: 0,
          width: labelWidth,
          child: Transform.translate(
            offset: const Offset(0, -8),
            child: Text(
              '${(h == _endHour ? 0 : h).toString().padLeft(2, '0')}:00',
              style: TextStyle(color: TWColors.gray_400, fontSize: fontSize - 3, height: 1),
            ),
          ),
        ),
      );
      widgets.add(
        Positioned(
          top: offsetY,
          left: labelWidth + 6,
          width: eventAreaWidth,
          child: CustomPaint(
            size: Size(eventAreaWidth, 1),
            painter: _DashedLinePainter(color: Colors.white.withValues(alpha: 0.15)),
          ),
        ),
      );
    }
    return widgets;
  }

  // ─── Current time indicator ───────────────────────────────────────────────

  Widget _buildCurrentTimeIndicator(double labelWidth, double eventAreaWidth) {
    final now = DateTime.now();
    final offsetY = _minutesToOffset(now.hour * 60 + now.minute);
    return Positioned(
      top: offsetY - 4,
      left: labelWidth + 2,
      width: eventAreaWidth + 4,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: const BoxDecoration(color: TWColors.red_400, shape: BoxShape.circle),
          ),
          Expanded(child: Container(height: 2, color: TWColors.red_400)),
        ],
      ),
    );
  }

  // ─── Event blocks ─────────────────────────────────────────────────────────

  List<Widget> _buildEventBlocks(
    List<EventModel> events,
    double labelWidth,
    double eventAreaWidth,
    double fontSize,
  ) {
    if (events.isEmpty) return [];
    final widgets = <Widget>[];
    final groups = _groupOverlappingEvents(events);

    for (final group in groups) {
      final colCount = group.length;
      for (int i = 0; i < group.length; i++) {
        final event = group[i];
        final startMin = event.start.hour * 60 + event.start.minute;
        final endMin = event.end.hour * 60 + event.end.minute;
        final topY = _minutesToOffset(startMin);
        final bottomY = _minutesToOffset(endMin);
        final blockHeight = (bottomY - topY).clamp(16.0, double.infinity);
        final colWidth = (eventAreaWidth - (colCount - 1) * 3) / colCount;
        final leftX = labelWidth + 6 + i * (colWidth + 3);

        widgets.add(
          Positioned(
            top: topY,
            left: leftX,
            width: colWidth,
            height: blockHeight,
            child: _EventBlock(event: event, fontSize: fontSize),
          ),
        );
      }
    }
    return widgets;
  }

  List<List<EventModel>> _groupOverlappingEvents(List<EventModel> events) {
    final sorted = [...events]..sort((a, b) => a.start.compareTo(b.start));
    final groups = <List<EventModel>>[];
    final used = <int>{};
    for (int i = 0; i < sorted.length; i++) {
      if (used.contains(i)) continue;
      final group = [sorted[i]];
      used.add(i);
      for (int j = i + 1; j < sorted.length; j++) {
        if (used.contains(j)) continue;
        if (group.any((e) => e.start.isBefore(sorted[j].end) && sorted[j].start.isBefore(e.end))) {
          group.add(sorted[j]);
          used.add(j);
        }
      }
      groups.add(group);
    }
    return groups;
  }
}

// ─── Dashed line ─────────────────────────────────────────────────────────────

class _DashedLinePainter extends CustomPainter {
  final Color color;
  const _DashedLinePainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color..strokeWidth = 0.8;
    const dash = 4.0, gap = 4.0;
    double x = 0;
    while (x < size.width) {
      canvas.drawLine(Offset(x, 0), Offset((x + dash).clamp(0, size.width), 0), paint);
      x += dash + gap;
    }
  }

  @override
  bool shouldRepaint(_DashedLinePainter old) => old.color != color;
}

// ─── Event block ─────────────────────────────────────────────────────────────

class _EventBlock extends StatelessWidget {
  final EventModel event;
  final double fontSize;

  const _EventBlock({required this.event, required this.fontSize});

  @override
  Widget build(BuildContext context) {
    final startStr = formatTime(context, event.start);
    final endStr = formatTime(context, event.end);
    final durationMinutes = event.end.difference(event.start).inMinutes;

    return ClipRRect(
      borderRadius: BorderRadius.circular(4),
      child: Container(
        decoration: BoxDecoration(
          color: _kEventColor.withValues(alpha: 0.75),
          borderRadius: BorderRadius.circular(4),
          border: Border(left: BorderSide(color: _kEventColor, width: 2.5)),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Flexible(
              child: Text(
                event.summary,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: fontSize - 2,
                  fontWeight: FontWeight.w600,
                  height: 1.2,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (durationMinutes >= 30) ...[
              const SizedBox(height: 1),
              Text(
                '$startStr – $endStr',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.75),
                  fontSize: fontSize - 3,
                  height: 1.1,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ─── Nav button ──────────────────────────────────────────────────────────────

class _NavButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _NavButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(4),
        child: Icon(icon, color: Colors.white, size: 20),
      ),
    );
  }
}
