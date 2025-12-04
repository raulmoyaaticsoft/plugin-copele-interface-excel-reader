<?php
/**
 * Utilidades comunes para productos WooCommerce
 */
defined('ABSPATH') || exit;

if (!function_exists('normalize_sku')) {
    /**
     * Normaliza SKUs (quita espacios y lo pasa a MAYÚSCULAS).
     */
    function normalize_sku(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}

if (!function_exists('find_existing_product_by_sku')) {
    /**
     * Devuelve el ID de un producto/variación por SKU (case-insensitive).
     * Si no lo encuentra por la API estándar, intenta búsqueda directa en postmeta.
     *
     * @param string $sku
     * @return int|false
     */
    function find_existing_product_by_sku(string $sku)
    {
        global $wpdb;

        $sku = trim($sku);
        if ($sku === '') return false;

        // 1) Intento WooCommerce
        $id = wc_get_product_id_by_sku($sku);
        if ($id) return (int)$id;

        // 2) SQL directo (insensible a mayúsculas y espacios alrededor)
        $id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sku'
              AND UPPER(TRIM(meta_value)) = UPPER(%s)
            LIMIT 1
        ", $sku));

        return $id ? (int)$id : false;
    }
}

if (!function_exists('precheck_duplicate_sku_and_cleanup')) {
    /**
     * Comprueba si existe cualquier post con ese SKU y, si está en estados problemáticos (trash/draft
     * o tipo inesperado), elimina para poder recrear. Si está publicado correctamente, no toca nada.
     *
     * @param string      $sku_final            SKU a comprobar (normalizado)
     * @param 'product'|'product_variation' $tipo_esperado Tipo que esperamos crear
     * @return array [ 'status' => 'ok'|'blocked', 'existing_id' => int|null ]
     */
    function precheck_duplicate_sku_and_cleanup(string $sku_final, string $tipo_esperado = 'product'): array
    {
        global $wpdb;

        $existing_id = wc_get_product_id_by_sku($sku_final);
        if (!$existing_id) {
            return ['status' => 'ok', 'existing_id' => null];
        }

        $post_status = get_post_status($existing_id);
        $post_type   = get_post_type($existing_id);

        // Si está publicado y el tipo coincide → bloquear recreación (ya existe "bien")
        if ($post_status === 'publish' && $post_type === $tipo_esperado) {
            return ['status' => 'blocked', 'existing_id' => (int)$existing_id];
        }

        // Si está en papelera, borrador, o tipo distinto → eliminar para dejar vía libre
        delete_post_meta($existing_id, '_sku');
        wp_delete_post($existing_id, true);

        // Limpieza extra del meta por si quedara colgado
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key = '_sku' AND meta_value = %s
        ", $sku_final));

        // Borrar caches/transients
        wc_delete_product_transients($existing_id);
        wp_cache_flush();

        return ['status' => 'ok', 'existing_id' => null];
    }
}

if (!function_exists('upsert_product_meta_bulk')) {
    /**
     * Actualiza en bloque metacampos de un producto.
     *
     * @param int   $post_id
     * @param array $meta [ meta_key => meta_value, ... ]
     */
    function upsert_product_meta_bulk(int $post_id, array $meta): void
    {
        foreach ($meta as $k => $v) {
            if ($k === '' || $k === null) continue;
            update_post_meta($post_id, (string)$k, $v);
        }
    }
}

if (!function_exists('ensure_variable_product')) {
    /**
     * Convierte un producto a variable si aún no lo es, devolviendo el objeto WC_Product_Variable.
     * Si ya es variable, devuelve el objeto tal cual.
     *
     * @param int $product_id
     * @return WC_Product_Variable|null
     */
    function ensure_variable_product(int $product_id): ?WC_Product_Variable
    {
        $product = wc_get_product($product_id);
        if (!$product || !$product->get_id()) return null;

        if ($product instanceof WC_Product_Variable) {
            return $product;
        }

        // "Convertir" re-instanciando como variable
        $variable = new WC_Product_Variable($product_id);
        return $variable;
    }
}
