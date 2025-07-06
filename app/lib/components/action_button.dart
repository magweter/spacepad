import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class ActionButton extends StatelessWidget {
  final String text;
  final VoidCallback onPressed;
  final Color backgroundColor;
  final Color borderColor;
  final Color textColor;
  final bool isPhone;
  final double cornerRadius;

  const ActionButton({
    super.key,
    required this.text,
    required this.onPressed,
    this.backgroundColor = const Color(0xFF4B5563), // gray_600
    this.borderColor = Colors.transparent,
    this.textColor = Colors.white,
    required this.isPhone,
    required this.cornerRadius,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(cornerRadius),
        color: backgroundColor.withValues(alpha: 0.3),
        border: borderColor != Colors.transparent 
          ? Border.all(color: borderColor.withValues(alpha: 0.3))
          : null,
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
                color: textColor,
                fontSize: isPhone ? 20 : 28,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      ),
    );
  }
} 