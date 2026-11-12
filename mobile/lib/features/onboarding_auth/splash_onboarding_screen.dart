import 'package:flutter/material.dart';
import '../../core/theme/app_tokens.dart';

enum AuthMode { signIn, register, resetPassword, verifyEmail }

class SplashOnboardingScreen extends StatefulWidget {
  const SplashOnboardingScreen({super.key, required this.onAuthenticated});

  final VoidCallback onAuthenticated;

  @override
  State<SplashOnboardingScreen> createState() => _SplashOnboardingScreenState();
}

class _SplashOnboardingScreenState extends State<SplashOnboardingScreen> {
  AuthMode _mode = AuthMode.signIn;
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController(text: 'owner@example.com');
  final _passwordController = TextEditingController(text: 'DemoPassword123!');
  final _nameController = TextEditingController();
  bool _obscurePassword = true;
  String? _statusMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  void _submit() {
    if (_mode == AuthMode.verifyEmail) {
      widget.onAuthenticated();
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) return;

    if (_mode == AuthMode.resetPassword) {
      setState(() {
        _statusMessage =
            'If an account exists for ${_emailController.text.trim()}, a reset link has been queued (Demo).';
      });
      return;
    }
    if (_mode == AuthMode.register) {
      setState(() {
        _mode = AuthMode.verifyEmail;
        _statusMessage =
            'Verification code sent to ${_emailController.text.trim()} (Demo).';
      });
      return;
    }
    widget.onAuthenticated();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.deepNavy,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 40,
                              height: 40,
                              decoration: BoxDecoration(
                                color: AppColors.deepNavy,
                                borderRadius: BorderRadius.circular(AppRadius.sm),
                              ),
                              child: const Icon(
                                Icons.graphic_eq,
                                color: AppColors.emeraldAccent,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'PLAUD Companion',
                                  style: Theme.of(context).textTheme.titleLarge,
                                ),
                                Text(
                                  'Phase 1 Demo Workspace',
                                  style: Theme.of(context).textTheme.labelMedium,
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        Text(
                          switch (_mode) {
                            AuthMode.signIn => 'Sign in to your workspace',
                            AuthMode.register => 'Create your account',
                            AuthMode.resetPassword => 'Reset your password',
                            AuthMode.verifyEmail => 'Verify your email',
                          },
                          style: Theme.of(context).textTheme.headlineMedium,
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Turn transcripts into structured team notes, grounded AI Q&A, and .docx exports.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        if (_statusMessage != null) ...[
                          Container(
                            padding: const EdgeInsets.all(AppSpacing.sm),
                            decoration: BoxDecoration(
                              color: AppColors.paleEmerald,
                              borderRadius: BorderRadius.circular(AppRadius.sm),
                            ),
                            child: Text(
                              _statusMessage!,
                              style: const TextStyle(color: AppColors.deepNavy),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                        ],
                        if (_mode == AuthMode.register) ...[
                          TextFormField(
                            controller: _nameController,
                            decoration: const InputDecoration(
                              labelText: 'Full name',
                              hintText: 'Alex Morgan',
                            ),
                            validator: (v) => (v == null || v.trim().isEmpty)
                                ? 'Enter your name'
                                : null,
                          ),
                          const SizedBox(height: AppSpacing.md),
                        ],
                        if (_mode != AuthMode.verifyEmail) ...[
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              labelText: 'Work email',
                              hintText: 'you@company.com',
                            ),
                            validator: (v) {
                              if (v == null || !v.contains('@')) {
                                return 'Enter a valid email address';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: AppSpacing.md),
                        ],
                        if (_mode == AuthMode.signIn ||
                            _mode == AuthMode.register) ...[
                          TextFormField(
                            controller: _passwordController,
                            obscureText: _obscurePassword,
                            decoration: InputDecoration(
                              labelText: 'Password',
                              suffixIcon: IconButton(
                                tooltip: _obscurePassword
                                    ? 'Show password'
                                    : 'Hide password',
                                onPressed: () => setState(
                                  () => _obscurePassword = !_obscurePassword,
                                ),
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                              ),
                            ),
                            validator: (v) {
                              if (v == null || v.length < 8) {
                                return 'Password must be at least 8 characters';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: AppSpacing.md),
                        ],
                        ElevatedButton(
                          onPressed: _submit,
                          child: Text(
                            switch (_mode) {
                              AuthMode.signIn => 'Continue to Demo Workspace',
                              AuthMode.register => 'Create Account',
                              AuthMode.resetPassword => 'Send Reset Link',
                              AuthMode.verifyEmail => 'Complete Verification',
                            },
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          alignment: WrapAlignment.spaceBetween,
                          children: [
                            if (_mode != AuthMode.register)
                              TextButton(
                                onPressed: () => setState(() {
                                  _statusMessage = null;
                                  _mode = AuthMode.register;
                                }),
                                child: const Text('Create account'),
                              ),
                            if (_mode != AuthMode.signIn)
                              TextButton(
                                onPressed: () => setState(() {
                                  _statusMessage = null;
                                  _mode = AuthMode.signIn;
                                }),
                                child: const Text('Back to sign in'),
                              ),
                            if (_mode == AuthMode.signIn)
                              TextButton(
                                onPressed: () => setState(() {
                                  _statusMessage = null;
                                  _mode = AuthMode.resetPassword;
                                }),
                                child: const Text('Forgot password?'),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
