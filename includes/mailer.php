<?php
/**
 * Envoi d'emails via PHPMailer (dossier /utilitaires/PHPMailer).
 *
 * Emplacements reconnus :
 *   utilitaires/PHPMailer/src/PHPMailer.php   (archive officielle)
 *   utilitaires/PHPMailer/PHPMailer.php
 * Si PHPMailer est absent, repli sur la fonction mail() de PHP.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

function load_phpmailer(): bool {
    if (class_exists(PHPMailer::class)) {
        return true;
    }
    $candidates = [
        UTIL_PATH . '/PHPMailer/src',
        UTIL_PATH . '/PHPMailer',
        UTIL_PATH . '/phpmailer/src',
        UTIL_PATH . '/phpmailer',
        UTIL_PATH . '/PHPMailer-master/src',
    ];
    foreach ($candidates as $dir) {
        if (is_file($dir . '/PHPMailer.php')) {
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
            return true;
        }
    }
    error_log('NEOSEN: PHPMailer introuvable dans ' . UTIL_PATH . ' — repli sur mail().');
    return false;
}

/**
 * Envoie un email HTML.
 *
 * @param string|array $to       destinataire(s)
 * @param array        $options  reply_to => [email, nom], attachments => [[chemin, nom]], text => version texte
 */
function send_mail($to, string $subject, string $html, array $options = []): bool {
    $recipients = is_array($to) ? $to : array_map('trim', explode(',', $to));
    $recipients = array_values(array_filter($recipients, fn($r) => filter_var($r, FILTER_VALIDATE_EMAIL)));
    if (!$recipients) {
        return false;
    }

    $fromEmail = env('MAIL_FROM_ADDRESS', setting('contact_email'));
    $fromName  = env('MAIL_FROM_NAME', site_name());
    $text      = $options['text'] ?? trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $html))));

    if (load_phpmailer()) {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            if (env('MAIL_HOST')) {
                $mail->isSMTP();
                $mail->Host       = env('MAIL_HOST');
                $mail->Port       = (int) env('MAIL_PORT', 587);
                $mail->SMTPAuth   = (bool) env('MAIL_USERNAME');
                $mail->Username   = (string) env('MAIL_USERNAME', '');
                $mail->Password   = (string) env('MAIL_PASSWORD', '');
                $enc = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
                if ($enc === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($enc === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                $mail->Timeout = 15;
            }
            $mail->setFrom($fromEmail, $fromName);
            foreach ($recipients as $r) {
                $mail->addAddress($r);
            }
            if (!empty($options['reply_to'][0])) {
                $mail->addReplyTo($options['reply_to'][0], $options['reply_to'][1] ?? '');
            }
            foreach ($options['attachments'] ?? [] as [$path, $name]) {
                if (is_file($path)) {
                    $mail->addAttachment($path, $name);
                }
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;
            return $mail->send();
        } catch (MailerException $e) {
            error_log('NEOSEN: échec envoi email — ' . $mail->ErrorInfo);
            return false;
        }
    }

    // Repli : mail() natif
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
    ];
    if (!empty($options['reply_to'][0])) {
        $headers[] = 'Reply-To: ' . $options['reply_to'][0];
    }
    return @mail(implode(',', $recipients), mb_encode_mimeheader($subject), $html, implode("\r\n", $headers));
}

/**
 * Gabarit HTML simple et sobre pour les emails.
 */
function mail_layout(string $title, string $content): string {
    $primary = e(setting('color_primary'));
    $site = e(site_name());
    return '<!doctype html><html lang="fr"><body style="margin:0;background:#f4f5f9;font-family:Arial,Helvetica,sans-serif;color:#0a0f1f">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:16px;overflow:hidden">'
        . '<tr><td style="padding:24px 32px;border-bottom:3px solid ' . $primary . ';font-weight:700;font-size:20px;letter-spacing:.08em">' . $site . '</td></tr>'
        . '<tr><td style="padding:32px"><h1 style="margin:0 0 20px;font-size:20px">' . e($title) . '</h1>' . $content . '</td></tr>'
        . '<tr><td style="padding:16px 32px;background:#f8f9fc;color:#6b7280;font-size:12px">' . $site . ' · ' . e(setting('site_domain')) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}
