import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/action_button.dart';
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
      return Obx(() => Row(
        mainAxisSize: MainAxisSize.min,
        children: [ActionButton(
        text: 'cancel_event',
        onPressed: controller.isCancelling.value ? null : () => controller.cancelCurrentEvent(),
        textColor: Colors.white,
        isPhone: isPhone,
        cornerRadius: cornerRadius,
        isLoading: controller.isCancelling.value,
      ),],));
    }
    
    return SpaceRow(
      mainAxisSize: MainAxisSize.min,
      spaceBetween: isPhone ? 16 : 24,
      children: [
        if (!controller.isReserved && controller.bookingEnabled) Obx(() {
          final isBooking = controller.isBooking.value;
          final bookingDuration = controller.bookingDuration.value;
          return controller.showBookingOptions.value ?
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Show all options, but disable and strikethrough if not available
              for (var min in [15, 30, 60])
                Padding(
                  padding: EdgeInsets.only(right: min == 60 ? 0 : (isPhone ? 12 : 16)),
                  child: ActionButton(
                    text: '$min min',
                    onPressed: (controller.availableBookingDurations.contains(min) && !isBooking)
                      ? () => controller.bookRoom(min)
                      : null,
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                    disabled: !controller.availableBookingDurations.contains(min),
                    isLoading: isBooking && bookingDuration == min, // Only show loading on the clicked button
                  ),
                ),
              SizedBox(width: isPhone ? 12 : 16),
              ActionButton(
                text: 'custom',
                onPressed: isBooking ? null : () => controller.showCustomBookingModal(context, isPhone, cornerRadius),
                isPhone: isPhone,
                cornerRadius: cornerRadius,
                disabled: isBooking,
                isLoading: isBooking && bookingDuration == null, // Show loading if custom booking is in progress
              ),
              SizedBox(width: isPhone ? 16 : 24),
              ActionButton(
                text: 'cancel',
                onPressed: isBooking ? null : () => controller.hideBookingOptions(),
                isPhone: isPhone,
                cornerRadius: cornerRadius,
                disabled: isBooking,
              ),
            ],
          ) :
          ActionButton(
            text: 'book_now',
            onPressed: isBooking ? null : () => controller.toggleBookingOptions(),
            isPhone: isPhone,
            cornerRadius: cornerRadius,
            isLoading: isBooking && bookingDuration == null, // Only show loading if no specific duration button was clicked
          );
        }),
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