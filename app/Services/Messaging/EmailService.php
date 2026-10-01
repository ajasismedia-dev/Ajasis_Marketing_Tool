<?php

namespace App\Services\Messaging;

use Exception;

class EmailService
{
    /**
     * Check if PHPMailer dependency is installed and available
     */
    public static function isDependencyAvailable(): bool
    {
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }

    /**
     * Check if SMTP is configured and enabled in config
     */
    public static function isConfigured(): bool
    {
        if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
            return false;
        }

        if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
            return false;
        }

        if (!defined('SMTP_FROM_EMAIL') || empty(SMTP_FROM_EMAIL)) {
            return false;
        }

        return true;
    }

    /**
     * Validate email format and prevent CRLF injection in email field
     */
    public static function validateEmail(string $email): bool
    {
        $email = trim($email);
        if (empty($email)) {
            return false;
        }
        if (strpos($email, "\r") !== false || strpos($email, "\n") !== false) {
            return false;
        }
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Sanitize subject by replacing newline and carriage returns with a single space
     */
    public static function sanitizeSubject(string $subject): string
    {
        $clean = trim(preg_replace('/[\r\n]+/', ' ', $subject));
        if (mb_strlen($clean) > 255) {
            $clean = mb_substr($clean, 0, 255);
        }
        return $clean;
    }

    /**
     * Get human-readable and machine-readable SMTP status
     *
     * @return array
     */
    public static function getStatus(): array
    {
        if (!self::isDependencyAvailable()) {
            return [
                'code' => 'dependency_missing',
                'status' => 'dependency_missing',
                'configured' => false,
                'reason' => 'PHPMailer dependency is missing',
                'label' => 'Bağımlılık Eksik',
                'badge_class' => 'status-dependency-missing'
            ];
        }

        if (!self::isConfigured()) {
            return [
                'code' => 'unconfigured',
                'status' => 'unconfigured',
                'configured' => false,
                'reason' => 'SMTP is not configured in config.php',
                'label' => 'Yapılandırılmadı',
                'badge_class' => 'status-unconfigured'
            ];
        }

        return [
            'code' => 'ready',
            'status' => 'ready',
            'configured' => true,
            'reason' => 'SMTP is configured and ready',
            'label' => 'Hazır',
            'badge_class' => 'status-ready'
        ];
    }

    /**
     * Send a single plain-text email via PHPMailer SMTP
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $body
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function send(string $toEmail, string $toName = '', string $subject = '', string $body = ''): array
    {
        if (!self::isDependencyAvailable()) {
            return [
                'success' => false,
                'error' => 'PHPMailer kütüphanesi yüklü değil. Lütfen sunucuda "composer install" çalıştırın.'
            ];
        }

        if (!self::isConfigured()) {
            return [
                'success' => false,
                'error' => 'SMTP yapılandırması etkin değil veya eksik.'
            ];
        }

        $toEmail = trim($toEmail);
        if (!self::validateEmail($toEmail)) {
            return [
                'success' => false,
                'error' => 'Geçersiz alıcı e-posta adresi.'
            ];
        }

        // Clean subject of CR/LF injection
        $cleanSubject = self::sanitizeSubject($subject);

        if (mb_strlen($body) > 10000) {
            return [
                'success' => false,
                'error' => 'Mesaj gövdesi maksimum 10.000 karakter olabilir.'
            ];
        }

        if (empty($body)) {
            return [
                'success' => false,
                'error' => 'E-posta içeriği boş olamaz.'
            ];
        }

        try {
            /** @var \PHPMailer\PHPMailer\PHPMailer $mail */
            $mailClass = 'PHPMailer\\PHPMailer\\PHPMailer';
            $mail = new $mailClass(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->Timeout = 10;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;

            if (defined('SMTP_USERNAME') && !empty(SMTP_USERNAME)) {
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            } else {
                $mail->SMTPAuth = false;
            }

            // Encryption
            $enc = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls';
            if ($enc === 'tls') {
                if (defined('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS')) {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = 'tls';
                }
            } elseif ($enc === 'ssl') {
                if (defined('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS')) {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = 'ssl';
                }
            }

            // Sender
            $fromEmail = SMTP_FROM_EMAIL;
            $fromName = defined('SMTP_FROM_NAME') && !empty(SMTP_FROM_NAME) ? SMTP_FROM_NAME : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Ajasis Media');
            $mail->setFrom($fromEmail, $fromName);

            if (defined('SMTP_REPLY_TO') && !empty(SMTP_REPLY_TO) && self::validateEmail(SMTP_REPLY_TO)) {
                $mail->addReplyTo(SMTP_REPLY_TO);
            }

            // Recipient
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(false);
            $mail->Subject = $cleanSubject;
            $mail->Body = $body;

            $mail->send();

            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            // Strip any sensitive credentials from error message
            $safeError = 'E-posta gönderilirken bir hata oluştu: ' . $e->getMessage();
            if (defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD)) {
                $safeError = str_replace(SMTP_PASSWORD, '***', $safeError);
            }
            if (defined('SMTP_USERNAME') && !empty(SMTP_USERNAME)) {
                $safeError = str_replace(SMTP_USERNAME, '***', $safeError);
            }
            return ['success' => false, 'error' => $safeError];
        }
    }
}
