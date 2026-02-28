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
    final isPortrait = MediaQuery.of(context).orientation == Orientation.portrait;
    
    return SpaceRow(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.start,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Show booking options when not reserved
        Obx(() {
          final showBooking = !controller.isReserved && controller.bookingEnabled;
          final showCheckIn = controller.isCheckInActive && controller.checkInEnabled;
          
          if (showBooking) {
            final isBooking = controller.isBooking.value;
            final bookingDuration = controller.bookingDuration.value;
            
            // If both booking and check-in are visible, combine them
            if (showCheckIn && !controller.showBookingOptions.value) {
              // Show "book_now" button and check-in button together
              return SpaceRow(
                mainAxisSize: MainAxisSize.min,
                spaceBetween: isPhone ? 12 : 16,
                mainAxisAlignment: MainAxisAlignment.start,
                children: [
                  ActionButton(
                    text: 'book_now',
                    onPressed: isBooking ? null : () => controller.toggleBookingOptions(),
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                    isLoading: isBooking && bookingDuration == null,
                  ),
                  ActionButton(
                    text: 'check_in',
                    onPressed: () => controller.checkIn(),
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                  ),
                ],
              );
            }
            
            return controller.showBookingOptions.value ?
          (isPortrait ? 
            // Portrait: Horizontally scrollable container wrapped in Flexible
            Flexible(
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
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
                          disabled: !controller.availableBookingDurations.contains(min) || (isBooking && bookingDuration != min),
                          isLoading: isBooking && bookingDuration == min, // Only show loading on the clicked button
                        ),
                      ),
                    if (controller.hasCustomBooking) ...[
                      SizedBox(width: isPhone ? 12 : 16),
                      ActionButton(
                        text: 'custom',
                        onPressed: isBooking ? null : () => controller.showCustomBookingModal(context, isPhone, cornerRadius),
                        isPhone: isPhone,
                        cornerRadius: cornerRadius,
                        disabled: isBooking,
                        isLoading: isBooking && bookingDuration == null, // Show loading if custom booking is in progress
                      ),
                    ],
                    SizedBox(width: isPhone ? 16 : 24),
                    ActionButton(
                      text: 'cancel',
                      onPressed: isBooking ? null : () => controller.hideBookingOptions(),
                      isPhone: isPhone,
                      cornerRadius: cornerRadius,
                      disabled: isBooking,
                    ),
                  ],
                ),
              ),
            ) :
            // Landscape: Keep buttons in a single row
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
                      disabled: !controller.availableBookingDurations.contains(min) || (isBooking && bookingDuration != min),
                      isLoading: isBooking && bookingDuration == min, // Only show loading on the clicked button
                    ),
                  ),
                if (controller.hasCustomBooking) ...[
                  SizedBox(width: isPhone ? 12 : 16),
                  ActionButton(
                    text: 'custom',
                    onPressed: isBooking ? null : () => controller.showCustomBookingModal(context, isPhone, cornerRadius),
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                    disabled: isBooking,
                    isLoading: isBooking && bookingDuration == null, // Show loading if custom booking is in progress
                  ),
                ],
                SizedBox(width: isPhone ? 16 : 24),
                ActionButton(
                  text: 'cancel',
                  onPressed: isBooking ? null : () => controller.hideBookingOptions(),
                  isPhone: isPhone,
                  cornerRadius: cornerRadius,
                  disabled: isBooking,
                ),
              ],
            )
          ) :
          ActionButton(
            text: 'book_now',
            onPressed: isBooking ? null : () => controller.toggleBookingOptions(),
            isPhone: isPhone,
            cornerRadius: cornerRadius,
            isLoading: isBooking && bookingDuration == null, // Only show loading if no specific duration button was clicked
          );
          }
          return SizedBox.shrink();
        }),
        // Show cancel button and custom booking when reserved (meeting is active)
        Obx(() {
          if (controller.isReserved && !controller.isCheckInActive && controller.bookingEnabled) {
            final isBooking = controller.isBooking.value;
            return SpaceRow(
              mainAxisSize: MainAxisSize.min,
              spaceBetween: isPhone ? 12 : 16,
              mainAxisAlignment: MainAxisAlignment.start,
              children: [
                if (controller.canCancelCurrentEvent)
                  ActionButton(
                    text: 'cancel_event',
                    onPressed: controller.isCancelling.value ? null : () => controller.cancelCurrentEvent(),
                    textColor: Colors.white,
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                    isLoading: controller.isCancelling.value,
                  ),
                if (controller.hasCustomBooking)
                  ActionButton(
                    text: 'reserve',
                    onPressed: isBooking ? null : () => controller.showCustomBookingModal(context, isPhone, cornerRadius),
                    isPhone: isPhone,
                    cornerRadius: cornerRadius,
                    disabled: isBooking,
                    isLoading: isBooking && controller.bookingDuration.value == null,
                  ),
              ],
            );
          }
          return SizedBox.shrink();
        }),
        Obx(() {
          // Show check-in button separately when booking is not enabled or reserved
          // Hide check-in button when booking options are expanded
          final showBooking = !controller.isReserved && controller.bookingEnabled;
          final showCheckIn = controller.isCheckInActive && controller.checkInEnabled;
          
          // Don't show check-in button when booking options are expanded
          if (showBooking && controller.showBookingOptions.value) {
            return SizedBox.shrink();
          }
          
          if (showCheckIn && (controller.isReserved || !controller.bookingEnabled)) {
            return ActionButton(
              text: 'check_in',
              onPressed: () => controller.checkIn(),
              isPhone: isPhone,
              cornerRadius: cornerRadius,
            );
          }
          return SizedBox.shrink();
        }),
      ]
    );
  }
} 