<?php
/**
 * Models/TareaModel.php
 * Compatible PHP 7.2+
 */
class TareaModel {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll($depto = '', $resp = '', $fecha = '') {
        $sql = 'SELECT * FROM tarea WHERE 1=1';
        $p   = [];
        if ($depto) { $sql .= ' AND departamento = :d';         $p[':d'] = $depto; }
        if ($resp)  { $sql .= ' AND responsable LIKE :r';       $p[':r'] = "%{$resp}%"; }
        if ($fecha) { $sql .= ' AND fecha_compromiso = :f';     $p[':f'] = $fecha; }
        $sql .= ' ORDER BY fecha_compromiso ASC';
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function getByResponsable($nombre, $depto = '', $fecha = '') {
        $sql = 'SELECT * FROM tarea WHERE responsable LIKE :r';
        $p   = [':r' => "%{$nombre}%"];
        if ($depto) { $sql .= ' AND departamento = :d';     $p[':d'] = $depto; }
        if ($fecha) { $sql .= ' AND fecha_compromiso = :f'; $p[':f'] = $fecha; }
        $sql .= ' ORDER BY fecha_compromiso ASC';
        $s = $this->pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function getById($id) {
        $s = $this->pdo->prepare('SELECT * FROM tarea WHERE id_tarea = :id LIMIT 1');
        $s->execute([':id' => $id]);
        return $s->fetch();
    }

    public function create($titulo, $resp, $depto, $fecha, $idMinuta = 0) {
        $s = $this->pdo->prepare(
            'INSERT INTO tarea (titulo, responsable, departamento, fecha_compromiso, estado, id_minuta, fecha_creacion)
             VALUES (:t, :r, :d, :f, "pendiente", :m, NOW())'
        );
        $s->execute([
            ':t' => htmlspecialchars(trim($titulo)),
            ':r' => htmlspecialchars(trim($resp)),
            ':d' => htmlspecialchars(trim($depto)),
            ':f' => $fecha ?: null,
            ':m' => (int) $idMinuta,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEstado($id, $estado) {
        $validos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
        if (!in_array($estado, $validos)) return false;
        $s = $this->pdo->prepare('UPDATE tarea SET estado = :e WHERE id_tarea = :id');
        $s->execute([':e' => $estado, ':id' => $id]);
        return $s->rowCount() > 0;
    }

    public function delete($id) {
        $s = $this->pdo->prepare('DELETE FROM tarea WHERE id_tarea = :id');
        $s->execute([':id' => $id]);
        return $s->rowCount() > 0;
    }

    public function metricas() {
        $s = $this->pdo->prepare(
            'SELECT
                COUNT(*)                                              AS total,
                SUM(estado = "pendiente")                            AS pendientes,
                SUM(estado = "completada")                           AS completadas,
                SUM(estado = "en_progreso")                         AS en_progreso,
                SUM(fecha_compromiso < CURDATE() AND estado != "completada") AS vencidas
             FROM tarea'
        );
        $s->execute();
        return $s->fetch();
    }

    public function metricasPorResponsable($nombre) {
        $s = $this->pdo->prepare(
            'SELECT
                COUNT(*)                                              AS total,
                SUM(estado = "pendiente")                            AS pendientes,
                SUM(estado = "completada")                           AS completadas,
                SUM(estado = "en_progreso")                         AS en_progreso,
                SUM(fecha_compromiso < CURDATE() AND estado != "completada") AS vencidas
             FROM tarea WHERE responsable LIKE :r'
        );
        $s->execute([':r' => "%{$nombre}%"]);
        return $s->fetch();
    }
}
