import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tailwind_components/tailwind_components.dart';
import 'package:spacepad/components/frosted_panel.dart';

class ActionButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final Color? borderColor;
  final Color? textColor;
  final bool isPhone;
  final double cornerRadius;
  final bool disabled;
  final bool isLoading;

  const ActionButton({
    super.key,
    required this.text,
    required this.onPressed,
    required this.isPhone,
    required this.cornerRadius,
    this.borderColor,
    this.textColor,
    this.disabled = false,
    this.isLoading = false,
  });

  @override
  Widget build(BuildContext context) {
    final Color effectiveBorderColor = borderColor ?? TWColors.gray_500.withAlpha(160);
    final bool isDisabled = disabled || isLoading;
    return Opacity(
      opacity: isDisabled ? 0.5 : 1.0,
      child: Container(
        margin: EdgeInsets.only(top: isPhone ? 10 : 20, bottom: isPhone ? 10 : 20),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(cornerRadius),
        ),
        child: FrostedPanel(
          borderRadius: cornerRadius,
          blurIntensity: 18,
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(cornerRadius),
              onTap: isDisabled ? null : onPressed,
              child: Stack(
                children: [
                  Padding(
                    padding: EdgeInsets.symmetric(
                      vertical: isPhone ? 12 : 16,
                      horizontal: isPhone ? 20 : 28,
                    ),
                    child: SizedBox(
                      height: isPhone ? 22 : 26, // Fixed height to match text line height
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          // Keep text in layout to maintain button width, but make it invisible when loading
                          Opacity(
                            opacity: isLoading ? 0 : 1,
                            child: Text(
                              text.tr,
                              style: TextStyle(
                                color: textColor ?? TWColors.white,
                                fontSize: isPhone ? 16 : 20,
                                fontWeight: FontWeight.w700,
                                height: 1.0, // Ensure consistent line height
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ),
                          // Show loading indicator on top when loading
                          if (isLoading)
                            SizedBox(
                              width: isPhone ? 20 : 24,
                              height: isPhone ? 20 : 24,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  textColor ?? TWColors.white,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                  if (disabled && !isLoading)
                    Positioned.fill(
                      child: CustomPaint(
                        painter: _DiagonalStrikethroughPainter(
                          color: effectiveBorderColor,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _DiagonalStrikethroughPainter extends CustomPainter {
  final Color color;
  static const double borderWidth = 2;
  _DiagonalStrikethroughPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = borderWidth;
    // Draw from the middle of the border on each corner
    canvas.drawLine(
      Offset(1, size.height - 1),
      Offset(size.width - 1, 1),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
