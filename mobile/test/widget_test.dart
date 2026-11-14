import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:plaud_companion/core/services/app_state.dart';
import 'package:plaud_companion/main.dart';

void main() {
  testWidgets('Auth screen displays PLAUD Companion and signs in', (tester) async {
    tester.view.physicalSize = const Size(450, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(const PlaudCompanionApp());
    expect(find.text('PLAUD Companion'), findsOneWidget);
    expect(find.text('Start Using PLAUD Companion'), findsOneWidget);

    await tester.tap(find.text('Start Using PLAUD Companion'));
    await tester.pumpAndSettle();

    expect(find.text('Recent Recordings'), findsOneWidget);
  });

  testWidgets('5-tab mobile navigation transitions across core screens', (tester) async {
    tester.view.physicalSize = const Size(450, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(const PlaudCompanionApp(initialAuthenticated: true));

    expect(find.byType(NavigationBar), findsOneWidget);

    // 1. Transcripts tab
    await tester.tap(find.text('Transcripts'));
    await tester.pumpAndSettle();
    expect(find.text('Transcripts & Recordings'), findsOneWidget);

    // 2. AI tab
    await tester.tap(find.text('AI Studio'));
    await tester.pumpAndSettle();
    expect(find.text('AI Meeting Intelligence'), findsOneWidget);

    // 3. Exports tab
    await tester.tap(find.text('Exports'));
    await tester.pumpAndSettle();
    expect(find.text('Export Deliverables'), findsOneWidget);

    // 4. Hardware tab
    await tester.tap(find.text('Hardware'));
    await tester.pumpAndSettle();
    expect(find.text('Workspace & Hardware Settings'), findsOneWidget);
  });

  testWidgets('Export service generates valid DOCX and PDF bytes', (tester) async {
    final t = AppState.instance.transcripts.first;
    final docx = await AppState.instance.exportTranscript(transcript: t, format: 'docx');
    expect(docx.title.endsWith('.docx'), isTrue);
    expect(docx.sizeBytes > 500, isTrue);

    final pdf = await AppState.instance.exportTranscript(transcript: t, format: 'pdf');
    expect(pdf.title.endsWith('.pdf'), isTrue);
    expect(pdf.sizeBytes > 200, isTrue);
  });
}
