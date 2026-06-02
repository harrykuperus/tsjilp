<?php

require_once __DIR__ . '/../config/secrets.php';

function send_email(string $to, string $name, string $subject, string $textBody, string $htmlBody = ''): bool {

    require_once __DIR__ . '/class-phpmailer.php';

    $mail = new PHPMailer(true);

    try {
        $safeName = trim(preg_replace('/\s+/', ' ', $name));
        $safeName = str_replace(["\r", "\n"], '', $safeName);

        $mail->isSMTP();
        $mail->Host       = secret('SMTP_HOST') ?: 'localhost';
        $mail->Port       = (int)(secret('SMTP_PORT') ?: 587);
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = secret('SMTP_SECURE') ?: 'tls';
        $mail->Username   = secret('SMTP_USERNAME');
        $mail->Password   = secret('SMTP_PASSWORD');

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $fromEmail = secret('SMTP_FROM_EMAIL') ?: 'noreply@localhost';
        $fromName  = secret('SMTP_FROM_NAME') ?: 'Tsjilp';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $safeName);

        $mail->Subject = $subject;
        $mail->Body    = $htmlBody ?: nl2br(htmlspecialchars($textBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $mail->AltBody = $textBody;

        return $mail->send() === true;

    } catch (Throwable $e) {
        error_log('Mailer throwable: ' . $e->getMessage());
        error_log('Mailer errorInfo: ' . $mail->ErrorInfo);
        return false;
    }
}