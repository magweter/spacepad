import 'package:flutter/material.dart';

/// A colour the LED strips can show. [label] is a translation key, used by the LED test page.
///
/// One list for both the test page and the live integration, so what the customer approved on
/// the test page is exactly what the room state drives later.
class LedColor {
  final String label;
  final bool on;
  final int r;
  final int g;
  final int b;

  const LedColor(this.label, {this.on = true, required this.r, required this.g, required this.b});

  Color get swatch => on ? Color.fromARGB(255, r, g, b) : Colors.black;

  static const green = LedColor('led_green', r: 0x00, g: 0xFF, b: 0x00);
  static const orange = LedColor('led_orange', r: 0xFF, g: 0x80, b: 0x00);
  static const red = LedColor('led_red', r: 0xFF, g: 0x00, b: 0x00);
  static const white = LedColor('led_white', r: 0xFF, g: 0xFF, b: 0xFF);
  static const off = LedColor('led_off', on: false, r: 0x00, g: 0x00, b: 0x00);

  static const all = [green, orange, red, white, off];
}
