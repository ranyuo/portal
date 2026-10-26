/* Persistencia y autorización PHP/MySQL. Los datos del navegador no conceden permisos. */
function portalURL(url) { return url.startsWith('/api/') ? '/index.php?route=' + encodeURIComponent(url.slice(1)) : url; }
const portalBootstrap=window.PORTAL_BOOTSTRAP;
let portalRevision=portalBootstrap.revision;
let portalBaseline=structuredClone(portalBootstrap.data);
const portalPasswords=new Map();
const portalSent=new WeakMap();
const portalKnown=new Set(['mensajes','reclamos','expedientes','suscriptores'].flatMap(t=>(portalBootstrap.data[t]||[]).map(r=>t+':'+r.id)));
function portalRequest(method,url,body) {
  const xhr=new XMLHttpRequest(); xhr.open(method,portalURL(url),false);
  xhr.setRequestHeader('Content-Type','application/json');xhr.setRequestHeader('Accept','application/json');xhr.setRequestHeader('X-CSRF-TOKEN',portalBootstrap.csrf);
  xhr.send(body===undefined?null:JSON.stringify(body));let result;try{result=JSON.parse(xhr.responseText)}catch{result={}}
  if(xhr.status<200||xhr.status>=300)throw new Error(result.message||(xhr.status===409?'Otra persona guardó cambios. Copia tu trabajo y recarga la página.':'No se pudo conectar con el servidor.'));
  return result;
}
function portalMerge(target,source) {
  for(const key of Object.keys(target))if(!(key in source))delete target[key];
  for(const [key,value] of Object.entries(source)){
    if(Array.isArray(value)){if(Array.isArray(target[key]))target[key].splice(0,target[key].length,...value);else target[key]=value;}
    else if(value&&typeof value==='object'){if(!target[key]||typeof target[key]!=='object'||Array.isArray(target[key]))target[key]={};portalMerge(target[key],value);}
    else target[key]=value;
  }
}
function portalLookup(type,reference,document,mode='codigo'){
  try{
    const result=portalRequest('POST','/api/consultas/'+type,{reference,document,mode});
    const records=result.records||[result.record];
    if(mode==='dni')DB[type]=DB[type].filter(r=>r.dni!==reference);
    for(const record of records){if(!record)continue;if(mode==='dni')record.dni=reference;Object.defineProperty(record,'__serverSaved',{value:true});
      const i=DB[type].findIndex(r=>r.id===record.id);if(i<0)DB[type].push(record);else DB[type][i]=record;}
    return result.record||records;
  }catch(error){toast(error.message,'warn');return null;}
}
loadDB=function(){DB=structuredClone(portalBootstrap.data);MEM_ONLY=false;};
currentUser=function(){return portalBootstrap.user;};
setSession=function(user){if(!user&&portalBootstrap.user){try{portalRequest('DELETE','/api/session');location.href='/#/admin';location.reload()}catch(error){toast(error.message,'err')}}};
function portalAccountFields(row){return {username:row.usuario,name:row.nombre,email:row.correo||'',role:row.rol,estado:row.estado,...Object.fromEntries(['vin','dni','personaId','programaId','area','cargo'].map(k=>[k,row[k]||'']))};}
function portalAccountOperations(){
  if(currentUser()?.rol!=='admin')return [];
  const before=new Map((portalBaseline.usuarios||[]).map(r=>[r.id,r]));const ops=[];
  for(const row of DB.usuarios||[]){const old=before.get(row.id);const fields=portalAccountFields(row);const password=portalPasswords.get(row.id);
    if(!old||password||JSON.stringify(fields)!==JSON.stringify(portalAccountFields(old))){ops.push({action:old?'update':'create',id:row.server_id,account:{...fields,...(password?{password}:{} )}});}
    else if(old.bloqueadoHasta&&!row.bloqueadoHasta)ops.push({action:'unlock',id:row.server_id});
  }return ops;
}
function portalNormalizeAutomaticChanges(){
  if(!DB.academico||!portalBaseline.academico||currentUser()?.rol==='admin')return;
  const role=currentUser().rol;
  const academicRole=IX_ROLE[role];const permissions=portalBaseline.academico.perms[academicRole]||{};
  const modules={estudiantes:'alumnos',sesiones:'horarios',publicados:'horarios',certs:'certificados',notas:'certificados',titulos:'titulos',empresas:'bolsa',ofertas:'bolsa',postulaciones:'bolsa',perfilesLab:'bolsa',perfilesDoc:'docentes',uds:'config',ambientes:'config',modulos:'config',areas:'mesa',tramites:'mesa',cfg:'config',perms:'usuarios',docs:'certificados',seq:'certificados'};
  const own=role==='docente'?['perfilesDoc']:['estudiante','egresado'].includes(role)?['estudiantes','perfilesLab','postulaciones','docs']:role==='empresa'?['empresas','ofertas','postulaciones']:[];
  for(const [key,module]of Object.entries(modules))if(!own.includes(key)&&(permissions[module]||0)<2&&key in portalBaseline.academico)DB.academico[key]=structuredClone(portalBaseline.academico[key]);
  if(role!=='secretaria')DB.personas=portalBaseline.personas.map(person=>role==='docente'&&person.id===currentUser().personaId?(DB.personas.find(p=>p.id===person.id)||person):structuredClone(person));
  // Los catálogos sincronizados son de lectura; las ediciones autorizadas se realizan en sus módulos.
  for(const key of ['programas','docentes','progMap'])DB.academico[key]=structuredClone(portalBaseline.academico[key]);
  if(!['admin','secretaria'].includes(currentUser()?.rol))DB.certificados=structuredClone(portalBaseline.certificados);
  for(const key of ['nombre','siglas','director','direccion','web'])DB.academico.cfg[key]=portalBaseline.academico.cfg[key];
}
saveDB=function(){
  try{
    if(!currentUser()||!location.hash.startsWith('#/admin')){
      for(const type of ['mensajes','reclamos','expedientes','suscriptores'])for(const record of DB[type]||[]){
        if(record.__serverSaved||portalKnown.has(type+':'+record.id))continue;
        if(!portalSent.has(record))portalSent.set(record,crypto.randomUUID());
        const result=portalRequest('POST','/api/solicitudes/'+type,{record,idempotency_key:portalSent.get(record)});
        Object.assign(record,result.record);Object.defineProperty(record,'__serverSaved',{value:true});portalKnown.add(type+':'+record.id);
      }return true;
    }
    portalNormalizeAutomaticChanges();
    const accounts=portalAccountOperations();
    if(!accounts.length&&JSON.stringify(DB)===JSON.stringify(portalBaseline))return true;
    const result=portalRequest('PUT','/api/portal',{data:DB,revision:portalRevision,accounts});
    portalRevision=result.revision;
    if(result.normalized){result.normalized.usuarios=result.users||DB.usuarios;portalMerge(DB,result.normalized);}
    portalBootstrap.user=result.user;portalPasswords.clear();portalBaseline=structuredClone(DB);return true;
  }catch(error){toast(error.message,'err');return false;}
};
resetDB=function(){toast('El restablecimiento se realiza desde la copia de seguridad del servidor.','warn');};
mailOut=function(){return 'Omitido';};
const portalOriginalLogin=bindLogin;
bindLogin=function(){portalOriginalLogin();const form=document.querySelector('[data-login]');if(!form)return;
  form.onsubmit=async event=>{event.preventDefault();const button=form.querySelector('[type=submit],button:not([type=button])');button.disabled=true;
    try{const response=await fetch(portalURL('/api/session'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':portalBootstrap.csrf},body:JSON.stringify({username:form.u.value.trim(),password:form.p.value})});
      const data=await response.json();if(!response.ok)throw new Error(data.message||'No se pudo iniciar sesión.');location.href='/#/admin/dashboard';location.reload();
    }catch(error){document.getElementById('lg-e').textContent=error.message;button.disabled=false;}};
};
// Se conserva el editor original de usuarios; las contraseñas viajan al servidor y se guardan con password_hash.
const portalUserFields=SPECS.usuarios.fields;
SPECS.usuarios.fields=function(work){const fields=portalUserFields(work);for(const f of fields)if(f.n==='pass1')f.hint='Mínimo 12 caracteres. La contraseña se protege con password_hash en PHP.';
  fields.push({n:'correo',l:'Correo electrónico',t:'email'});
  fields.push({n:'personaId',l:'Persona del docente (para su CV)',t:'select',opts:()=>DB.personas.map(p=>[p.id,fullName(p)])});return fields;};
const portalUserValidate=SPECS.usuarios.validate;
SPECS.usuarios.validate=function(work){const errors=portalUserValidate(work);if(work.pass1&&[...work.pass1].length<12)errors.pass1='La contraseña necesita al menos 12 caracteres.';return errors;};
SPECS.usuarios.afterSave=function(work){if(work.pass1)portalPasswords.set(work.id,work.pass1);delete work.pass1;delete work.pass2;};
const portalOriginalAdmin=renderAdmin;
renderAdmin=function(parts,query){if(currentUser()?.rol==='admin'&&parts[1]==='respaldo')return {html:admHead('Copias de seguridad','Copia completa del contenido, usuarios y archivos guardados en MySQL.','','download')+`<section class="panel"><div class="pb"><p>Guarda esta copia SQL en un lugar privado. Incluye datos personales y contraseñas protegidas de las cuentas.</p><form method="post" action="/backup.php"><input type="hidden" name="_csrf" value="${esc(portalBootstrap.csrf)}"><button class="btn">Descargar copia completa de MySQL</button></form><p>Para restaurarla, conserva primero una copia actual e importa el SQL desde phpMyAdmin en hPanel. Conserva también portal-private/config.php y los archivos del sitio.</p></div></section>`};if(currentUser()&&parts[1]==='perfil'){location.href='/accounts.php';return {html:''};}return portalOriginalAdmin(parts,query);};
IXHOST.createStudentAccount=function(student){try{const result=portalRequest('POST','/api/cuentas',{action:'student',student_id:student.id});DB.usuarios.push(result.user);portalBaseline.usuarios=structuredClone(DB.usuarios);return {usuario:result.user.usuario,pass:result.temporary_password,rolLabel:ROLES[result.user.rol].label};}catch(error){return {error:error.message};}};
IXHOST.defaultPermissions=function(){return portalBootstrap.data.academico.perms;};
IXHOST.registerCompany=function(values){return portalRequest('POST','/api/empresas',{...values,password:values.pass});};
async function portalUpload(file){
  const body=new FormData();body.append('file',file);
  const response=await fetch(portalURL('/api/archivos'),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':portalBootstrap.csrf},body});const data=await response.json();
  if(!response.ok)throw new Error(data.message||'No se pudo subir el archivo.');return data.file;
}

const portalOldDocBlob=docBlob;
docBlob=function(doc){if(doc?.fileId){const xhr=new XMLHttpRequest();xhr.open('GET',doc.url,false);xhr.overrideMimeType('text/plain; charset=x-user-defined');xhr.send();if(xhr.status!==200)throw new Error('No tienes acceso al archivo.');return new Blob([Uint8Array.from(xhr.responseText,c=>c.charCodeAt(0)&255)],{type:doc.type});}return portalOldDocBlob(doc);};
const portalOldOpenDoc=openDoc;
openDoc=function(doc){if(doc?.fileId){window.open(doc.url,'_blank','noopener');return;}portalOldOpenDoc(doc);};
const portalOldDownloadDoc=downloadDoc;
downloadDoc=function(doc){if(doc?.fileId){const a=document.createElement('a');a.href=doc.url+'&download=1';a.download=doc.original||doc.nombre;a.click();return;}portalOldDownloadDoc(doc);};
// Los campos de archivo del módulo académico antes conservaban solo nombre y tamaño.
document.addEventListener('change',async event=>{
  const input=event.target;if(!input.matches('.ix input[type=file]')||input.dataset.act||input.dataset.id)return;
  const files=[...input.files];if(!files.length){input._portalFiles=[];return;}
  input._portalUploading=true;
  try{input._portalFiles=await Promise.all(files.map(portalUpload));}catch(error){input._portalFiles=null;input.value='';toast(error.message,'err');}finally{input._portalUploading=false;}
},true);
document.addEventListener('submit',event=>{if(event.target.querySelector('input[type=file]')&&[...event.target.querySelectorAll('input[type=file]')].some(i=>i._portalUploading)){event.preventDefault();event.stopImmediatePropagation();toast('Espera a que termine la carga del archivo.','warn');}},true);
// El horario y los catálogos se generan con los datos del servidor; no se crean cifras ficticias.
seedVisits=function(){};
countVisit=function(){};
let portalLastTracked='';
function portalTrack(){const path=location.hash||'#/';if(path.startsWith('#/admin')||path===portalLastTracked)return;portalLastTracked=path;
  fetch(portalURL('/api/visitas'),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':portalBootstrap.csrf},body:JSON.stringify({path})}).catch(()=>{});}
window.addEventListener('hashchange',portalTrack);
document.addEventListener('DOMContentLoaded',()=>{const style=document.createElement('style');style.textContent='.pz-lg-demo,.demo-users{display:none!important}';document.head.append(style);portalTrack();});

// El acceso desde Bolsa laboral usa la misma sesión PHP que el resto de la intranet.
document.addEventListener('submit',async event=>{
 const form=event.target;if(!form.matches('[data-blog]'))return;event.preventDefault();event.stopImmediatePropagation();
 try{const values=new FormData(form);portalRequest('POST','/api/session',{username:values.get('u'),password:values.get('p')});location.href='/#/admin/ix-bolsa';location.reload();}catch(error){toast(error.message,'err');}
},true);
