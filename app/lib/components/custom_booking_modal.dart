import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/components/solid_button.dart';
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
  late DateTime _startTime;
  late DateTime _endTime;
  DateTime? _nextMeetingStart;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    
    // Clamp start time to now if in the past
    _startTime = now;
    
    // Calculate default end time: 1 hour from now, or until next meeting if available
    final upcomingEvents = widget.controller.upcomingEvents;
    if (upcomingEvents.isNotEmpty) {
      _nextMeetingStart = upcomingEvents.first.start;
      final oneHourFromStart = _startTime.add(const Duration(hours: 1));
      _endTime = (_nextMeetingStart != null && _nextMeetingStart!.isBefore(oneHourFromStart))
          ? _nextMeetingStart!
          : oneHourFromStart;
    } else {
      _endTime = _startTime.add(const Duration(hours: 1));
    }
    
    // Ensure end time is strictly after start time
    _endTime = _endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)
        ? _startTime.add(const Duration(minutes: 1))
        : _endTime;
    
    _titleController = TextEditingController(text: 'reserved'.tr);
  }

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _selectStartTime() async {
    final now = DateTime.now();
    // Ensure initial time is not in the past
    final initialTime = _startTime.isBefore(now) 
        ? TimeOfDay.fromDateTime(now) 
        : TimeOfDay.fromDateTime(_startTime);
    
    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: initialTime,
    );
    if (picked != null) {
      final selectedDateTime = DateTime(
        now.year,
        now.month,
        now.day,
        picked.hour,
        picked.minute,
      );
      
      // Prevent selecting time in the past
      final validStartTime = selectedDateTime.isBefore(now) ? now : selectedDateTime;
      
      setState(() {
        _startTime = validStartTime;
        // Ensure end time is after start time
        if (_endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)) {
          _endTime = _startTime.add(const Duration(hours: 1));
        }
      });
    }
  }

  Future<void> _selectEndTime() async {
    final now = DateTime.now();
    // Ensure initial time is not in the past and is after start time
    final minEndTime = _startTime.add(const Duration(minutes: 1));
    final initialEndTime = _endTime.isBefore(minEndTime) 
        ? TimeOfDay.fromDateTime(minEndTime) 
        : TimeOfDay.fromDateTime(_endTime);
    
    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: initialEndTime,
    );
    if (picked != null) {
      final selectedDateTime = DateTime(
        now.year,
        now.month,
        now.day,
        picked.hour,
        picked.minute,
      );
      
      // Prevent selecting time in the past or before start time
      final minValidTime = minEndTime.isAfter(now) ? minEndTime : now.add(const Duration(minutes: 1));
      final validEndTime = selectedDateTime.isBefore(minValidTime) 
          ? minValidTime 
          : (selectedDateTime.isAfter(_startTime) ? selectedDateTime : minValidTime);
      
      setState(() {
        _endTime = validEndTime;
      });
    }
  }

  void _setStartTimeToNow() {
    setState(() {
      final now = DateTime.now();
      // Clamp start time to now if in the past
      _startTime = now;
      
      // Ensure end time is strictly after start time
      final oneHourFromStart = _startTime.add(const Duration(hours: 1));
      if (_nextMeetingStart != null && _nextMeetingStart!.isBefore(oneHourFromStart)) {
        _endTime = _nextMeetingStart!.isAfter(_startTime) 
            ? _nextMeetingStart! 
            : _startTime.add(const Duration(minutes: 1));
      } else {
        _endTime = _endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)
            ? oneHourFromStart
            : _endTime;
      }
      
      // Final check: ensure end time is strictly after start time
      _endTime = _endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)
          ? _startTime.add(const Duration(minutes: 1))
          : _endTime;
    });
  }

  void _setEndTimeToMax() {
    if (_nextMeetingStart != null) {
      setState(() {
        final now = DateTime.now();
        // Clamp start time to now if in the past
        _startTime = _startTime.isBefore(now) ? now : _startTime;
        
        // Set end time to next meeting start, but ensure it's strictly after start time
        final oneHourFromStart = _startTime.add(const Duration(hours: 1));
        _endTime = (_nextMeetingStart != null && _nextMeetingStart!.isBefore(oneHourFromStart))
            ? _nextMeetingStart!
            : oneHourFromStart;
        
        // Ensure end time is strictly after start time
        _endTime = _endTime.isBefore(_startTime) || _endTime.isAtSameMomentAs(_startTime)
            ? _startTime.add(const Duration(minutes: 1))
            : _endTime;
      });
    }
  }

  void _bookCustom() {
    if (_titleController.text.trim().isEmpty) {
      return;
    }
    
    // Clamp and normalize times before sending to backend
    final now = DateTime.now();
    
    // Clamp start time to now if in the past
    final clampedStartTime = _startTime.isBefore(now) ? now : _startTime;
    
    // Preserve next meeting clipping behavior if applicable
    final oneHourFromStart = clampedStartTime.add(const Duration(hours: 1));
    final endTimeWithClipping = (_nextMeetingStart != null && _nextMeetingStart!.isBefore(oneHourFromStart))
        ? _nextMeetingStart!
        : oneHourFromStart;
    
    // Ensure end time is strictly after start time (at least 1 minute after)
    // Use max of endTimeWithClipping or minimum valid end time
    final minValidEndTime = clampedStartTime.add(const Duration(minutes: 1));
    final finalEndTime = endTimeWithClipping.isBefore(minValidEndTime) || endTimeWithClipping.isAtSameMomentAs(minValidEndTime)
        ? minValidEndTime
        : endTimeWithClipping;
    
    widget.controller.bookCustom(
      _titleController.text.trim(),
      clampedStartTime,
      finalEndTime,
    );
    
    Navigator.of(context).pop();
  }

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
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Title and close button
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
                // Meeting title input
                Padding(
                  padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                  child: SpaceCol(
                    spaceBetween: 8,
                    children: [
                      Text(
                        'meeting_title'.tr,
                        style: TextStyle(
                          color: AppTheme.platinum,
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      TextField(
                        controller: _titleController,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 17,
                        ),
                        decoration: InputDecoration(
                          enabledBorder: OutlineInputBorder(
                            borderSide: BorderSide(color: TWColors.gray_500),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderSide: BorderSide(color: Colors.white),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          filled: true,
                          fillColor: TWColors.gray_800.withOpacity(0.5),
                        ),
                      ),
                    ],
                  ),
                ),
                
                // Start and End time side by side
                Padding(
                  padding: const EdgeInsets.fromLTRB(26, 0, 26, 20),
                  child: Row(
                    children: [
                      // Start time
                      Expanded(
                        child: SpaceCol(
                          spaceBetween: 8,
                          children: [
                            Text(
                              'start_time'.tr,
                              style: TextStyle(
                                color: AppTheme.platinum,
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Row(
                              children: [
                                Expanded(
                                  child: GestureDetector(
                                    onTap: _selectStartTime,
                                    child: Container(
                                      padding: EdgeInsets.symmetric(
                                        horizontal: 12,
                                        vertical: 12,
                                      ),
                                      decoration: BoxDecoration(
                                        color: TWColors.gray_800.withOpacity(0.5),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: TWColors.gray_500),
                                      ),
                                      child: Text(
                                        DateFormat('HH:mm').format(_startTime),
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 15,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                SizedBox(width: 8),
                                SolidButton(
                                  text: 'now',
                                  onPressed: _setStartTimeToNow,
                                  fontSize: 15,
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      SizedBox(width: 16),
                      // End time
                      Expanded(
                        child: SpaceCol(
                          spaceBetween: 8,
                          children: [
                            Text(
                              'end_time'.tr,
                              style: TextStyle(
                                color: AppTheme.platinum,
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Row(
                              children: [
                                Expanded(
                                  child: GestureDetector(
                                    onTap: _selectEndTime,
                                    child: Container(
                                      padding: EdgeInsets.symmetric(
                                        horizontal: 12,
                                        vertical: 12,
                                      ),
                                      decoration: BoxDecoration(
                                        color: TWColors.gray_800.withAlpha(128),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: TWColors.gray_500),
                                      ),
                                      child: Text(
                                        DateFormat('HH:mm').format(_endTime),
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 15,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                SizedBox(width: 8),
                                SolidButton(
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
                
                // Action buttons
                Padding(
                  padding: const EdgeInsets.fromLTRB(26, 16, 26, 20),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      SolidButton(
                        text: 'cancel',
                        onPressed: () => Navigator.of(context).pop(),
                        fontSize: 17,
                        padding: EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      ),
                      SizedBox(width: 12),
                      SolidButton(
                        text: 'book',
                        onPressed: _bookCustom,
                        fontSize: 17,
                        padding: EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      ),
                    ],
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

