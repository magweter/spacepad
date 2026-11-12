import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:spacepad/controllers/login_controller.dart';
import 'package:spacepad/exceptions/api_exception.dart';

void main() {
  group('LoginController.errorMessageKey', () {
    test(
        'a 429 from the pairing endpoint is reported as a throttle, not a wrong code',
        () {
      final key = LoginController.errorMessageKey(
        ApiException(code: 429, message: 'Too Many Attempts.'),
      );

      expect(key, 'login_throttled');
    });

    test('a 400 with an incorrect code is reported as a wrong code', () {
      final key = LoginController.errorMessageKey(
        ApiException(
            code: 400,
            message: 'Code is incorrect.',
            errors: {'code': 'incorrect'}),
      );

      expect(key, 'login_failed');
    });

    test('a TLS handshake failure keeps its own message', () {
      final key =
          LoginController.errorMessageKey(const HandshakeException('bad cert'));

      expect(key, 'ssl_error');
    });

    test('anything else falls back to the generic failure', () {
      final key = LoginController.errorMessageKey(Exception('boom'));

      expect(key, 'login_failed');
    });
  });
}
