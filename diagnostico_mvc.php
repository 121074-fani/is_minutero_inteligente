<?php
/**
 * diagnostico_mvc.php
 * Abre: http://localhost/minutero/diagnostico_mvc.php
 * Diagnostica todos los problemas del MVC automáticamente.
 */
header('Content-Type: text/html; charset=UTF-8');
$ok  = '<span style="color:green;font-weight:700;">✅ OK</span>';
$err = '<span style="color:red;font-weight:700;">❌ ERROR</span>';
echo '<style>body{font-family:monospace;padding:20px;background:#f5eef8;font-size:14px;}
.box{background:white;border-radius:10px;padding:16px;margin:12px 0;border-left:4px solid #9b59b6;}
.fix{background:#fff8e1;border-left-color:#f39c12;border-radius:10px;padding:14px;margin:8px 0;}
h2{color:#4a235a;margin-top:24px;}pre{background:#f0f0f0;padding:10px;border-radius:6px;overflow-x:auto;}
</style><h2>🔍 Diagnóstico MVC — Minutero Inteligente</h2>';

$base    = __DIR__;
$errores = 0;

// 1. Archivos MVC
echo '<div class="box"><b>1. Archivos MVC</b><br>';
$archivos = [
    'app/Core/Router.php','app/Core/Auth.php','app/Core/MailService.php',
    'app/Models/UsuarioModel.php','app/Models/TareaModel.php','app/Models/MinutaModel.php',
    'app/Controllers/AuthController.php','app/Controllers/TareaController.php',
    'app/Controllers/MinutaController.php','app/Controllers/UsuarioController.php',
    'php/conexion.php','api.php',
];
foreach ($archivos as $f) {
    $existe = file_exists("{$base}/{$f}");
    if (!$existe) $errores++;
    echo ($existe ? $ok : $err) . " {$f}<br>";
}
echo '</div>';

// 2. Sintaxis PHP
echo '<div class="box"><b>2. Sintaxis de clases MVC</b><br>';
$clases = [
    'app/Core/Auth.php', 'app/Core/Router.php', 'app/Core/MailService.php',
    'app/Models/UsuarioModel.php','app/Models/TareaModel.php','app/Models/MinutaModel.php',
    'app/Controllers/AuthController.php',
];
foreach ($clases as $f) {
    if (!file_exists("{$base}/{$f}")) { echo "$err $f (no existe)<br>"; continue; }
    // Verificar llaves balanceadas
    $c = file_get_contents("{$base}/{$f}");
    $opens  = substr_count($c, '{');
    $closes = substr_count($c, '}');
    $bal = ($opens === $closes);
    if (!$bal) $errores++;
    echo ($bal ? $ok : $err) . " {$f} (llaves: {$opens}/{$closes})<br>";
}
echo '</div>';

// 3. Conexión BD
echo '<div class="box"><b>3. Conexión a base de datos</b><br>';
try {
    require_once "{$base}/php/conexion.php";
    echo "$ok Conexión MySQL exitosa<br>";
    // Verificar tabla usuarios
    $cnt = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    echo "$ok Tabla usuarios: {$cnt} registros<br>";
    // Verificar rol enum
    $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch();
    $rolDef = $cols['Type'] ?? '';
    $tieneMinutero = strpos($rolDef, 'minutero') !== false;
    echo ($tieneMinutero ? $ok : $err) . " Rol 'minutero' en ENUM: {$rolDef}<br>";
    if (!$tieneMinutero) {
        $errores++;
        echo '<div class="fix">⚠️ Ejecuta en phpMyAdmin:<br>
        <pre>ALTER TABLE usuarios MODIFY COLUMN rol ENUM(\'admin\',\'minutero\',\'empleado\') NOT NULL DEFAULT \'empleado\';</pre></div>';
    }
    // Tablas de tokens
    foreach (['tokens_validacion','tokens_firma'] as $tabla) {
        $ex = $pdo->query("SHOW TABLES LIKE '{$tabla}'")->rowCount() > 0;
        if (!$ex) $errores++;
        echo ($ex ? $ok : $err) . " Tabla {$tabla}<br>";
    }
} catch (Exception $e) {
    $errores++;
    echo "$err Conexión fallida: " . $e->getMessage() . '<br>';
}
echo '</div>';

// 4. Test del autoload MVC
echo '<div class="box"><b>4. Test carga del MVC (require_once)</b><br>';
try {
    require_once "{$base}/app/Core/Auth.php";
    require_once "{$base}/app/Core/MailService.php";
    require_once "{$base}/app/Core/Router.php";
    require_once "{$base}/app/Models/UsuarioModel.php";
    require_once "{$base}/app/Models/TareaModel.php";
    require_once "{$base}/app/Models/MinutaModel.php";
    require_once "{$base}/app/Controllers/AuthController.php";
    echo "$ok Todas las clases MVC cargaron correctamente<br>";
} catch (Throwable $e) {
    $errores++;
    echo "$err Error al cargar: " . $e->getMessage() . "<br>";
    echo '<div class="fix">Archivo: ' . $e->getFile() . ' línea ' . $e->getLine() . '</div>';
}
echo '</div>';

// 5. Test login directo
echo '<div class="box"><b>5. Test login (sin sesión)</b><br>';
try {
    $uModel = new UsuarioModel($pdo);
    $u = $uModel->findByCorreo('carlos.mendoza@itm.edu.mx');
    if ($u) {
        $passOk = password_verify('Admin123', $u['password']);
        echo ($passOk ? $ok : $err) . " carlos.mendoza@itm.edu.mx / Admin123<br>";
        if (!$passOk) {
            $errores++;
            echo '<div class="fix">La contraseña no coincide. Actualiza en phpMyAdmin:<br>
            <pre>UPDATE usuarios SET password=\'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi\'
WHERE correo=\'carlos.mendoza@itm.edu.mx\';</pre></div>';
        }
        echo "$ok Rol del usuario: " . $u['rol'] . "<br>";
    } else {
        $errores++;
        echo "$err Usuario carlos.mendoza@itm.edu.mx no existe. Ejecuta datos_prueba.sql<br>";
    }
    // Probar con el correo que usó el usuario
    $diana = $uModel->findByCorreo('diana.horo5@gmail.com');
    if ($diana) {
        echo "$ok Usuario diana.horo5@gmail.com encontrado (rol: {$diana['rol']})<br>";
        $passOk2 = password_verify('Admin123', $diana['password']);
        echo ($passOk2 ? $ok : '⚠️') . " Contraseña Admin123: " . ($passOk2 ? 'correcta' : 'NO coincide') . "<br>";
        if (!$passOk2) {
            echo '<div class="fix">Actualiza la contraseña de diana.horo5@gmail.com:<br>
            <pre>UPDATE usuarios SET password=\'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi\'
WHERE correo=\'diana.horo5@gmail.com\';</pre></div>';
        }
    } else {
        echo "⚠️ diana.horo5@gmail.com no está en la BD (¿contraseña diferente?)<br>";
    }
} catch (Throwable $e) {
    $errores++;
    echo "$err Error: " . $e->getMessage() . "<br>";
}
echo '</div>';

// 6. Edge localStorage fix
echo '<div class="box"><b>6. Compatibilidad con Edge (localStorage)</b><br>';
echo "⚠️ Edge bloquea localStorage en localhost con protección de rastreo.<br>";
echo '<b>Solución:</b> Ve a <code>edge://settings/privacy</code> → <b>Excepciones de rastreo</b> → agrega <code>http://localhost</code><br>';
echo 'O simplemente usa <b>Chrome</b> o <b>Firefox</b> para desarrollo local.<br>';
echo '</div>';

// Resumen
$color = $errores === 0 ? 'green' : 'red';
echo "<div style='background:white;border-radius:10px;padding:16px;border-left:4px solid {$color};margin-top:20px;'>
<b style='color:{$color};font-size:1.1rem;'>" . ($errores === 0 ? '✅ Todo está correcto — el MVC debería funcionar.' : "❌ Se encontraron {$errores} problema(s). Corrige los marcados en rojo.") . "</b>
<br><br>Si todo está verde y aún falla: usa <b>Chrome</b> en vez de Edge.
</div>";
?>
