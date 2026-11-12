import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:spacepad/components/calendar_modal.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/components/glass_theme.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/models/display_settings_model.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/models/event_status.dart';
import 'package:spacepad/pages/dashboard_page.dart';
import 'package:spacepad/translations/translations.dart';

class _FakeDashboardController extends DashboardController {
  final DisplaySettingsModel settings;
  final List<EventModel> fakeEvents;
  final String? location;
  final int? capacity;

  _FakeDashboardController(this.settings, this.fakeEvents, {this.location, this.capacity});

  @override
  void onInit() {
    updateTime();
    globalDisplay = DisplayModel(id: '1', name: 'Boardroom', location: location, capacity: capacity, settings: settings);
    globalSettings.value = settings;
    events.assignAll(fakeEvents);
    loading.value = false;
  }
}

DisplaySettingsModel _settings(String theme) => DisplaySettingsModel(
      checkInEnabled: false,
      bookingEnabled: true,
      hasCustomBooking: false,
      checkInGracePeriod: 5,
      checkInMinutes: 15,
      calendarEnabled: true,
      hideAdminActions: false,
      showMeetingTitle: true,
      theme: theme,
      fontFamily: 'Inter',
    );

EventModel _event(String id, int fromMinutes, int toMinutes) {
  final now = DateTime.now();
  return EventModel(
    id: id,
    status: EventStatus.confirmed,
    summary: 'Design sync',
    start: now.add(Duration(minutes: fromMinutes)),
    end: now.add(Duration(minutes: toMinutes)),
  );
}

Future<DashboardController> _pump(WidgetTester tester, String theme, List<EventModel> events, {String? location, int? capacity}) async {
  tester.view.physicalSize = const Size(1024, 600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  addTearDown(Get.reset);

  await tester.pumpWidget(const SizedBox());
  Get.reset();
  final controller = Get.put<DashboardController>(_FakeDashboardController(_settings(theme), events, location: location, capacity: capacity));
  await tester.pumpWidget(GetMaterialApp(
    translations: AppTranslations(),
    locale: const Locale('en'),
    home: const DashboardPage(),
  ));
  await tester.pump();
  return controller;
}

RoomTone _glowTone(WidgetTester tester) => tester.widget<GlassGlow>(find.byType(GlassGlow)).tone;

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);

  testWidgets('the classic theme draws no glow', (tester) async {
    await _pump(tester, 'classic', []);

    expect(find.byType(GlassGlow), findsNothing);
  });

  testWidgets('the glow follows the room state', (tester) async {
    await _pump(tester, 'glass', []);
    expect(_glowTone(tester), RoomTone.available);

    await _pump(tester, 'glass', [_event('now', -20, 40)]);
    expect(_glowTone(tester), RoomTone.reserved);

    // A meeting starting within ten minutes: about to change.
    await _pump(tester, 'glass', [_event('soon', 5, 35)]);
    expect(_glowTone(tester), RoomTone.transitioning);
  });

  testWidgets('an offline display turns grey and says so', (tester) async {
    final controller = await _pump(tester, 'glass', [_event('now', -20, 40)]);

    controller.isOffline.value = true;
    controller.isDataStale.value = true;
    await tester.pump();

    expect(_glowTone(tester), RoomTone.offline);
    expect(find.text('no_internet_connection'.tr), findsOneWidget);
  });

  testWidgets('the border takes the status colour', (tester) async {
    await _pump(tester, 'glass', [_event('now', -20, 40)]);

    final frame = tester.widget<AnimatedContainer>(find.ancestor(
      of: find.byType(GlassGlow),
      matching: find.byType(AnimatedContainer),
    ).last);
    expect((frame.decoration as BoxDecoration).color, GlassTokens.reserved);
  });

  testWidgets('frosted panels get the glass edge only in the glass theme', (tester) async {
    Border? edge(WidgetTester tester) {
      final panel = find.byKey(const ValueKey('bottom-toolbar'));
      final box = tester.widget<Container>(find.descendant(of: panel, matching: find.byType(Container)).first);
      return (box.decoration as BoxDecoration).border as Border?;
    }

    await _pump(tester, 'classic', []);
    expect(edge(tester), isNull);

    await _pump(tester, 'glass', []);
    expect(edge(tester)?.top.color, GlassTokens.glassBorder);
    expect(tester.widget<FrostedPanel>(find.byKey(const ValueKey('bottom-toolbar'))), isNotNull);
  });

  testWidgets('the schedule popup takes the glass edge in the glass theme', (tester) async {
    Border? edge() {
      final panel = find.descendant(of: find.byType(CalendarModal), matching: find.byType(FrostedPanel)).first;
      final box = tester.widget<Container>(find.descendant(of: panel, matching: find.byType(Container)).first);
      return (box.decoration as BoxDecoration).border as Border?;
    }

    await _pump(tester, 'glass', []);
    await tester.tap(find.text('view_schedule'.tr));
    await tester.pumpAndSettle();
    expect(edge()?.top.color, GlassTokens.glassBorder);

    await _pump(tester, 'classic', []);
    await tester.tap(find.text('view_schedule'.tr));
    await tester.pumpAndSettle();
    expect(edge(), isNull);
  });

  group('the line under the room name', () {
    String? subline(WidgetTester tester) {
      final found = find.byKey(const ValueKey('room-subline'));
      return found.evaluate().isEmpty ? null : tester.widget<Text>(found).data;
    }

    testWidgets('lists the location and the seats', (tester) async {
      await _pump(tester, 'glass', [], location: 'Floor 1', capacity: 12);
      expect(subline(tester), 'Floor 1, 12 seats');
    });

    testWidgets('says seat for one', (tester) async {
      await _pump(tester, 'classic', [], capacity: 1);
      expect(subline(tester), '1 seat');
    });

    testWidgets('shows the location alone', (tester) async {
      await _pump(tester, 'classic', [], location: 'Floor 1');
      expect(subline(tester), 'Floor 1');
    });

    testWidgets('is left out when neither is set', (tester) async {
      await _pump(tester, 'glass', [], location: '  ');
      expect(subline(tester), isNull);
    });
  });
}
