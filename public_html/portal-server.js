function portalURL(url) { return url.startsWith('/api/') ? '/index.php?route=' + encodeURIComponent(url.slice(1)) : url; }
/* Adaptador de la interfaz existente a sesiones y persistencia de PHP. */
const portalBootstrap = window.PORTAL_BOOTSTRAP;
let portalRevision = portalBootstrap.revision;
let portalBaseline = JSON.stringify(portalBootstrap.data);
const portalSent = new WeakMap();
function portalRequest(method, url, body) {
  const xhr = new XMLHttpRequest();
  // El editor original espera una confirmación síncrona antes de cerrar formularios.
  xhr.open(method, portalURL(url), false);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.setRequestHeader('Accept', 'application/json');
  xhr.setRequestHeader('X-CSRF-TOKEN', portalBootstrap.csrf);
  xhr.send(body === undefined ? null : JSON.stringify(body));
  let result; try { result = JSON.parse(xhr.responseText); } catch { result = {}; }
  if (xhr.status < 200 || xhr.status >= 300) {
    if (xhr.status === 409) throw new Error('Otra persona guardó cambios. Copia tu trabajo y recarga la página antes de continuar.');
    const first = Object.values(result.errors || {}).flat()[0];
    throw new Error(first || result.message || 'No se pudo conectar con el servidor.');
  }
  return result;
}
function portalLookup(type, reference, document) {
  try {
    const result = portalRequest('POST', '/api/consultas/' + type, {reference, document});
    Object.defineProperty(result.record, '__serverSaved', {value: true, enumerable: false});
    DB[type] = DB[type] || [];
    const old = DB[type].findIndex(x => x.id === result.record.id);
    if (old < 0) DB[type].push(result.record); else DB[type][old] = result.record;
    return result.record;
  } catch (error) { toast(error.message, 'warn'); return null; }
}
loadDB = function () { DB = structuredClone(portalBootstrap.data); MEM_ONLY = false; };
currentUser = function () { return portalBootstrap.user; };
setSession = function (user) {
  if (!user && portalBootstrap.user) {
    try { portalRequest('DELETE', '/api/session'); location.href = '/#/admin'; location.reload(); }
    catch (error) { toast(error.message, 'err'); }
  }
};
saveDB = function () {
  try {
    if (portalBootstrap.user) {
      // Las cuentas se gestionan en PHP, fuera del documento de contenido.
      const body = JSON.stringify(DB);
      if (body === portalBaseline) return true;
      const result = portalRequest('PUT', '/api/portal', {data: DB, revision: portalRevision});
      portalRevision = result.revision;
      portalBaseline = body;
      return true;
    }
    for (const type of ['mensajes', 'reclamos', 'expedientes', 'suscriptores']) {
      for (const record of (DB[type] || [])) {
        if (record.__serverSaved) continue;
        if (!portalSent.has(record)) portalSent.set(record, crypto.randomUUID());
        const result = portalRequest('POST', '/api/solicitudes/' + type, {record, idempotency_key: portalSent.get(record)});
        Object.assign(record, result.record);
        Object.defineProperty(record, '__serverSaved', {value: true, enumerable: false});
      }
    }
    return true;
  } catch (error) { toast(error.message, 'err'); return false; }
};
resetDB = function () { toast('El restablecimiento se realiza desde las herramientas del servidor.', 'warn'); };
mailOut = function () { return 'Pendiente'; };
const originalLoginBinding = bindLogin;
bindLogin = function () {
  originalLoginBinding();
  const form = document.querySelector('[data-login]');
  if (!form) return;
  form.onsubmit = async event => {
    event.preventDefault();
    const button = form.querySelector('[type=submit], button:not([type=button])');
    button.disabled = true;
    try {
      const response = await fetch(portalURL('/api/session'), {method: 'POST', headers: {
        'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': portalBootstrap.csrf
      }, body: JSON.stringify({username: form.u.value.trim(), password: form.p.value})});
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudo iniciar sesión.');
      location.href = '/#/admin/dashboard'; location.reload();
    } catch (error) { document.getElementById('lg-e').textContent = error.message; button.disabled = false; }
  };
};
// El editor de usuarios anterior guardaba contraseñas en el navegador; se sustituye por el de PHP.
const originalRenderAdmin = renderAdmin;
renderAdmin = function (parts, query) {
  if (currentUser() && ['usuarios', 'perfil'].includes(parts[1])) {
    return {title: 'Cuentas', html: '<main class="wrap" style="padding:32px"><h1>Cuentas del portal</h1><p><a href="/accounts.php">Gestionar cuentas y contraseña</a></p><p><a href="#/admin/dashboard">Volver al panel</a></p></main>'};
  }
  return originalRenderAdmin(parts, query);
};
// Nunca mostrar accesos de demostración ni prometer correos que aún no se han enviado.
document.addEventListener('DOMContentLoaded', () => {
  const style = document.createElement('style');
  style.textContent = '.pz-lg-demo,.demo-users{display:none!important}';
  document.head.append(style);
});
