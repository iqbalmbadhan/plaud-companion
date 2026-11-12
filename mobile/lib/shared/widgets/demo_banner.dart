import 'package:flutter/material.dart';
import '../../core/theme/app_tokens.dart';

/// Clearly labels Phase 1 demo mode so users never confuse mock data with live backend state.
class DemoModeBanner extends StatelessWidget {
  const DemoModeBanner({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Phase 1 Demo Mode active. Displaying sample workspace data.',
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: compact ? AppSpacing.xxs : AppSpacing.xs,
        ),
        decoration: BoxDecoration(
          color: AppColors.paleEmerald,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          border: Border.all(color: AppColors.emeraldAccent.withOpacity(0.35)),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.science_outlined,
              size: 16,
              color: AppColors.deepNavy,
            ),
            const SizedBox(width: AppSpacing.xs),
            Expanded(
              child: Text(
                'DEMO DATA · Phase 1 UI Preview (no live backend connected)',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: AppColors.deepNavy,
                    ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
