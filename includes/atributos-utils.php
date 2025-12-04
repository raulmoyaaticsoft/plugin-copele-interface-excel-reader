<?php
/**
 * Utilidades de atributos y taxonomías globales WooCommerce.
 */
defined('ABSPATH') || exit;

/**
 * Crea el atributo global si no existe.
 */
function crear_o_registrar_atributo(string $tax_name, string $label): void {
    $attribute_id = wc_attribute_taxonomy_id_by_name($tax_name);
    if (!$attribute_id) {
        wc_create_attribute([
            'slug'         => $tax_name,
            'name'         => ucfirst(str_replace('_', ' ', $label)),
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ]);
        register_taxonomy($tax_name, 'product', ['hierarchical' => false]);
        escribir_log_debug("➕ Atributo global creado: {$tax_name}");
    }
}

/**
 * Crea los términos del atributo si no existen.
 *
 * @return array valores sanitizados existentes o creados
 */
function crear_terminos_atributo(string $tax_name, string $valores_raw): array {
    $valores_raw = trim((string)$valores_raw);
    if ($valores_raw === '') return [];

    $valores = preg_split('/[,;\/]+/', $valores_raw, -1, PREG_SPLIT_NO_EMPTY);
    $valores = array_values(array_unique(array_map('trim', $valores)));

    foreach ($valores as $v) {
        $term = term_exists($v, $tax_name);
        if (!$term) {
            $res = wp_insert_term($v, $tax_name);
            if (is_wp_error($res)) {
                escribir_log_debug("⚠️ Error creando término '{$v}' en {$tax_name}: " . $res->get_error_message());
            } else {
                escribir_log_debug("🧩 Término '{$v}' creado en {$tax_name}");
            }
        }
    }
    return $valores;
}

/**
 * Garantiza que el atributo y términos existan y devuelve array listo para set_attributes()
 */
function ensure_attribute_and_terms(string $key, $val): array {
    $tax_slug = 'pa_' . sanitize_title($key);
    crear_o_registrar_atributo($tax_slug, $key);
    return crear_terminos_atributo($tax_slug, is_array($val) ? implode(',', $val) : (string)$val);
}
