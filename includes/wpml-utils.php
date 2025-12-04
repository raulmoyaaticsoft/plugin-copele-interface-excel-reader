<?php
/**
 * Funciones auxiliares WPML seguras.
 */
defined('ABSPATH') || exit;

function wpml_is_active(): bool {
    return function_exists('apply_filters') && defined('ICL_SITEPRESS_VERSION');
}

/**
 * Obtiene TRID de un elemento (producto, variación, categoría).
 */
function wpml_get_content_trid(string $element_type, int $element_id) {
    if (!wpml_is_active()) return null;
    $type = apply_filters('wpml_element_type', $element_type);
    return apply_filters('wpml_element_trid', null, $element_id, $type);
}

/**
 * Asigna idioma y TRID a un elemento (producto o taxonomía) de forma segura.
 */
function safe_wpml_set_language(int $element_id, string $element_type, ?int $trid, string $lang, ?string $source_lang = null, string $tag = ''): void {
    if (!wpml_is_active() || !$element_id) return;

    $payload = [
        'element_id'            => $element_id,
        'element_type'          => $element_type,
        'language_code'         => $lang,
        'source_language_code'  => $source_lang,
    ];
    if ($trid) $payload['trid'] = $trid;

    do_action('wpml_set_element_language_details', $payload);
    escribir_log_debug("🌐 WPML asignado ({$tag}) → element_id={$element_id} type={$element_type} lang={$lang} trid=" . var_export($trid, true));
}

/**
 * Devuelve el ID de producto traducido por SKU e idioma.
 */
function get_valid_id_by_sku_and_lang(string $sku, string $lang, string $tipoEsperado = 'product'): ?int {
    global $wpdb;
    $sku = strtoupper(trim($sku));

    $id = wc_get_product_id_by_sku($sku);
    if (!$id) {
        $id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_sku'
              AND UPPER(TRIM(meta_value)) = %s
            LIMIT 1
        ", $sku));
    }
    if (!$id) return null;

    $pt = get_post_type($id);
    $st = get_post_status($id);
    if (!in_array($st, ['publish', 'draft', 'private'], true)) return null;

    if (!wpml_is_active() || $lang === 'es') return (int)$id;

    $type = apply_filters('wpml_element_type', $pt);
    $trid = apply_filters('wpml_element_trid', null, $id, $type);
    if (!$trid) return (int)$id;

    $translations = apply_filters('wpml_get_element_translations', [], $trid, $type);
    if (!empty($translations[$lang])) {
        $tid = (int)($translations[$lang]->element_id ?? 0);
        $tst = get_post_status($tid);
        return in_array($tst, ['publish', 'draft', 'private'], true) ? $tid : null;
    }
    return (int)$id;
}
