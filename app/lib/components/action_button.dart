import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class ActionButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final Color? borderColor;
  final Color? textColor;
  final bool isPhone;
  final double cornerRadius;
  final bool disabled;

  const ActionButton({
    super.key,
    required this.text,
    required this.onPressed,
    required this.isPhone,
    required this.cornerRadius,
    this.borderColor,
    this.textColor,
    this.disabled = false,
  });

  @override
  Widget build(BuildContext context) {
    final Color effectiveBorderColor = borderColor ?? TWColors.gray_500.withAlpha(160);
    return Opacity(
      opacity: disabled ? 0.5 : 1.0,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(cornerRadius),
          color: Colors.transparent,
          border: Border.all(color: effectiveBorderColor, width: 2),
        ),
        margin: EdgeInsets.only(top: isPhone ? 10 : 20, bottom: isPhone ? 10 : 20),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(cornerRadius),
            onTap: disabled ? null : onPressed,
            child: Stack(
              children: [
                Padding(
                  padding: EdgeInsets.symmetric(
                    vertical: isPhone ? 12 : 16,
                    horizontal: isPhone ? 20 : 28,
                  ),
                  child: Center(
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
                if (disabled)
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
