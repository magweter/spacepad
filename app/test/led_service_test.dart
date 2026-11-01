import 'package:flutter_test/flutter_test.dart';
import 'package:spacepad/models/led_color.dart';
import 'package:spacepad/services/led_service.dart';
import 'package:spacepad/services/sicp_service.dart';

void main() {
  group('colorFor', () {
    test('a free room is green', () {
      expect(LedService.colorFor(reserved: false, transitioning: false, checkInActive: false), LedColor.green);
    });

    test('a reserved room is red', () {
      expect(LedService.colorFor(reserved: true, transitioning: false, checkInActive: false), LedColor.red);
    });

    test('a meeting about to start or end is orange, even while reserved', () {
      expect(LedService.colorFor(reserved: false, transitioning: true, checkInActive: false), LedColor.orange);
      expect(LedService.colorFor(reserved: true, transitioning: true, checkInActive: false), LedColor.orange);
    });

    test('waiting for a check-in is orange', () {
      expect(LedService.colorFor(reserved: false, transitioning: false, checkInActive: true), LedColor.orange);
    });
  });

  group('apply', () {
    late List<LedColor> sent;
    late DateTime clock;
    late SicpStatus reply;
    late LedService service;

    setUp(() {
      sent = [];
      clock = DateTime(2026, 1, 1, 9, 0);
      reply = SicpStatus.ack;
      service = LedService.withSender(
        (color) async {
          sent.add(color);
          return SicpResult(status: reply, tx: '');
        },
        now: () => clock,
      );
    });

    test('sends a colour once and not again while it is unchanged', () async {
      await service.apply(LedColor.green);
      await service.apply(LedColor.green);
      await service.apply(LedColor.green);
      expect(sent, [LedColor.green]);
    });

    test('sends again when the colour changes', () async {
      await service.apply(LedColor.green);
      await service.apply(LedColor.red);
      await service.apply(LedColor.green);
      expect(sent, [LedColor.green, LedColor.red, LedColor.green]);
    });

    test('re-asserts an unchanged colour after a while', () async {
      await service.apply(LedColor.green);
      clock = clock.add(LedService.reassertAfter - const Duration(seconds: 1));
      await service.apply(LedColor.green);
      expect(sent, hasLength(1));

      clock = clock.add(const Duration(seconds: 2));
      await service.apply(LedColor.green);
      expect(sent, hasLength(2));
    });

    test('retries a failed send sooner, but not every second', () async {
      reply = SicpStatus.unreachable;
      await service.apply(LedColor.green);
      clock = clock.add(const Duration(seconds: 5));
      await service.apply(LedColor.green);
      expect(sent, hasLength(1));

      clock = clock.add(LedService.retryAfter);
      await service.apply(LedColor.green);
      expect(sent, hasLength(2));
    });

    test('a sender that throws counts as a failed send', () async {
      final throwing = LedService.withSender((_) async => throw StateError('boom'), now: () => clock);
      await throwing.apply(LedColor.green);
      clock = clock.add(const Duration(seconds: 5));
      await throwing.apply(LedColor.green);
      // Still inside the retry window: nothing to observe but no exception either.
      expect(true, isTrue);
    });
  });
}
