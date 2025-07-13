import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/action_button.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class ActionPanel extends StatelessWidget {
  final dynamic controller;
  final bool isPhone;
  final double cornerRadius;

  const ActionPanel({
    super.key,
    required this.controller,
    required this.isPhone,
    required this.cornerRadius,
  });

  @override
  Widget build(BuildContext context) {
    // Cancel button if reserved
    if (controller.isReserved && !controller.isCheckInActive && controller.bookingEnabled) {
      return ActionButton(
        text: 'cancel_event',
        onPressed: () => controller.cancelCurrentEvent(),
        textColor: Colors.white,
        isPhone: isPhone,
        cornerRadius: cornerRadius,
      );
    }
    
    return SpaceRow(
      mainAxisSize: MainAxisSize.min,
      spaceBetween: isPhone ? 16 : 24,
      children: [
        if (!controller.isReserved && controller.bookingEnabled) Obx(() => controller.showBookingOptions.value ?
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Booking options
              for (var min in controller.availableBookingDurations)
                Padding(
                  padding: EdgeInsets.only(right: min == controller.availableBookingDurations.last ? 0 : (isPhone ? 12 : 16)),
                  child: ActionButton(
                    text: '$min min',
                    onPressed: () => controller.bookRoom(min, 'reserved'.tr),
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                  ),
                ),
              SizedBox(width: isPhone ? 16 : 24),
              ActionButton(
                text: 'cancel',
                onPressed: () => controller.hideBookingOptions(),
                isPhone: isPhone,
                cornerRadius: cornerRadius,
              ),
            ],
          ) :
          ActionButton(
            text: 'book_now',
            onPressed: () => controller.toggleBookingOptions(),
            isPhone: isPhone,
            cornerRadius: cornerRadius,
          ),
        ),
        if (controller.isCheckInActive && controller.checkInEnabled) ActionButton(
          text: 'check_in',
          onPressed: () => controller.checkIn(),
          isPhone: isPhone,
          cornerRadius: cornerRadius,
        ),
        SizedBox.shrink()
      ]
    );
  }
} 