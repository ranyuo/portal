<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo configuración de pruebas.');$cfg=require $path;if(!str_ends_with($cfg['db_name'],'_test'))throw new RuntimeException('Solo base de pruebas.');
$db=portal_app()->db();$before=$db->query('SELECT id,username,password FROM pisco_users ORDER BY id')->fetchAll();$state=$db->query('SELECT data,revision FROM pisco_state WHERE id=1')->fetch();
foreach(['role','profile','auth_version','login_failures','locked_until','last_login'] as $col)$db->exec('ALTER TABLE pisco_users DROP COLUMN '.$col);
PortalSchema::apply($db);PortalSchema::apply($db);
if($before!==$db->query('SELECT id,username,password FROM pisco_users ORDER BY id')->fetchAll()||$state!==$db->query('SELECT data,revision FROM pisco_state WHERE id=1')->fetch())throw new RuntimeException('La actualización modificó las cuentas o el contenido.');
echo "OK esquema antiguo actualizado dos veces conservando contraseñas y contenido exactos.\n";
