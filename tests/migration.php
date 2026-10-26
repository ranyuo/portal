<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base de pruebas.');
$app=portal_app();$db=$app->db();PortalSchema::apply($db);$db->exec('DELETE FROM pisco_rate_limits');$count=0;
function ok(bool $v,string $label):void{global $count;if(!$v)throw new RuntimeException('FALLO '.$label);$count++;echo "OK $label\n";}
function denied(int $status,callable $f,string $label):void{try{$f();}catch(PortalError $e){if($e->status!==$status)throw new RuntimeException($label.': '.$e->status.' '.$e->getMessage());ok(true,$label);return;}throw new RuntimeException('FALLO sin rechazo '.$label);}
function actor(string $username='admin'):void{global $db;$s=$db->prepare('SELECT id,auth_version FROM pisco_users WHERE username=?');$s->execute([$username]);$u=$s->fetch();$_SESSION['user_id']=(int)$u['id'];$_SESSION['auth_version']=(int)$u['auth_version'];}
function saveData(array $data):array{global $app;return $app->save(['data'=>$data,'revision'=>$app->bootstrap()['revision']]);}
function change(callable $f):array{global $app;$data=$app->bootstrap()['data'];$f($data);return saveData($data);}
function proof(string $type):array{global $app;$c=$app->challenge(['purpose'=>$type]);return ['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true];}
actor();$pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";$file=PortalFiles::store($app,'prueba-migracion.pdf',$pdf);
foreach(PortalPolicy::definitions()['specs'] as $key=>$spec){
 $col=$spec['collection'];if($col==='usuarios')continue;$d=$app->bootstrap()['data'];$r=$d[$col][0]??[];$r['id']='migration-'.$key;$r['deleted']=false;$r['estado']=$spec['states'][0]??'Borrador';$r['vigente']=false;
 foreach($spec['fields'] as $f){$name=$f['name'];if($f['unique'])$r[$name]=$f['type']==='number'?2090:('migracion-'.$key.'-'.$name);if($f['required']&&empty($r[$name]))$r[$name]=match($f['type']){'number'=>1,'email'=>'migracion@example.test','date'=>date('Y-m-d'),'datetime-local'=>date('Y-m-d\TH:i'),default=>'Prueba '.$key};}
 foreach(['titulo','nombre','nombres','slug'] as $field)if(isset($r[$field]))$r[$field]='Prueba migracion '.$key.' '.$field;
 if(isset($r['slug']))$r['slug']='migracion-'.$key;
 if($col==='programas')$r['codigo']='MIG99';
 if($col==='personas'){$r['titulo']='Ing.';$r['dni']='79999991';$r['especialidad']='Informática';$r['condicion']='Contratado';$r['correo']='persona-migracion@example.test';}
 if($col==='jerarquica')$r['esDirector']=false;
 if($col==='documentos'){$r['refModulo']='';$r['archivo']=$file;$r['grupo']='migracion-documento';$r['version']='99.0';}
 if($col==='certificados'){$r['dni']='79999992';$r['codigo']='MIGRACION-CERT-2026';$r['programaId']='apsti';$r['fecha']=date('Y-m-d');unset($r['ref']);}
 if($col==='becarios')$r=[...$r,'periodoId'=>$d['becasPeriodos'][0]['id'],'dni'=>'79999993','apellidos'=>'Prueba','nombres'=>'Migración','programaId'=>'apsti','tipoId'=>$d['tiposBeca'][0]['id']];
 if($col==='admisiones')$r['anio']=2090;
 if($col==='matriculas')$r['periodo']='2090-II';
 if($col==='becasPeriodos'){$r['anio']=2090;$r['periodo']='II';}
 if($col==='tupa'){$r['anio']=2090;$r['codigo']='MIGRACION-TUPA';}
 $d[$col][]=$r;try{saveData($d);}catch(Throwable $e){throw new RuntimeException('Crear '.$col.': '.$e->getMessage(),0,$e);}ok(PortalPolicy::find($app->bootstrap()['data'][$col],'id',$r['id'])!==null,"Crear y leer $col en MySQL");
 change(function(&$d)use($col,$r){foreach($d[$col] as &$row)if($row['id']===$r['id'])$row['notaMigracion']='Edición persistente';});ok(PortalPolicy::find($app->bootstrap()['data'][$col],'id',$r['id'])['notaMigracion']==='Edición persistente',"Editar y volver a leer $col");
 change(function(&$d)use($col,$r){foreach($d[$col] as &$row)if($row['id']===$r['id'])$row['deleted']=true;});ok(PortalPolicy::find($app->bootstrap()['data'][$col],'id',$r['id'])['deleted']===true,"Papelera $col");
 change(function(&$d)use($col,$r){foreach($d[$col] as &$row)if($row['id']===$r['id'])$row['deleted']=false;});ok(PortalPolicy::find($app->bootstrap()['data'][$col],'id',$r['id'])['deleted']===false,"Restaurar $col");
 change(function(&$d)use($col,$r){$d[$col]=array_values(array_filter($d[$col],fn($row)=>$row['id']!==$r['id']));});ok(PortalPolicy::find($app->bootstrap()['data'][$col],'id',$r['id'])===null,"Eliminar definitivamente $col sin referencias");
 $db->exec('DELETE FROM pisco_rate_limits');
}
$beforeAudit=(int)$db->query('SELECT COUNT(*) FROM pisco_audit')->fetchColumn();
change(function(&$d){$d['noticias'][]=[...$d['noticias'][0],'id'=>'scheduled-audit','titulo'=>'Noticia programada de prueba','slug'=>'programada-prueba','estado'=>'Publicada','fecha'=>'2099-01-01T08:00'];$d['documentos'][]=[...$d['documentos'][0],'id'=>'historic-audit','nombre'=>'Documento histórico de prueba','estado'=>'Histórico','version'=>'777.0'];});
$app->logout();$public=$app->bootstrap()['data'];ok(!in_array('scheduled-audit',array_column($public['noticias'],'id'),true),'Noticia futura no enviada al visitante');ok(in_array('historic-audit',array_column($public['documentos'],'id'),true),'Documento histórico disponible públicamente');actor();
$base=$app->bootstrap();$one=$base['data']['noticias'][0];$two=$base['data']['noticias'][1];$updatedOne=[...$one,'titulo'=>'Edición simultánea A'];$updatedTwo=[...$two,'titulo'=>'Edición simultánea B'];
$patch=fn($a,$b)=>[['path'=>['noticias'],'kind'=>'rows','changes'=>[['key'=>'id:'.$a['id'],'before'=>$a,'after'=>$b]]]];
$app->save(['revision'=>$base['revision'],'patch'=>$patch($one,$updatedOne)]);$app->save(['revision'=>$base['revision'],'patch'=>$patch($two,$updatedTwo)]);ok(true,'Dos registros distintos se guardan desde la misma revisión');denied(409,fn()=>$app->save(['revision'=>$base['revision'],'patch'=>$patch($one,[...$one,'titulo'=>'Sobrescribir A'])]),'Conflicto en el mismo registro rechazado');
$b=$app->bootstrap();$published=array_key_first($b['data']['academico']['publicados']);$lesson=current(array_filter($b['data']['academico']['sesiones'],fn($s)=>PortalDomain::group($s)===$published));
if($lesson)denied(422,fn()=>change(function(&$d)use($lesson){$d['academico']['sesiones'][]=[...$lesson,'id'=>987654321];}),'Cruce rechazado al cambiar un horario ya publicado');
$n=$app->bootstrap()['data'];$old=$n;$n['academico']['notifs']=[];$n['academico']['ofertas'][]=['id'=>987654321,'estado'=>'Publicada','prog'=>1,'puesto'=>'Aviso limitado'];PortalDomain::notifications($old,$n,[['vin'=>'est:1001','ixId'=>101],['vin'=>'est:1002','ixId'=>102]]);ok(array_column($n['academico']['notifs'],'to')===['u:101'],'Aviso de oferta solo al programa correspondiente');
$app->logout();$message=['nombres'=>'Prueba servidor','correo'=>'proof@example.test','asunto'=>'Consulta','mensaje'=>'Mensaje que comprueba las validaciones de servidor.'];$key='11111111-1111-4111-a111-111111111111';
denied(422,fn()=>$app->submit('mensajes',['record'=>$message,'idempotency_key'=>$key]),'Formulario sin desafío rechazado');$p=proof('mensajes');denied(422,fn()=>$app->submit('mensajes',['record'=>$message,'idempotency_key'=>$key,'proof'=>[...$p,'answer'=>'-1']]),'Respuesta de desafío incorrecta rechazada');denied(422,fn()=>$app->submit('mensajes',['record'=>$message,'idempotency_key'=>$key,'proof'=>[...$p,'accept'=>false]]),'Consentimiento obligatorio en PHP');
$app->submit('mensajes',['record'=>$message,'idempotency_key'=>$key,'proof'=>$p]);denied(422,fn()=>$app->submit('mensajes',['record'=>$message,'idempotency_key'=>'22222222-2222-4222-a222-222222222222','proof'=>$p]),'No se reutiliza el desafío para otra solicitud');actor();
ok((int)$db->query('SELECT COUNT(*) FROM pisco_audit')->fetchColumn()>$beforeAudit,'Bitácora persistente registra acciones por entidad');
$backup=PortalBackups::create($app);$hashBefore=$db->query('SELECT SHA2(data,256) FROM pisco_state WHERE id=1')->fetchColumn();$usersBefore=$db->query('SELECT id,username,password FROM pisco_users ORDER BY id')->fetchAll();$filesBefore=$db->query('SELECT id,sha256 FROM pisco_files ORDER BY id')->fetchAll();
change(function(&$d){$d['config']['inst']['nombre']='Cambio después de respaldar';});
PortalBackups::restore($app,PortalBackups::path($backup['id']));actor();ok($hashBefore===$db->query('SELECT SHA2(data,256) FROM pisco_state WHERE id=1')->fetchColumn(),'Restauración portable recupera contenido exacto');ok($usersBefore===$db->query('SELECT id,username,password FROM pisco_users ORDER BY id')->fetchAll(),'Restauración portable recupera cuentas y contraseñas');ok($filesBefore===$db->query('SELECT id,sha256 FROM pisco_files ORDER BY id')->fetchAll(),'Restauración portable recupera adjuntos exactos');
$broken=PortalBackups::path(bin2hex(random_bytes(16)));$raw=file_get_contents(PortalBackups::path($backup['id']));file_put_contents($broken,substr($raw,0,-35));$before=$db->query('SELECT SHA2(data,256) FROM pisco_state WHERE id=1')->fetchColumn();denied(422,fn()=>PortalBackups::restore($app,$broken),'Respaldo incompleto rechazado');ok($before===$db->query('SELECT SHA2(data,256) FROM pisco_state WHERE id=1')->fetchColumn(),'Restauración fallida no modifica datos');unlink($broken);
PortalBackups::configure($app,['enabled'=>true,'hour'=>0,'retention'=>2]);$scheduled=PortalBackups::scheduled($app);ok($scheduled!==null,'Tarea programada crea una copia');ok(PortalBackups::scheduled($app)===null,'Tarea programada no duplica la copia diaria');
echo "\n$count comprobaciones adicionales de migración aprobadas.\n";ob_end_flush();
