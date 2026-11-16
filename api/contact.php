<?php
/**
 * API — Traitement du formulaire de contact.
 *
 * 1. Vérifie CSRF, pot de miel, délai minimal, limite de fréquence et reCAPTCHA
 * 2. Valide les champs côté serveur
 * 3. Enregistre la demande dans MySQL (+ pièce jointe éventuelle)
 * 4. Notifie NEOSEN par email (PHPMailer) et envoie un accusé de réception
 * 5. Répond en JSON (fetch) ou redirige vers /contact (sans JavaScript)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/includes/recaptcha.php';
require_once ROOT_PATH . '/includes/mailer.php';

if (!empty($_POST['lang'])) {
    current_lang((string) $_POST['lang']);
}

/**
 * Termine la requête avec une réponse adaptée (JSON ou redirection).
 */
function contact_respond(bool $ok, array $errors = [], array $old = []): void {
    if (wants_json()) {
        json_response([
            'success' => $ok,
            'message' => $ok ? (string) setting('contact_success') : ($errors['_form'] ?? t('form.fix_errors')),
            'errors'  => $errors,
        ], $ok ? 200 : 422);
    }
    if ($ok) {
        $_SESSION['contact_sent'] = true;
    } else {
        $_SESSION['contact_errors'] = $errors ?: ['_form' => t('form.error.generic')];
        unset($old['_csrf'], $old['recaptcha_token'], $old['website']);
        $_SESSION['contact_old'] = $old;
    }
    redirect(url('contact') . '#formulaire', 303);
}

if (!is_post()) {
    redirect(url('contact'));
}

// Requête trop volumineuse : PHP vide $_POST
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    contact_respond(false, ['attachment' => t('form.error.file'), '_form' => 'Pièce jointe trop volumineuse (5 Mo maximum).']);
}

$input = [];
foreach (['first_name', 'last_name', 'company', 'email', 'phone', 'project_type', 'budget', 'message'] as $field) {
    $input[$field] = trim((string) ($_POST[$field] ?? ''));
}
$input['consent'] = !empty($_POST['consent']);
$input['email'] = mb_strtolower($input['email']);

/* ------------------------------------------------------------------
 * Protection anti-spam
 * ------------------------------------------------------------------ */
if (!csrf_check()) {
    contact_respond(false, ['_form' => t('form.error.csrf')], $input);
}

// Pot de miel rempli ou formulaire envoyé en moins de 3 secondes : robot probable.
// On répond « succès » pour ne pas renseigner le robot, sans rien enregistrer.
$elapsed = time() - (int) ($_POST['form_ts'] ?? 0);
if (!empty($_POST['website']) || $elapsed < 3) {
    error_log('NEOSEN: soumission de contact bloquée (pot de miel / délai) depuis ' . client_ip());
    contact_respond(true);
}

// Limite : 3 demandes par IP sur 10 minutes
$stmt = db()->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$stmt->execute([client_ip()]);
if ((int) $stmt->fetchColumn() >= 3) {
    contact_respond(false, ['_form' => t('form.error.rate')], $input);
}

$token = (string) ($_POST['recaptcha_token'] ?? ($_POST['g-recaptcha-response'] ?? ''));
if (!recaptcha_verify($token, 'contact')) {
    contact_respond(false, ['_form' => t('form.error.spam')], $input);
}

/* ------------------------------------------------------------------
 * Validation côté serveur
 * ------------------------------------------------------------------ */
