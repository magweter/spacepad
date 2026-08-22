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
    Color? backgroundColor,
    this.padding,
  }) : backgroundColor = backgroundColor ?? defaultLift;

  /// The lift every frosted surface takes, whatever sits behind it.
  ///
  /// Deliberately one value rather than one per background: a lighter panel over an image and
  /// a darker one on black meant the bottom bar, the timeline panel and the buttons each
  /// landed somewhere slightly different. This much white still reads as a surface on plain
  /// black, which is where the buttons needed it.
  static const Color defaultLift = Color(0x33FFFFFF);

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
          : TWColors.black.withValues(alpha: 0.1),
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
