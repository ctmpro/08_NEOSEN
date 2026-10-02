<?php
/**
 * Composants de formulaire du back-office.
 */

function f_attrs(array $attrs): string {
    $out = '';
    foreach ($attrs as $k => $v) {
        if ($v === false || $v === null) continue;
        $out .= $v === true ? ' ' . $k : ' ' . $k . '="' . e($v) . '"';
    }
    return $out;
}

function f_help(?string $help): string {
    return $help ? '<p class="help">' . e($help) . '</p>' : '';
}

function f_text(string $name, string $label, $value = '', array $opt = []): string {
    $type = $opt['type'] ?? 'text';
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    return '<div class="field' . (!empty($opt['class']) ? ' ' . $opt['class'] : '') . '">'
        . '<label for="' . $id . '">' . e($label) . (!empty($opt['required']) ? ' <span class="req">*</span>' : '') . '</label>'
        . '<input type="' . e($type) . '" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"'
        . f_attrs(['required' => !empty($opt['required']), 'placeholder' => $opt['placeholder'] ?? null, 'maxlength' => $opt['maxlength'] ?? null, 'data-slug-source' => $opt['slug_source'] ?? null, 'data-slug-target' => $opt['slug_target'] ?? null, 'autocomplete' => $opt['autocomplete'] ?? null, 'min' => $opt['min'] ?? null, 'max' => $opt['max'] ?? null])
        . '>' . f_help($opt['help'] ?? null) . '</div>';
}

function f_textarea(string $name, string $label, $value = '', array $opt = []): string {
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    $rich = !empty($opt['rich']);
    $toolbar = $rich ? '<div class="rte-toolbar" data-rte-for="' . $id . '">'
        . '<button type="button" data-tag="strong" title="Gras"><b>B</b></button>'
        . '<button type="button" data-tag="em" title="Italique"><i>I</i></button>'
        . '<button type="button" data-tag="h2" title="Titre">H2</button>'
        . '<button type="button" data-tag="h3" title="Sous-titre">H3</button>'
        . '<button type="button" data-tag="p" title="Paragraphe">¶</button>'
        . '<button type="button" data-tag="ul" title="Liste">• Liste</button>'
        . '<button type="button" data-tag="a" title="Lien">Lien</button>'
        . '<button type="button" data-preview title="Aperçu">Aperçu</button>'
        . '</div><div class="rte-preview" hidden></div>' : '';
    return '<div class="field' . (!empty($opt['class']) ? ' ' . $opt['class'] : '') . '">'
        . '<label for="' . $id . '">' . e($label) . (!empty($opt['required']) ? ' <span class="req">*</span>' : '') . '</label>'
        . $toolbar
        . '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($opt['rows'] ?? 4) . '"'
        . f_attrs(['required' => !empty($opt['required']), 'placeholder' => $opt['placeholder'] ?? null, 'class' => $rich ? 'mono-area' : null])
        . '>' . e($value) . '</textarea>' . f_help($opt['help'] ?? null) . '</div>';
}

function f_select(string $name, string $label, array $options, $value = '', array $opt = []): string {
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    $html = '<div class="field' . (!empty($opt['class']) ? ' ' . $opt['class'] : '') . '"><label for="' . $id . '">' . e($label) . '</label><select id="' . $id . '" name="' . e($name) . '">';
    if (isset($opt['empty'])) {
        $html .= '<option value="">' . e($opt['empty']) . '</option>';
    }
    foreach ($options as $k => $v) {
        $html .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html . '</select>' . f_help($opt['help'] ?? null) . '</div>';
}

function f_toggle(string $name, string $label, $checked = false, ?string $help = null): string {
    return '<div class="field field--toggle"><label class="toggle"><input type="hidden" name="' . e($name) . '" value="0">'
        . '<input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span class="toggle-ui"></span><span>' . e($label) . '</span></label>'
        . f_help($help) . '</div>';
}

function f_color(string $name, string $label, $value = '#000000', ?string $help = null): string {
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    return '<div class="field"><label for="' . $id . '">' . e($label) . '</label><div class="color-field">'
        . '<input type="color" value="' . e($value) . '" data-color-sync="' . $id . '">'
        . '<input type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">'
        . '</div>' . f_help($help) . '</div>';
}

/**
 * Champ d'upload d'image/vidéo avec aperçu, suppression et URL alternative.
 */
function f_media(string $name, string $label, ?string $current, array $opt = []): string {
    $kind = $opt['kind'] ?? 'image';
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    $accept = $kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,image/avif';
    $preview = '';
    if ($current) {
        $preview = $kind === 'video'
            ? '<video src="' . e(media_url($current)) . '" muted controls preload="metadata"></video>'
            : '<img src="' . e(media_url($current)) . '" alt="">';
    }
    return '<div class="field field--media' . (!empty($opt['class']) ? ' ' . $opt['class'] : '') . '">'
        . '<label for="' . $id . '">' . e($label) . '</label>'
        . '<div class="media-input">'
        . '<div class="media-preview' . ($current ? '' : ' is-empty') . '" data-media-preview>' . ($preview ?: '<span>' . ($kind === 'video' ? 'Aucune vidéo' : 'Aucune image') . '</span>') . '</div>'
        . '<div class="media-actions">'
        . '<label class="btn btn-sm btn-ghost file-btn">' . icon('upload', 'icon') . 'Choisir un fichier<input type="file" id="' . $id . '" name="' . e($name) . '" accept="' . $accept . '" data-media-input></label>'
        . ($current ? '<label class="check-inline"><input type="checkbox" name="' . e($name) . '_remove" value="1"> Supprimer</label>' : '')
        . '<p class="help">' . e($opt['help'] ?? ($kind === 'video' ? 'MP4 ou WebM, 60 Mo max.' : 'JPG, PNG, WebP, SVG — converti automatiquement en WebP optimisé.')) . '</p>'
        . '</div></div></div>';
}

/**
 * Sélecteur d'icône (grille visuelle).
 */
function f_icon(string $name, string $label, ?string $value): string {
    $id = 'f-' . preg_replace('/\W/', '-', $name);
    $html = '<div class="field"><label>' . e($label) . '</label><div class="icon-picker" role="radiogroup">';
    foreach (icon_choices() as $choice) {
        $html .= '<label class="icon-choice" title="' . e($choice) . '"><input type="radio" name="' . e($name) . '" value="' . e($choice) . '"' . ($value === $choice ? ' checked' : '') . '>' . icon($choice) . '</label>';
    }
    return $html . '</div></div>';
}

/**
 * Traite un champ média à l'enregistrement. Retourne le nouveau chemin (ou l'ancien).
 */
function handle_media_field(string $name, ?string $current, string $dir, string $kind = 'image', array &$errors = []): ?string {
    if (!empty($_POST[$name . '_remove'])) {
        delete_media($current);
        $current = null;
    }
    $file = $_FILES[$name] ?? null;
    if (has_upload($file)) {
        $res = $kind === 'video' ? store_video($file, $dir) : store_image($file, $dir);
        if (isset($res['error'])) {
            $errors[] = $res['error'];
        } else {
            if ($current) delete_media($current);
            $current = $res['path'];
        }
    }
    return $current;
}
