import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:spacepad/date_format_helper.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:spacepad/translations/translations.dart';

Map<String, dynamic> eventJson({bool? isAllDay}) => {
      'id': 'evt-1',
      'status': 'confirmed',
      'summary': 'Training day',
      'start': '2026-11-09T23:00:00+00:00',
      'end': '2026-11-10T23:00:00+00:00',
      if (isAllDay != null) 'isAllDay': isAllDay,
    };

void main() {
  test('reads the all-day flag from the API', () {
    expect(EventModel.fromJson(eventJson(isAllDay: true)).isAllDay, isTrue);
  });

  test('an older backend without the flag reads as a timed event', () {
    expect(EventModel.fromJson(eventJson()).isAllDay, isFalse);
  });

  testWidgets('an all-day event shows as all day instead of midnight to midnight', (tester) async {
    late String allDay;
    late String timed;

    await tester.pumpWidget(GetMaterialApp(
      translations: AppTranslations(),
      locale: const Locale('en', 'US'),
      home: Builder(builder: (context) {
        allDay = formatEventTimes(context, EventModel.fromJson(eventJson(isAllDay: true)));
        timed = formatEventTimes(context, EventModel.fromJson(eventJson(isAllDay: false)));
        return const SizedBox();
      }),
    ));

    expect(allDay, 'All day');
    expect(timed, contains(' - '));
  });
}
