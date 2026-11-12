import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:spacepad/components/action_panel.dart';
import 'package:spacepad/components/frosted_panel.dart';
import 'package:spacepad/components/status_title.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/models/display_settings_model.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/models/event_status.dart';
import 'package:spacepad/pages/dashboard_page.dart';
import 'package:spacepad/translations/translations.dart';

const _roomName = 'Boardroom Lighthouse';

/// A subject long enough to run past three lines, like the one that pushed into the buttons.
const _longTitle = "Jamie Doe internal review 'Automatically processing incoming work orders' "
    'followed by the planning for next quarter and the open questions from last week';

/// The dashboard's state without the services behind it: no API, no timers.
class _FakeDashboardController extends DashboardController {
  final DisplaySettingsModel settings;
  final List<EventModel> fakeEvents;

  _FakeDashboardController(this.settings, this.fakeEvents);

  @override
  void onInit() {
    updateTime();
    globalDisplay = DisplayModel(id: '1', name: _roomName, settings: settings);
    globalSettings.value = settings;
    events.assignAll(fakeEvents);
    loading.value = false;
  }
}

DisplaySettingsModel _settings({String timelineMode = 'none', String size = 'normal'}) {
  return DisplaySettingsModel(
    checkInEnabled: false,
    bookingEnabled: true,
    hasCustomBooking: false,
    checkInGracePeriod: 5,
    checkInMinutes: 15,
    calendarEnabled: true,
    hideAdminActions: false,
    showMeetingTitle: true,
    fontFamily: 'Inter',
    extendEnabled: true,
    showOrganizer: true,
    timelineWidgetEnabled: timelineMode != 'none',
    timelineWidgetMode: timelineMode,
    fontSizeRoomName: size,
    fontSizeClock: size,
    fontSizeTitle: size,
    fontSizeDetails: size,
    fontSizeToolbar: size,
    fontSizeCalendar: size,
  );
}

List<EventModel> _events() {
  final now = DateTime.now();
  return [
    EventModel(
      id: 'current',
      status: EventStatus.confirmed,
      summary: _longTitle,
      start: now.subtract(const Duration(minutes: 20)),
      end: now.add(const Duration(minutes: 40)),
      organizerName: 'Jamie Doe',
    ),
    EventModel(
      id: 'next',
      status: EventStatus.confirmed,
      summary: 'Quarterly assessment',
      start: now.add(const Duration(minutes: 60)),
      end: now.add(const Duration(minutes: 90)),
    ),
  ];
}

/// A 1024x600 room panel, the tightest common screen, and an iPad in landscape.
const _screens = [Size(1024, 600), Size(1180, 820)];

Future<void> _pumpDashboard(WidgetTester tester, DisplaySettingsModel settings, {Size screen = const Size(1024, 600)}) async {
  tester.view.physicalSize = screen;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  addTearDown(Get.reset);

  // Start from an empty tree and a fresh controller, so a second pump in one test is not served
  // the controller of the first.
  await tester.pumpWidget(const SizedBox());
  Get.reset();
  Get.put<DashboardController>(_FakeDashboardController(settings, _events()));
  // Dutch, because its labels run longer than the English ones.
  await tester.pumpWidget(GetMaterialApp(
    translations: AppTranslations(),
    locale: const Locale('nl'),
    home: const DashboardPage(),
  ));
  await tester.pump();
}

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);

  for (final screen in _screens) {
  for (final mode in ['none', 'side_panel', 'inline', 'full_panel']) {
    for (final size in ['normal', 'xlarge']) {
      testWidgets('a long title keeps clear of the header and the bottom bar (${screen.width.toInt()}x${screen.height.toInt()}, $mode, $size)', (tester) async {
        await _pumpDashboard(tester, _settings(timelineMode: mode, size: size), screen: screen);

        expect(tester.takeException(), isNull, reason: 'the layout overflowed');

        final header = tester.getRect(find.text(_roomName));
        final title = tester.getRect(find.byType(StatusTitle));
        final buttons = tester.getRect(find.byType(ActionPanel));
        final bottomBar = tester.getRect(find.byKey(const ValueKey('bottom-toolbar')));

        expect(title.top - header.bottom, greaterThanOrEqualTo(12), reason: 'title pressed against the header');
        // The room name sits in the top right corner, in line with the right edge of the bottom bar.
        expect(header.right, closeTo(bottomBar.right, 2), reason: 'room name not in the corner');
        expect(bottomBar.top - buttons.bottom, greaterThanOrEqualTo(12), reason: 'buttons pressed against the bottom bar');

        final lines = tester.widget<Text>(find.descendant(of: find.byType(StatusTitle), matching: find.byType(Text))).maxLines!;
        expect(lines, inInclusiveRange(1, 3));
      });
    }
  }
  }

  testWidgets('a larger size makes the text larger', (tester) async {
    await _pumpDashboard(tester, _settings(size: 'normal'));
    final normal = tester.getRect(find.text(_roomName)).height;

    await _pumpDashboard(tester, _settings(size: 'xlarge'));
    final xlarge = tester.getRect(find.text(_roomName)).height;

    expect(xlarge, closeTo(normal * 1.5, 2));
  });
}
