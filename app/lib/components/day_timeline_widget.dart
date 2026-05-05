import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/services/display_service.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class DayTimelineWidget extends StatefulWidget {
  final DashboardController controller;
  final bool isPhone;
  final double cornerRadius;

  const DayTimelineWidget({
    super.key,
    required this.controller,
    required this.isPhone,
    required this.cornerRadius,
  });

  @override
  State<DayTimelineWidget> createState() => _DayTimelineWidgetState();
}

class _DayTimelineWidgetState extends State<DayTimelineWidget> {
  DateTime _selectedDate = DateTime.now();
  List<EventModel> _events = [];
  bool _loading = false;

  static const int _startHour = 7;
  static const int _endHour = 21;
  static const int _totalMinutes = (_endHour - _startHour) * 60;

  @override
  void initState() {
    super.initState();
    _events = widget.controller.events.toList();
  }

  @override
  void didUpdateWidget(DayTimelineWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_isToday(_selectedDate)) {
      setState(() {
        _events = widget.controller.events.toList();
      });
    }
  }

  bool _isToday(DateTime date) {
    final now = DateTime.now();
    return date.year == now.year && date.month == now.month && date.day == now.day;
  }

  bool _isTomorrow(DateTime date) {
    final tomorrow = DateTime.now().add(const Duration(days: 1));
    return date.year == tomorrow.year && date.month == tomorrow.month && date.day == tomorrow.day;
  }

  bool _isYesterday(DateTime date) {
    final yesterday = DateTime.now().subtract(const Duration(days: 1));
    return date.year == yesterday.year && date.month == yesterday.month && date.day == yesterday.day;
  }

  String _formatSelectedDate() {
    if (_isToday(_selectedDate)) return 'timeline_today'.tr;
    if (_isTomorrow(_selectedDate)) return 'timeline_tomorrow'.tr;
    if (_isYesterday(_selectedDate)) return 'timeline_yesterday'.tr;
    return DateFormat('E d MMM', Get.locale?.toString()).format(_selectedDate);
  }

  Future<void> _loadEventsForDate(DateTime date) async {
    if (_isToday(date)) {
      setState(() {
        _selectedDate = date;
        _events = widget.controller.events.toList();
        _loading = false;
      });
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
      if (mounted) {
        setState(() {
          _events = events;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _prevDay() => _loadEventsForDate(_selectedDate.subtract(const Duration(days: 1)));
  void _nextDay() => _loadEventsForDate(_selectedDate.add(const Duration(days: 1)));

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) => Theme(
        data: ThemeData.dark().copyWith(
          colorScheme: const ColorScheme.dark(
            primary: AppTheme.orange,
            surface: Color(0xFF1E1E1E),
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) _loadEventsForDate(picked);
  }

  double _minutesToOffset(int minutes, double totalHeight) {
    final clamped = (minutes - _startHour * 60).clamp(0, _totalMinutes);
    return clamped / _totalMinutes * totalHeight;
  }

  @override
  Widget build(BuildContext context) {
    final fontSize = widget.isPhone ? 13.0 : 15.0;
    final headerFontSize = widget.isPhone ? 14.0 : 16.0;

    return FrostedPanel(
      borderRadius: widget.cornerRadius,
      blurIntensity: 18,
      padding: EdgeInsets.all(widget.isPhone ? 10 : 14),
      child: Column(
        children: [
          _buildHeader(headerFontSize),
          SizedBox(height: widget.isPhone ? 8 : 10),
          Expanded(
            child: _loading
                ? const Center(
                    child: CircularProgressIndicator(
                      color: AppTheme.orange,
                      strokeWidth: 2,
                    ),
                  )
                : _buildTimeline(fontSize),
          ),
        ],
      ),
    );
  }

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
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: fontSize,
                      fontWeight: FontWeight.w600,
                    ),
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  Icons.calendar_today_outlined,
                  color: TWColors.gray_300,
                  size: fontSize,
                ),
              ],
            ),
          ),
        ),
        _NavButton(icon: Icons.chevron_right, onTap: _nextDay),
      ],
    );
  }

  Widget _buildTimeline(double fontSize) {
    if (_events.isEmpty) {
      return Center(
        child: Text(
          'timeline_no_events'.tr,
          style: TextStyle(color: TWColors.gray_400, fontSize: fontSize),
        ),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final totalHeight = constraints.maxHeight;
        final labelWidth = widget.isPhone ? 36.0 : 42.0;
        final eventAreaWidth = constraints.maxWidth - labelWidth - 4;

        return Stack(
          children: [
            // Hour lines and labels
            ..._buildHourRows(totalHeight, labelWidth, eventAreaWidth, fontSize),
            // Current time indicator
            if (_isToday(_selectedDate)) _buildCurrentTimeIndicator(totalHeight, labelWidth, eventAreaWidth),
            // Events
            ..._buildEventBlocks(totalHeight, labelWidth, eventAreaWidth, fontSize),
          ],
        );
      },
    );
  }

  List<Widget> _buildHourRows(double totalHeight, double labelWidth, double eventAreaWidth, double fontSize) {
    final widgets = <Widget>[];
    for (int h = _startHour; h <= _endHour; h++) {
      final offsetY = _minutesToOffset(h * 60, totalHeight);
      widgets.add(
        Positioned(
          top: offsetY,
          left: 0,
          width: labelWidth,
          child: Transform.translate(
            offset: const Offset(0, -8),
            child: Text(
              '${h.toString().padLeft(2, '0')}:00',
              style: TextStyle(
                color: TWColors.gray_400,
                fontSize: fontSize - 1,
                height: 1,
              ),
            ),
          ),
        ),
      );
      widgets.add(
        Positioned(
          top: offsetY,
          left: labelWidth + 4,
          width: eventAreaWidth,
          child: Container(
            height: 0.5,
            color: Colors.white.withValues(alpha: 0.12),
          ),
        ),
      );
    }
    return widgets;
  }

  Widget _buildCurrentTimeIndicator(double totalHeight, double labelWidth, double eventAreaWidth) {
    final now = DateTime.now();
    final currentMinutes = now.hour * 60 + now.minute;
    if (currentMinutes < _startHour * 60 || currentMinutes > _endHour * 60) {
      return const SizedBox.shrink();
    }
    final offsetY = _minutesToOffset(currentMinutes, totalHeight);
    return Positioned(
      top: offsetY - 1,
      left: labelWidth,
      width: eventAreaWidth + 4,
      child: Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: const BoxDecoration(
              color: TWColors.red_400,
              shape: BoxShape.circle,
            ),
          ),
          Expanded(
            child: Container(
              height: 2,
              color: TWColors.red_400,
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _buildEventBlocks(double totalHeight, double labelWidth, double eventAreaWidth, double fontSize) {
    final widgets = <Widget>[];
    final groups = _groupOverlappingEvents(_events);

    for (final group in groups) {
      final colCount = group.length;
      for (int i = 0; i < group.length; i++) {
        final event = group[i];
        final startMinutes = event.start.hour * 60 + event.start.minute;
        final endMinutes = event.end.hour * 60 + event.end.minute;
        final topY = _minutesToOffset(startMinutes, totalHeight);
        final bottomY = _minutesToOffset(endMinutes, totalHeight);
        final blockHeight = (bottomY - topY).clamp(14.0, double.infinity);

        final colWidth = (eventAreaWidth - (colCount - 1) * 3) / colCount;
        final leftX = labelWidth + 4 + i * (colWidth + 3);

        widgets.add(
          Positioned(
            top: topY,
            left: leftX,
            width: colWidth,
            height: blockHeight,
            child: _EventBlock(
              event: event,
              fontSize: fontSize,
            ),
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
        final overlaps = group.any((e) => e.start.isBefore(sorted[j].end) && sorted[j].start.isBefore(e.end));
        if (overlaps) {
          group.add(sorted[j]);
          used.add(j);
        }
      }
      groups.add(group);
    }

    return groups;
  }
}

class _EventBlock extends StatelessWidget {
  final EventModel event;
  final double fontSize;

  const _EventBlock({required this.event, required this.fontSize});

  @override
  Widget build(BuildContext context) {
    final startStr = formatTime(context, event.start);
    final endStr = formatTime(context, event.end);
    final durationMinutes = event.end.difference(event.start).inMinutes;
    final showEndTime = durationMinutes >= 30;

    return ClipRRect(
      borderRadius: BorderRadius.circular(5),
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.orange.withValues(alpha: 0.85),
          borderRadius: BorderRadius.circular(5),
          border: Border(
            left: BorderSide(color: AppTheme.orange, width: 2),
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              event.summary,
              style: TextStyle(
                color: Colors.white,
                fontSize: fontSize - 1,
                fontWeight: FontWeight.w600,
                height: 1.2,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            if (durationMinutes >= 20) ...[
              const SizedBox(height: 1),
              Text(
                showEndTime ? '$startStr – $endStr' : startStr,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.8),
                  fontSize: fontSize - 2,
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
