import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/build_flags.dart';
import 'package:spacepad/services/sicp_service.dart';

enum LedConnection { notTested, checking, connected, unreachable }

/// A colour button on the LED test page. [label] is a translation key.
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

class LedLogEntry {
  final DateTime time;
  final String action;
  final bool ok;
  final String result;
  final String tx;
  final String rx;

  const LedLogEntry({required this.time, required this.action, required this.ok, required this.result, required this.tx, required this.rx});

  String get timeLabel => DateFormat('HH:mm:ss').format(time);
}

class LedTestController extends GetxController {
  static const int maxLogEntries = 20;

  final TextEditingController host = TextEditingController(text: '127.0.0.1');
  final TextEditingController port = TextEditingController(text: '5000');
  final TextEditingController monitorId = TextEditingController(text: '1');

  final RxBool busy = RxBool(false);
  final Rx<LedConnection> connection = Rx(LedConnection.notTested);
  final Rx<LedLogEntry?> lastResult = Rx(null);
  final Rx<LedState?> reportedState = Rx(null);
  final RxString reportedError = RxString('');
  final RxList<LedLogEntry> log = RxList();
  final RxString device = RxString('-');
  final RxString androidVersion = RxString('-');

  String get appVersion => appVersionDefine.isEmpty ? '-' : appVersionDefine;

  @override
  void onInit() {
    super.onInit();

    _loadDeviceInfo();
    testConnection();
  }

  @override
  void onClose() {
    // No "off" command here: the LED keeps the last colour when leaving the page.
    host.dispose();
    port.dispose();
    monitorId.dispose();
    super.onClose();
  }

  Future<void> testConnection() async {
    if (busy.value) return;

    connection.value = LedConnection.checking;
    final result = await _run('led_action_test'.tr, (h, p, id) => SicpService.instance.getLed(h, p, id));

    // Any well-formed reply, even "not supported", proves the display answers SICP.
    final answered = result != null &&
        result.status != SicpStatus.unreachable &&
        result.status != SicpStatus.timeout &&
        result.status != SicpStatus.invalid;
    connection.value = answered ? LedConnection.connected : LedConnection.unreachable;
  }

  Future<void> setColor(LedColor color) async {
    final result = await _run(
      'led_action_set'.trParams({'color': color.label.tr}),
      (h, p, id) => SicpService.instance.setLed(h, p, id, on: color.on, r: color.r, g: color.g, b: color.b),
    );
    _updateConnection(result);
  }

  Future<void> readStatus() async {
    final result = await _run('led_action_read'.tr, (h, p, id) => SicpService.instance.getLed(h, p, id));
    _updateConnection(result);
    if (result == null) return;

    reportedState.value = result.led;
    reportedError.value = result.led == null ? _describe(result) : '';
  }

  void _updateConnection(SicpResult? result) {
    if (result == null) return;
    if (result.status == SicpStatus.unreachable) {
      connection.value = LedConnection.unreachable;
    } else if (result.status != SicpStatus.timeout && result.status != SicpStatus.invalid) {
      connection.value = LedConnection.connected;
    }
  }

  /// Validates the connection fields, runs one command and logs it. Returns null when the
  /// fields are invalid or another command is still running.
  Future<SicpResult?> _run(String action, Future<SicpResult> Function(String host, int port, int id) command) async {
    if (busy.value) return null;

    final h = host.text.trim();
    final p = int.tryParse(port.text.trim());
    final id = int.tryParse(monitorId.text.trim());
    if (h.isEmpty || p == null || p < 1 || p > 65535 || id == null || id < 0 || id > 255) {
      _addLog(LedLogEntry(time: DateTime.now(), action: action, ok: false, result: 'led_invalid_settings'.tr, tx: '', rx: ''));
      if (connection.value == LedConnection.checking) connection.value = LedConnection.unreachable;
      return null;
    }

    busy.value = true;
    try {
      final result = await command(h, p, id);
      _addLog(LedLogEntry(
        time: DateTime.now(),
        action: action,
        ok: result.ok,
        result: _describe(result),
        tx: result.tx,
        rx: result.rx,
      ));
      return result;
    } finally {
      busy.value = false;
    }
  }

  void _addLog(LedLogEntry entry) {
    lastResult.value = entry;
    log.insert(0, entry);
    if (log.length > maxLogEntries) log.removeRange(maxLogEntries, log.length);
  }

  String _describe(SicpResult result) {
    switch (result.status) {
      case SicpStatus.ack:
        return 'led_success'.tr;
      case SicpStatus.ledReport:
        final led = result.led!;
        return led.on ? 'led_status_on'.trParams({'color': led.hex}) : 'led_status_off'.tr;
      case SicpStatus.unreachable:
        return 'led_failed'.trParams({'reason': 'led_reason_unreachable'.tr});
      case SicpStatus.timeout:
        return 'led_failed'.trParams({'reason': 'led_reason_timeout'.tr});
      case SicpStatus.nack:
        return 'led_failed'.trParams({'reason': 'led_reason_nack'.tr});
      case SicpStatus.nav:
        return 'led_failed'.trParams({'reason': 'led_reason_nav'.tr});
      case SicpStatus.invalid:
        return 'led_failed'.trParams({'reason': 'led_reason_invalid'.tr});
    }
  }

  Future<void> _loadDeviceInfo() async {
    if (!Platform.isAndroid) {
      androidVersion.value = Platform.operatingSystemVersion;
      return;
    }

    try {
      final info = await DeviceInfoPlugin().androidInfo;
      device.value = '${info.manufacturer} ${info.model}';
      androidVersion.value = '${info.version.release} (SDK ${info.version.sdkInt})';
    } catch (_) {
      // Diagnostics only; leave the placeholders.
    }
  }
}
