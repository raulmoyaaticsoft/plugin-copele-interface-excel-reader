<?php
/**
 * Lógica principal de sincronización Excel → WooCommerce
 */
defined('ABSPATH') || exit;

function read_excel_to_array_interface()
{
    try {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/excel/productos.xlsx';

        if (!file_exists($file_path)) {
            throw new Exception("El archivo Excel no existe en {$file_path}");
        }

        escribir_log_debug("🚀 Iniciando sincronización desde Excel...");
        $sortedArr = leer_excel_a_array($file_path);

        escribir_log_debug("===========================");
        escribir_log_debug("🧭 RESUMEN DE $sortedArr");
        escribir_log_debug("===========================");

        // Procesar productos padre
        foreach ($sortedArr['parent'] as $arr) {
            $ref = trim($arr['referencia']);
            if ($ref === '') continue;

            $sku_final = normalize_sku($ref);
            escribir_log_debug("📦 Procesando producto PARENT {$sku_final}");

            // Prevenir duplicados
            $check = precheck_duplicate_sku_and_cleanup($sku_final, 'product');
            if ($check['status'] === 'blocked') {
                escribir_log_debug("⏩ Producto {$sku_final} ya existente y publicado, se omite creación.");
                continue;
            }

            // Crear producto padre
            $product = new WC_Product_Variable();
            $product->set_name($arr['descripcion_es'] ?: 'Producto sin nombre');
            $product->set_sku($sku_final);
            $product->set_status('publish');
            $product->save();

            $product_id = $product->get_id();
            escribir_log_debug("✅ Creado producto padre ID={$product_id} SKU={$sku_final}");

            // Crear atributos de filtros (ejemplo)
            $attr_keys = ['filtro_tipo', 'filtro_modelo', 'filtro_material'];
            foreach ($attr_keys as $key) {
                if (empty($arr[$key])) continue;
                $vals = ensure_attribute_and_terms($key, $arr[$key]);
                wp_set_object_terms($product_id, $vals, 'pa_' . sanitize_title($key), false);
            }

            escribir_log_debug("🧩 Atributos básicos aplicados a producto {$sku_final}");
        }

        // Procesar productos hijo
        foreach ($sortedArr['child'] as $arr) {
            $ref = trim($arr['referencia']);
            if ($ref === '') continue;

            $sku_final = normalize_sku($ref);
            escribir_log_debug("🔸 Procesando VARIACIÓN {$sku_final}");

            // Asociar con padre
            $padre_ref = trim($arr['codigos_asociados']);
            $padre_ref = explode('-', $padre_ref)[0] ?? '';
            $padre_sku = normalize_sku($padre_ref);
            $padre_id  = wc_get_product_id_by_sku($padre_sku);
            if (!$padre_id) {
                escribir_log_debug("⚠️ No se encontró el padre {$padre_sku} para {$sku_final}");
                continue;
            }

            $product = new WC_Product_Variation();
            $product->set_parent_id($padre_id);
            $product->set_sku($sku_final);
            $product->set_regular_price('0');
            $product->set_manage_stock(true);
            $product->set_stock_quantity(100);
            $product->save();

            escribir_log_debug("✅ Variación creada ID={$product->get_id()} padre={$padre_sku}");

            // Atributos específicos de variación
            $attr_keys = ['color', 'capacidad', 'otro_capacidad', 'patas', 'modelo', 'otro_modelo', 'tipo', 'material'];
            $atts = [];
            foreach ($attr_keys as $key) {
                if (empty($arr[$key])) continue;
                $vals = ensure_attribute_and_terms($key, $arr[$key]);
                $tax  = 'pa_' . sanitize_title($key);
                $atts[$tax] = $vals[0] ?? null;
            }
            $product->set_attributes($atts);
            $product->save();

            escribir_log_debug("🎨 Variación {$sku_final} con atributos: " . json_encode($atts));
        }

        escribir_log_debug("🎉 Sincronización completada con éxito");
        return ['status' => 'ok', 'message' => 'Sincronización finalizada'];
    } catch (Throwable $e) {
        $msg = "🛑 Error en read_excel_to_array_interface(): {$e->getMessage()} | Archivo: {$e->getFile()} | Línea: {$e->getLine()}";
        escribir_log_debug($msg);
        return ['status' => 'error', 'message' => $msg];
    }
}
