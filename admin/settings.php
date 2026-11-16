<?php
/**
 * Paramètres & contenus éditables (généré à partir de config/settings_schema.php).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
admin_csrf_guard();
$lang = admin_lang();

$schema = settings_schema();
$tab = array_key_exists($_GET['tab'] ?? '', $schema) ? $_GET['tab'] : array_key_first($schema);
$group = $schema[$tab];

if (is_post()) {
    $errors = [];
    $current = settings_all($lang);
    foreach ($group['fields'] as $key => $field) {
        $type = $field['type'];
        if ($type === 'image' || $type === 'video') {
            $old = $current[$key] ?? null;
            // Ne jamais supprimer physiquement un fichier par défaut (assets/)
            $new = handle_media_field($key, $old ?? null, 'settings', $type, $errors);
            if (!empty($_POST[$key . '_remove'])) {
                $new = '';
            }
            if ($new !== $old) {
                save_setting($key, $new, $lang);
            }
            continue;
        }
        if (!array_key_exists($key, $_POST)) {
            continue;
        }
        $value = is_array($_POST[$key]) ? end($_POST[$key]) : (string) $_POST[$key];
        $value = trim(str_replace("\r\n", "\n", $value));
        switch ($type) {
            case 'email':
                foreach (csv_list($value) as $mail) {
                    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $errors[] = "« {$field['label']} » : adresse invalide ({$mail}).";
                }
                break;
            case 'url':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "« {$field['label']} » : URL invalide.";
                    continue 2;
                }
                break;
            case 'color':
                if (!preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                    $errors[] = "« {$field['label']} » : couleur invalide (format #RRGGBB).";
                    continue 2;
                }
                break;
            case 'bool':
                $value = $value === '1' ? '1' : '0';
                break;
            case 'html':
                $value = clean_html($value);
                break;
            case 'select':
                if (!array_key_exists($value, $field['options'])) continue 2;
                break;
        }
        save_setting($key, $value, $lang);
    }
    flash($errors ? 'warning' : 'success', $errors ? 'Enregistré partiellement : ' . implode(' ', $errors) : 'Modifications enregistrées.');
    redirect(admin_url('settings.php', ['tab' => $tab]));
}

$values = settings_all($lang);
$val = fn($key, $field) => array_key_exists($key, $values) && $values[$key] !== null ? $values[$key] : ($field['default'] ?? '');

admin_header('Textes, médias & réglages', 'settings', [[icon('external', 'icon') . 'Voir le site', url(), 'btn-ghost']]);
?>
<?= admin_lang_switcher() ?>
<div class="settings-layout">
    <nav class="settings-nav" aria-label="Groupes de paramètres">
        <?php foreach ($schema as $key => $g): ?>
        <a href="<?= e(admin_url('settings.php', ['tab' => $key])) ?>" class="<?= $key === $tab ? 'is-active' : '' ?>"><?= icon($g['icon'] ?? 'settings') ?><span><?= e($g['label']) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form method="post" enctype="multipart/form-data" class="card settings-form" data-dirty-check>
        <?= csrf_field() ?>
        <div class="card-head"><h2><?= e($group['label']) ?></h2></div>
        <div class="form-grid">
        <?php foreach ($group['fields'] as $key => $field):
            $value = $val($key, $field);
            $help = $field['help'] ?? null;
            $wide = in_array($field['type'], ['textarea', 'html', 'lines', 'image', 'video'], true) ? 'span-2' : '';
            switch ($field['type']) {
                case 'textarea':
                    echo f_textarea($key, $field['label'], $value, ['rows' => 3, 'help' => $help, 'class' => $wide]);
                    break;
                case 'lines':
                    echo f_textarea($key, $field['label'], $value, ['rows' => 5, 'help' => $help ?? 'Une entrée par ligne.', 'class' => $wide]);
                    break;
                case 'html':
                    echo f_textarea($key, $field['label'], $value, ['rows' => 12, 'rich' => true, 'help' => $help, 'class' => $wide]);
                    break;
                case 'image':
                case 'video':
                    echo f_media($key, $field['label'], $value ?: null, ['kind' => $field['type'], 'help' => $help, 'class' => $wide]);
                    break;
                case 'color':
                    echo f_color($key, $field['label'], $value, $help);
                    break;
                case 'bool':
                    echo f_toggle($key, $field['label'], $value === '1', $help);
                    break;
                case 'select':
                    echo f_select($key, $field['label'], $field['options'], $value, ['help' => $help]);
                    break;
                default:
                    $type = ['email' => 'text', 'url' => 'url', 'tel' => 'tel'][$field['type']] ?? 'text';
                    echo f_text($key, $field['label'], $value, ['type' => $type, 'help' => $help]);
            }
        endforeach; ?>
        </div>
        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn-primary"><?= icon('check') ?> Enregistrer</button>
        </div>
    </form>
</div>
<?php admin_footer(); ?>
