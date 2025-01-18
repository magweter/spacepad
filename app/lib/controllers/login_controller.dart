import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_udid/flutter_udid.dart';
import 'package:get/get.dart';
import 'package:spacepad/exceptions/api_exception.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/components/toast.dart';

class LoginController extends GetxController {
  final RxString code = RxString('');
  final RxBool loading = RxBool(false);
  final DeviceInfoPlugin deviceInfoPlugin = DeviceInfoPlugin();

  void codeChanged(val) {
    code.value = val;
  }

  bool get submitActive {
    return code.value.length == 6;
  }

  Future<String?> getDeviceId() async {
    return await FlutterUdid.udid;
  }

  Future<String?> getDeviceName() async {
    if (Platform.isAndroid) {
      AndroidDeviceInfo androidInfo = await deviceInfoPlugin.androidInfo;
      return androidInfo.model;
    }

    if (Platform.isIOS) {
      IosDeviceInfo iosInfo = await deviceInfoPlugin.iosInfo;
      return iosInfo.utsname.machine;
    }

    return null;
  }

  Future<void> submit() async {
    if (loading.value) return;

    loading.value = true;

    final deviceUid = await getDeviceId();
    final deviceName = await getDeviceName();

    try {
      await AuthService.instance.login(
          code.value,
          deviceUid ?? 'Unknown device',
          deviceName ?? 'Unknown model'
      );
    } on ApiException catch (apiException) {
      if (apiException.code == 422) {
        Toast.showError('Your code is incorrect. Please refresh your dashboard to acquire the most recent code');
      }
    } catch (e) {
      Toast.showError('An unexpected error arisen. Please check if you have an internet connection');
    }

    loading.value = false;
  }
}