import 'package:flutter/material.dart';

/// The meeting title or room state: the largest text on the display.
///
/// Takes at most [maxLines] lines, and fewer when the space between the header and the buttons
/// cannot hold that many. It is meant to sit in a [Flexible] slot, so it is laid out in whatever
/// height is left after the logo, the meeting details and the buttons, and it shortens with an
/// ellipsis instead of growing into them. A long meeting subject used to run to four or five
/// lines and press the logo and the buttons against the edges of the screen.
class StatusTitle extends StatelessWidget {
  final String text;
  final TextStyle style;
  final int maxLines;

  const StatusTitle({
    super.key,
    required this.text,
    required this.style,
    this.maxLines = 3,
  });

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, constraints) {
      var lines = maxLines;

      if (constraints.hasBoundedHeight) {
        final lineHeight = (TextPainter(
          text: TextSpan(text: text, style: style),
          textDirection: Directionality.of(context),
          textScaler: MediaQuery.maybeTextScalerOf(context) ?? TextScaler.noScaling,
          maxLines: 1,
        )..layout()).height;

        lines = (constraints.maxHeight / lineHeight).floor().clamp(1, maxLines);
      }

      return Text(
        text,
        style: style,
        maxLines: lines,
        overflow: TextOverflow.ellipsis,
      );
    });
  }
}
