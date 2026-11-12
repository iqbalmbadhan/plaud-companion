import 'package:flutter/material.dart';

/// Official design tokens derived from Docs/UX.md.
abstract final class AppColors {
  // Brand & Surfaces
  static const Color deepNavy = Color(0xFF101B33);
  static const Color navySurface = Color(0xFF172541);
  static const Color emeraldAccent = Color(0xFF18A879);
  static const Color paleEmerald = Color(0xFFE8F7F1);

  // Neutral Surfaces & Borders
  static const Color mainBackground = Color(0xFFF6F8FB);
  static const Color whiteSurface = Color(0xFFFFFFFF);
  static const Color border = Color(0xFFE5EAF0);

  // Typography
  static const Color primaryText = Color(0xFF172033);
  static const Color secondaryText = Color(0xFF667085);

  // Semantic States
  static const Color success = Color(0xFF18A879);
  static const Color warning = Color(0xFFD97706);
  static const Color warningSurface = Color(0xFFFEF3C7);
  static const Color error = Color(0xFFDC2626);
  static const Color errorSurface = Color(0xFFFEE2E2);
  static const Color info = Color(0xFF2563EB);
  static const Color infoSurface = Color(0xFFDBEAFE);
}

abstract final class AppSpacing {
  static const double xxs = 4.0;
  static const double xs = 8.0;
  static const double sm = 12.0;
  static const double md = 16.0;
  static const double lg = 24.0;
  static const double xl = 32.0;
  static const double xxl = 48.0;

  // Minimum accessible touch target size
  static const double minTouchTarget = 48.0;

  // Responsive breakpoint (mobile bottom bar vs desktop sidebar)
  static const double desktopBreakpoint = 900.0;
}

abstract final class AppRadius {
  static const double sm = 8.0;
  static const double md = 12.0;
  static const double lg = 16.0;
  static const double pill = 999.0;
}
