<?php
/**
 * Models/UsuarioModel.php
 * Compatible PHP 7.2+ — sin union types ni typed properties
 */
class UsuarioModel {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findByCorreo($correo) {
        $s = $this->pdo->prepare('SELECT * FROM usuarios WHERE correo = :c LIMIT 1');
        $s->execute([':c' => strtolower(trim($correo))]);
        return $s->fetch();
    }

    public function findById($id) {
        $s = $this->pdo->prepare(
            'SELECT id_usuario, nombre, rfc, correo, rol, telefono, fecha_registro
             FROM usuarios WHERE id_usuario = :id LIMIT 1'
        );
        $s->execute([':id' => $id]);
        return $s->fetch();
    }

    public function getAll() {
        return $this->pdo->query(
            'SELECT id_usuario, nombre, rfc, correo, rol, telefono, fecha_registro
             FROM usuarios ORDER BY nombre'
        )->fetchAll();
    }

    public function create($nombre, $rfc, $correo, $password, $rol, $tel = '') {
        $s = $this->pdo->prepare(
            'INSERT INTO usuarios (nombre, rfc, correo, password, rol, telefono, fecha_registro)
             VALUES (:n, :r, :c, :p, :ro, :t, NOW())'
        );
        $s->execute([
            ':n'  => htmlspecialchars(trim($nombre)),
            ':r'  => strtoupper(trim($rfc)),
            ':c'  => strtolower(trim($correo)),
            ':p'  => password_hash($password, PASSWORD_BCRYPT),
            ':ro' => $rol,
            ':t'  => $tel,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTelefono($id, $tel) {
        $s = $this->pdo->prepare('UPDATE usuarios SET telefono = :t WHERE id_usuario = :id');
        $s->execute([':t' => $tel, ':id' => $id]);
        return $s->rowCount() > 0;
    }

    public function delete($id) {
        $s = $this->pdo->prepare('DELETE FROM usuarios WHERE id_usuario = :id');
        $s->execute([':id' => $id]);
        return $s->rowCount() > 0;
    }

    public function existeCorreo($correo) {
        $s = $this->pdo->prepare('SELECT 1 FROM usuarios WHERE correo = :c LIMIT 1');
        $s->execute([':c' => strtolower($correo)]);
        return (bool) $s->fetch();
    }
}
