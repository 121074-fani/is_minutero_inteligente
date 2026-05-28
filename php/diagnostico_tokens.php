<?php
/**
 * diagnostico_tokens.php
 * Abre en el navegador: http://localhost/minutero/php/diagnostico_tokens.php
 * Muestra exactamente qué está fallando con el sistema de tokens.
 */
header('Content-Type: text/html; charset=UTF-8');
echo '<style>body{font-family:monospace;padding:20px;background:#f5eef8;}
.ok{color:green;font-weight:bold;} .err{color:red;font-weight:bold;}
.box{background:white;border-radius:8px;padding:16px;margin:12px 0;border-left:4px solid #9b59b6;}
h2{color:#4a235a;}</style>';
echo '<h2>🔍 Diagnóstico — Sistema de Tokens</h2>';

// 1. Conexión BD
echo '<div class="box"><b>1. Conexión a la base de datos</b><br>';
try {
    require_once 'conexion.php';
    echo '<span class="ok">✅ Conexión exitosa a MySQL</span>';
} catch (Exception $e) {
    echo '<span class="err">❌ Error: ' . $e->getMessage() . '</span>';
    exit;
}
echo '</div>';

// 2. Tablas requeridas
echo '<div class="box"><b>2. Tablas requeridas</b><br>';
$tablas = ['tokens_validacion','tokens_firma','detalles_minuta','tarea','usuarios'];
foreach ($tablas as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '{$t}'")->rowCount();
    echo $exists
        ? "<span class='ok'>✅ {$t}</span><br>"
        : "<span class='err'>❌ {$t} — NO EXISTE. Ejecuta sql_tokens_nuevas_tablas.sql</span><br>";
}
echo '</div>';

// 3. Columnas en detalles_minuta
echo '<div class="box"><b>3. Columnas nuevas en detalles_minuta</b><br>';
$cols = $pdo->query("SHOW COLUMNS FROM detalles_minuta")->fetchAll(PDO::FETCH_COLUMN);
$reqs = ['token_validacion','validada','participantes_json'];
foreach ($reqs as $c) {
    echo in_array($c, $cols)
        ? "<span class='ok'>✅ {$c}</span><br>"
        : "<span class='err'>❌ {$c} — falta. Ejecuta sql_tokens_nuevas_tablas.sql</span><br>";
}
echo '</div>';

// 4. Test INSERT token_validacion
echo '<div class="box"><b>4. Test: Insertar token de prueba</b><br>';
try {
    // Minuta de prueba
    $pdo->exec("INSERT INTO detalles_minuta (lugar,fecha,hora,correo_responsable,tipo,area,fecha_registro)
                VALUES ('TEST',CURDATE(),CURTIME(),'test@test.com','presencial','',NOW())");
    $idTest = (int) $pdo->lastInsertId();

    $token   = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
    $expira  = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    $ins = $pdo->prepare('INSERT INTO tokens_validacion (id_minuta,correo,token,fecha_expiracion) VALUES (?,?,?,?)');
    $ins->execute([$idTest,'test@test.com',$token,$expira]);
    echo "<span class='ok'>✅ Token insertado: <b>{$token}</b> (minuta ID: {$idTest})</span><br>";

    // Verificar
    $chk = $pdo->prepare('SELECT id FROM tokens_validacion WHERE token=? AND usado=0 AND fecha_expiracion>NOW() LIMIT 1');
    $chk->execute([$token]);
    $row = $chk->fetch();
    echo $row
        ? "<span class='ok'>✅ Verificación exitosa: token encontrado y válido</span><br>"
        : "<span class='err'>❌ Token insertado pero no se puede leer</span><br>";

    // Limpiar prueba
    $pdo->prepare('DELETE FROM tokens_validacion WHERE id_minuta=?')->execute([$idTest]);
    $pdo->prepare('DELETE FROM detalles_minuta WHERE id_minuta=?')->execute([$idTest]);
    echo "<span class='ok'>✅ Limpieza de datos de prueba correcta</span>";
} catch (Exception $e) {
    echo '<span class="err">❌ Error: ' . $e->getMessage() . '</span>';
}
echo '</div>';

// 5. Test INSERT token_firma
echo '<div class="box"><b>5. Test: Insertar token de firma</b><br>';
try {
    $pdo->exec("INSERT INTO detalles_minuta (lugar,fecha,hora,correo_responsable,tipo,area,fecha_registro)
                VALUES ('TEST2',CURDATE(),CURTIME(),'test@test.com','presencial','',NOW())");
    $idTest2 = (int) $pdo->lastInsertId();
    $tok64   = bin2hex(random_bytes(32));
    $exp72   = date('Y-m-d H:i:s', strtotime('+72 hours'));
    $ins2 = $pdo->prepare('INSERT INTO tokens_firma (id_minuta,correo,nombre,token,fecha_expiracion) VALUES (?,?,?,?,?)');
    $ins2->execute([$idTest2,'part@test.com','Participante Test',$tok64,$exp72]);
    echo "<span class='ok'>✅ Token firma insertado (64 chars)</span><br>";
    $pdo->prepare('DELETE FROM tokens_firma WHERE id_minuta=?')->execute([$idTest2]);
    $pdo->prepare('DELETE FROM detalles_minuta WHERE id_minuta=?')->execute([$idTest2]);
    echo "<span class='ok'>✅ Limpieza correcta</span>";
} catch (Exception $e) {
    echo '<span class="err">❌ Error: ' . $e->getMessage() . '</span>';
}
echo '</div>';

echo '<div class="box" style="border-left-color:green;"><b>✅ Si todo está verde, los tokens deben funcionar.</b><br>
Si hay errores rojos, ejecuta primero <code>sql_tokens_nuevas_tablas.sql</code> en phpMyAdmin.</div>';
?>
