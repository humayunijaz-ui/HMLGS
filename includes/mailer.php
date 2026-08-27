<?php
/**
 * send_email() - application-wide helper for outgoing notification emails.
 * Uses the SMTP_* constants and SmtpMailer class. Both are loaded here so
 * this file has no external dependency ordering requirements beyond PHP itself.
 *
 * Returns true on success, false on failure. On failure, the SMTP error
 * (if any) is written to PHP's error log rather than shown to the user,
 * so a mail outage never blocks merit-list generation itself.
 */
require_once __DIR__ . '/SmtpMailer.php';

function send_email($toEmail, $toName, $subject, $htmlBody)
{
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (SMTP_HOST === '' || SMTP_USERNAME === '') {
        // Mail not configured yet (see config/config.php) - skip silently rather than fatal-erroring.
        error_log('send_email(): SMTP not configured - skipped email to ' . $toEmail);
        return false;
    }

    try {
        $mailer = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION);
        $ok = $mailer->send(SMTP_FROM_EMAIL, SMTP_FROM_NAME, $toEmail, $toName, $subject, $htmlBody);
        if (!$ok) {
            error_log('send_email() failed for ' . $toEmail . ': ' . $mailer->lastError);
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('send_email() exception for ' . $toEmail . ': ' . $e->getMessage());
        return false;
    }
}
