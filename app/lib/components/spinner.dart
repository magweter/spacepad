import 'package:flutter/material.dart';
import 'package:spacepad/theme.dart';

class Spinner extends StatelessWidget {
  final double size;
  final EdgeInsets? padding;
  final double? thickness;
  final Color? color;

  const Spinner({super.key, required this.size, this.padding, this.thickness, this.color = AppTheme.oxford});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: size,
      width: size,
      margin: padding,
      child: CircularProgressIndicator(strokeWidth: thickness ?? 3, color: color),
    );
  }
}
