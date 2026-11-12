import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// The room states the display shows, each with its own colour.
enum RoomTone { available, reserved, transitioning, offline }

/// Colours and type of the glass display theme, taken from the dark values of the design
/// system's tokens so the tablet matches the device screens in the portal and on the website.
class GlassTokens {
  GlassTokens._();

  /// `ground` (dark): the screen behind the glow.
  static const Color ground = Color(0xFF0B1220);

  /// `glass` (dark): the fill of a frosted surface.
  static const Color glass = Color(0x1AFFFFFF);

  /// `glass-border` (dark): the hairline edge of a frosted surface.
  static const Color glassBorder = Color(0x38FFFFFF);

  /// `status-*` (dark): green free, red in use, amber about to change, grey offline.
  static const Color available = Color(0xFF4ADE80);
  static const Color reserved = Color(0xFFF87171);
  static const Color transitioning = Color(0xFFFBBF24);
  static const Color offline = Color(0xFF64748B);

  /// Status changes crossfade instead of jumping.
  static const Duration crossfade = Duration(milliseconds: 400);

  static Color statusColor(RoomTone tone) {
    switch (tone) {
      case RoomTone.reserved:
        return reserved;
      case RoomTone.transitioning:
        return transitioning;
      case RoomTone.offline:
        return offline;
      case RoomTone.available:
        return available;
    }
  }

  /// The display face (Space Grotesk, bold, tight tracking) for the clock and the status title.
  static TextStyle displayFace({required double fontSize, Color color = Colors.white}) {
    return GoogleFonts.spaceGrotesk(
      fontSize: fontSize,
      fontWeight: FontWeight.w700,
      letterSpacing: -0.03 * fontSize,
      color: color,
    );
  }
}

/// Tells the frosted surfaces below it whether the display runs the glass theme.
///
/// A scope rather than a parameter, because the buttons, chips, the bottom bar and the
/// timeline all draw a [FrostedPanel] and each sits a few widgets deep. Dialogs open in a route
/// of their own, outside the scope, so they keep their own look.
class GlassScope extends InheritedWidget {
  final bool enabled;

  const GlassScope({super.key, required this.enabled, required super.child});

  static bool of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<GlassScope>()?.enabled ?? false;
  }

  @override
  bool updateShouldNotify(GlassScope oldWidget) => enabled != oldWidget.enabled;
}

/// The glass theme's screen: the dark ground with a soft glow in the status colour bleeding in
/// from the top right and bottom left corners.
///
/// Sits above a background image when one is set, so the glow and the status still read on it.
class GlassGlow extends StatelessWidget {
  final RoomTone tone;
  final Widget child;

  const GlassGlow({super.key, required this.tone, required this.child});

  Widget _glow(Alignment center, double strength) {
    final color = GlassTokens.statusColor(tone);
    return Positioned.fill(
      child: IgnorePointer(
        child: AnimatedContainer(
          duration: GlassTokens.crossfade,
          curve: Curves.easeOut,
          decoration: BoxDecoration(
            gradient: RadialGradient(
              center: center,
              radius: 0.95,
              colors: [color.withValues(alpha: strength), color.withValues(alpha: 0)],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        _glow(const Alignment(1.05, -1.1), 0.22),
        _glow(const Alignment(-1.05, 1.1), 0.30),
        child,
      ],
    );
  }
}
