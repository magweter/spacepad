import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:spacepad/pages/login_page.dart';
import 'package:spacepad/pages/splash_page.dart';
import 'package:spacepad/theme.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/translations/translations.dart';
import 'package:wakelock_plus/wakelock_plus.dart';
import 'package:timezone/data/latest.dart' as tz;

// Supported locales list
const List<Locale> supportedLocales = [
  Locale('en'),
  Locale('nl'),
  Locale('fr'),
  Locale('es'),
  Locale('de'),
  Locale('sv'),
];

// Helper function to validate if a locale is exactly supported
bool isLocaleSupported(Locale locale) {
  return supportedLocales.any((supportedLocale) => 
    supportedLocale.languageCode == locale.languageCode &&
    supportedLocale.countryCode == locale.countryCode);
}

// Helper function to get the best matching locale
Locale getBestMatchingLocale(Locale? requestedLocale) {
  if (requestedLocale == null) {
    return const Locale('en');
  }
  
  // First try exact match (both language and country code match)
  for (final supportedLocale in supportedLocales) {
    if (supportedLocale.languageCode == requestedLocale.languageCode &&
        supportedLocale.countryCode == requestedLocale.countryCode) {
      return supportedLocale;
    }
  }
  
  // Try to find a locale with the same language code (return supported locale, not original)
  for (final supportedLocale in supportedLocales) {
    if (supportedLocale.languageCode == requestedLocale.languageCode) {
      return supportedLocale;
    }
  }
  
  // Fallback to English
  return const Locale('en');
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await dotenv.load(fileName: ".env");

  await AuthService.instance.initialise();

  tz.initializeTimeZones();

  WakelockPlus.enable();

  SystemChrome.setEnabledSystemUIMode(SystemUiMode.manual, overlays: []);

  // Set a valid locale based on device locale or fallback to English
  final deviceLocale = Get.deviceLocale;
  final validLocale = getBestMatchingLocale(deviceLocale);
  
  // Debug information (remove in production)
  if (deviceLocale != null) {
    print('Device locale: ${deviceLocale.languageCode}_${deviceLocale.countryCode}');
    print('Selected locale: ${validLocale.languageCode}');
    print('Is supported: ${isLocaleSupported(deviceLocale)}');
  }
  
  Get.updateLocale(validLocale);

  runApp(const App());
}

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    // Resolve locale consistently using the same logic as in main()
    final resolvedLocale = getBestMatchingLocale(Get.locale);
    
    return GetMaterialApp(
      themeMode: ThemeMode.light,
      theme: AppTheme.data,
      initialRoute: '/',
      transitionDuration: Duration.zero,
      translations: AppTranslations(),
      locale: resolvedLocale,
      fallbackLocale: const Locale('en'),
      supportedLocales: supportedLocales,
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
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