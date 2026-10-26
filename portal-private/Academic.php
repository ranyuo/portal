<?php
declare(strict_types=1);
final class PortalAcademic
{
    public static function validate(array $old,array &$new): void
    {
        $contracts=json_decode(file_get_contents(__DIR__.'/contracts/academic.json'),true,flags:JSON_THROW_ON_ERROR);$a=&$new['academico'];$o=$old['academico'];
        foreach($contracts as $key=>$spec){if($key==='usuarios')continue;
            if(!is_array($a[$key]??null)||!array_is_list($a[$key]))throw new PortalError(422,'Catálogo académico no válido: '.$key.'.');
            foreach($a[$key] as &$row){if(!is_array($row))throw new PortalError(422,'Registro académico no válido.');$prior=PortalPolicy::find($o[$key]??[],'id',$row['id']??null);if(PortalPolicy::equal($prior,$row))continue;
                if(!isset($row['id'])||filter_var($row['id'],FILTER_VALIDATE_INT)===false||(int)$row['id']<1)throw new PortalError(422,'Identificador académico no válido.');$row['id']=(int)$row['id'];
                foreach(['prog','est','mod','emp'] as $field)if(array_key_exists($field,$row)&&$row[$field]!==''&&$row[$field]!==null){if(filter_var($row[$field],FILTER_VALIDATE_INT)===false)throw new PortalError(422,'Vínculo académico no válido.');$row[$field]=(int)$row[$field];}
                foreach($spec['fields'] as $f){$v=$row[$f['name']]??null;$empty=$v===null||$v===''||(is_array($v)&&$v===[]);
                    if($f['required']&&$empty&&(!$prior||!empty($prior[$f['name']])))throw new PortalError(422,'Completa '.$key.'.'.$f['name'].'.');if($empty)continue;
                    if($f['type']==='number'&&(!is_numeric($v)||($f['min']!==null&&$v<$f['min'])||($f['max']!==null&&$v>$f['max'])))throw new PortalError(422,'Número fuera del rango permitido en '.$key.'.'.$f['name'].'.');
                    if($f['type']==='email'&&(!is_string($v)||!filter_var($v,FILTER_VALIDATE_EMAIL)))throw new PortalError(422,'Correo académico no válido.');
                    if($f['type']==='date'){$date=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if(!$date||$date->format('Y-m-d')!==$v)throw new PortalError(422,'Fecha académica no válida.');}
                    if($f['type']==='multi'&&!is_array($v))throw new PortalError(422,'Selección múltiple no válida.');
                    if($f['type']==='time'&&(!is_string($v)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$v)))throw new PortalError(422,'Hora no válida.');
                    if($f['unique'])foreach($a[$key] as $other)if(($other['id']??null)!==$row['id']&&isset($other[$f['name']])&&PortalRules::normal((string)$other[$f['name']])===PortalRules::normal((string)$v))throw new PortalError(422,'Valor duplicado en '.$key.'.'.$f['name'].'.');
                }
                $refs=match($key){'uds','ambientes','modulos','estudiantes'=>['prog'=>'programas'],'certs'=>['est'=>'estudiantes','mod'=>'modulos'],'notas','titulos'=>['est'=>'estudiantes'],'ofertas'=>['emp'=>'empresas','prog'=>'programas'],default=>[]};
                foreach($refs as $field=>$target)if(!empty($row[$field])&&!PortalPolicy::find($a[$target],'id',$row[$field]))throw new PortalError(422,'La referencia académica '.$target.' no existe.');
                if($key==='empresas'){
                    $ruc=(string)($row['ruc']??'');$weights=[5,4,3,2,7,6,5,4,3,2];$sum=0;if(!preg_match('/^[0-9]{11}$/',$ruc))throw new PortalError(422,'El RUC debe tener once dígitos.');foreach($weights as $i=>$weight)$sum+=(int)$ruc[$i]*$weight;$digit=11-$sum%11;$digit=$digit===10?0:($digit===11?1:$digit);if((int)$ruc[10]!==$digit)throw new PortalError(422,'El dígito verificador del RUC no coincide.');
                }
                if($key==='docentes'){
                    if(!empty($row['per'])&&!PortalPolicy::find($new['personas'],'id',$row['per']))throw new PortalError(422,'La persona del docente no existe.');
                    if(($row['desde']??'')>=($row['hasta']??''))throw new PortalError(422,'La hora final del docente debe ser posterior a la inicial.');
                    foreach($row['uds']??[] as $id)if(!PortalPolicy::find($a['uds'],'id',$id))throw new PortalError(422,'Unidad didáctica asignada no válida.');
                }
                if($key==='modulos')foreach($a['modulos'] as $other)if($other['id']!==$row['id']&&$other['prog']===$row['prog']&&PortalRules::normal($other['cod'])===PortalRules::normal($row['cod']))throw new PortalError(422,'El código del módulo ya existe en ese programa.');
                if($key==='tramites'&&!PortalPolicy::find($a['areas'],'cod',$row['area']))throw new PortalError(422,'El área responsable del trámite no existe.');
            }unset($row);
        }
        foreach($o['areas'] as $r)if(!PortalPolicy::find($a['areas'],'id',$r['id'])){
            if(PortalPolicy::find($a['tramites'],'area',$r['cod']))throw new PortalError(422,'El área tiene trámites relacionados.');
            foreach($new['expedientes'] as $exp)if(in_array($r['cod'],[$exp['area']??'',$exp['areaDestino']??''],true))throw new PortalError(422,'El área tiene expedientes relacionados.');
        }
    }
}
