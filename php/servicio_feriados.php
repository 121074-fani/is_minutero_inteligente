<?php
/**
 * servicio_feriados.php
 * CONSUMO DE SERVICIO WEB EXTERNO — requisito académico.
 * 
 * Consulta la API pública Nager.Date para obtener días festivos de México
 * y los devuelve en JSON al frontend (usado por el calendario).
 * 
 * URL de uso: php/servicio_feriados.php?año=2025
 */

header('Content-Type: application/json; charset=UTF-8');

$anio  = (int) ($_GET['año'] ?? date('Y'));
$pais  = 'MX'; // México

// --- PETICIÓN HTTP A API EXTERNA usando file_get_contents con contexto ---
$url     = "https://date.nager.at/api/v3/PublicHolidays/{$anio}/{$pais}";
$contexto = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 8,
        'header'  => "Accept: application/json\r\n",
    ]
]);

$respuesta = @file_get_contents($url, false, $contexto);

if ($respuesta === false) {
    // Si falla la API externa, devolvemos festivos estáticos básicos
    $feriados = [
        ['date' => "{$anio}-01-01", 'localName' => 'Año Nuevo',             'name' => "New Year's Day"],
        ['date' => "{$anio}-02-05", 'localName' => 'Día de la Constitución','name' => 'Constitution Day'],
        ['date' => "{$anio}-03-21", 'localName' => 'Natalicio de Benito Juárez', 'name' => 'Benito Juárez\'s birthday'],
        ['date' => "{$anio}-05-01", 'localName' => 'Día del Trabajo',       'name' => 'Labor Day'],
        ['date' => "{$anio}-09-16", 'localName' => 'Día de la Independencia','name' => 'Independence Day'],
        ['date' => "{$anio}-11-20", 'localName' => 'Día de la Revolución',  'name' => 'Revolution Day'],
        ['date' => "{$anio}-12-25", 'localName' => 'Navidad',               'name' => 'Christmas Day'],
    ];
    echo json_encode(['exito' => true, 'fuente' => 'estatico', 'feriados' => $feriados]);
    exit;
}

$feriados = json_decode($respuesta, true);

echo json_encode([
    'exito'    => true,
    'fuente'   => 'api_nager',
    'año'      => $anio,
    'feriados' => $feriados,
]);
?>
