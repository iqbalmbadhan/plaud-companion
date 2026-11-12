import 'package:flutter/material.dart';
import 'app/app_shell.dart';
import 'core/theme/app_theme.dart';
import 'features/onboarding_auth/splash_onboarding_screen.dart';

void main() {
  runApp(const PlaudCompanionApp());
}

class PlaudCompanionApp extends StatefulWidget {
  const PlaudCompanionApp({super.key, this.initialAuthenticated = false});

  final bool initialAuthenticated;

  @override
  State<PlaudCompanionApp> createState() => _PlaudCompanionAppState();
}

class _PlaudCompanionAppState extends State<PlaudCompanionApp> {
  late bool _isAuthenticated = widget.initialAuthenticated;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'PLAUD Companion',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: _isAuthenticated
          ? AppShell(
              onSignOut: () => setState(() => _isAuthenticated = false),
            )
          : SplashOnboardingScreen(
              onAuthenticated: () => setState(() => _isAuthenticated = true),
            ),
    );
  }
}
