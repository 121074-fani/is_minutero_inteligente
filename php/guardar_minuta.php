<?php
/**
 * guardar_minuta.php
 * Actualiza la minuta provisional creada en solicitar_token.php
 * y autoagrega todos los acuerdos como tareas en la tabla tarea.
 */
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit; }
require_once 'conexion.php';

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);
if (!$datos) { echo json_encode(['exito'=>false,'mensaje'=>'Sin datos.']); exit; }

$idMinuta = (int) ($datos['id_minuta'] ?? 0);
$temas    = $datos['temas']    ?? [];
$acuerdos = $datos['acuerdos'] ?? [];

try {
    $pdo->beginTransaction();

    if ($idMinuta) {
        // Actualizar minuta provisional
        $stmt = $pdo->prepare(
            'UPDATE detalles_minuta SET
                lugar=:lugar, fecha=:fecha, hora=:hora, correo_responsable=:correo,
                tipo=:tipo, area=:area, temas_json=:temas_json,
                fecha_proxima=:fecha_proxima, hora_proxima=:hora_proxima
             WHERE id_minuta=:id'
        );
        $stmt->execute([
            ':lugar'         => htmlspecialchars(trim($datos['lugar'] ?? '')),
            ':fecha'         => $datos['fecha'] ?? date('Y-m-d'),
            ':hora'          => $datos['hora']  ?? null,
            ':correo'        => strtolower(trim($datos['correo'] ?? '')),
            ':tipo'          => $datos['tipo']  ?? 'presencial',
            ':area'          => $datos['area']  ?? '',
            ':temas_json'    => json_encode($temas, JSON_UNESCAPED_UNICODE),
            ':fecha_proxima' => $datos['fecha_proxima'] ?? null,
            ':hora_proxima'  => $datos['hora_proxima']  ?? null,
            ':id'            => $idMinuta,
        ]);
    } else {
        // Crear minuta nueva (flujo alternativo sin token)
        $stmt = $pdo->prepare(
            'INSERT INTO detalles_minuta (lugar,fecha,hora,correo_responsable,tipo,area,temas_json,fecha_proxima,hora_proxima,fecha_registro)
             VALUES (:lugar,:fecha,:hora,:correo,:tipo,:area,:temas_json,:fecha_proxima,:hora_proxima,NOW())'
        );
        $stmt->execute([
            ':lugar'=>htmlspecialchars(trim($datos['lugar']??'')),
            ':fecha'=>$datos['fecha']??date('Y-m-d'),
            ':hora'=>$datos['hora']??null,
            ':correo'=>strtolower(trim($datos['correo']??'')),
            ':tipo'=>$datos['tipo']??'presencial',
            ':area'=>$datos['area']??'',
            ':temas_json'=>json_encode($temas,JSON_UNESCAPED_UNICODE),
            ':fecha_proxima'=>$datos['fecha_proxima']??null,
            ':hora_proxima'=>$datos['hora_proxima']??null,
        ]);
        $idMinuta = (int) $pdo->lastInsertId();
    }

    // Eliminar tareas previas de esta minuta (si se guarda de nuevo)
    $pdo->prepare('DELETE FROM tarea WHERE id_minuta=:id')->execute([':id'=>$idMinuta]);

    // Autoagregar acuerdos como tareas en el panel de seguimiento
    if (!empty($acuerdos)) {
        $stmtT = $pdo->prepare(
            'INSERT INTO tarea (titulo, responsable, departamento, fecha_compromiso, estado, id_minuta, fecha_creacion)
             VALUES (:titulo, :responsable, :departamento, :fecha_compromiso, "pendiente", :id_minuta, NOW())'
        );
        foreach ($acuerdos as $a) {
            if (empty($a['actividad']) || empty($a['responsable'])) continue;
            $stmtT->execute([
                ':titulo'           => htmlspecialchars(trim($a['actividad'])),
                ':responsable'      => htmlspecialchars(trim($a['responsable'])),
                ':departamento'     => $datos['area'] ?? '',
                ':fecha_compromiso' => $a['fecha_acuerdo'] ?? null,
                ':id_minuta'        => $idMinuta,
            ]);
        }
    }

    $pdo->commit();

    // Contar tareas creadas
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM tarea WHERE id_minuta=:id');
    $cnt->execute([':id'=>$idMinuta]);
    $numTareas = (int) $cnt->fetchColumn();

    echo json_encode([
        'exito'      => true,
        'id_minuta'  => $idMinuta,
        'tareas_creadas' => $numTareas,
        'mensaje'    => "Minuta #{$idMinuta} guardada. {$numTareas} tarea(s) agregadas al panel de seguimiento."
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['exito'=>false,'mensaje'=>'Error BD: '.$e->getMessage()]);
}
?>
