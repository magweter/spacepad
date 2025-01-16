import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  AppTheme._();

  static const Color black = Color(0xFF000000);
  static const Color oxford = Color(0xFF14213D);
  static const Color orange = Color(0xFFFCA311);
  static const Color platinum = Color(0xFFE5E5E5);
  static const Color white = Color(0xFFFFFFFF);

  static ThemeData get data {
    return ThemeData(
        textTheme: GoogleFonts.interTextTheme(),
        scaffoldBackgroundColor: Colors.white,
        colorScheme: ColorScheme.fromSeed(seedColor: oxford),
        inputDecorationTheme: InputDecorationTheme(
          hintStyle: const TextStyle(fontSize: 14.5),
          filled: true,
          fillColor: Colors.white,
          isDense: true,
          isCollapsed: true,
          contentPadding: const EdgeInsets.only(
              top: 14,
              bottom: 10,
              left: 20,
              right: 20
          ),
          outlineBorder: const BorderSide(color: oxford, width: 1),
          border: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
          errorBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
          focusedBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
          disabledBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
          enabledBorder: OutlineInputBorder(
            borderSide: const BorderSide(color: oxford, width: 1),
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        dividerColor: Colors.grey.shade300,
        dividerTheme: DividerThemeData(
          color: Colors.grey.shade300,
          thickness: .5,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            foregroundColor: Colors.black,
            backgroundColor: orange,
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w500,
            ),
            elevation: 0,
            shadowColor: Colors.transparent,
            padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 15)
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.black,
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w500,
            ),
            elevation: 0,
            shadowColor: Colors.transparent,
            padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 15),
            side: const BorderSide(color: oxford, width: 1.5), // Border color and width
          ),
        ),
        appBarTheme: const AppBarTheme(
          systemOverlayStyle: SystemUiOverlayStyle.light,
          foregroundColor: Colors.white,
          titleTextStyle: TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 24,
            height: 0.5
          )
        ),
        textButtonTheme: TextButtonThemeData(
            style: TextButton.styleFrom(
              foregroundColor: Colors.black,
              textStyle: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w400,
              ),
            )
        ),
        floatingActionButtonTheme: FloatingActionButtonThemeData(
          extendedPadding: const EdgeInsets.symmetric(horizontal: 25, vertical: 0),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(100)
          ),
        ),
    );
  }
}