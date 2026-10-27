<?php
declare(strict_types=1);

final class PortalError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

require_once __DIR__.'/Schema.php';
require_once __DIR__.'/Policy.php';
require_once __DIR__.'/Accounts.php';
require_once __DIR__.'/Files.php';
require_once __DIR__.'/Domain.php';
require_once __DIR__.'/Forms.php';
require_once __DIR__.'/Audit.php';
require_once __DIR__.'/Backups.php';
require_once __DIR__.'/Patch.php';
require_once __DIR__.'/Rules.php';
require_once __DIR__.'/Services.php';
require_once __DIR__.'/Academic.php';

final class PortalApp
{
    use PortalAccounts, PortalForms;
    private const PUBLIC_COLLECTIONS = ['__v', 'config', 'slides', 'enlaces', 'paginas', 'reconocimientos',
        'programas', 'personas', 'jerarquica', 'egresados', 'categoriasNoticia', 'noticias', 'eventos',
        'tiposDocumento', 'documentos', 'tupa', 'tupaDocs', 'estadisticas', 'licenciamiento', 'inversiones',
        'convocatorias', 'admisiones', 'matriculas', 'tiposBeca', 'becasPeriodos', 'locales', 'servicios', 'visitas', 'academico'];

    public const PRIVATE_COLLECTIONS = ['usuarios', 'auditoria', 'mensajes', 'reclamos', 'expedientes', 'certificados', 'correos', 'suscriptores', 'becarios'];

    private const RULES = [
        'mensajes' => ['nombres' => 'required|string|max:160', 'documento' => 'nullable|string|max:20',
            'correo' => 'required|email|max:254', 'telefono' => 'nullable|string|max:30',
            'asunto' => 'required|string|max:200', 'programa' => 'nullable|string|max:100', 'mensaje' => 'required|string|min:10|max:10000'],
        'suscriptores' => ['correo' => 'required|email|max:254'],
        'reclamos' => ['nombres' => 'required|string|max:200', 'nombresP' => 'nullable|string|max:100',
            'apellidos' => 'nullable|string|max:100', 'tipo' => 'required|in:Reclamo,Queja',
            'tipoDoc' => 'required|string|max:20', 'numDoc' => 'required|string|max:20',
            'correo' => 'required|email|max:254', 'telefono' => 'nullable|string|max:30',
            'condicion' => 'required|string|max:100', 'programaId' => 'nullable|string|max:100',
            'fechaHecho' => 'required|date|before_or_equal:today', 'areaHecho' => 'nullable|string|max:100',
            'descripcion' => 'required|string|min:20|max:10000', 'pedido' => 'required|string|min:5|max:5000', 'adjunto' => 'nullable|array'],
        'expedientes' => ['nombres' => 'required|string|max:200', 'nombresP' => 'nullable|string|max:100',
            'apellidos' => 'nullable|string|max:100', 'tipoPersona' => 'required|string|max:100',
            'tipoDoc' => 'required|string|max:20', 'numDoc' => 'required|string|max:20',
            'correo' => 'required|email|max:254', 'telefono' => 'required|string|max:30', 'direccion' => 'nullable|string|max:300',
            'tipoTramite' => 'required|string|max:200', 'docRef' => 'nullable|string|max:200',
            'asunto' => 'required|string|min:5|max:2000', 'folios' => 'required|integer|min:1|max:100000',
            'observaciones' => 'nullable|string|max:10000', 'areaDestino' => 'required|string|max:100',
            'archivos' => 'required|array|min:1|max:5', 'archivos.*' => 'required|array'],
    ];

    private ?PDO $connection = null;

    public function __construct(private readonly array $config) {}

