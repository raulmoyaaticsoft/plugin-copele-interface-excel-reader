<?php
/**
 * Utilidades para creación y sincronización de categorías WooCommerce.
 */
defined('ABSPATH') || exit;

/**
 * Crea la jerarquía completa de categorías a partir del texto de subcategoría.
 * Ejemplo: "aves_bebederos_colgantes" → crea jerarquía anidada.
 *
 * @return array lista de niveles creados o existentes [ ['id'=>..,'slug'=>..,'name'=>..], ... ]
 */
function build_full_category_hierarchy_es(string $subcat_raw): array {
    $tax = 'product_cat';
    $out = [];
    $raw = strtolower(trim($subcat_raw));
    if ($raw === '') return $out;

    $raw = str_replace('/', '_', $raw);
    $niveles = array_filter(explode('_', $raw), fn($v) => trim($v) !== '');
    if (!$niveles) return $out;

    $parent_id = 0;
    $acc_slug  = '';
    foreach ($niveles as $i => $p) {
        $slug_clean = sanitize_title($p);
        $name_clean = ucwords(str_replace('-', ' ', $slug_clean));
        $slug_final = $i === 0 ? $slug_clean : $acc_slug . '-' . $slug_clean;

        $term = get_term_by('slug', $slug_final, $tax);
        if (!$term) {
            $res = wp_insert_term($name_clean, $tax, [
                'slug'   => $slug_final,
                'parent' => $parent_id
            ]);
            if (is_wp_error($res)) {
                escribir_log_debug("⚠️ Error creando categoría '{$name_clean}' → " . $res->get_error_message());
                continue;
            }
            $term_id = (int)$res['term_id'];
            $term = get_term($term_id, $tax);
            escribir_log_debug("🧩 Creada categoría '{$term->name}' (slug={$term->slug}) parent={$term->parent}");
        } else {
            escribir_log_debug("✔ Reutilizada categoría '{$term->name}' (slug={$term->slug})");
        }

        $parent_id = (int)$term->term_id;
        $acc_slug  = $slug_final;

        $out[] = ['id' => (int)$term->term_id, 'slug' => $term->slug, 'name' => $term->name];
    }
    return $out;
}

/**
 * Busca o crea traducciones de categorías existentes (si WPML activo).
 *
 * @param array  $cats_es Jerarquía original del español
 * @param string $langDestino Idioma a traducir (en, fr, etc.)
 * @return array IDs traducidos listos para asignar
 */
function obtener_traducciones_categoria_hierarchy(array $cats_es, string $langDestino = 'en'): array {
    $traducidas = [];
    foreach ($cats_es as $cat) {
        $tr_id = wpml_is_active()
            ? apply_filters('wpml_object_id', $cat['id'], 'product_cat', false, $langDestino)
            : null;

        if (!$tr_id) {
            // Crear “sombra” traducida si no existe
            $term_es = get_term($cat['id'], 'product_cat');
            if ($term_es && !is_wp_error($term_es)) {
                $res = wp_insert_term($term_es->name, 'product_cat', [
                    'slug'   => $term_es->slug . '-' . $langDestino,
                    'parent' => 0,
                ]);
                if (!is_wp_error($res)) {
                    $tr_id = $res['term_id'];
                    if (wpml_is_active()) {
                        $trid = wpml_get_content_trid('tax_product_cat', $cat['id']);
                        do_action('wpml_set_element_language_details', [
                            'element_id'            => $tr_id,
                            'element_type'          => 'tax_product_cat',
                            'trid'                  => $trid,
                            'language_code'         => $langDestino,
                            'source_language_code'  => 'es',
                        ]);
                    }
                    escribir_log_debug("🧩 Creada traducción de categoría '{$term_es->name}' → {$langDestino}");
                }
            }
        }
        if ($tr_id) $traducidas[] = (int)$tr_id;
    }
    return $traducidas;
}
