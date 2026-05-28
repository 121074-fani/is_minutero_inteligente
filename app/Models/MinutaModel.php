<?php
/**
 * Models/MinutaModel.php
 * Compatible PHP 7.2+
 */
class MinutaModel {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        return $this->pdo->query(
            'SELECT * FROM detalles_minuta ORDER BY fecha DESC'
        )->fetchAll();
    }

    public function getById($id) {
        $s = $this->pdo->prepare(
            'SELECT * FROM detalles_minuta WHERE id_minuta = :id LIMIT 1'
        );
        $s->execute([':id' => $id]);
        $m = $s->fetch();
        if (!$m) return false;
        $m['temas'] = $m['temas_json'] ? json_decode($m['temas_json'], true) : [];
        $t = $this->pdo->prepare(
            'SELECT * FROM tarea WHERE id_minuta = :id ORDER BY fecha_compromiso'
        );
        $t->execute([':id' => $id]);
        $m['acuerdos'] = $t->fetchAll();
        return $m;
    }

    public function crearProvisional($lugar, $correo) {
        $s = $this->pdo->prepare(
            "INSERT INTO detalles_minuta
                (lugar, fecha, hora, correo_responsable, tipo, area, fecha_registro)
             VALUES (:l, CURDATE(), CURTIME(), :c, 'presencial', '', NOW())"
        );
        $s->execute([
            ':l' => htmlspecialchars($lugar),
            ':c' => strtolower($correo),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar($id, $d, $temas) {
        $s = $this->pdo->prepare(
            'UPDATE detalles_minuta SET
                lugar              = :l,
                fecha              = :f,
                hora               = :h,
                correo_responsable = :c,
                tipo               = :t,
                area               = :a,
                temas_json         = :tj,
                participantes_json = :pj,
                fecha_proxima      = :fp,
                hora_proxima       = :hp
             WHERE id_minuta = :id'
        );
        return $s->execute([
            ':l'  => htmlspecialchars(trim($d['lugar']  ?? '')),
            ':f'  => $d['fecha']  ?? date('Y-m-d'),
            ':h'  => $d['hora']   ?? null,
            ':c'  => strtolower(trim($d['correo'] ?? '')),
            ':t'  => $d['tipo']   ?? 'presencial',
            ':a'  => $d['area']   ?? '',
            ':tj' => json_encode($temas, JSON_UNESCAPED_UNICODE),
            ':pj' => $d['participantes_json'] ?? null,
            ':fp' => $d['fecha_proxima'] ?? null,
            ':hp' => $d['hora_proxima']  ?? null,
            ':id' => $id,
        ]);
    }

    public function marcarValidada($id) {
        $this->pdo->prepare(
            'UPDATE detalles_minuta SET validada = 1 WHERE id_minuta = :id'
        )->execute([':id' => $id]);
    }

    public function delete($id) {
        $this->pdo->prepare(
            'DELETE FROM tarea WHERE id_minuta = :id'
        )->execute([':id' => $id]);
        $s = $this->pdo->prepare(
            'DELETE FROM detalles_minuta WHERE id_minuta = :id'
        );
        $s->execute([':id' => $id]);
        return $s->rowCount() > 0;
    }

    // ── Token OTP (6 dígitos, 30 min) ─────────────────────
    public function crearTokenOTP($idMinuta, $correo) {
        $token  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $this->pdo->prepare(
            'UPDATE tokens_validacion SET usado = 1 WHERE correo = :c'
        )->execute([':c' => strtolower($correo)]);

        $this->pdo->prepare(
            'INSERT INTO tokens_validacion (id_minuta, correo, token, fecha_expiracion)
             VALUES (:id, :c, :t, :e)'
        )->execute([
            ':id' => $idMinuta,
            ':c'  => strtolower($correo),
            ':t'  => $token,
            ':e'  => $expira,
        ]);

        $this->pdo->prepare(
            'UPDATE detalles_minuta SET token_validacion = :t WHERE id_minuta = :id'
        )->execute([':t' => $token, ':id' => $idMinuta]);

        return $token;
    }

    public function verificarTokenOTP($idMinuta, $token) {
        $s = $this->pdo->prepare(
            'SELECT id FROM tokens_validacion
             WHERE id_minuta = :id AND token = :t AND usado = 0
               AND fecha_expiracion > NOW()
             LIMIT 1'
        );
        $s->execute([':id' => $idMinuta, ':t' => $token]);
        $row = $s->fetch();

        if (!$row) {
            $chk = $this->pdo->prepare(
                'SELECT usado, fecha_expiracion FROM tokens_validacion
                 WHERE id_minuta = :id AND token = :t LIMIT 1'
            );
            $chk->execute([':id' => $idMinuta, ':t' => $token]);
            $prev = $chk->fetch();
            if (!$prev)          return ['exito' => false, 'mensaje' => 'Token incorrecto.'];
            if ($prev['usado'])  return ['exito' => false, 'mensaje' => 'Token ya utilizado.'];
            return ['exito' => false, 'mensaje' => 'Token expirado. Solicita uno nuevo.'];
        }

        $this->pdo->prepare(
            'UPDATE tokens_validacion SET usado = 1 WHERE id = :id'
        )->execute([':id' => $row['id']]);

        $this->marcarValidada($idMinuta);
        return ['exito' => true, 'mensaje' => 'Reunión validada correctamente.'];
    }

    // ── Token Firma (64 chars, 72h) ─────────────────────────
    public function crearTokenFirma($idMinuta, $correo, $nombre) {
        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $this->pdo->prepare(
            'INSERT INTO tokens_firma
                (id_minuta, correo, nombre, token, fecha_expiracion)
             VALUES (:id, :c, :n, :t, :e)
             ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                firmado = 0,
                fecha_firma = NULL,
                fecha_expiracion = VALUES(fecha_expiracion)'
        )->execute([
            ':id' => $idMinuta,
            ':c'  => strtolower($correo),
            ':n'  => $nombre,
            ':t'  => $token,
            ':e'  => $expira,
        ]);

        return $token;
    }

    public function verificarTokenFirma($token) {
        $s = $this->pdo->prepare(
            'SELECT tf.*, dm.lugar, dm.fecha, dm.area
             FROM tokens_firma tf
             JOIN detalles_minuta dm ON tf.id_minuta = dm.id_minuta
             WHERE tf.token = :t LIMIT 1'
        );
        $s->execute([':t' => $token]);
        $row = $s->fetch();

        if (!$row)           return ['exito' => false, 'mensaje' => 'Token inválido.'];
        if ($row['firmado']) return ['exito' => false, 'mensaje' => 'Ya firmado.', 'ya_firmado' => true];
        if ($row['fecha_expiracion'] < date('Y-m-d H:i:s'))
                             return ['exito' => false, 'mensaje' => 'Token expirado (72 horas).'];

        return ['exito' => true, 'datos' => $row];
    }

    public function registrarFirma($token, $camara) {
        $val = $this->verificarTokenFirma($token);
        if (!$val['exito']) return $val;

        $this->pdo->prepare(
            'UPDATE tokens_firma
             SET firmado = 1, fecha_firma = NOW(), camara_verificada = :c
             WHERE token = :t'
        )->execute([':c' => (int) $camara, ':t' => $token]);

        return [
            'exito'   => true,
            'mensaje' => 'Firma registrada correctamente.',
            'nombre'  => $val['datos']['nombre'],
        ];
    }

    public function estadoFirmas($idMinuta) {
        $s = $this->pdo->prepare(
            'SELECT nombre, correo, firmado, fecha_firma, camara_verificada, fecha_expiracion
             FROM tokens_firma
             WHERE id_minuta = :id
             ORDER BY nombre ASC'
        );
        $s->execute([':id' => $idMinuta]);
        return $s->fetchAll();
    }

    // ── Minutas donde el empleado es responsable o participante ──
    public function getByParticipante($correo, $nombre) {
        // Busca por correo_responsable O por participantes_json O por nombre de responsable en tareas
        $s = $this->pdo->prepare(
            "SELECT DISTINCT dm.*
             FROM detalles_minuta dm
             LEFT JOIN tarea t ON t.id_minuta = dm.id_minuta
             WHERE dm.correo_responsable = :correo
                OR dm.participantes_json LIKE :correo2
                OR t.responsable LIKE :nombre
             ORDER BY dm.fecha DESC"
        );
        $s->execute([
            ':correo'  => strtolower(trim($correo)),
            ':correo2' => '%' . strtolower(trim($correo)) . '%',
            ':nombre'  => '%' . trim($nombre) . '%',
        ]);
        return $s->fetchAll();
    }

    // ── Verificar si un empleado tiene acceso a una minuta ──────
    public function tieneAcceso($idMinuta, $correo, $nombre) {
        $s = $this->pdo->prepare(
            "SELECT COUNT(*) FROM (
                SELECT id_minuta FROM detalles_minuta
                WHERE id_minuta = :id AND correo_responsable = :correo
                UNION
                SELECT id_minuta FROM detalles_minuta
                WHERE id_minuta = :id2 AND participantes_json LIKE :correo2
                UNION
                SELECT id_minuta FROM tarea
                WHERE id_minuta = :id3 AND responsable LIKE :nombre
             ) AS sub"
        );
        $s->execute([
            ':id'     => $idMinuta, ':correo'  => strtolower($correo),
            ':id2'    => $idMinuta, ':correo2' => '%' . strtolower($correo) . '%',
            ':id3'    => $idMinuta, ':nombre'  => '%' . $nombre . '%',
        ]);
        return (int) $s->fetchColumn() > 0;
    }

}
