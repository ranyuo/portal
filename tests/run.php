<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$configPath = getenv('PORTAL_CONFIG_FILE');
if (!$configPath || !str_ends_with($configPath, 'config.test.php')) throw new RuntimeException('Usa PORTAL_CONFIG_FILE apuntando a config.test.php.');
$config = require $configPath;
if (!str_ends_with($config['db_name'], '_test')) throw new RuntimeException('Pruebas bloqueadas: la base debe terminar en _test.');
$app = portal_app();
$db = $app->db();
foreach (['pisco_audit','pisco_settings','pisco_files','pisco_visits','pisco_schema','pisco_rate_limits','pisco_users','pisco_state'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException('FALLO: '.$message);
    $count++;
    echo 'OK '.$message."\n";
}
function failure(int $status, callable $operation, string $message): void {
    try { $operation(); } catch (PortalError $error) { check($error->status === $status, $message); return; }
    throw new RuntimeException('FALLO: no se rechazó '.$message);
}
function uuid(): string { $h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20); }
function proof(string $purpose): array {global $app;$c=$app->challenge(['purpose'=>$purpose]);return ['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true];}
$account = ['install_key' => $config['install_key'], 'username' => 'admin', 'name' => 'Administrador de prueba',
    'email' => 'admin@example.test', 'password' => 'PruebaNativa-Segura-2026', 'password_confirmation' => 'PruebaNativa-Segura-2026'];
check($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'Pruebas ejecutadas en MySQL');
failure(403, fn () => $app->install([...$account, 'install_key' => 'incorrecta']), 'Instalador exige clave privada');
$app->install($account);
check($app->installed(), 'Instalación crea tablas y administrador');
failure(410, fn () => $app->install($account), 'Instalador bloqueado después de instalar');
check($app->user() === null, 'Instalar no crea una sesión autenticada');
$public = $app->bootstrap();
check($public['data']['usuarios'] === [] && $public['data']['academico']['estudiantes'] === [], 'Datos privados ausentes en estado público');
failure(403, fn () => $app->save(['revision'=>1,'data'=>$public['data']]), 'Visitantes no pueden modificar contenido');
failure(419, fn () => $app->checkCsrf('falso'), 'Se rechaza CSRF incorrecto');
$app->checkCsrf($_SESSION['csrf']); check(true, 'CSRF correcto aceptado');
failure(422, fn () => $app->login(['username'=>'admin','password'=>'incorrecta']), 'Contraseña incorrecta rechazada');
$app->login(['username'=>'admin','password'=>$account['password']]);
check($app->user()['usuario'] === 'admin', 'Inicio de sesión PHP');
$hash = $db->query('SELECT password FROM pisco_users LIMIT 1')->fetchColumn();
check($hash !== $account['password'] && password_verify($account['password'],$hash), 'Contraseña guardada como hash');
$before = $app->bootstrap(); $data = $before['data']; $data['noticias'][0]['titulo'] = 'Prueba persistente sin framework';
$app->save(['data'=>$data,'revision'=>$before['revision']]);
failure(409, fn () => $app->save(['data'=>$data,'revision'=>$before['revision']]), 'Guardado concurrente obsoleto rechazado');
$current=$app->bootstrap(); $tampered=$current['data'];$tampered['usuarios'][0]['passHash']='falso';
failure(422,fn()=>$app->save(['data'=>$tampered,'revision'=>$current['revision']]),'No se cambian contraseñas mediante contenido');
$app->logout();
check($app->bootstrap()['data']['noticias'][0]['titulo']==='Prueba persistente sin framework','Noticia guardada visible a visitantes');
$contact=['record'=>['nombres'=>'Prueba','correo'=>'prueba@example.test','asunto'=>'Consulta','mensaje'=>'Consulta de prueba desde PHP y MySQL.','estado'=>'Atendido'],'idempotency_key'=>uuid(),'proof'=>proof('mensajes')];
$result=$app->submit('mensajes',$contact);$duplicate=$app->submit('mensajes',$contact);
check($result['status']===201&&$result['record']['estado']==='Nuevo','Mensaje validado y recibido en MySQL');
check($duplicate['status']===200&&$duplicate['record']['id']===$result['record']['id'],'Reintento no duplica el mensaje');
failure(422,fn()=>$app->submit('mensajes',['record'=>['correo'=>'invalido'],'idempotency_key'=>uuid()]),'Campos obligatorios validados en servidor');
failure(404,fn()=>$app->submit('usuarios',$contact),'No hay registro público de administradores');
$claim=['nombres'=>'Persona de prueba','tipo'=>'Reclamo','tipoDoc'=>'DNI','numDoc'=>'12345678','correo'=>'prueba@example.test','condicion'=>'Visitante','fechaHecho'=>date('Y-m-d'),'descripcion'=>'Descripción suficientemente larga para registrar este reclamo.','pedido'=>'Solicito una respuesta.','codigo'=>'FALSO'];
$receipt=$app->submit('reclamos',['record'=>$claim,'idempotency_key'=>uuid(),'proof'=>proof('reclamos')])['record'];
check((bool)preg_match('/^RCL-\d{4}-00001$/',$receipt['codigo']),'Código del reclamo generado por servidor');
failure(404,fn()=>$app->lookup('reclamos',['reference'=>$receipt['codigo'],'document'=>'00000000']),'Consulta rechaza documento equivocado');
$found=$app->lookup('reclamos',['reference'=>$receipt['codigo'],'document'=>'12345678'])['record'];
check($found['estado']==='Recibido'&&!isset($found['correo']),'Seguimiento válido sin revelar datos de contacto');
$record=['nombres'=>'Persona de prueba','tipoPersona'=>'Persona natural','tipoDoc'=>'DNI','numDoc'=>'12345678','correo'=>'prueba@example.test','telefono'=>'999888777','tipoTramite'=>'Solicitud','asunto'=>'Solicitud de información','folios'=>1,'areaDestino'=>'MP','archivos'=>[['original'=>'archivo.pdf','data'=>'data:application/pdf;base64,'.base64_encode('<script>alert(1)</script>')]]];
failure(422,fn()=>$app->submit('expedientes',['record'=>$record,'idempotency_key'=>uuid(),'proof'=>proof('expedientes')]),'Archivo disfrazado de PDF rechazado');
$record['archivos'][0]['data']='data:application/pdf;base64,'.base64_encode("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
$receipt=$app->submit('expedientes',['record'=>$record,'idempotency_key'=>uuid(),'proof'=>proof('expedientes')])['record'];
check(strlen($receipt['archivos'][0]['sha256'])===64,'PDF comprobado y hash calculado');
$found=$app->lookup('expedientes',['reference'=>$receipt['numero'],'document'=>$receipt['cargoCod']])['record'];
check(!isset($found['archivos'],$found['archivo']),'Consulta de expediente no descarga adjuntos privados');
check($app->bootstrap()['data']['mensajes']===[]&&$app->bootstrap()['data']['reclamos']===[],'Formularios privados no publicados en estado público');
$app->login(['username'=>'admin','password'=>$account['password']]);
failure(422,fn()=>$app->changePassword(['current_password'=>'incorrecta','password'=>'ClaveNuevaSegura-2026','password_confirmation'=>'ClaveNuevaSegura-2026']),'Cambio de contraseña exige contraseña actual');
$app->changePassword(['current_password'=>$account['password'],'password'=>'ClaveNuevaSegura-2026','password_confirmation'=>'ClaveNuevaSegura-2026']);
$app->logout(); $app->login(['username'=>'admin','password'=>'ClaveNuevaSegura-2026']);
check($app->user()!==null,'Nueva contraseña permite iniciar sesión');
$app->createAccount(['username'=>'segundo','name'=>'Segundo admin','email'=>'segundo@example.test','password'=>'OtraClaveSegura-2026','password_confirmation'=>'OtraClaveSegura-2026']);
check(count($app->accounts())===2,'Administrador puede crear otra cuenta');
$app->logout(); failure(403,fn()=>$app->accounts(),'Cierre de sesión revoca acceso');
$db->exec('DELETE FROM pisco_rate_limits');
for($i=0;$i<5;$i++){try{$app->login(['username'=>'admin','password'=>'incorrecta']);}catch(PortalError){}}
failure(423,fn()=>$app->login(['username'=>'admin','password'=>'incorrecta']),'Límite de intentos de acceso aplicado');
echo "\n$count comprobaciones aprobadas.\n";
ob_end_flush();
