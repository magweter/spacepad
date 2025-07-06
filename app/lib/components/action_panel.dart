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
    if (controller.isReserved) {
      return ActionButton(
        text: 'cancel_event',
        onPressed: () => controller.cancelCurrentEvent(),
        textColor: Colors.white,
        isPhone: isPhone,
        cornerRadius: cornerRadius,
      );
    }
    // Booking interface if booking is enabled
    if (controller.shouldShowBooking) {
      return Obx(() => controller.showBookingOptions.value
        // Show booking options as outlined buttons
        ? Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (var min in [15, 30, 60])
                Padding(
                  padding: EdgeInsets.only(right: min == 60 ? 0 : (isPhone ? 12 : 16)),
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
          )
        // Show initial Book now button as outlined
        : ActionButton(
            text: 'book_now',
            onPressed: () => controller.toggleBookingOptions(),
            isPhone: isPhone,
            cornerRadius: cornerRadius,
          ),
      );
    }
    return SizedBox.shrink();
  }
} 