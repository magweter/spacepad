import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:spacepad/models/device_model.dart';
import 'package:spacepad/pages/dashboard_page.dart';
import 'package:spacepad/pages/display_page.dart';
import 'package:spacepad/pages/login_page.dart';
import 'package:spacepad/services/api_service.dart';

class AuthService {
  AuthService._();
  static final AuthService instance = AuthService._();

  late SharedPreferences _sharedPrefs;
  Rxn<DeviceModel> currentDevice = Rxn<DeviceModel>();

  Future<void> initialise() async {
    _sharedPrefs = await SharedPreferences.getInstance();
  }

  Future<void> setBaseUrl(String url) async {
    await ApiService.setBaseUrl(url);
  }

  Future<void> login(String code, String uid, String name) async {
    Map result = await ApiService.post('auth/login', {
      "code" : code,
      "uid" : uid,
      "name" : name,
    });

    Map data = result['data'];

    await setAuthToken(data['token']);
    currentDevice.value = DeviceModel.fromJson(data['device']);

    await Get.offAll(
            () => currentDevice.value?.display != null ?
        const DashboardPage() :
        const DisplayPage()
    );
  }

  Future<void> verify() async {
    try {
      Map result = await ApiService.get('devices/me');

      Map data = result['data'];

      currentDevice.value = DeviceModel.fromJson(data);

      await Get.offAll(
              () => currentDevice.value?.display != null ?
                const DashboardPage() :
                const DisplayPage()
      );
    } catch(e) {
      if (kDebugMode) print(e);
    }

    signOut();
  }

  Future<void> changeDisplay(Map body) async {
    Map result = await ApiService.put('devices/display', body);

    Map data = result['data'];

    currentDevice.value = DeviceModel.fromJson(data);
  }

  Future<void> signOut() async {
    currentDevice.value = null;

    await deleteAuthToken();

    await Get.offAll(() => const LoginPage());
  }

  String? getAuthToken() {
    return _sharedPrefs.getString('token');
  }

  Future<bool> setAuthToken(String token) {
    return _sharedPrefs.setString('token', token);
  }

  Future<bool> deleteAuthToken() {
    return _sharedPrefs.remove('token');
  }
}