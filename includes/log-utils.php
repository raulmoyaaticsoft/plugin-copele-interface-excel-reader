<?php
/**
 * Utilidades de logging centralizado
 *
 * Crea/usa el fichero: /wp-content/plugins/<tu-plugin>/logs_productos.txt
 */
defined('ABSPATH') || exit;

if (!function_exists('log_producto_atributo')) {
    /**
     * Escribe una línea en el log principal de la sync.
     *
     * @param string $mensaje       Texto a registrar
     * @param bool   $forzar_reset  Si true, vacía el log al inicio de la ejecución actual
     */
    function log_producto_atributo(string $mensaje, bool $forzar_reset = false): void
    {
        static $log_limpiado = false;

        // Ruta del log (un nivel por encima de /includes)
        $log_file = __DIR__ . '/../logs_productos.txt';
        $fecha    = date('Y-m-d H:i:s');

        // Crear archivo si no existe
        if (!file_exists($log_file)) {
            @touch($log_file);
        }

        // Limpiar log si se pide explícitamente o si ya existía y aún no se ha limpiado en esta ejecución
        if (($forzar_reset || !$log_limpiado) && file_exists($log_file)) {
            file_put_contents($log_file, ""); // vaciar
            $log_limpiado = true;
            file_put_contents($log_file, "[$fecha] 🧹 Log limpiado al iniciar sincronización\n", FILE_APPEND);
        }

        if (trim($mensaje) !== '') {
            file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
        }
    }
}

if (!function_exists('escribir_log_debug')) {
    /**
     * Alias corto para logging.
     */
    function escribir_log_debug(string $mensaje): void
    {
        log_producto_atributo($mensaje);
    }
}
