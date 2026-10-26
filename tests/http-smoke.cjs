/* Requiere Playwright en el entorno de desarrollo, no en el hosting. */
const {chromium}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
const base=process.env.PISCO_BASE_URL||'http://127.0.0.1:8083';
if(!['127.0.0.1','localhost'].includes(new URL(base).hostname)) throw Error('Esta prueba modifica datos: utiliza solo un servidor local de pruebas.');
const fixture=JSON.parse(fs.readFileSync(process.env.PISCO_TEST_CREDENTIALS,'utf8'));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox'],headless:true});
 try{
  const adminContext=await browser.newContext(), guestContext=await browser.newContext();
  for(const context of [adminContext,guestContext])await context.route('https://**/*',r=>r.abort());
  const admin=await adminContext.newPage(),guest=await guestContext.newPage(),errors=[];
  for(const p of [admin,guest])p.on('pageerror',e=>errors.push(e.message));
  const installer=await admin.goto(base+'/install.php');
  if(installer.status()===200){
   await admin.locator('[name=install_key]').fill(fixture.install_key);
   await admin.locator('[name=name]').fill('Administrador de prueba');
   await admin.locator('[name=username]').fill('admin');
   await admin.locator('[name=email]').fill('admin@example.test');
   await admin.locator('[name=password]').fill(fixture.password);
   await admin.locator('[name=password_confirmation]').fill(fixture.password);
   await admin.getByRole('button',{name:'Instalar y crear mi cuenta'}).click();
   await admin.getByText('Instalación completada.',{exact:false}).waitFor();
   console.log('Instalador web: tablas y administrador creados');
  }else assert.equal(installer.status(),410);
  assert.equal((await admin.goto(base+'/install.php')).status(),410);
  for(const route of ['#/','#/programas','#/admision','#/noticias','#/servicios','#/contacto','#/servicios/mesa-de-partes','#/servicios/libro-de-reclamaciones','#/servicios/consulta-certificados','#/servicios/bolsa-laboral','#/admin']){
   const r=await guest.goto(base+'/'+route);if(r)assert.equal(r.status(),200);await guest.waitForTimeout(150);
   assert.ok((await guest.locator('#app').innerText()).length>100);console.log('Ruta pública:',route,'OK');
  }
  await guest.goto(base+'/');await guest.setViewportSize({width:320,height:800});
  assert.equal(await guest.locator('.home-announcement').count(),1);
  assert.ok(await guest.locator('.home-announcement').evaluate(e=>e.getBoundingClientRect().right<=innerWidth));
  await guest.goto(base+'/#/programas');await guest.waitForTimeout(150);assert.equal(await guest.locator('.home-announcement').count(),0);
  console.log('Franja amarilla: visible solo en inicio y adaptada a móvil');
  await admin.goto(base+'/#/admin');await admin.locator('#lg-u').fill('admin');await admin.locator('#lg-p').fill(fixture.password);
  await admin.locator('[data-login] button[type=submit]').click();
  await admin.waitForFunction(()=>window.PORTAL_BOOTSTRAP?.user?.usuario==='admin');
  await admin.waitForFunction(()=>typeof DB!=='undefined'&&DB?.noticias?.length>0);
  console.log('Inicio de sesión: PHP nativo');
  const original=await admin.evaluate(()=>structuredClone(DB.noticias[0]));
  await admin.goto(base+'/#/admin/noticias/'+original.id);
  const title='Noticia de prueba PHP y MySQL sin framework';
  await admin.locator('[data-cf] [name=titulo]').fill(title);
  await admin.getByRole('button',{name:'Guardar cambios',exact:true}).click();
  await admin.waitForFunction(()=>!location.hash.includes('/noticias/n1'));
  await guest.goto(base+'/#/noticias');await guest.reload();
  await guest.getByRole('heading',{name:title,exact:true}).waitFor();
  console.log('Noticia editada en un navegador y visible en otro');
  const security=await guest.evaluate(async()=>({
   csrf:(await fetch('/index.php?route=api%2Fportal',{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json'},body:'{}'})).status,
   user:(await(await fetch('/index.php?route=api%2Fportal')).json()).user
  }));assert.equal(security.csrf,419);assert.equal(security.user,null);
  assert.equal((await guest.request.get(base+'/portal-private/config.php')).status(),404);
  console.log('CSRF y carpeta privada: protegidos');
  await admin.goto(base+'/#/admin/noticias');await admin.reload();await admin.waitForFunction(()=>DB?.noticias?.length>0);
  assert.equal(await admin.evaluate(original=>{DB.noticias=DB.noticias.map(n=>n.id===original.id?original:n);return saveDB()},original),true);
  await guest.goto(base+'/#/contacto');
  await guest.locator('[name=nombres]').fill('Prueba PHP Hostinger');await guest.locator('[name=correo]').fill('prueba-php@example.test');
  await guest.locator('[name=asunto]').selectOption({label:'Información de programas de estudio'});
  await guest.locator('[name=mensaje]').fill('Mensaje de prueba desde PHP nativo y MySQL.');
  await guest.locator('#cap-c [name=captcha]').fill(String((await guest.locator('#cap-c b').innerText()).split('+').reduce((a,n)=>a+Number(n.trim()),0)));
  await guest.locator('[data-contact] [name=acepto]').check();await guest.getByRole('button',{name:'Enviar mensaje',exact:true}).click();
  await guest.getByRole('heading',{name:'Mensaje enviado',exact:true}).waitFor();
  await admin.reload();await admin.waitForFunction(()=>DB?.mensajes?.length>0);
  assert.ok(await admin.evaluate(()=>DB.mensajes.some(m=>m.correo==='prueba-php@example.test')));
  console.log('Formulario de contacto: recibido por el administrador');
  assert.equal(await admin.evaluate(()=>{DB.mensajes=DB.mensajes.filter(m=>m.correo!=='prueba-php@example.test');return saveDB()}),true);
  assert.equal((await admin.goto(base+'/accounts.php')).status(),200);await admin.getByRole('heading',{name:'Cuentas del portal',exact:true}).waitFor();
  await guest.goto(base+'/');await guest.setViewportSize({width:1280,height:800});
  if(process.env.PISCO_SCREENSHOT)await guest.screenshot({path:process.env.PISCO_SCREENSHOT,fullPage:false});
  assert.deepEqual(errors,[]);console.log('Navegador: sin errores de JavaScript');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
