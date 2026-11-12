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

      // Lay the title out the way it will be painted, at this width and with the ellipsis, and
      // keep the most lines that fit. Dividing the height by the height of one line was not
      // enough: a wrapped paragraph is not always a whole number of single lines tall, and at
      // the largest sizes the last line came out half covered by the meeting details.
      if (constraints.hasBoundedHeight && constraints.hasBoundedWidth) {
        final textScaler = MediaQuery.maybeTextScalerOf(context) ?? TextScaler.noScaling;
        final direction = Directionality.of(context);

        for (lines = maxLines; lines > 1; lines--) {
          final painter = TextPainter(
            text: TextSpan(text: text, style: style),
            textDirection: direction,
            textScaler: textScaler,
            maxLines: lines,
            ellipsis: '\u2026',
          )..layout(maxWidth: constraints.maxWidth);
          final height = painter.height;
          painter.dispose();

          if (height <= constraints.maxHeight) break;
        }
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
