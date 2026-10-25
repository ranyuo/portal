<?php
declare(strict_types=1);

final class PortalError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

final class PortalApp
{
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
        }
        return $this->connection;
    }

    public function bootstrap(): array
    {
        $state = $this->state();
        $data = json_decode($state['data'], true, flags: JSON_THROW_ON_ERROR);
        $user = $this->user();
        if ($user === null) {
            $data = array_intersect_key($data, array_flip(self::PUBLIC_COLLECTIONS));
            foreach (self::PRIVATE_COLLECTIONS as $key) {
                $data[$key] = [];
            }
            foreach ($data as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    $data[$key] = array_values(array_filter($value, fn ($row) => ! is_array($row)
                        || (! ($row['deleted'] ?? false) && (! isset($row['estado']) || in_array($row['estado'], ['Publicado', 'Publicada', 'Activo', 'Vigente', 'Abierto', 'Cerrado'])))));
                }
            }
            $data['personas'] = array_map(function ($person) {
                foreach (['dni', 'numDoc', 'direccion', 'fechaNacimiento', 'correoPersonal', 'telefonoPersonal'] as $key) {
                    unset($person[$key]);
                }
                if (isset($person['cv'])) {
                    if (! ($person['cv']['autoriza']['ok'] ?? false)) {
                        unset($person['cv']);
                    } else {
                        foreach (['dni', 'nacimiento', 'fnac', 'sexo', 'direccion', 'domicilio', 'correoPersonal', 'celular', 'huella', 'docs', 'cvPdf'] as $key) {
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
        }
        $data['usuarios'] = $user ? [$user] : [];
        return ['data' => $data, 'revision' => (int)$state['revision'], 'user' => $user, 'csrf' => $_SESSION['csrf']];
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
        if (empty($_SESSION['user_id'])) return null;
        $statement = $this->db()->prepare('SELECT id, username, name, email FROM pisco_users WHERE id = ? AND active = 1');
        $statement->execute([$_SESSION['user_id']]);
        $user = $statement->fetch();
        if (!$user) { unset($_SESSION['user_id']); return null; }
        return ['id' => 'server-'.$user['id'], 'server_id' => (int)$user['id'], 'usuario' => $user['username'],
            'nombre' => $user['name'], 'correo' => $user['email'], 'rol' => 'admin', 'estado' => 'Activo'];
    }

    public function requireAdmin(): array
    {
        return $this->user() ?? throw new PortalError(403, 'Inicia sesión como administrador para continuar.');
    }

    private function state(bool $lock = false): array
    {
        $state = $this->db()->query('SELECT data, revision FROM pisco_state WHERE id = 1'.($lock ? ' FOR UPDATE' : ''))->fetch();
        if (!$state) throw new PortalError(503, 'Completa la instalación en /install.php.');
        return $state;
    }

    private function transaction(callable $operation): array
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $result = $operation();
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    private function updateState(array $data, int $revision): void
    {
        $statement = $this->db()->prepare('UPDATE pisco_state SET data = ?, revision = ? WHERE id = 1');
        $statement->execute([json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $revision]);
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
        $input = self::validate($input, ['username' => 'required|string|max:80', 'password' => 'required|string|max:72']);
        $username = strtolower(trim($input['username']));
        $this->rate('login:'.$username, 5, 300);
        $statement = $this->db()->prepare('SELECT id, password FROM pisco_users WHERE username = ? AND active = 1');
        $statement->execute([$username]);
        $user = $statement->fetch();
        // Always run password_verify, including unknown usernames, to avoid a timing shortcut.
        $valid = password_verify($input['password'], $user['password'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid) throw new PortalError(422, 'Usuario o contraseña incorrectos.');
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->db()->prepare('UPDATE pisco_users SET password = ? WHERE id = ?')->execute([password_hash($input['password'], PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return ['ok' => true];
    }

    public function logout(): array
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return ['ok' => true];
    }

    public function save(array $input): array
    {
        $user = $this->requireAdmin();
        $this->rate('save', 120, 60);
        if (!isset($input['revision'], $input['data']) || !is_int($input['revision']) || !is_array($input['data'])) {
            throw new PortalError(422, 'El contenido o su revisión no son válidos.');
        }
        return $this->transaction(function () use ($input, $user) {
            $state = $this->state(true);
            if ((int)$state['revision'] !== $input['revision']) {
                throw new PortalError(409, 'Otra persona guardó cambios. Recarga la página antes de continuar.');
            }
            $data = $input['data'];
            $previous = json_decode($state['data'], true, flags: JSON_THROW_ON_ERROR);
            if (array_diff(array_keys($previous), array_keys($data))) throw new PortalError(422, 'El contenido está incompleto. Recarga la página.');
            foreach (['config', 'noticias', 'programas', 'usuarios', 'auditoria'] as $field) {
                if (!isset($data[$field]) || !is_array($data[$field])) throw new PortalError(422, 'El contenido no tiene un formato válido.');
            }
            foreach ($data['usuarios'] as $row) {
                if (!is_array($row) || isset($row['passHash']) || ($row['id'] ?? '') !== $user['id']) {
                    throw new PortalError(422, 'Gestiona las cuentas desde /accounts.php.');
                }
            }
            $data['usuarios'] = [];
            $data['__v'] = 13;
            $revision = (int)$state['revision'] + 1;
            $data['auditoria'] = array_slice([['id' => bin2hex(random_bytes(16)), 'fecha' => date(DATE_ATOM),
                'usuario' => $user['usuario'], 'rol' => 'Administrador', 'accion' => 'Guardado en servidor',
                'entidad' => 'Portal', 'registro' => 'Revisión '.$revision, 'detalle' => ''], ...$data['auditoria']], 0, 1500);
            $this->updateState($data, $revision);
            return ['revision' => $revision];
        });
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
            foreach (explode(';', file_get_contents(__DIR__.'/schema.sql')) as $sql) {
                if (trim($sql) !== '') $this->db()->exec($sql);
            }
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

    private function accountInput(array $input): array
    {
        $data = self::validate($input, ['username' => 'required|string|min:3|max:80', 'name' => 'required|string|max:150',
            'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:72']);
        $data['username'] = strtolower($data['username']);
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $data['username'])) throw new PortalError(422, 'El usuario solo admite letras, números, puntos y guiones.');
        if (!isset($input['password_confirmation']) || $data['password'] !== $input['password_confirmation']) throw new PortalError(422, 'Las contraseñas no coinciden.');
        if (strlen($data['password']) > 72) throw new PortalError(422, 'La contraseña no puede superar 72 bytes.');
        return $data;
    }

    public function accounts(): array
    {
        $this->requireAdmin();
        return $this->db()->query('SELECT username, name, email FROM pisco_users WHERE active = 1 ORDER BY username')->fetchAll();
    }

    public function createAccount(array $input, bool $authorize = true): void
    {
        if ($authorize) { $this->requireAdmin(); $this->rate('accounts', 10, 60); }
        $data = $this->accountInput($input);
        try {
            $this->db()->prepare('INSERT INTO pisco_users (username,name,email,password) VALUES (?,?,?,?)')
                ->execute([$data['username'], $data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new PortalError(422, 'El usuario o el correo ya están registrados.');
            throw $error;
        }
    }

    public function changePassword(array $input): void
    {
        $user = $this->requireAdmin();
        $this->rate('password', 5, 300);
        $data = self::validate($input, ['current_password' => 'required|string|max:72', 'password' => 'required|string|min:12|max:72']);
        if (($input['password_confirmation'] ?? null) !== $data['password'] || strlen($data['password']) > 72) throw new PortalError(422, 'Revisa la nueva contraseña y su confirmación.');
        $statement = $this->db()->prepare('SELECT password FROM pisco_users WHERE id = ?');
        $statement->execute([$user['server_id']]);
        if (!password_verify($data['current_password'], $statement->fetchColumn())) throw new PortalError(422, 'La contraseña actual no es correcta.');
        $this->db()->prepare('UPDATE pisco_users SET password = ? WHERE id = ?')->execute([password_hash($data['password'], PASSWORD_DEFAULT), $user['server_id']]);
        session_regenerate_id(true);
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
            $id = hash('sha256', session_id().'|'.$type.'|'.$input['idempotency_key']);
            foreach ($data[$type] ?? [] as $existing) {
                if (($existing['id'] ?? null) === $id) return ['record' => $existing, 'status' => 200];
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
                preg_match('/^.{0,80}/us', $record['descripcion'], $prefix); $record['asunto'] = $prefix[0] ?? '';
                $cfg = $data['config']['lr'];
                $days = (int)($cfg[$record['tipo'] === 'Queja' ? 'plazoQueja' : 'plazoReclamo'] ?? 15);
                $record += ['plazoDias' => $days, 'vence' => $this->deadline($days, $data)->format(DATE_ATOM),
                    'responsable' => ['nombre' => $cfg['responsable'], 'cargo' => $cfg['cargo'], 'correo' => $cfg['correo']],
                    'area' => '', 'notas' => [], 'respuesta' => '', 'notifInst' => 'Pendiente', 'notifUser' => 'Pendiente', 'anulado' => false];
            }
            if ($type === 'expedientes') {
                foreach (['MPV', 'DOC'] as $counter) {
                    $data['config']['contadores'][$counter][$year] = (int)($data['config']['contadores'][$counter][$year] ?? 0) + 1;
                }
                $cfg = $data['config']['mpv'];
                $record['numero'] = sprintf('%s-%s-%06d', $cfg['prefijo'], $year, $data['config']['contadores']['MPV'][$year]);
                $record['numDocumento'] = sprintf('DOC-%s-%06d', $year, $data['config']['contadores']['DOC'][$year]);
                $record['cargoCod'] = strtoupper(bin2hex(random_bytes(16)));
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
                    'usuarioRegistro' => 'Ciudadano – portal web', 'canal' => 'Mesa de Partes Virtual',
                    'area' => 'MP', 'archivo' => $record['archivos'][0]];
            }
            if (in_array($type, ['reclamos', 'expedientes'], true)) {
                $record['historial'] = [['fecha' => $record['fecha'], 'usuario' => 'portal', 'estado' => 'Recibido', 'nota' => 'Registro recibido desde el portal.']];
            }
            $data[$type] = [$record, ...($data[$type] ?? [])];
            $this->updateState($data, (int)$state['revision'] + 1);
            return ['record' => $record, 'status' => 201];
        });
    }

    public function lookup(string $type, array $input): array
    {
        if (!in_array($type, ['expedientes', 'reclamos', 'certificados'], true)) throw new PortalError(404, 'Consulta no disponible.');
        $this->rate('lookups', 10, 60);
        $input = self::validate($input, ['reference' => 'required|string|max:150', 'document' => ($type === 'certificados' ? 'nullable' : 'required').'|string|max:50']);
        $data = json_decode($this->state()['data'], true, flags: JSON_THROW_ON_ERROR);
        $field = $type === 'expedientes' ? 'numero' : 'codigo';
        foreach ($data[$type] ?? [] as $record) {
            if (strtoupper($record[$field] ?? '') !== strtoupper($input['reference'])) continue;
            if ($type !== 'certificados' && !hash_equals((string)($record['numDoc'] ?? ''), $input['document'])
                && (empty($record['cargoCod']) || !hash_equals($record['cargoCod'], strtoupper($input['document'])))) continue;
            foreach (['archivos', 'archivo', 'adjunto', 'notas', 'notaInterna', 'correo', 'telefono', 'direccion', 'usuarioRegistro'] as $key) unset($record[$key]);
            if ($type === 'certificados') {
                $record = array_intersect_key($record, array_flip(['id', 'codigo', 'nombres', 'programaId', 'tipo', 'fecha', 'estado', 'origen']));
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
        return ['nombre' => basename($valid['original']), 'original' => basename($valid['original']),
            'data' => 'data:'.$mime.';base64,'.base64_encode($bytes), 'type' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }
}
