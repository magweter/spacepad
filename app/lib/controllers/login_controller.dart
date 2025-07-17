import 'dart:io';
import 'dart:core';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_udid/flutter_udid.dart';
import 'package:get/get.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/services/server_service.dart';
import 'package:spacepad/services/api_service.dart';

class LoginController extends GetxController {
  final AuthService _authService = AuthService.instance;
  final ServerService _serverService = ServerService();
  final RxBool loading = false.obs;
  final RxBool isSelfHosted = false.obs;
  final RxString url = ''.obs;
  final RxString code = ''.obs;
  final RxBool submitActive = false.obs;
  final DeviceInfoPlugin deviceInfoPlugin = DeviceInfoPlugin();

  void toggleSelfHosted(bool value) {
    isSelfHosted.value = value;
    _updateSubmitActive();
  }

  void urlChanged(String value) {
    url.value = value;
    _updateSubmitActive();
  }

  void codeChanged(String value) {
    code.value = value;
    _updateSubmitActive();
  }

  void _updateSubmitActive() {
    if (isSelfHosted.value) {
      submitActive.value = url.value.isNotEmpty && code.value.length == 6;
    } else {
      submitActive.value = code.value.length == 6;
    }
  }

  bool _isValidUrl(String url) {
    try {
      final uri = Uri.parse(url);
      return uri.isAbsolute && (uri.scheme == 'http' || uri.scheme == 'https');
    } catch (e) {
      return false;
    }
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
    try {
      if (isSelfHosted.value) {
        if (!_isValidUrl(url.value)) {
          Toast.showError('invalid_url'.tr);
          return;
        }

        var trimmedUrl = url.value.endsWith('/') ? url.value.substring(0, url.value.length - 1) : url.value;
        if (!await _serverService.isServerReachable(trimmedUrl)) {
          Toast.showError('server_unreachable'.tr);
          return;
        }

        // Set the custom base URL for the API service
        await ApiService.setBaseUrl(trimmedUrl);
      } else {
        await ApiService.resetToServerBaseUrl();
      }

      final deviceId = await getDeviceId() ?? 'Unknown device';
      final deviceName = await getDeviceName() ?? 'Unknown model';
      await _authService.login(code.value, deviceId, deviceName);
    } catch (e) {
      Toast.showError('login_failed'.tr);
    } finally {
      loading.value = false;
    }
  }
}