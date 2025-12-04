<?php
/**
 * Utilidades para gestión de imágenes de producto WooCommerce
 */
defined('ABSPATH') || exit;

/**
 * Descarga o asigna imagen destacada a un producto por URL.
 *
 * @param int    $product_id
 * @param string $image_url
 * @return int|false attachment_id o false si falla
 */
function attach_image_to_product(int $product_id, string $image_url)
{
    if (empty($image_url)) return false;

    // Evitar duplicados
    $existing_thumb = get_post_thumbnail_id($product_id);
    if ($existing_thumb) {
        escribir_log_debug("🖼 Imagen ya existente para producto {$product_id}, se omite.");
        return $existing_thumb;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        escribir_log_debug("⚠️ Error descargando imagen {$image_url} → " . $tmp->get_error_message());
        return false;
    }

    $file_array = [
        'name'     => basename($image_url),
        'tmp_name' => $tmp
    ];

    $attachment_id = media_handle_sideload($file_array, $product_id);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        escribir_log_debug("⚠️ Error asignando imagen: " . $attachment_id->get_error_message());
        return false;
    }

    set_post_thumbnail($product_id, $attachment_id);
    escribir_log_debug("✅ Imagen asignada correctamente a producto {$product_id}");
    return $attachment_id;
}
