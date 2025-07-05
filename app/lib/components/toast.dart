import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:heroicons/heroicons.dart';

class Toast {
  Toast._();

  static void showSuccess(String message) {
    _showSnackBar(message, HeroIcons.check, Colors.green);
  }

  static void showError(String message) {
    _showSnackBar(message, HeroIcons.exclamationCircle, Colors.red);
  }

  static void _showSnackBar(String message, HeroIcons icon, Color color) async {
    /// Small delay to make sure widget tree is built.
    await Future.delayed(const Duration(milliseconds: 100));

    Get.showSnackbar(
        GetSnackBar(
          messageText: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(child: Padding(
                padding: const EdgeInsets.only(top: 3),
                child: Text(message, style: const TextStyle(
                  color: Colors.black,
                  fontSize: 14,
                  height: 1.2,
                  fontWeight: FontWeight.w600,
                )),
              )),

              const SizedBox(width: 2),

              IconButton(
                onPressed: () => Get.closeCurrentSnackbar(),
                icon: const HeroIcon(HeroIcons.xMark, size: 20),
              )
            ],
          ),
          margin: const EdgeInsets.symmetric(horizontal: 35, vertical: 10),
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 7),
          backgroundColor: Colors.white,
          boxShadows: const [
            BoxShadow(
              color: Colors.black12,
              spreadRadius: 1,
              blurRadius: 2,
              offset: Offset(0, 0), // changes position of shadow
            ),
          ],
          icon: Padding(
              padding: const EdgeInsets.only(left: 12, right: 8),
              child: HeroIcon(icon, color: color, style: HeroIconStyle.solid, size: 26)
          ),
          borderRadius: 10,
          duration: const Duration(seconds: 4),
          animationDuration: const Duration(milliseconds: 350),
          forwardAnimationCurve: Curves.fastEaseInToSlowEaseOut,
          reverseAnimationCurve: Curves.fastOutSlowIn,
          borderWidth: 1,
          borderColor: Colors.grey[300],
        )
    );
  }
}