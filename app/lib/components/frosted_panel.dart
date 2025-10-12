import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:tailwind_components/tailwind_components.dart';

class FrostedPanel extends StatelessWidget {
  final Widget child;
  final double borderRadius;
  final double blurIntensity;
  final Color backgroundColor;
  final EdgeInsetsGeometry? padding;

  const FrostedPanel({
    super.key,
    required this.child,
    this.borderRadius = 20,
    this.blurIntensity = 18,
    this.backgroundColor = const Color(0x14FFFFFF), // Colors.white.withAlpha((0.08 * 255).toInt())
    this.padding,
  });

  /// Creates a frosted panel with gray background (for use with background images)
  factory FrostedPanel.gray({
    required Widget child,
    double borderRadius = 20,
    bool hasBackgroundImage = false,
    EdgeInsetsGeometry? padding,
  }) {
    return FrostedPanel(
      borderRadius: borderRadius,
      blurIntensity: 0, // No blur for gray panels
      backgroundColor: hasBackgroundImage 
          ? TWColors.black.withValues(alpha: 0.8)
          : TWColors.black.withValues(alpha: 0.4),
      padding: padding,
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    final panel = Container(
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
      padding: padding,
      child: child,
    );

    // Only apply backdrop filter if blur intensity is greater than 0
    if (blurIntensity > 0) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: blurIntensity, sigmaY: blurIntensity),
          child: panel,
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: panel,
    );
  }
}

