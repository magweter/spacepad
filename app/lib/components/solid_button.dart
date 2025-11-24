import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tailwind_components/tailwind_components.dart';

class SolidButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final double? fontSize;
  final EdgeInsets? padding;
  final double borderRadius;

  const SolidButton({
    super.key,
    required this.text,
    this.onPressed,
    this.fontSize,
    this.padding,
    this.borderRadius = 8,
  });

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed != null ? 1.0 : 0.5,
      child: GestureDetector(
        onTap: onPressed,
        child: Container(
          padding: padding ?? EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: TWColors.gray_800.withAlpha(128),
            borderRadius: BorderRadius.circular(borderRadius),
            border: Border.all(color: TWColors.gray_500),
          ),
          child: Text(
            text.tr,
            style: TextStyle(
              color: Colors.white,
              fontSize: fontSize ?? 15,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}

