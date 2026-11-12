import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

/// Outcome of one SICP command.
enum SicpStatus { ack, nack, nav, ledReport, unreachable, timeout, invalid }

/// LED state as reported by the display.
class LedState {
  final bool on;
  final int r;
  final int g;
  final int b;

  const LedState({required this.on, required this.r, required this.g, required this.b});

  String get hex => '#${[r, g, b].map((v) => v.toRadixString(16).padLeft(2, '0')).join().toUpperCase()}';
}

/// A parsed reply frame.
class SicpReply {
  final SicpStatus status;
  final LedState? led;

  const SicpReply(this.status, [this.led]);
}

/// Result of a command, with the raw bytes for the diagnostic log.
class SicpResult {
  final SicpStatus status;
  final LedState? led;
  final String tx;
  final String rx;
  final String? error;

  const SicpResult({required this.status, this.led, required this.tx, this.rx = '', this.error});

  bool get ok => status == SicpStatus.ack || status == SicpStatus.ledReport;
}

/// Talks SICP (Philips Serial/IP Control Protocol) over raw TCP.
///
/// Frame: `[MsgSize][MonitorID][Group][Data...][Checksum]`, where MsgSize counts every byte
/// including itself and the checksum is the XOR of all preceding bytes. The display handles one
/// command at a time, so calls are serialised and each opens its own short-lived connection.
class SicpService {
  SicpService._();
  static final SicpService instance = SicpService._();

  static const int cmdSetLed = 0xF3;
  static const int cmdGetLed = 0xF4;
  static const int ack = 0x06;
  static const int nack = 0x15;
  static const int nav = 0x18;

  static const Duration connectTimeout = Duration(seconds: 1);
  static const Duration replyTimeout = Duration(seconds: 1);

  Future<void> _queue = Future.value();

  static Uint8List buildFrame(int monitorId, List<int> data) {
    final bytes = <int>[data.length + 4, monitorId & 0xFF, 0x00, ...data];
    bytes.add(bytes.fold(0, (a, b) => a ^ b));
    return Uint8List.fromList(bytes);
  }

  static SicpReply parseReply(List<int> bytes) {
    if (bytes.length < 4 || bytes[0] != bytes.length) return const SicpReply(SicpStatus.invalid);

    final checksum = bytes.sublist(0, bytes.length - 1).fold(0, (a, b) => a ^ b);
    if (checksum != bytes.last) return const SicpReply(SicpStatus.invalid);

    final data = bytes.sublist(3, bytes.length - 1);
    if (data.isEmpty) return const SicpReply(SicpStatus.invalid);

    if (data[0] == cmdGetLed && data.length >= 5) {
      return SicpReply(SicpStatus.ledReport, LedState(on: data[1] == 1, r: data[2], g: data[3], b: data[4]));
    }

    // Reports are `[0x00, code]`; tolerate a bare code as the first data byte as well.
    final code = data[0] == 0x00 && data.length >= 2 ? data[1] : data[0];
    switch (code) {
      case ack:
        return const SicpReply(SicpStatus.ack);
      case nack:
        return const SicpReply(SicpStatus.nack);
      case nav:
        return const SicpReply(SicpStatus.nav);
    }

    return const SicpReply(SicpStatus.invalid);
  }

  static String toHex(List<int> bytes) =>
      bytes.map((b) => b.toRadixString(16).padLeft(2, '0').toUpperCase()).join(' ');

  Future<SicpResult> setLed(String host, int port, int monitorId,
      {required bool on, required int r, required int g, required int b}) {
    return _send(host, port, buildFrame(monitorId, [cmdSetLed, on ? 1 : 0, r, g, b]));
  }

  Future<SicpResult> getLed(String host, int port, int monitorId) {
    return _send(host, port, buildFrame(monitorId, [cmdGetLed]));
  }

  Future<SicpResult> _send(String host, int port, Uint8List frame) {
    final result = _queue.then((_) => _exchange(host, port, frame));
    _queue = result.then((_) {}, onError: (_) {});
    return result;
  }

  Future<SicpResult> _exchange(String host, int port, Uint8List frame) async {
    final tx = toHex(frame);
    Socket socket;

    try {
      socket = await Socket.connect(host, port, timeout: connectTimeout);
    } catch (e) {
      return SicpResult(status: SicpStatus.unreachable, tx: tx, error: e.toString());
    }

    final buffer = <int>[];
    final done = Completer<void>();
    final sub = socket.listen(
      (chunk) {
        buffer.addAll(chunk);
        if (buffer.isNotEmpty && buffer.length >= buffer[0] && !done.isCompleted) done.complete();
      },
      onError: (_) {
        if (!done.isCompleted) done.complete();
      },
      onDone: () {
        if (!done.isCompleted) done.complete();
      },
    );

    try {
      socket.add(frame);
      await socket.flush();
      await done.future.timeout(replyTimeout);
    } on TimeoutException {
      // Handled below: an empty or short buffer means no complete reply.
    } catch (e) {
      await sub.cancel();
      socket.destroy();
      return SicpResult(status: SicpStatus.unreachable, tx: tx, rx: toHex(buffer), error: e.toString());
    }

    await sub.cancel();
    socket.destroy();

    final rx = toHex(buffer);
    if (buffer.isEmpty || buffer.length < buffer[0]) {
      return SicpResult(status: SicpStatus.timeout, tx: tx, rx: rx);
    }

    final reply = parseReply(buffer.sublist(0, buffer[0]));
    return SicpResult(status: reply.status, led: reply.led, tx: tx, rx: rx);
  }
}
