<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo configuración de pruebas.');$cfg=require$path;if(!str_ends_with($cfg['db_name'],'_test'))throw new RuntimeException('Solo base de pruebas.');$app=portal_app();$db=$app->db();$u=$db->query("SELECT id,auth_version FROM pisco_users WHERE username='admin'")->fetch();$_SESSION['user_id']=(int)$u['id'];$_SESSION['auth_version']=(int)$u['auth_version'];$count=0;
function check(bool$v,string$l):void{global$count;if(!$v)throw new RuntimeException($l);$count++;echo"OK $l\n";}
function reject(int$status,callable$f,string$l):void{try{$f();}catch(PortalError$e){check($e->status===$status,$l);return;}throw new RuntimeException($l);}
function edit(callable$f):void{global$app;$b=$app->bootstrap();$d=$b['data'];$f($d);$app->save(['data'=>$d,'revision'=>$b['revision']]);}
$id='qa-lookup-'.bin2hex(random_bytes(4));$code='QA-'.strtoupper(bin2hex(random_bytes(4)));
try{
edit(function(&$d)use($id,$code){$d['certificados'][]=['id'=>$id,'codigo'=>$code,'dni'=>'78999991','nombres'=>'Persona sintética de pruebas','programaId'=>$d['programas'][0]['id'],'tipo'=>'Certificado','fecha'=>date('Y-m-d'),'estado'=>'Anulado','detalle'=>'Detalle verificado','motivo'=>'Anulado por prueba de calidad','deleted'=>false];});
$r=$app->lookup('certificados',['reference'=>$code,'document'=>'']);check($r['record']['detalle']==='Detalle verificado','Consulta por código conserva detalle');check($r['record']['motivo']==='Anulado por prueba de calidad','Consulta por código muestra motivo de anulación');check(!isset($r['record']['dni']),'Consulta por código no entrega DNI completo');
$r=$app->lookup('certificados',['reference'=>'78999991','document'=>'','mode'=>'dni']);check(count($r['records'])===1&&$r['records'][0]['motivo']==='Anulado por prueba de calidad','Consulta por DNI devuelve el mismo motivo');
reject(422,fn()=>$app->lookup('certificados',['reference'=>'123','document'=>'','mode'=>'dni']),'DNI inválido rechazado por PHP');reject(422,fn()=>$app->lookup('certificados',['reference'=>$code,'document'=>'','mode'=>'otro']),'Modo de consulta desconocido rechazado');
edit(function(&$d)use($id){foreach($d['certificados']as&$r)if($r['id']===$id)$r['deleted']=true;});reject(404,fn()=>$app->lookup('certificados',['reference'=>$code,'document'=>'']),'Certificado en papelera no se verifica por código');$r=$app->lookup('certificados',['reference'=>'78999991','document'=>'','mode'=>'dni']);check($r['records']===[],'Certificado en papelera no aparece por DNI');
}finally{edit(function(&$d)use($id){$d['certificados']=array_values(array_filter($d['certificados'],fn($r)=>$r['id']!==$id));});}
echo"\n$count comprobaciones de consultas aprobadas.\n";ob_end_flush();
