/// Compile-time switches, set with `--dart-define`.
///
/// Every flag here is a `const`, so code guarded by `if (flag)` is removed from builds where
/// the flag is off. Keep it that way: a non-const read would keep the guarded code in the binary.
library;

/// The LED test page for Philips SICP displays, only in a test build:
/// `--dart-define=LED_TEST=true`. A normal build contains no trace of it.
const bool ledTestEnabled = bool.fromEnvironment('LED_TEST');

/// App version shown on the LED test page, passed by `tools/build_led_test.sh`.
const String appVersionDefine = String.fromEnvironment('APP_VERSION');