$errors = [];
$limits = ['first_name' => 100, 'last_name' => 100, 'company' => 150, 'email' => 190, 'phone' => 40, 'project_type' => 100, 'budget' => 100, 'message' => 5000];
foreach (['first_name', 'last_name', 'email', 'project_type', 'message'] as $required) {
    if ($input[$required] === '') {
        $errors[$required] = t('form.error.required');
    }
}
foreach ($limits as $field => $max) {
    if (!isset($errors[$field]) && mb_strlen($input[$field]) > $max) {
        $errors[$field] = t('form.error.length');
    }
}
if (!isset($errors['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = t('form.error.email');
}
if ($input['phone'] !== '' && !preg_match('/^[+()\d\s.\-]{6,40}$/', $input['phone'])) {
    $errors['phone'] = t('form.error.phone');
}
if (!isset($errors['message']) && mb_strlen($input['message']) < 10) {
    $errors['message'] = t('form.error.message_short');
}
$types = setting_lines('contact_project_types');
if (!isset($errors['project_type']) && $types && !in_array($input['project_type'], $types, true)) {
    $errors['project_type'] = t('form.error.required');
}
$budgets = setting_lines('contact_budgets');
if ($input['budget'] !== '' && $budgets && !in_array($input['budget'], $budgets, true)) {
    $errors['budget'] = t('form.error.required');
}
if (!$input['consent']) {
    $errors['consent'] = t('form.error.consent');
}
// Liens multiples dans le message : spam probable
if (!isset($errors['message']) && preg_match_all('#https?://#i', $input['message']) > 3) {
    $errors['message'] = t('form.error.spam');
}

$attachment = null;
if (!$errors && has_upload($_FILES['attachment'] ?? null)) {
    $result = store_attachment($_FILES['attachment']);
    if (isset($result['error'])) {
        $errors['attachment'] = $result['error'];
    } else {
        $attachment = $result;
    }
}

if ($errors) {
    contact_respond(false, $errors, $input);
}

/* ------------------------------------------------------------------
 * Enregistrement
 * ------------------------------------------------------------------ */
$stmt = db()->prepare(
    'INSERT INTO contact_messages
        (first_name, last_name, company, email, phone, project_type, budget, message, attachment_path, attachment_name, ip_address, user_agent)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $input['first_name'], $input['last_name'], $input['company'] ?: null, $input['email'], $input['phone'] ?: null,
    $input['project_type'], $input['budget'] ?: null, $input['message'],
    $attachment['path'] ?? null, $attachment['name'] ?? null,
    client_ip(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
]);
$messageId = (int) db()->lastInsertId();

/* ------------------------------------------------------------------
 * Notifications email
 * ------------------------------------------------------------------ */
$fullName = $input['first_name'] . ' ' . $input['last_name'];
$rows = [
    'Nom'           => $fullName,
    'Entreprise'    => $input['company'],
    'Email'         => $input['email'],
    'Téléphone'     => $input['phone'],
    'Type de projet'=> $input['project_type'],
    'Budget'        => $input['budget'],
];
$table = '<table cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">';
foreach ($rows as $label => $value) {
    if ($value !== '') {
        $table .= '<tr><td style="border-bottom:1px solid #eef0f5;color:#6b7280;width:150px">' . e($label) . '</td><td style="border-bottom:1px solid #eef0f5"><strong>' . e($value) . '</strong></td></tr>';
    }
}
$table .= '</table>';
$adminLink = absolute_url('', APP_DEFAULT_LANG) . 'admin/messages.php?id=' . $messageId;
$html = mail_layout('Nouvelle demande de projet', $table
    . '<p style="margin:24px 0 8px;color:#6b7280;font-size:13px">Message</p>'
    . '<div style="background:#f8f9fc;border-radius:12px;padding:16px;font-size:14px;line-height:1.6">' . nl2br(e($input['message'])) . '</div>'
    . ($attachment ? '<p style="font-size:13px;color:#6b7280">Pièce jointe : ' . e($attachment['name']) . '</p>' : '')
    . '<p style="margin-top:24px"><a href="' . e($adminLink) . '" style="display:inline-block;background:' . e(setting('color_primary')) . ';color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Voir dans l\'administration</a></p>');

$subject = strtr((string) setting('mail_subject'), ['{name}' => $fullName, '{type}' => $input['project_type'], '{site}' => site_name()]);
$sent = send_mail((string) setting('notification_email'), $subject, $html, [
    'reply_to'    => [$input['email'], $fullName],
    'attachments' => $attachment ? [[ROOT_PATH . '/' . $attachment['path'], $attachment['name']]] : [],
]);
if ($sent) {
    db()->prepare('UPDATE contact_messages SET mail_sent = 1 WHERE id = ?')->execute([$messageId]);
}

if (setting_bool('mail_autoreply')) {
    $text = strtr((string) setting('mail_autoreply_text'), ['{first_name}' => $input['first_name'], '{name}' => $fullName, '{site}' => site_name()]);
    send_mail($input['email'], 'Nous avons bien reçu votre demande — ' . site_name(),
        mail_layout('Merci pour votre message', '<div style="font-size:15px;line-height:1.7">' . nl2br(e($text)) . '</div>'));
}

contact_respond(true);
