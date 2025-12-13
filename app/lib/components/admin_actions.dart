import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AdminActions extends StatelessWidget {
  final dynamic controller;
  final bool isPhone;

  const AdminActions({
    super.key,
    required this.controller,
    required this.isPhone,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Refresh button
        Obx(() {
          final iconSize = isPhone ? 20.0 : 28.0;
          return Opacity(
            opacity: 0.6,
            child: SizedBox(
              width: 24,
              height: iconSize,
              child: IconButton(
                icon: controller.isRefreshing.value
                    ? SizedBox(
                        width: iconSize - 8,
                        height: iconSize - 8,
                        child: CircularProgressIndicator(
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
        SizedBox(width: 15),
        // Logout/Switch room button
        Opacity(
          opacity: 0.6,
          child: SizedBox(
            width: 24,
            height: isPhone ? 20 : 28,
            child: IconButton(
              icon: const Icon(Icons.logout, size: 24, color: Colors.white),
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

