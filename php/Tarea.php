<?php
/**
 * Clase Tarea
 * Gestiona las tareas/acuerdos de las minutas con PDO y consultas preparadas.
 */
class Tarea {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------------
    // CREAR TAREA
    // -----------------------------------------------------------------------
    public function crear(string $titulo, string $responsable,
                          string $departamento, string $fecha_compromiso,
                          int $id_minuta = 0): array {

        if (empty($titulo) || empty($responsable) || empty($fecha_compromiso)) {
            return ['exito' => false, 'mensaje' => 'Título, responsable y fecha son obligatorios.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tarea (titulo, responsable, departamento, fecha_compromiso, estado, id_minuta, fecha_creacion)
             VALUES (:titulo, :responsable, :departamento, :fecha_compromiso, "pendiente", :id_minuta, NOW())'
        );

        $stmt->execute([
            ':titulo'           => htmlspecialchars(trim($titulo)),
            ':responsable'      => htmlspecialchars(trim($responsable)),
            ':departamento'     => htmlspecialchars(trim($departamento)),
            ':fecha_compromiso' => $fecha_compromiso,
            ':id_minuta'        => $id_minuta,
        ]);

        return ['exito' => true, 'mensaje' => 'Tarea creada.', 'id' => $this->pdo->lastInsertId()];
    }

    // -----------------------------------------------------------------------
    // OBTENER TODAS LAS TAREAS (con filtros opcionales)
    // -----------------------------------------------------------------------
    public function obtenerTodas(string $departamento = '', string $responsable = '', string $fecha = ''): array {

        $sql    = 'SELECT * FROM tarea WHERE 1=1';
        $params = [];

        if (!empty($departamento)) {
            $sql .= ' AND departamento = :departamento';
            $params[':departamento'] = $departamento;
        }

        if (!empty($responsable)) {
            $sql .= ' AND responsable LIKE :responsable';
            $params[':responsable'] = '%' . $responsable . '%';
        }

        if (!empty($fecha)) {
            $sql .= ' AND fecha_compromiso = :fecha';
            $params[':fecha'] = $fecha;
        }

        $sql .= ' ORDER BY fecha_compromiso ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // OBTENER TAREA POR ID
    // -----------------------------------------------------------------------
    public function obtenerPorId(int $id): array|false {
        $stmt = $this->pdo->prepare('SELECT * FROM tarea WHERE id_tarea = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // -----------------------------------------------------------------------
    // ACTUALIZAR ESTADO DE TAREA
    // -----------------------------------------------------------------------
    public function actualizarEstado(int $id, string $estado): array {
        $estadosValidos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
        if (!in_array($estado, $estadosValidos)) {
            return ['exito' => false, 'mensaje' => 'Estado no válido.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tarea SET estado = :estado WHERE id_tarea = :id'
        );
        $stmt->execute([':estado' => $estado, ':id' => $id]);

        return ['exito' => true, 'mensaje' => 'Estado actualizado.', 'filas' => $stmt->rowCount()];
    }

    // -----------------------------------------------------------------------
    // ELIMINAR TAREA
    // -----------------------------------------------------------------------
    public function eliminar(int $id): array {
        $stmt = $this->pdo->prepare('DELETE FROM tarea WHERE id_tarea = :id');
        $stmt->execute([':id' => $id]);
        return ['exito' => true, 'mensaje' => 'Tarea eliminada.', 'filas' => $stmt->rowCount()];
    }

    // -----------------------------------------------------------------------
    // MÉTRICAS (para el dashboard)
    // -----------------------------------------------------------------------
    public function obtenerMetricas(): array {
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*)                                         AS total,
                SUM(estado = "pendiente")                        AS pendientes,
                SUM(estado = "completada")                       AS completadas,
                SUM(estado = "en_progreso")                      AS en_progreso,
                SUM(fecha_compromiso < CURDATE() AND estado != "completada") AS vencidas
             FROM tarea'
        );
        $stmt->execute();
        return $stmt->fetch();
    }
}
?>
