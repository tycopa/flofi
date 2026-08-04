<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('send_mail')) {
    function send_mail(string $to, string $toName, string $subject, string $body): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = MAIL_SECURE;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPOptions = unserialize(MAIL_SMTP_OPTIONS);

            $mail->setFrom(MAIL_FROM, APP_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
