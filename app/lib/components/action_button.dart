import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class ActionButton extends StatelessWidget {
  final String text;
  final VoidCallback onPressed;
  final Color? borderColor;
  final Color? textColor;
  final bool isPhone;
  final double cornerRadius;

  const ActionButton({
    super.key,
    required this.text,
    required this.onPressed,
    required this.isPhone,
    required this.cornerRadius,
    this.borderColor,
    this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(cornerRadius),
        color: Colors.transparent,
        border: Border.all(color: borderColor ?? TWColors.gray_500.withAlpha(160), width: 2),
      ),
      margin: EdgeInsets.only(top: isPhone ? 10 : 20, bottom: isPhone ? 10 : 20),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(cornerRadius),
          onTap: onPressed,
          child: Padding(
            padding: EdgeInsets.symmetric(
              vertical: isPhone ? 12 : 16,
              horizontal: isPhone ? 20 : 28,
            ),
            child: Text(
              text.tr,
              style: TextStyle(
                color: textColor ?? TWColors.white,
                fontSize: isPhone ? 16 : 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      ),
    );
  }
} 