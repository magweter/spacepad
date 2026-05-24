import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AdminActions extends StatelessWidget {
  final dynamic controller;

  const AdminActions({
    super.key,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    // Scale icon size proportionally with the shortest screen dimension so
    // both icons stay consistent when the window is resized freely on desktop.
    final shortestSide = MediaQuery.of(context).size.shortestSide;
    final iconSize = (28.0 * (shortestSide / 800).clamp(0.55, 1.3)).roundToDouble();

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Refresh button
        Obx(() {
          return Opacity(
            opacity: 0.6,
            child: SizedBox(
              width: iconSize,
              height: iconSize,
              child: IconButton(
                icon: controller.isRefreshing.value
                    ? SizedBox(
                        width: iconSize - 8,
                        height: iconSize - 8,
                        child: const CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : Icon(Icons.refresh, size: iconSize, color: Colors.white),
                onPressed: controller.isRefreshing.value ? null : () {
                  controller.refreshDisplayData();
                },
                tooltip: 'refresh_data'.tr,
                padding: EdgeInsets.zero,
                alignment: Alignment.center,
              ),
            ),
          );
        }),
        SizedBox(width: iconSize * 0.5),
        // Logout/Switch room button
        Opacity(
          opacity: 0.6,
          child: SizedBox(
            width: iconSize,
            height: iconSize,
            child: IconButton(
              icon: Icon(Icons.logout, size: iconSize, color: Colors.white),
              onPressed: () {
                controller.switchRoom();
              },
              tooltip: 'switch_room'.tr,
              padding: EdgeInsets.zero,
              alignment: Alignment.center,
            ),
          ),
        ),
      ],
    );
  }
}
