<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues single-use password reset links (60-minute expiry, only the SHA-256 hash is stored).
 */
class PasswordResetService
{
    /**
     * @return array{url: string, mailed: bool, show_link: bool}
     *   show_link is true only outside production when no real mailer is configured,
     *   so the link can be shown on screen during development.
     */
    public function issue(User $user): array
    {
        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $url = route('password.reset', ['token' => $plainToken, 'email' => $user->email]);
        $mailer = strtolower((string) config('mail.default', 'log'));
        $showLink = !app()->environment('production') && in_array($mailer, ['log', 'array'], true);
        if ($showLink) {
            return ['url' => $url, 'mailed' => false, 'show_link' => true];
        }

        try {
            Mail::raw("You recently requested to reset your password for PLAUD Companion. Click the link below to set a new password:\n\n{$url}\n\nThis password reset link will expire in 60 minutes.\nIf you did not request a password reset, no further action is required.", function ($message) use ($user): void {
                $message->to($user->email, $user->display_name ?: $user->email)
                    ->subject('Reset Your PLAUD Companion Password');
            });

            return ['url' => $url, 'mailed' => true, 'show_link' => false];
        } catch (\Throwable $e) {
            report($e);

            return ['url' => $url, 'mailed' => false, 'show_link' => false];
        }
    }
}
