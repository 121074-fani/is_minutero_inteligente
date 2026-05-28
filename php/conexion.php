<?php
// conexion.php
$host = '127.0.0.1';
$port = '3307';    
$db   = 'minutero_inteligente';
$user = 'root';
$pass = '';

// Nueva línea de DSN incluyendo el puerto
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     // AQUÍ se define $pdo
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error crítico de conexión: " . $e->getMessage());
}
?>
