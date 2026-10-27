<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$p=getenv('PORTAL_CONFIG_FILE');if(!$p||!str_ends_with($p,'config.test.php'))throw new RuntimeException('Solo pruebas.');$cfg=require$p;if(!str_ends_with($cfg['db_name'],'_test'))throw new RuntimeException('Solo base de pruebas.');
$app=portal_app();$db=$app->db();$setting=$db->query("SELECT setting_value FROM pisco_settings WHERE setting_key='role_permissions'")->fetchColumn();$users=$db->query('SELECT id,auth_version FROM pisco_users')->fetchAll();$session=$_SESSION;$n=0;
function check(bool $v,string $m):void{global$n;if(!$v)throw new RuntimeException($m);$n++;echo "OK $m\n";}
function deny(int$s,callable$f,string$m):void{try{$f();}catch(PortalError$e){check($e->status===$s,$m);return;}throw new RuntimeException('No rechazado: '.$m);}
try{
 $admin=$db->query("SELECT * FROM pisco_users WHERE role='admin' AND active=1 LIMIT 1")->fetch();$_SESSION['user_id']=$admin['id'];$_SESSION['auth_version']=$admin['auth_version'];
 $before=$app->roleSettings();$role=PortalPolicy::defaults()['roles']['imagen'];$input=['role'=>'imagen','label'=>'Comunicaciones institucionales','mods'=>array_values(array_diff($role['mods'],['noticias'])),'expected'=>$before['imagen']??null];
 deny(422,fn()=>$app->saveRole([...$input,'role'=>'admin']),'Administrador protegido');
 deny(422,fn()=>$app->saveRole([...$input,'mods'=>['usuarios']]),'Sin escalamiento fuera del ámbito del rol');
 $app->saveRole($input);check(PortalPolicy::definitions()['roles']['imagen']['label']===$input['label'],'Nombre guardado');check(!PortalPolicy::can(['rol'=>'imagen'],'noticias'),'Módulo revocado en servidor');check(PortalPolicy::can(['rol'=>'imagen'],'eventos'),'Otros módulos conservados');
 $new=portal_app();$new->db();check(!PortalPolicy::can(['rol'=>'imagen'],'noticias'),'Permisos persisten en otra instancia');
 $data=json_decode($db->query('SELECT data FROM pisco_state WHERE id=1')->fetchColumn(),true);$public=(new ReflectionMethod(PortalApp::class,'publicData'))->invoke($app,$data);$user=['rol'=>'imagen','usuario'=>'imagen'];$view=PortalPolicy::project($data,$user,$public);$incoming=$view;$incoming['noticias'][]=['id'=>'no-autorizado','titulo'=>'No permitido'];deny(403,fn()=>PortalPolicy::apply($data,$view,$incoming,$user),'API rechaza escritura con permiso revocado');
 deny(409,fn()=>$app->saveRole($input),'Conflicto de edición detectado');
 $staff=$db->query("SELECT * FROM pisco_users WHERE role='imagen' AND active=1 LIMIT 1")->fetch();check((int)$staff['auth_version']>(int)array_column($users,'auth_version','id')[$staff['id']],'Sesiones del rol revocadas');
 $_SESSION['user_id']=$staff['id'];$_SESSION['auth_version']=$staff['auth_version'];deny(403,fn()=>$app->saveRole($input),'Solo administración modifica roles');
 $_SESSION['user_id']=$admin['id'];$_SESSION['auth_version']=$admin['auth_version'];$now=$app->roleSettings();$app->saveRole([...$input,'expected'=>$now['imagen'],'reset'=>true]);check(PortalPolicy::can(['rol'=>'imagen'],'noticias'),'Restauración de permisos originales');
 $current=$app->roleSettings();$app->saveRole([...$input,'expected'=>$current['imagen']??null]);
 $backup=PortalBackups::create($app,'prueba-roles');
 try {
   $stream=gzopen(PortalBackups::path($backup['id']),'rb');$header=json_decode(gzgets($stream),true);gzclose($stream);check($header['role_permissions']['imagen']['label']===$input['label'],'Respaldo portable incluye roles');
   $current=$app->roleSettings();$app->saveRole([...$input,'expected'=>$current['imagen'],'reset'=>true]);
   PortalBackups::restore($app,PortalBackups::path($backup['id']));$restored=portal_app();$restored->db();check(!PortalPolicy::can(['rol'=>'imagen'],'noticias'),'Restauración recupera permisos del respaldo');
 }finally{@unlink(PortalBackups::path($backup['id']));}
 deny(422,fn()=>PortalPolicy::validateRoles(['admin'=>['label'=>'Otro','mods'=>['dashboard']]]),'Respaldo no puede alterar administrador');
 PortalPolicy::configureRoles([]);$consistent=true;foreach(PortalPolicy::TOP as $collection=>$allowed)foreach($allowed as $role)$consistent=$consistent&&PortalPolicy::can(['rol'=>$role],PortalPolicy::collectionModule($collection,['rol'=>$role]));check($consistent,'Todos los ámbitos institucionales originales conservados');
 echo "$n comprobaciones aprobadas.\n";
}finally{
 if($setting===false)$db->exec("DELETE FROM pisco_settings WHERE setting_key='role_permissions'");else $db->prepare("UPDATE pisco_settings SET setting_value=? WHERE setting_key='role_permissions'")->execute([$setting]);
 foreach($users as$row)$db->prepare('UPDATE pisco_users SET auth_version=? WHERE id=?')->execute([$row['auth_version'],$row['id']]);$_SESSION=$session;PortalPolicy::configureRoles($setting?json_decode($setting,true):[]);
}
