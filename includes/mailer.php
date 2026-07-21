<?php
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Returns true if SMTP has been configured in config.php.
 */
function mail_is_configured(): bool {
    return SMTP_HOST !== '' && SMTP_USERNAME !== '' && SMTP_PASSWORD !== '';
}

/**
 * Sends the password reset email. Returns true on success, false on failure
 * (including "not configured" — check mail_is_configured() first if you
 * want to distinguish that case from a real send failure).
 */
function send_reset_email(string $toEmail, string $toName, string $resetLink): bool {
    if (!mail_is_configured()) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your Canopy password';
        $mail->Body = '
            <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
                <h2 style="color:#0A3323;">Reset your password</h2>
                <p>Hi ' . htmlspecialchars($toName) . ',</p>
                <p>We received a request to reset your Canopy password. Click the
                button below to choose a new one. This link expires in 1 hour.</p>
                <p style="margin: 24px 0;">
                    <a href="' . htmlspecialchars($resetLink) . '"
                       style="background:#0A3323; color:#F7F4D5; padding:12px 22px;
                              border-radius:8px; text-decoration:none; font-weight:bold;">
                        Reset Password
                    </a>
                </p>
                <p style="color:#888; font-size:13px;">
                    If you didn\'t request this, you can safely ignore this email.
                </p>
            </div>';
        $mail->AltBody = "Reset your Canopy password: {$resetLink} (expires in 1 hour)";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}
