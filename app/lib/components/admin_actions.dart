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
        Opacity(
          opacity: 0.6,
          child: SizedBox(
            width: 24,
            height: isPhone ? 20 : 28,
            child: IconButton(
              icon: const Icon(Icons.refresh, size: 28, color: Colors.white),
              onPressed: () {
                controller.refreshDisplayData();
              },
              tooltip: 'refresh_data'.tr,
              padding: EdgeInsets.zero,
              alignment: Alignment.center,
            ),
          ),
        ),
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

