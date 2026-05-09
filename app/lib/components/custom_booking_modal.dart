import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/components/solid_button.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class CustomBookingModal extends StatefulWidget {
  final dynamic controller;
  final bool isPhone;
  final double cornerRadius;

  const CustomBookingModal({
    super.key,
    required this.controller,
    required this.isPhone,
    required this.cornerRadius,
  });

  @override
  State<CustomBookingModal> createState() => _CustomBookingModalState();
}

class _CustomBookingModalState extends State<CustomBookingModal> {
  late TextEditingController _titleController;
  late TextEditingController _descriptionController;
  late TextEditingController _attendeesController;
  late DateTime _selectedDate;
  late DateTime _startTime;
  late DateTime _endTime;
  DateTime? _nextMeetingStart;
  bool _isLoadingEvents = false;

  bool get _isToday {
    final now = DateTime.now();
    return _selectedDate.year == now.year &&
        _selectedDate.month == now.month &&
        _selectedDate.day == now.day;
  }

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _selectedDate = DateTime(now.year, now.month, now.day);

    _initTimesForDate(isFirstInit: true);

    _titleController = TextEditingController(text: 'reserved'.tr);
    _descriptionController = TextEditingController();
    _attendeesController = TextEditingController();
  }

  void _initTimesForDate({bool isFirstInit = false}) {
    final now = DateTime.now();

    if (_isToday) {
      // Today: start from now or after current event
      final currentEvent = widget.controller.currentEvent;
      if (isFirstInit && currentEvent != null && currentEvent.end.isAfter(now)) {
        _startTime = currentEvent.end;
      } else {
        _startTime = now;
      }

      // Default end: 1 hour from start, or capped at next meeting
      final upcomingEvents = widget.controller.upcomingEvents as List<EventModel>;
      final nextMeetingAfterStart = upcomingEvents
          .where((e) => e.start.isAfter(_startTime))
          .toList()
        ..sort((a, b) => a.start.compareTo(b.start));

      if (nextMeetingAfterStart.isNotEmpty) {
        _nextMeetingStart = nextMeetingAfterStart.first.start;
        final oneHourFromStart = _startTime.add(const Duration(hours: 1));
        _endTime = _nextMeetingStart!.isBefore(oneHourFromStart)
            ? _nextMeetingStart!
            : oneHourFromStart;
      } else {
        _nextMeetingStart = null;
        _endTime = _startTime.add(const Duration(hours: 1));
      }
    } else {
      // Future date: default 08:00 – 09:00
      _startTime = DateTime(_selectedDate.year, _selectedDate.month, _selectedDate.day, 8, 0);
      _endTime = DateTime(_selectedDate.year, _selectedDate.month, _selectedDate.day, 9, 0);
      _nextMeetingStart = null;
    }

    // Ensure end > start
    if (!_endTime.isAfter(_startTime)) {
      _endTime = _startTime.add(const Duration(minutes: 1));
    }
  }

  Future<void> _loadEventsForDate(DateTime date) async {
    setState(() => _isLoadingEvents = true);
    try {
      final events = await widget.controller.getEventsForDate(date) as List<EventModel>;
      final eventsAfterStart = events
          .where((e) => e.start.isAfter(_startTime))
          .toList()
        ..sort((a, b) => a.start.compareTo(b.start));

      final next = eventsAfterStart.isNotEmpty ? eventsAfterStart.first.start : null;

      setState(() {
        _nextMeetingStart = next;
        if (next != null) {
          final oneHourFromStart = _startTime.add(const Duration(hours: 1));
          _endTime = next.isBefore(oneHourFromStart) ? next : oneHourFromStart;
          if (!_endTime.isAfter(_startTime)) {
            _endTime = _startTime.add(const Duration(minutes: 1));
          }
        }
      });
    } catch (_) {
      // Events couldn't be loaded; leave _nextMeetingStart as-is
    } finally {
      setState(() => _isLoadingEvents = false);
    }
  }

  Future<void> _selectDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 5),
    );
    if (picked == null) return;

    setState(() {
      _selectedDate = picked;
      _initTimesForDate();
    });

    if (!_isToday) {
      await _loadEventsForDate(picked);
    }
  }

  Future<void> _selectStartTime() async {
    final now = DateTime.now();
    final initialTime = TimeOfDay.fromDateTime(_startTime);

    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: initialTime,
    );
    if (picked == null) return;

    var selected = DateTime(
      _selectedDate.year,
      _selectedDate.month,
      _selectedDate.day,
      picked.hour,
      picked.minute,
    );

    // For today only: clamp to now
    if (_isToday && selected.isBefore(now)) {
      selected = now;
    }

    bool endWasReset = false;
    setState(() {
      _startTime = selected;
      if (!_endTime.isAfter(_startTime)) {
        _endTime = _startTime.add(const Duration(hours: 1));
        endWasReset = true;
      }
    });
    if (endWasReset) {
      Toast.showError('end_time_adjusted'.tr);
    }
  }

  Future<void> _selectEndTime() async {
    final minEnd = _startTime.add(const Duration(minutes: 1));
    final initialTime = _endTime.isBefore(minEnd)
        ? TimeOfDay.fromDateTime(minEnd)
        : TimeOfDay.fromDateTime(_endTime);

    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: initialTime,
    );
    if (picked == null) return;

    var selected = DateTime(
      _selectedDate.year,
      _selectedDate.month,
      _selectedDate.day,
      picked.hour,
      picked.minute,
    );

    if (!selected.isAfter(_startTime)) {
      selected = minEnd;
      Toast.showError('end_time_must_be_after_start'.tr);
    }

    setState(() => _endTime = selected);
  }

  void _setStartTimeToNow() {
    final now = DateTime.now();
    setState(() {
      _startTime = now;
      final oneHour = _startTime.add(const Duration(hours: 1));
      if (_nextMeetingStart != null && _nextMeetingStart!.isBefore(oneHour)) {
        _endTime = _nextMeetingStart!.isAfter(_startTime)
            ? _nextMeetingStart!
            : _startTime.add(const Duration(minutes: 1));
      } else if (_endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)) {
        _endTime = oneHour;
      }
      if (!_endTime.isAfter(_startTime)) {
        _endTime = _startTime.add(const Duration(minutes: 1));
      }
    });
  }

  void _setEndTimeToMax() {
    if (_nextMeetingStart == null) return;
    setState(() {
      final now = DateTime.now();
      if (_isToday && _startTime.isBefore(now)) _startTime = now;
      _endTime = _nextMeetingStart!.isAfter(_startTime)
          ? _nextMeetingStart!
          : _startTime.add(const Duration(minutes: 1));
    });
  }

  List<String> _parseAttendees() {
    return _attendeesController.text
        .split(',')
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  }

  void _bookCustom() {
    if (_titleController.text.trim().isEmpty) return;

    final now = DateTime.now();

    // For today: clamp start to now
    final clampedStart = (_isToday && _startTime.isBefore(now)) ? now : _startTime;

    // Ensure end > start by at least 1 minute
    final minEnd = clampedStart.add(const Duration(minutes: 1));
    final finalEnd = _endTime.isBefore(minEnd) ? minEnd : _endTime;

    final description = _descriptionController.text.trim();
    final attendees = _parseAttendees();

    widget.controller.bookCustom(
      _titleController.text.trim(),
      clampedStart,
      finalEnd,
      description: description.isNotEmpty ? description : null,
      attendees: attendees.isNotEmpty ? attendees : null,
    );

    Navigator.of(context).pop();
  }

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final tomorrow = today.add(const Duration(days: 1));
    final d = DateTime(date.year, date.month, date.day);
    if (d == today) return 'timeline_today'.tr;
    if (d == tomorrow) return 'timeline_tomorrow'.tr;
    return '${date.day}-${date.month}-${date.year}';
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _attendeesController.dispose();
    super.dispose();
  }

  Widget _buildLabel(String text) => Text(
        text,
        style: TextStyle(
          color: AppTheme.platinum,
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
      );

  Widget _buildTextField(TextEditingController controller, {int maxLines = 1, String? hint}) => TextField(
        controller: controller,
        maxLines: maxLines,
        style: const TextStyle(color: Colors.white, fontSize: 17),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: TWColors.gray_500),
          enabledBorder: OutlineInputBorder(
            borderSide: BorderSide(color: TWColors.gray_500),
            borderRadius: BorderRadius.circular(8),
          ),
          focusedBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: Colors.white),
            borderRadius: BorderRadius.circular(8),
          ),
          filled: true,
          fillColor: TWColors.gray_800.withOpacity(0.5),
        ),
      );

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Center(
        child: SizedBox(
          width: 800,
          child: FrostedPanel(
            borderRadius: 20,
            blurIntensity: 18,
            padding: const EdgeInsets.symmetric(horizontal: 0, vertical: 0),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Header
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 20, 26, 20),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'custom_booking'.tr,
                            style: TextStyle(
                              color: AppTheme.platinum,
                              fontSize: 22,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.of(context).pop(),
                          icon: Icon(Icons.close, color: AppTheme.platinum, size: 28),
                          splashRadius: 22,
                        ),
                      ],
                    ),
                  ),

                  // Date picker
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                    child: SpaceCol(
                      spaceBetween: 8,
                      children: [
                        _buildLabel('booking_date'.tr),
                        GestureDetector(
                          onTap: _selectDate,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            decoration: BoxDecoration(
                              color: TWColors.gray_800.withOpacity(0.5),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: TWColors.gray_500),
                            ),
                            child: Row(
                              children: [
                                Icon(Icons.calendar_today, color: AppTheme.platinum, size: 18),
                                const SizedBox(width: 10),
                                Text(
                                  _formatDate(_selectedDate),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  // Meeting title
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                    child: SpaceCol(
                      spaceBetween: 8,
                      children: [
                        _buildLabel('meeting_title'.tr),
                        _buildTextField(_titleController),
                      ],
                    ),
                  ),

                  // Start and End time
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                    child: Row(
                      children: [
                        // Start time
                        Expanded(
                          child: SpaceCol(
                            spaceBetween: 8,
                            children: [
                              _buildLabel('start_time'.tr),
                              Row(
                                children: [
                                  Expanded(
                                    child: GestureDetector(
                                      onTap: _selectStartTime,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                        decoration: BoxDecoration(
                                          color: TWColors.gray_800.withOpacity(0.5),
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: TWColors.gray_500),
                                        ),
                                        child: Text(
                                          formatTime(context, _startTime),
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 15,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  if (_isToday) ...[
                                    const SizedBox(width: 8),
                                    SolidButton(
                                      text: 'now',
                                      onPressed: _setStartTimeToNow,
                                      fontSize: 15,
                                    ),
                                  ],
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 16),
                        // End time
                        Expanded(
                          child: SpaceCol(
                            spaceBetween: 8,
                            children: [
                              _buildLabel('end_time'.tr),
                              Row(
                                children: [
                                  Expanded(
                                    child: GestureDetector(
                                      onTap: _selectEndTime,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                        decoration: BoxDecoration(
                                          color: TWColors.gray_800.withAlpha(128),
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: TWColors.gray_500),
                                        ),
                                        child: Text(
                                          formatTime(context, _endTime),
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 15,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  _isLoadingEvents
                                      ? const SizedBox(
                                          width: 32,
                                          height: 32,
                                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                        )
                                      : SolidButton(
                                          text: 'max',
                                          onPressed: _nextMeetingStart != null ? _setEndTimeToMax : null,
                                          fontSize: 15,
                                        ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  // Description
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                    child: SpaceCol(
                      spaceBetween: 8,
                      children: [
                        _buildLabel('booking_description'.tr),
                        _buildTextField(_descriptionController, maxLines: 3),
                      ],
                    ),
                  ),

                  // Attendees
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                    child: SpaceCol(
                      spaceBetween: 8,
                      children: [
                        _buildLabel('booking_attendees'.tr),
                        _buildTextField(_attendeesController, hint: 'jan@bedrijf.nl, sarah@bedrijf.nl'),
                      ],
                    ),
                  ),

                  // Action buttons
                  Padding(
                    padding: const EdgeInsets.fromLTRB(26, 8, 26, 20),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        SolidButton(
                          text: 'close',
                          onPressed: () => Navigator.of(context).pop(),
                          fontSize: 17,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                        ),
                        const SizedBox(width: 12),
                        SolidButton(
                          text: 'book',
                          onPressed: _bookCustom,
                          fontSize: 17,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
