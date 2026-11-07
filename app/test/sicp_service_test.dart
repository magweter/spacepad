import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:spacepad/services/sicp_service.dart';

void main() {
  group('buildFrame', () {
    test('matches the documented green example for monitor 1', () {
      final frame = SicpService.buildFrame(1, [0xF3, 0x01, 0x00, 0xFF, 0x00]);
      expect(SicpService.toHex(frame), '09 01 00 F3 01 00 FF 00 05');
    });

    test('builds a Get LED frame', () {
      final frame = SicpService.buildFrame(1, [0xF4]);
      expect(SicpService.toHex(frame), '05 01 00 F4 F0');
    });
  });

  group('parseReply', () {
    test('recognises ACK, NACK and NAV reports', () {
      expect(SicpService.parseReply(SicpService.buildFrame(1, [0x00, 0x06])).status, SicpStatus.ack);
      expect(SicpService.parseReply(SicpService.buildFrame(1, [0x00, 0x15])).status, SicpStatus.nack);
      expect(SicpService.parseReply(SicpService.buildFrame(1, [0x00, 0x18])).status, SicpStatus.nav);
    });

    test('tolerates a bare status code', () {
      expect(SicpService.parseReply(SicpService.buildFrame(1, [0x06])).status, SicpStatus.ack);
    });

    test('reads an LED report', () {
      final reply = SicpService.parseReply(SicpService.buildFrame(1, [0xF4, 0x01, 0xFF, 0x80, 0x00]));
      expect(reply.status, SicpStatus.ledReport);
      expect(reply.led!.on, isTrue);
      expect(reply.led!.hex, '#FF8000');
    });

    test('rejects a bad checksum or length', () {
      expect(SicpService.parseReply([0x06, 0x01, 0x00, 0x00, 0x06, 0x00]).status, SicpStatus.invalid);
      expect(SicpService.parseReply([0x09, 0x01, 0x00, 0x00, 0x06, 0x01]).status, SicpStatus.invalid);
    });
  });

  group('exchange', () {
    late ServerSocket server;

    tearDown(() async => server.close());

    Future<void> serve(void Function(Socket socket, List<int> request) onRequest) async {
      server = await ServerSocket.bind(InternetAddress.loopbackIPv4, 0);
      server.listen((socket) => socket.listen((data) => onRequest(socket, data)));
    }

    test('returns ACK from the display', () async {
      await serve((socket, _) => socket.add(SicpService.buildFrame(1, [0x00, 0x06])));
      final result = await SicpService.instance.setLed('127.0.0.1', server.port, 1, on: true, r: 0, g: 255, b: 0);
      expect(result.status, SicpStatus.ack);
      expect(result.tx, '09 01 00 F3 01 00 FF 00 05');
    });

    test('times out when the display stays silent', () async {
      await serve((_, __) {});
      final watch = Stopwatch()..start();
      final result = await SicpService.instance.getLed('127.0.0.1', server.port, 1);
      expect(result.status, SicpStatus.timeout);
      expect(watch.elapsed, lessThan(const Duration(seconds: 2)));
    });

    test('reports unreachable on a closed port', () async {
      server = await ServerSocket.bind(InternetAddress.loopbackIPv4, 0);
      final port = server.port;
      await server.close();
      final result = await SicpService.instance.getLed('127.0.0.1', port, 1);
      expect(result.status, SicpStatus.unreachable);
    });
  });
}
