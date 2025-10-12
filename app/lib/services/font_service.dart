import 'package:google_fonts/google_fonts.dart';
import 'package:flutter/material.dart';

class FontService {
  FontService._();
  static final FontService instance = FontService._();

  // Available fonts
  static const List<String> availableFonts = [
    'Inter',
    'Roboto',
    'Open Sans',
    'Lato',
    'Poppins',
    'Montserrat',
  ];

  // Font family mapping
  static const Map<String, String> fontFamilyMapping = {
    'Inter': 'Inter',
    'Roboto': 'Roboto',
    'Open Sans': 'OpenSans',
    'Lato': 'Lato',
    'Poppins': 'Poppins',
    'Montserrat': 'Montserrat',
  };

  /// Get TextStyle for a specific font family
  TextStyle getTextStyle({
    required String fontFamily,
    double? fontSize,
    FontWeight? fontWeight,
    Color? color,
    double? letterSpacing,
    double? height,
  }) {
    final googleFontFamily = fontFamilyMapping[fontFamily] ?? 'Inter';
    
    switch (googleFontFamily) {
      case 'Inter':
        return GoogleFonts.inter(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      case 'Roboto':
        return GoogleFonts.roboto(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      case 'OpenSans':
        return GoogleFonts.openSans(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      case 'Lato':
        return GoogleFonts.lato(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      case 'Poppins':
        return GoogleFonts.poppins(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      case 'Montserrat':
        return GoogleFonts.montserrat(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
      default:
        return GoogleFonts.inter(
          fontSize: fontSize,
          fontWeight: fontWeight,
          color: color,
          letterSpacing: letterSpacing,
          height: height,
        );
    }
  }

  /// Preload fonts to avoid loading delays
  Future<void> preloadFonts() async {
    for (final fontFamily in availableFonts) {
      final googleFontFamily = fontFamilyMapping[fontFamily] ?? 'Inter';
      
      try {
        switch (googleFontFamily) {
          case 'Inter':
            await GoogleFonts.pendingFonts([GoogleFonts.inter()]);
            break;
          case 'Roboto':
            await GoogleFonts.pendingFonts([GoogleFonts.roboto()]);
            break;
          case 'OpenSans':
            await GoogleFonts.pendingFonts([GoogleFonts.openSans()]);
            break;
          case 'Lato':
            await GoogleFonts.pendingFonts([GoogleFonts.lato()]);
            break;
          case 'Poppins':
            await GoogleFonts.pendingFonts([GoogleFonts.poppins()]);
            break;
          case 'Montserrat':
            await GoogleFonts.pendingFonts([GoogleFonts.montserrat()]);
            break;
        }
      } catch (e) {
        // Font loading failed, continue with others
        print('Failed to load font $fontFamily: $e');
      }
    }
  }

  /// Force reload a specific font
  Future<void> reloadFont(String fontFamily) async {
    final googleFontFamily = fontFamilyMapping[fontFamily] ?? 'Inter';
    
    try {
      print('FontService: Reloading font: $fontFamily (mapped to: $googleFontFamily)');
      
      switch (googleFontFamily) {
        case 'Inter':
          await GoogleFonts.pendingFonts([GoogleFonts.inter()]);
          break;
        case 'Roboto':
          await GoogleFonts.pendingFonts([GoogleFonts.roboto()]);
          break;
        case 'OpenSans':
          await GoogleFonts.pendingFonts([GoogleFonts.openSans()]);
          break;
        case 'Lato':
          await GoogleFonts.pendingFonts([GoogleFonts.lato()]);
          break;
        case 'Poppins':
          await GoogleFonts.pendingFonts([GoogleFonts.poppins()]);
          break;
        case 'Montserrat':
          await GoogleFonts.pendingFonts([GoogleFonts.montserrat()]);
          break;
      }
    } catch (e) {
      print('Failed to reload font $fontFamily: $e');
    }
  }

  /// Get font display name for UI
  String getFontDisplayName(String fontFamily) {
    return fontFamily;
  }

  /// Validate if font is available
  bool isFontAvailable(String fontFamily) {
    return availableFonts.contains(fontFamily);
  }
}
