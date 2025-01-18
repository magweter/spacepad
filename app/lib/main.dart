import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:spacepad/pages/login_page.dart';
import 'package:spacepad/pages/splash_page.dart';
import 'package:spacepad/theme.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:wakelock_plus/wakelock_plus.dart';
import 'package:timezone/data/latest.dart' as tz;

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await dotenv.load(fileName: ".env");

  await AuthService.instance.initialise();

  tz.initializeTimeZones();

  WakelockPlus.enable();

  SystemChrome.setEnabledSystemUIMode(SystemUiMode.manual, overlays: []);

  runApp(const App());
}

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      themeMode: ThemeMode.light,
      theme: AppTheme.data,
      initialRoute: '/',
      transitionDuration: Duration.zero,
      locale: Get.deviceLocale,
      fallbackLocale: const Locale('en'),
      debugShowCheckedModeBanner: false,
      getPages: [
        GetPage(name: '/', page: () {
          if (AuthService.instance.getAuthToken() != null) {
            return const SplashPage();
          }

          return const LoginPage();
        })
      ],
    );
  }
}