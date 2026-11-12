import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:spacepad/components/status_title.dart';

const _longTitle = 'Jamie Doe internal review Automatically processing incoming work orders '
    'and a great many more words that no longer fit on the screen';

/// A column of [height] with a fixed block above the title, the way the logo and the action
/// buttons take their share of the dashboard before the title gets what is left.
Widget _layout({required double height, required double reserved}) {
  return Directionality(
    textDirection: TextDirection.ltr,
    child: Center(
      child: SizedBox(
        width: 400,
        height: height,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: reserved),
            const Flexible(
              child: StatusTitle(text: _longTitle, style: TextStyle(fontSize: 40)),
            ),
          ],
        ),
      ),
    ),
  );
}

int _maxLines(WidgetTester tester) => tester.widget<Text>(find.text(_longTitle)).maxLines!;

void main() {
  testWidgets('a long title stops at three lines when there is room for more', (tester) async {
    await tester.pumpWidget(_layout(height: 600, reserved: 0));

    expect(_maxLines(tester), 3);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a long title gives up lines rather than pushing into its neighbours', (tester) async {
    // 100px left for a 40px line: two lines fit, a third would overflow.
    await tester.pumpWidget(_layout(height: 200, reserved: 100));

    expect(_maxLines(tester), 2);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a title always keeps at least one line', (tester) async {
    await tester.pumpWidget(_layout(height: 130, reserved: 100));

    expect(_maxLines(tester), 1);
  });
}
