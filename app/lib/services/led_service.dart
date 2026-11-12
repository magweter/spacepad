import 'dart:async';

import 'package:spacepad/models/led_color.dart';
import 'package:spacepad/services/sicp_service.dart';

/// Drives the LED strips on a Philips SICP display from the room state.
///
/// The tablet is the display, so the strips are reached over SICP on the loopback address with
/// the Philips defaults (port 5000, monitor 1), the same values the LED test page starts with.
/// The dashboard asks for a colour every second; this class sends it only when it changed, when
/// the previous attempt failed a while ago, or when it is time to re-assert it (a display that
/// rebooted comes back with its strips off).
class LedService {
  LedService._() : this.withSender(_sicpSender);

  /// Testing seam: [send] replaces the SICP round trip and [now] the clock.
  LedService.withSender(Future<SicpResult> Function(LedColor color) send, {DateTime Function()? now})
      : _send = send,
        _now = now ?? DateTime.now;

  static final LedService instance = LedService._();

  static const String host = '127.0.0.1';
  static const int port = 5000;
  static const int monitorId = 1;

  /// How long a colour is trusted to still be on the strips before it is sent again.
  static const Duration reassertAfter = Duration(minutes: 5);

  /// How long to wait before retrying after a failed send, so a tablet whose strips are not
  /// reachable does not knock on the port every second.
  static const Duration retryAfter = Duration(seconds: 30);

  final Future<SicpResult> Function(LedColor color) _send;
  final DateTime Function() _now;

  LedColor? _lastColor;
  DateTime? _lastSentAt;
  bool _lastOk = false;
  bool _busy = false;

  /// The colour that belongs to a room state, as the test page let the customer approve them.
  ///
  /// Same precedence as the coloured border on the dashboard: a meeting about to start or end
  /// (or waiting for a check-in) is orange, a running meeting red, otherwise green.
  static LedColor colorFor({required bool reserved, required bool transitioning, required bool checkInActive}) {
    if (transitioning || checkInActive) return LedColor.orange;
    if (reserved) return LedColor.red;
    return LedColor.green;
  }

  /// Puts [color] on the strips when that is not already known to be the case.
  Future<void> apply(LedColor color) async {
    if (_busy) return;

    final now = _now();
    if (identical(color, _lastColor) && _lastSentAt != null) {
      final age = now.difference(_lastSentAt!);
      if (_lastOk && age < reassertAfter) return;
      if (!_lastOk && age < retryAfter) return;
    }

    _busy = true;
    try {
      final result = await _send(color);
      _lastColor = color;
      _lastSentAt = _now();
      _lastOk = result.ok;
    } catch (_) {
      _lastColor = color;
      _lastSentAt = _now();
      _lastOk = false;
    } finally {
      _busy = false;
    }
  }

  static Future<SicpResult> _sicpSender(LedColor color) {
    return SicpService.instance.setLed(host, port, monitorId, on: color.on, r: color.r, g: color.g, b: color.b);
  }
}
