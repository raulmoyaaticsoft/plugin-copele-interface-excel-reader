<?php
/**
 * Lectura de archivo Excel (PhpSpreadsheet)
 */
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
defined('ABSPATH') || exit;

/**
 * Lee el Excel completo y devuelve un array estructurado.
 *
 * @param string $file_path Ruta absoluta del Excel
 * @return array ['parent'=>[], 'child'=>[]]
 */
function leer_excel_a_array(string $file_path): array
{
    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($file_path);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, true);

    if (empty($data)) return ['parent' => [], 'child' => []];

    $headers = array_map(fn($v) => sanitize_title(trim((string)$v)), $data[1]);
    unset($data[1]);

    $sorted = ['parent' => [], 'child' => []];

    foreach ($data as $row) {
        $arr = [];
        foreach ($headers as $i => $key) {
            $arr[$key] = trim((string)($row[$i] ?? ''));
        }

        if (empty($arr['referencia'])) continue;

        $is_parent = (isset($arr['padre']) && $arr['padre'] === '1');
        $grupo = $is_parent ? 'parent' : 'child';
        $sorted[$grupo][] = $arr;
    }

    escribir_log_debug("============================");
    escribir_log_debug("🧭 RESUMEN DE $file_path");
    escribir_log_debug("============================");
    escribir_log_debug("PARENTS: " . count($sorted['parent']));
    escribir_log_debug("CHILDREN: " . count($sorted['child']));

    return $sorted;
}
