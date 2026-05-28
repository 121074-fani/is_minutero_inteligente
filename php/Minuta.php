<?php
/**
 * Clase Minuta
 * Gestiona el registro completo de minutas (cabecera + temas + acuerdos)
 * usando PDO con transacciones para garantizar integridad.
 */
class Minuta {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------------
    // GUARDAR MINUTA COMPLETA (con transacción)
    // Guarda la cabecera en detalles_minuta y los acuerdos en tarea
    // -----------------------------------------------------------------------
    public function guardar(array $datos, array $temas, array $acuerdos): array {

        // Validar datos mínimos
        if (empty($datos['lugar']) || empty($datos['fecha']) || empty($datos['correo'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios de la reunión.'];
        }

        try {
            // Iniciar transacción — si algo falla, nada se guarda a medias
            $this->pdo->beginTransaction();

            // 1. Insertar cabecera de la minuta
            $stmtMinuta = $this->pdo->prepare(
                'INSERT INTO detalles_minuta
                    (lugar, fecha, hora, correo_responsable, tipo, area,
                     temas_json, fecha_proxima, hora_proxima, fecha_registro)
                 VALUES
                    (:lugar, :fecha, :hora, :correo, :tipo, :area,
                     :temas_json, :fecha_proxima, :hora_proxima, NOW())'
            );

            $stmtMinuta->execute([
                ':lugar'         => htmlspecialchars(trim($datos['lugar'])),
                ':fecha'         => $datos['fecha'],
                ':hora'          => $datos['hora']          ?? null,
                ':correo'        => strtolower(trim($datos['correo'])),
                ':tipo'          => $datos['tipo']          ?? 'presencial',
                ':area'          => $datos['area']          ?? '',
                ':temas_json'    => json_encode($temas, JSON_UNESCAPED_UNICODE),
                ':fecha_proxima' => $datos['fecha_proxima'] ?? null,
                ':hora_proxima'  => $datos['hora_proxima']  ?? null,
            ]);

            $idMinuta = (int) $this->pdo->lastInsertId();

            // 2. Insertar cada acuerdo como tarea en la tabla tarea
            if (!empty($acuerdos)) {
                $stmtTarea = $this->pdo->prepare(
                    'INSERT INTO tarea
                        (titulo, responsable, fecha_compromiso, estado, id_minuta, fecha_creacion)
                     VALUES
                        (:titulo, :responsable, :fecha_compromiso, "pendiente", :id_minuta, NOW())'
                );

                foreach ($acuerdos as $acuerdo) {
                    if (empty($acuerdo['actividad']) || empty($acuerdo['responsable'])) continue;

                    $stmtTarea->execute([
                        ':titulo'           => htmlspecialchars(trim($acuerdo['actividad'])),
                        ':responsable'      => htmlspecialchars(trim($acuerdo['responsable'])),
                        ':fecha_compromiso' => $acuerdo['fecha_acuerdo'] ?? null,
                        ':id_minuta'        => $idMinuta,
                    ]);
                }
            }

            // Confirmar transacción
            $this->pdo->commit();

            return [
                'exito'    => true,
                'mensaje'  => 'Minuta guardada correctamente.',
                'id_minuta'=> $idMinuta,
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'Error al guardar la minuta: ' . $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // OBTENER TODAS LAS MINUTAS (para dashboard/reportes)
    // -----------------------------------------------------------------------
    public function obtenerTodas(): array {
        $stmt = $this->pdo->prepare(
            'SELECT id_minuta, lugar, fecha, hora, correo_responsable, tipo, area, fecha_proxima, fecha_registro
             FROM detalles_minuta
             ORDER BY fecha DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // OBTENER MINUTA POR ID (con sus tareas relacionadas)
    // -----------------------------------------------------------------------
    public function obtenerPorId(int $id): array|false {
        $stmtMinuta = $this->pdo->prepare(
            'SELECT * FROM detalles_minuta WHERE id_minuta = :id LIMIT 1'
        );
        $stmtMinuta->execute([':id' => $id]);
        $minuta = $stmtMinuta->fetch();

        if (!$minuta) return false;

        // Parsear temas_json de vuelta a array
        $minuta['temas'] = json_decode($minuta['temas_json'] ?? '[]', true);

        // Obtener tareas/acuerdos asociados
        $stmtTareas = $this->pdo->prepare(
            'SELECT * FROM tarea WHERE id_minuta = :id ORDER BY fecha_compromiso ASC'
        );
        $stmtTareas->execute([':id' => $id]);
        $minuta['acuerdos'] = $stmtTareas->fetchAll();

        return $minuta;
    }
}
?>
