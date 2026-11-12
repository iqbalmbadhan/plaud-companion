import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:plaud_companion/core/data/demo_data.dart';
import 'package:plaud_companion/core/services/plaud_sdk_service.dart';
import 'package:plaud_companion/features/workspace/workspace_screen.dart';
import 'package:plaud_companion/main.dart';

void main() {
  testWidgets('Auth screen validates input and signs into Demo Workspace',
      (tester) async {
    await tester.pumpWidget(const PlaudCompanionApp());
    expect(find.text('Sign in to your workspace'), findsOneWidget);

    await tester.tap(find.text('Continue to Demo Workspace'));
    await tester.pumpAndSettle();

    expect(find.text('Product & Engineering Overview'), findsOneWidget);
    expect(find.text('128'), findsOneWidget);
  });

  testWidgets('5-tab mobile navigation transitions across core screens',
      (tester) async {
    tester.view.physicalSize = const Size(400, 844);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(const PlaudCompanionApp(initialAuthenticated: true));

    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byType(NavigationRail), findsNothing);

    await tester.tap(find.text('Transcripts'));
    await tester.pumpAndSettle();
    expect(find.text('Q4 Mobile Architecture & Plaud SDK Sync'), findsOneWidget);

    await tester.tap(find.text('AI'));
    await tester.pumpAndSettle();
    expect(find.text('Workspace AI Assistant'), findsOneWidget);

    await tester.tap(find.text('Exports'));
    await tester.pumpAndSettle();
    expect(find.text('Bulk Export Config'), findsOneWidget);

    await tester.tap(find.text('Workspace'));
    await tester.pumpAndSettle();
    expect(find.text('Members & Roles'), findsOneWidget);
  });

  testWidgets('Desktop layout renders NavigationRail sidebar', (tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(const PlaudCompanionApp(initialAuthenticated: true));

    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.byType(NavigationBar), findsNothing);
  });

  testWidgets('Workspace screen shows Plaud Hardware & SDK status and syncs recordings',
      (tester) async {
    final mockPlaudService = MockPlaudSdkService();

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: WorkspaceScreen(
            activeWorkspace: DemoData.workspaces.first,
            onSelectWorkspace: (_) {},
            onSignOut: () {},
            plaudService: mockPlaudService,
          ),
        ),
      ),
    );

    expect(find.text('PLAUD Hardware & SDK'), findsOneWidget);
    expect(find.text('PLAUD NOTE (8821)'), findsOneWidget);
    expect(find.text('2 pending recordings on device'), findsOneWidget);

    // Tap sync button
    await tester.tap(find.text('Sync Now'));
    await tester.pumpAndSettle();

    expect(find.text('0 pending recordings on device'), findsOneWidget);
    expect(find.text('Plaud audio recordings successfully synced to workspace!'),
        findsOneWidget);
  });
}
