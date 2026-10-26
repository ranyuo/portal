<?php
declare(strict_types=1);
final class PortalAudit
{
    public static function write(PortalApp $app,string $action,string $entity,string $key='',array $details=[],?array $actor=null): void
    {
        $actor??=$app->user();
        try{$app->db()->prepare('INSERT INTO pisco_audit (occurred_at,user_id,username,role,action,entity,record_key,details) VALUES (?,?,?,?,?,?,?,?)')->execute([date('Y-m-d H:i:s.u'),$actor['server_id']??null,$actor['usuario']??'visitante',$actor['rol']??'publico',substr($action,0,80),substr($entity,0,100),substr($key,0,250),json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);}
        catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
    }
    public static function rows(PortalApp $app,int $limit=1000): array
    {
        try{$rows=$app->db()->query('SELECT * FROM pisco_audit ORDER BY id DESC LIMIT '.max(1,min(5000,$limit)))->fetchAll();}
        catch(PDOException $e){if($e->getCode()==='42S02')return [];throw $e;}
        return array_map(fn($r)=>['id'=>'audit-'.$r['id'],'fecha'=>$r['occurred_at'],'usuario'=>$r['username'],'rol'=>$r['role'],'accion'=>$r['action'],'entidad'=>$r['entity'],'registro'=>$r['record_key'],'detalle'=>self::describe(json_decode($r['details'],true)??[])],$rows);
    }
    public static function legacy(PortalApp $app,array $data): array
    {
        try{if($app->db()->query("SELECT setting_value FROM pisco_settings WHERE setting_key='legacy_audit_imported'")->fetchColumn())return [];}catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
        return $data['auditoria']??[];
    }
    private static function describe(array $details): string
    {return implode(' · ',array_map(fn($k,$v)=>$k.': '.(is_array($v)?implode(', ',array_map(fn($x)=>is_scalar($x)?(string)$x:'[estructura]',$v)):(string)$v),array_keys($details),array_values($details)));}
    public static function changes(PortalApp $app,array $before,array $after,array $actor): void
    {
        $visit=function(string $path,mixed $old,mixed $new)use($app,$actor){
            if(PortalPolicy::equal($old,$new))return;
            if(is_array($old)&&is_array($new)&&array_is_list($old)&&array_is_list($new)){
                $a=[];$b=[];foreach($old as $r)if(is_array($r))$a[PortalPolicy::key($path,$r)]=$r;foreach($new as $r)if(is_array($r))$b[PortalPolicy::key($path,$r)]=$r;
                foreach(array_unique([...array_keys($a),...array_keys($b)]) as $id){$previous=$a[$id]??null;$next=$b[$id]??null;if(PortalPolicy::equal($previous,$next))continue;
                    $fields=[];foreach(array_unique([...array_keys($previous??[]),...array_keys($next??[])]) as $field)if(!PortalPolicy::equal($previous[$field]??null,$next[$field]??null))$fields[]=$field;
                    $action=$previous===null?'Crear':($next===null?'Eliminar definitivamente':(!empty($next['deleted'])?'Enviar a papelera':(!empty($previous['deleted'])?'Restaurar':'Modificar')));
                    self::write($app,$action,$path,(string)($next['id']??$previous['id']??$id),['campos'=>$fields,'estado_anterior'=>$previous['estado']??'','estado_actual'=>$next['estado']??''],$actor);
                }
            }else self::write($app,'Cambiar configuración',$path,'',['campos'=>is_array($old)&&is_array($new)?array_values(array_filter(array_unique([...array_keys($old),...array_keys($new)]),fn($k)=>!PortalPolicy::equal($old[$k]??null,$new[$k]??null))):['valor']],$actor);
        };
        foreach($after as $key=>$value){if(in_array($key,['usuarios','auditoria','visitas','correos','__v'],true))continue;if($key==='academico'){foreach($value as $sub=>$rows)if(!in_array($sub,['bitacora','usuarios','notifs'],true))$visit('academico.'.$sub,$before[$key][$sub]??null,$rows);}else $visit($key,$before[$key]??null,$value);}
    }
}