    public function db(): PDO
    {
        if ($this->connection === null) {
            $this->connection = new PDO(
                'mysql:host='.$this->config['db_host'].';port='.(int)($this->config['db_port'] ?? 3306).';dbname='.$this->config['db_name'].';charset=utf8mb4',
                $this->config['db_user'], $this->config['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            try {
                $roles=$this->connection->query("SELECT setting_value FROM pisco_settings WHERE setting_key='role_permissions'")->fetchColumn();
                PortalPolicy::configureRoles($roles?json_decode($roles,true,flags:JSON_THROW_ON_ERROR):[]);
            } catch(PDOException $error) { if($error->getCode()!=='42S02')throw $error;PortalPolicy::configureRoles([]); }
        }
        return $this->connection;
    }

    private static function publishedScholarship(array $row,array $data): bool
    {
        if(($row['deleted']??false)||($row['estado']??'')!=='Publicado')return false;
        $period=PortalPolicy::find($data['becasPeriodos']??[],'id',$row['periodoId']??null);
        return $period&&!($period['deleted']??false)&&in_array($period['estado']??'',['Abierto','Cerrado','Vigente','Publicado'],true);
    }
    private function publicData(array $data): array
    {
        $scholarships=array_values(array_filter($data['becarios']??[],fn($row)=>self::publishedScholarship($row,$data)));
            $data = array_intersect_key($data, array_flip(self::PUBLIC_COLLECTIONS));
            foreach (self::PRIVATE_COLLECTIONS as $key) {
                $data[$key] = [];
            }
            foreach ($data as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    $data[$key] = array_values(array_filter($value, fn ($row) => ! is_array($row)
                        || (! ($row['deleted'] ?? false) && (! isset($row['estado']) || in_array($row['estado'], ($key==='documentos'?['Publicado','Histórico']:['Publicado', 'Publicada', 'Activo', 'Vigente', 'Abierto', 'Cerrado']))))));
                }
            }
            // La fecha de publicación se aplica en el servidor, también a los adjuntos.
            $data['noticias']=array_values(array_filter($data['noticias']??[],fn($row)=>!empty($row['fecha'])&&strtotime($row['fecha'])!==false&&strtotime($row['fecha'])<=time()));
            $data['personas'] = array_map(function ($person) {
                foreach (['dni', 'numDoc', 'direccion', 'fechaNacimiento', 'correoPersonal', 'telefonoPersonal'] as $key) {
                    unset($person[$key]);
                }
                if (isset($person['cv'])) {
                    if (($person['cv']['autoriza']['ok'] ?? false)!==true) {
                        unset($person['cv']);
                    } else {
                        foreach (['dni', 'nacimiento', 'fnac', 'sexo', 'direccion', 'domicilio', 'correoPersonal', 'celular', 'huella', 'docs'] as $key) {
                            unset($person['cv'][$key]);
                        }
                    }
                }

                return $person;
            }, $data['personas'] ?? []);
            $academic = $data['academico'] ?? [];
            $publicAcademic = [];
            foreach ($academic as $key => $value) {
                $publicAcademic[$key] = is_array($value) && array_is_list($value) ? [] : $value;
            }
            foreach (['programas', 'modulos', 'areas', 'tramites'] as $key) {
                $publicAcademic[$key] = $academic[$key] ?? [];
            }
            $publicAcademic['empresas'] = array_values(array_map(fn ($company) => array_intersect_key($company,
                array_flip(['id', 'nom', 'ruc', 'rubro', 'ubic', 'logo', 'web', 'validada', 'estadoVal'])),
                array_filter($academic['empresas'] ?? [], fn ($company) => ($company['validada'] ?? false))));
            $publicAcademic['ofertas'] = array_values(array_filter($academic['ofertas'] ?? [], fn ($offer) => ($offer['estado'] ?? '') === 'Publicada'));
            $data['academico'] = $publicAcademic;
            unset($data['config']['contadores']);

        $data['becarios']=array_values(array_map(function($r){$r=array_intersect_key($r,array_flip(['id','periodoId','apellidos','nombres','programaId','tipoId','motivo','estado','dni']));$r['dni']='****'.substr((string)($r['dni']??''),-4);return $r;},array_filter($scholarships,fn($r)=>!($r['deleted']??false)&&($r['estado']??'')==='Publicado')));
        return $data;
    }
    public function bootstrap(): array
    {
        $state=$this->state();$full=json_decode($state['data'],true,flags:JSON_THROW_ON_ERROR);$user=$this->user();
        try{$full['visitas']=$this->db()->query('SELECT visit_day,views FROM pisco_visits')->fetchAll(PDO::FETCH_KEY_PAIR);}catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;$full['visitas']=[];}
        $data=PortalPolicy::project($full,$user,$this->publicData($full));
        $data['usuarios']=$this->visibleAccounts($user,$full);
        if($user&&$user['rol']==='admin'){$data['auditoria']=[...PortalAudit::rows($this),...PortalAudit::legacy($this,$full)];$data['academico']['bitacora']=array_map(fn($r)=>['id'=>$r['id'],'f'=>$r['fecha'],'u'=>$r['usuario'],'a'=>$r['accion'],'m'=>$r['entidad'],'d'=>$r['registro'].' · '.$r['detalle']],PortalAudit::rows($this));}
        return ['data'=>$data,'revision'=>(int)$state['revision'],'user'=>$user,'csrf'=>$_SESSION['csrf'],'schema_version'=>PortalSchema::VERSION,'roles'=>PortalPolicy::definitions()['roles']];
    }
    public function input(): array
    {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 64 * 1024 * 1024) {
            throw new PortalError(413, 'La solicitud es demasiado grande. Máximo 40 MB de adjuntos en total.');
        }
        try {
            $input = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PortalError(422, 'La solicitud no contiene JSON válido.');
        }
        if (!is_array($input)) throw new PortalError(422, 'Solicitud no válida.');
        PortalRules::structure($input);
        return $input;
    }

    public function checkCsrf(string $token): void
    {
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new PortalError(419, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
        }
    }

    public function user(): ?array
    {
        if(empty($_SESSION['user_id']))return null;
        if(!isset($_SESSION['auth_version'])){unset($_SESSION['user_id']);return null;}
        $statement=$this->db()->prepare('SELECT * FROM pisco_users WHERE id=? AND active=1');$statement->execute([$_SESSION['user_id']]);$user=$statement->fetch();
        if(!$user){unset($_SESSION['user_id']);return null;}
        if(isset($_SESSION['auth_version'])&&(int)$_SESSION['auth_version']!==(int)($user['auth_version']??1)){unset($_SESSION['user_id'],$_SESSION['auth_version']);return null;}
        $_SESSION['auth_version']=(int)($user['auth_version']??1);return $this->accountDto($user);
    }
    public function requireUser(): array {return $this->user()??throw new PortalError(403,'Inicia sesión para continuar.');}
    public function requireAdmin(): array
    {
        $user=$this->requireUser();if(PortalPolicy::role($user)!=='admin')throw new PortalError(403,'Esta acción requiere una cuenta administradora.');return $user;
    }
    private function state(bool $lock = false): array
    {
        $state = $this->db()->query('SELECT data, revision FROM pisco_state WHERE id = 1'.($lock ? ' FOR UPDATE' : ''))->fetch();
        if (!$state) throw new PortalError(503, 'Completa la instalación en /install.php.');
        $state['data']=json_encode($this->normalizeDates(json_decode($state['data'],true,flags:JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        return $state;
    }

    private function normalizeDates(array $data): array
    {
        foreach(['noticias','eventos'] as $key)foreach($data[$key]??[] as $i=>$r)if(!empty($r['fecha'])){try{$data[$key][$i]['fecha']=(new DateTimeImmutable($r['fecha'],new DateTimeZone($this->config['timezone']??'America/Lima')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');}catch(Exception){}}
        return $data;
    }

    private function transaction(callable $operation): array
    {
        $db = $this->db();
        $sessionBefore=$_SESSION;
        $db->beginTransaction();
        try {
            $result = $operation();
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION=$sessionBefore;
            throw $error;
        }
    }

    private function updateState(array $data, int $revision): void
    {
        $statement = $this->db()->prepare('UPDATE pisco_state SET data = ?, revision = ? WHERE id = 1');
        $statement->execute([json_encode($this->normalizeDates($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $revision]);
    }

    private function rate(string $action, int $limit, int $seconds): void
    {
        $window = intdiv(time(), $seconds) * $seconds;
        $key = hash('sha256', $action.'|'.($_SERVER['REMOTE_ADDR'] ?? 'local').'|'.$window);
        $statement = $this->db()->prepare('INSERT INTO pisco_rate_limits (rate_key,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits = hits + 1');
        $statement->execute([$key, $window + $seconds]);
        $statement = $this->db()->prepare('SELECT hits FROM pisco_rate_limits WHERE rate_key = ?');
        $statement->execute([$key]);
        if ((int)$statement->fetchColumn() > $limit) {
            header('Retry-After: '.max(1, $window + $seconds - time()));
            throw new PortalError(429, 'Demasiados intentos. Espera unos minutos antes de continuar.');
        }
        if (random_int(1, 100) === 1) {
            $statement = $this->db()->prepare('DELETE FROM pisco_rate_limits WHERE expires_at < ? LIMIT 1000');
            $statement->execute([time()]);
        }
    }

    public function login(array $input): array
    {
        $input=self::validate($input,['username'=>'required|string|max:80','password'=>'required|string|max:72']);$username=strtolower(trim($input['username']));
        $this->rate('login',60,60);
        $db=$this->db();$db->beginTransaction();
        try{
            $stmt=$db->prepare('SELECT * FROM pisco_users WHERE username=? AND active=1 FOR UPDATE');$stmt->execute([$username]);$row=$stmt->fetch();
            if($row&&!empty($row['locked_until'])&&strtotime($row['locked_until'])>time()){$db->commit();throw new PortalError(423,'Cuenta bloqueada por intentos fallidos. Espera cinco minutos o solicita el desbloqueo.');}
            $ok=password_verify($input['password'],$row['password']??'$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if(isset($input['role'])&&(!is_string($input['role'])||$input['role']!==($row['role']??'')))$ok=false;
            if(!$row||!$ok){
                if($row){$failures=(int)($row['login_failures']??0)+1;$locked=$failures>=5?date('Y-m-d H:i:s',time()+300):null;
                    $db->prepare('UPDATE pisco_users SET login_failures=?,locked_until=? WHERE id=?')->execute([$locked?0:$failures,$locked,$row['id']]);}
                PortalAudit::write($this,'Intento de acceso fallido','Cuentas',$username,['bloqueada'=>isset($locked)&&$locked?'sí':'no'],['usuario'=>'visitante','rol'=>'publico']);
                $db->commit();throw new PortalError(422,'Usuario o contraseña incorrectos.');
            }
            if(password_needs_rehash($row['password'],PASSWORD_DEFAULT))$db->prepare('UPDATE pisco_users SET password=? WHERE id=?')->execute([password_hash($input['password'],PASSWORD_DEFAULT),$row['id']]);
            $db->prepare('UPDATE pisco_users SET login_failures=0,locked_until=NULL,last_login=? WHERE id=?')->execute([date('Y-m-d H:i:s'),$row['id']]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        PortalAudit::write($this,'Iniciar sesión','Cuentas',(string)$row['id'],[], $this->accountDto($row));
        session_regenerate_id(true);$_SESSION['user_id']=(int)$row['id'];$_SESSION['auth_version']=(int)($row['auth_version']??1);$_SESSION['csrf']=bin2hex(random_bytes(32));return ['ok'=>true];
    }

    public function logout(): array
    {
        if($this->user())PortalAudit::write($this,'Cerrar sesión','Cuentas',(string)$this->user()['server_id']);
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return ['ok' => true];
    }

    public function save(array $input): array
    {
        $user=$this->requireUser();$this->rate('save',120,60);
        if(!isset($input['revision'])||!is_int($input['revision'])||(!isset($input['patch'])&&!is_array($input['data']??null)))throw new PortalError(422,'Contenido o revisión no válidos.');
        return $this->transaction(function()use($input,$user){
            $state=$this->state(true);$user=$this->requireUser();if(!isset($input['patch'])&&(int)$state['revision']!==$input['revision'])throw new PortalError(409,'Otra persona guardó cambios. Recarga la página antes de continuar.');
            $previous=json_decode($state['data'],true,flags:JSON_THROW_ON_ERROR);
            foreach($input['data']['usuarios']??[] as $account)if(!is_array($account)||isset($account['passHash'],$account['password'])||isset($account['passHash'])||isset($account['password']))throw new PortalError(422,'Las contraseñas se gestionan mediante el servicio de cuentas.');
            $visible=PortalPolicy::project($previous,$user,$this->publicData($previous));$visible['usuarios']=$this->visibleAccounts($user,$previous);
            $incoming=isset($input['patch'])?PortalPatch::apply($visible,$input['patch']):$input['data'];
            $data=PortalPolicy::apply($previous,$visible,$incoming,$user);
            PortalRules::structure($data);
            $ops=$input['accounts']??[];if(!is_array($ops)||count($ops)>100)throw new PortalError(422,'Operaciones de cuentas no válidas.');
            foreach($ops as $operation){if(!is_array($operation))throw new PortalError(422,'Cuenta no válida.');$this->accountOperation($operation);}
            $aliases=[];$data=PortalFiles::persist($this,$data,$aliases,0,$previous);
            PortalServices::apply($previous,$data,$user);PortalRules::apply($previous,$data,$user);PortalRules::catalogs($data);
            PortalAcademic::validate($previous,$data);
            PortalDomain::validate($previous,$data,$user);PortalDomain::syncCertificates($data);$this->validateBindings($data);
            $allAccounts=array_map($this->accountDto(...),$this->db()->query('SELECT * FROM pisco_users')->fetchAll());
            PortalDomain::notifications($previous,$data,$allAccounts);
            $data['usuarios']=[];$data['__v']=13;$data['visitas']=$previous['visitas']??[];
            $changed=[];foreach($data as $key=>$value)if(!PortalPolicy::equal($value,$previous[$key]??null))$changed[]=$key;
            PortalAudit::changes($this,$previous,$data,$user);
            $data['auditoria']=$previous['auditoria']??[];
            $this->updateState($data,(int)$state['revision']+1);
            $data=$this->normalizeDates($data);
            $projected=PortalPolicy::project($data,$this->user(),$this->publicData($data));
            if($this->user()&&$this->user()['rol']==='admin'){$projected['auditoria']=[...PortalAudit::rows($this),...PortalAudit::legacy($this,$data)];$projected['academico']['bitacora']=array_map(fn($r)=>['id'=>$r['id'],'f'=>$r['fecha'],'u'=>$r['usuario'],'a'=>$r['accion'],'m'=>$r['entidad'],'d'=>$r['registro'].' · '.$r['detalle']],PortalAudit::rows($this));}
            return ['revision'=>(int)$state['revision']+1,'user'=>$this->user(),'users'=>$this->visibleAccounts($this->user(),$data),'normalized'=>$projected,'files'=>array_values($aliases)];
        });
    }
    public function track(array $input): array
    {
        $user=$this->user();if($user&&$user['rol']==='admin')return ['ok'=>true];
        $path=$input['path']??'';if(!is_string($path)||strlen($path)>250||!str_starts_with($path,'#/')||str_starts_with($path,'#/admin'))throw new PortalError(422,'Ruta no válida.');
        $this->rate('visits',60,60);$path=explode('?',$path)[0];$seen=$_SESSION['page_views'][$path]??0;
        if(time()-$seen<10)return ['ok'=>true];
        if(count($_SESSION['page_views']??[])>100)$_SESSION['page_views']=[];
        $_SESSION['page_views'][$path]=time();
        $this->db()->prepare('INSERT INTO pisco_visits (visit_day,views) VALUES (?,1) ON DUPLICATE KEY UPDATE views=views+1')->execute([date('Y-m-d')]);
        return ['ok'=>true];
    }
    public function upload(): array
    {
        $this->rate('uploads',30,60);
        if(!$this->user()){try{$proof=json_decode(is_string($_POST['proof']??null)?$_POST['proof']:'',true,flags:JSON_THROW_ON_ERROR);}catch(JsonException){throw new PortalError(422,'Verifica el formulario antes de subir adjuntos.');}$purpose=$_POST['purpose']??'';if(!in_array($purpose,['expedientes','reclamos'],true))throw new PortalError(422,'Formulario de adjuntos no válido.');$this->submissionProof($purpose,['proof'=>$proof,'idempotency_key'=>'upload']);}
        $file=$_FILES['file']??null;
        if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new PortalError(422,'No se pudo recibir el archivo. Revisa los límites de tamaño de PHP.');
        if((int)$file['size']>40*1024*1024)throw new PortalError(413,'Máximo 40 MB por archivo.');
        return ['file'=>PortalFiles::store($this,$file['name'],file_get_contents($file['tmp_name']))];
    }
    public function registerCompany(array $input): array
    {
        $this->rate('company-registration',5,300);
        if(($input['declaration']??false)!==true)throw new PortalError(422,'Debes aceptar la declaración de registro y el tratamiento de datos.');
        $r=self::validate($input,['ruc'=>'required|string|max:11','nom'=>'required|string|max:200','sector'=>'required|string|max:100','ubic'=>'required|string|max:200','dir'=>'required|string|max:300','contacto'=>'required|string|max:150','cargoC'=>'required|string|max:150','correo'=>'required|email|max:254','tel'=>'required|string|max:30','web'=>'nullable|string|max:300','password'=>'required|string|min:12|max:72']);
        if(!preg_match('/^(10|15|17|20)[0-9]{9}$/',$r['ruc']))throw new PortalError(422,'RUC no válido.');
        $weights=[5,4,3,2,7,6,5,4,3,2];$sum=0;foreach($weights as $i=>$w)$sum+=(int)$r['ruc'][$i]*$w;$digit=11-$sum%11;if($digit===10)$digit=0;if($digit===11)$digit=1;
        if($digit!==(int)$r['ruc'][10])throw new PortalError(422,'El dígito verificador del RUC no es válido.');
        return $this->transaction(function()use($r){
            $state=$this->state(true);$data=json_decode($state['data'],true);
            if(PortalPolicy::find($data['academico']['empresas'],'ruc',$r['ruc']))throw new PortalError(422,'Ya existe una empresa con ese RUC.');
            $id=max([0,...array_column($data['academico']['empresas'],'id')])+1;
            $company=[...array_diff_key($r,['password'=>1]),'id'=>$id,'validada'=>false,'estadoVal'=>'Pendiente','fechaReg'=>date('Y-m-d')];
            $data['academico']['empresas'][]=$company;PortalDomain::staff($data,'Empresa pendiente de validación: '.$company['nom'],'bolsa');$this->updateState($data,(int)$state['revision']+1);
            PortalAudit::write($this,'Registrar empresa','academico.empresas',(string)$id);
            $this->createAccount(['username'=>$r['ruc'],'name'=>$r['nom'],'email'=>$r['correo'],'password'=>$r['password'],'role'=>'empresa','vin'=>'emp:'.$id,'dni'=>$r['ruc']],false);
            return ['company'=>$company,'username'=>$r['ruc'],'revision'=>(int)$state['revision']+1];
        });
    }
    public function interfaceEvent(array $input): array
    {
        $this->requireUser();$this->rate('events',120,60);
        $action=$input['action']??'';if(!in_array($action,['Exportar','Vista previa'],true))throw new PortalError(422,'Evento no permitido.');
        $filename=$input['filename']??'';if(!is_string($filename)||strlen($filename)>200)throw new PortalError(422,'Nombre de archivo no válido.');
        PortalAudit::write($this,$action,'Interfaz',$filename,['origen'=>'Solicitud desde la interfaz','bytes'=>max(0,(int)($input['size']??0))]);return ['ok'=>true];
    }
    private function validateBindings(array $data): void
    {
        foreach($this->db()->query('SELECT username,role,profile FROM pisco_users WHERE active=1')->fetchAll() as $row){$p=json_decode($row['profile']??'{}',true)??[];$target=null;
            if(in_array($row['role'],['estudiante','egresado'],true))$target=PortalPolicy::find($data['academico']['estudiantes'],'id',PortalPolicy::binding($p,'est'));
            elseif($row['role']==='empresa')$target=PortalPolicy::find($data['academico']['empresas'],'id',PortalPolicy::binding($p,'emp'));
            elseif($row['role']==='docente'){$target=PortalPolicy::find($data['academico']['docentes'],'id',PortalPolicy::binding($p,'doc'));$person=PortalPolicy::find($data['personas'],'id',$p['personaId']??'');if(!$person||($person['deleted']??false))$target=null;}
            elseif($row['role']==='coordinador')$target=PortalPolicy::find($data['programas'],'id',$p['programaId']??'');else continue;
            if(!$target||($target['deleted']??false))throw new PortalError(422,'El registro está vinculado a la cuenta activa '.$row['username'].'. Desactiva o cambia su vínculo antes de eliminarlo.');
        }
    }
    public function installed(): bool
    {
        try { return (int)$this->db()->query('SELECT COUNT(*) FROM pisco_users')->fetchColumn() > 0; }
        catch (PDOException $error) {
            if ($error->getCode() === '42S02') return false;
            throw $error;
        }
    }

    public function install(array $input): void
    {
        $key = $this->config['install_key'] ?? '';
        if (!is_string($key) || strlen($key) < 32) throw new PortalError(503, 'Configura una clave de instalación privada de al menos 32 caracteres.');
        if (!is_string($input['install_key'] ?? null) || !hash_equals($key, $input['install_key'])) throw new PortalError(403, 'La clave de instalación no coincide.');
        $lock = fopen(__DIR__.'/install.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) throw new PortalError(503, 'No se pudo proteger la instalación. Revisa los permisos de portal-private.');
        try {
            if ($this->installed()) throw new PortalError(410, 'La aplicación ya está instalada.');
            $this->accountInput($input);
            PortalSchema::apply($this->db());
            $this->transaction(function () use ($input) {
                $seed = file_get_contents(__DIR__.'/data/portal.json');
                json_decode($seed, true, flags: JSON_THROW_ON_ERROR);
                $this->db()->prepare('INSERT INTO pisco_state (id,data,revision) VALUES (1,?,1) ON DUPLICATE KEY UPDATE id = id')->execute([$seed]);
                $this->createAccount($input, false);
                return [];
            });
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function validate(array $input, array $rules): array
    {
        $result = [];
        foreach ($rules as $field => $specification) {
            if (str_contains($field, '.*')) continue;
            $spec = explode('|', $specification);
            $present = array_key_exists($field, $input);
            $value = $input[$field] ?? null;
            if (!$present || $value === null || $value === '') {
                if (in_array('required', $spec, true)) throw new PortalError(422, 'Completa el campo '.$field.'.');
                if ($present) $result[$field] = $value;
                continue;
            }
            foreach ($spec as $rule) {
                [$kind, $argument] = array_pad(explode(':', $rule, 2), 2, '');
                $valid = match ($kind) {
                    'string' => is_string($value),
                    'array' => is_array($value),
                    'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'in' => is_string($value) && in_array($value, explode(',', $argument), true),
                    'date' => is_string($value) && ($date = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) !== false && $date->format('Y-m-d') === $value,
                    'before_or_equal' => is_string($value) && $value <= date('Y-m-d'),
                    'min', 'max' => true,
                    default => true,
                };
                if (!$valid) throw new PortalError(422, 'Revisa el campo '.$field.'.');
                if ($kind === 'min' || $kind === 'max') {
                    $size = in_array('integer', $spec, true) ? (int)$value : (is_array($value) ? count($value) : preg_match_all('/./us', (string)$value));
                    if (($kind === 'min' && $size < (int)$argument) || ($kind === 'max' && $size > (int)$argument)) throw new PortalError(422, 'El tamaño del campo '.$field.' no es válido.');
                }
            }
            $result[$field] = in_array('integer', $spec, true) ? (int)$value : $value;
        }
        return $result;
    }
    public function submit(string $type, array $input): array
    {
        if (!isset(self::RULES[$type])) throw new PortalError(404, 'Tipo de solicitud no disponible.');
        $this->rate('submissions', 10, 60);
        if (!is_string($input['idempotency_key'] ?? null) || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $input['idempotency_key']) || !is_array($input['record'] ?? null)) {
            throw new PortalError(422, 'Solicitud no válida.');
        }
        $this->submissionProof($type,$input);
        $record = self::validate($input['record'], self::RULES[$type]);
        if (!empty($record['archivos'])) {
            $record['archivos'] = array_map($this->validateFile(...), $record['archivos']);
        }
        if (!empty($record['adjunto'])) $record['adjunto'] = $this->validateFile($record['adjunto']);
        if (array_sum(array_column($record['archivos'] ?? [], 'size')) + ($record['adjunto']['size'] ?? 0) > 40 * 1024 * 1024) {
            throw new PortalError(422, 'Los adjuntos no pueden superar 40 MB en total.');
        }
        return $this->transaction(function () use ($record, $type, $input) {
            $state = $this->state(true);
            $data = json_decode($state['data'], true, flags: JSON_THROW_ON_ERROR);
            $record=$this->formDomain($type,$record,$data);
            $id = hash('sha256', session_id().'|'.$type.'|'.$input['idempotency_key']);
            foreach ($data[$type] ?? [] as $existing) {
                if (($existing['id'] ?? null) === $id) { $_SESSION['challenges'][$input['proof']['challenge_id']]['used_key']=$input['idempotency_key'];return ['record' => $existing, 'status' => 200,'revision'=>(int)$state['revision'],'counters'=>$this->user()?($data['config']['contadores']??[]):null]; }
            }
            $record['id'] = $id;
            $record['fecha'] = date(DATE_ATOM);
            $record['estado'] = $type === 'mensajes' ? 'Nuevo' : 'Recibido';
            $record['historial'] = [];
            $year = date('Y');
            if ($type === 'reclamos') {
                $count = (int)($data['config']['contadores']['RCL'][$year] ?? 0) + 1;
                $data['config']['contadores']['RCL'][$year] = $count;
                $record['codigo'] = sprintf('RCL-%s-%05d', $year, $count);
                do { $record['cargoCod'] = strtoupper(bin2hex(random_bytes(8))); }
                while (PortalPolicy::find($data['reclamos'], 'cargoCod', $record['cargoCod']) !== null);
                preg_match('/^.{0,80}/us', $record['descripcion'], $prefix); $record['asunto'] = $prefix[0] ?? '';
                $cfg = $data['config']['lr'];
                $days = (int)($cfg[$record['tipo'] === 'Queja' ? 'plazoQueja' : 'plazoReclamo'] ?? 15);
                $record += ['plazoDias' => $days, 'vence' => $this->deadline($days, $data)->format(DATE_ATOM),
                    'responsable' => ['nombre' => $cfg['responsable'], 'cargo' => $cfg['cargo'], 'correo' => $cfg['correo']],
                    'area' => '', 'notas' => [], 'respuesta' => '', 'notifInst' => 'Omitido', 'notifUser' => 'Omitido', 'anulado' => false];
            }
            if ($type === 'expedientes') {
                foreach (['MPV', 'DOC'] as $counter) {
                    $data['config']['contadores'][$counter][$year] = (int)($data['config']['contadores'][$counter][$year] ?? 0) + 1;
                }
                $cfg = $data['config']['mpv'];
                $record['numero'] = sprintf('%s-%s-%06d', $cfg['prefijo'], $year, $data['config']['contadores']['MPV'][$year]);
                $record['numDocumento'] = sprintf('DOC-%s-%06d', $year, $data['config']['contadores']['DOC'][$year]);
                do { $record['cargoCod'] = strtoupper(bin2hex(random_bytes(8))); }
                while (PortalPolicy::find($data['expedientes'], 'cargoCod', $record['cargoCod']) !== null);
                $received = new DateTimeImmutable();
                $business = fn ($date) => (int)$date->format('N') < 6 && !in_array($date->format('Y-m-d'), $cfg['feriados'] ?? [], true);
                $inHours = $business($received) && $received->format('H:i') >= $cfg['horaIni'] && $received->format('H:i') <= $cfg['horaFin'];
                if (!$inHours) {
                    if (!$business($received) || $received->format('H:i') > $cfg['horaFin']) {
                        do { $received = $received->modify('+1 day'); } while (!$business($received));
                    }
                    [$hour, $minute] = explode(':', $cfg['horaIni']);
                    $received = $received->setTime((int)$hour, (int)$minute);
                }
                $record += ['fechaRecepcion' => $received->format(DATE_ATOM), 'enHorario' => $inHours,
                    'usuarioRegistro' => $this->user()['nombre']??'Ciudadano – portal web', 'canal' => 'Mesa de Partes Virtual',
                    'area' => 'MP', 'archivo' => $record['archivos'][0]];
            }
            if (in_array($type, ['reclamos', 'expedientes'], true)) {
                $record['historial'] = [['fecha' => $record['fecha'], 'usuario' => 'portal', 'estado' => 'Recibido', 'nota' => 'Registro recibido desde el portal.']];
            }
            $data[$type] = [$record, ...($data[$type] ?? [])];
            if(in_array($type,['mensajes','reclamos','expedientes'],true))PortalDomain::staff($data,'Nuevo registro: '.($record['codigo']??$record['numero']??$record['asunto']??$type),$type==='expedientes'?'mesa':$type);
            $this->updateState($data, (int)$state['revision'] + 1);
            PortalAudit::write($this,'Registrar solicitud',$type,$record['codigo']??$record['numero']??$id);
            $_SESSION['challenges'][$input['proof']['challenge_id']]['used_key']=$input['idempotency_key'];
            return ['record' => $record, 'status' => 201,'revision'=>(int)$state['revision']+1,'counters'=>$this->user()?($data['config']['contadores']??[]):null];
        });
    }

    public function lookup(string $type, array $input): array
    {
        if (!in_array($type, ['expedientes', 'reclamos', 'certificados', 'becarios'], true)) throw new PortalError(404, 'Consulta no disponible.');
        $this->rate('lookups', 10, 60);
        $input = self::validate($input, ['reference' => 'required|string|max:150', 'document' => (in_array($type,['certificados','becarios'],true) ? 'nullable' : 'required').'|string|max:50', 'mode' => 'nullable|string|max:10']);
        $data = json_decode($this->state()['data'], true, flags: JSON_THROW_ON_ERROR);
        if (!in_array($input['mode'] ?? '', ['', 'codigo', 'dni'], true)) throw new PortalError(422, 'Modo de consulta no válido.');
        if ($type === 'certificados' && ($input['mode'] ?? '') === 'dni' && !preg_match('/^[0-9]{8}$/', $input['reference'])) throw new PortalError(422, 'El DNI debe tener ocho dígitos.');
        if ($type === 'becarios') {
            if(!preg_match('/^[0-9]{8}$/',$input['reference']))throw new PortalError(422,'DNI no válido.');
            $matches=[];foreach($data['becarios']??[] as $r)if(self::publishedScholarship($r,$data)&&($r['dni']??'')===$input['reference']){$r=array_intersect_key($r,array_flip(['id','periodoId','apellidos','nombres','programaId','tipoId','motivo','estado','dni']));$r['dni']='****'.substr($r['dni'],-4);$matches[]=$r;}return ['records'=>$matches];
        }
        if ($type === 'certificados' && ($input['mode'] ?? '') === 'dni') {
            $matches=[]; foreach ($data['certificados']??[] as $r) if (!($r['deleted']??false) && ($r['dni']??'')===$input['reference']) $matches[]=array_intersect_key($r,array_flip(['id','codigo','nombres','programaId','tipo','detalle','fecha','estado','origen','motivo']));
            return ['records'=>$matches];
        }
        if ($type === 'reclamos') {
            $input['reference']=strtoupper(trim($input['reference']));$input['document']=strtoupper(trim($input['document']));
            if (!preg_match('/^[A-Z0-9-]{6,20}$/D',$input['document'])) throw new PortalError(422,'Ingresa el documento completo del reclamante (DNI de 8 dígitos, no el número de folios) o el código de verificación de 16 caracteres.');
        }
        $claimReference=fn($value)=>preg_replace('/^(RCL|LR)-(\d{4})-0*(\d+)$/i','$1-$2-$3',strtoupper(trim((string)$value)));
        $field = $type === 'expedientes' ? 'numero' : 'codigo';
        foreach ($data[$type] ?? [] as $record) {
            if ($record['deleted'] ?? false) continue;
            if ($type==='reclamos' ? $claimReference($record[$field]??'')!==$claimReference($input['reference']) : strtoupper($record[$field] ?? '') !== strtoupper($input['reference'])) continue;
            $recordDocument=(string)($record['numDoc']??'');if($type==='reclamos')$recordDocument=strtoupper(trim($recordDocument));
            if ($type !== 'certificados' && !hash_equals($recordDocument, $input['document'])
                && (empty($record['cargoCod']) || !hash_equals($record['cargoCod'], strtoupper($input['document'])))) continue;
            foreach (['archivos', 'archivo', 'adjunto', 'notas', 'notaInterna', 'correo', 'telefono', 'direccion', 'usuarioRegistro'] as $key) unset($record[$key]);
            if ($type === 'certificados') {
                $record = array_intersect_key($record, array_flip(['id', 'codigo', 'nombres', 'programaId', 'tipo', 'detalle', 'fecha', 'estado', 'origen', 'motivo']));
            } else {
                $record['historial'] = array_map(fn ($item) => array_intersect_key($item, array_flip(['fecha', 'estado'])), $record['historial'] ?? []);
            }
            return ['record' => $record];
        }
        throw new PortalError(404, 'No se encontró un registro con esos datos.');
    }

    private function deadline(int $days, array $data): DateTimeImmutable
    {
        $date = new DateTimeImmutable();
        while ($days > 0) {
            $date = $date->modify('+1 day');
            if ((int)$date->format('N') < 6 && !in_array($date->format('Y-m-d'), $data['config']['mpv']['feriados'] ?? [], true)) $days--;
        }
        return $date;
    }

    private function validateFile(mixed $file): array
    {
        if (!is_array($file)) throw new PortalError(422, 'Adjunto no válido.');
        if (isset($file['fileId'])) return PortalFiles::metadata($this, $file, true);
        $valid = self::validate($file, ['original' => 'required|string|max:255', 'data' => 'required|string|max:57000000']);
        if (!preg_match('/^data:([^;,]+);base64,(.*)$/s', $valid['data'], $match)) throw new PortalError(422, 'Adjunto no válido.');
        $bytes = base64_decode($match[2], true);
        if ($bytes === false || strlen($bytes) > 40 * 1024 * 1024) throw new PortalError(422, 'Adjunto no válido o demasiado grande.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extensions = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'],
            'application/zip' => ['docx', 'xlsx', 'pptx'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx']];
        if (!isset($extensions[$mime]) || !in_array(strtolower(pathinfo($valid['original'], PATHINFO_EXTENSION)), $extensions[$mime], true)) {
            throw new PortalError(422, 'El contenido del adjunto no coincide con un formato permitido.');
        }
        return PortalFiles::store($this, $valid['original'], $bytes);
    }
}
