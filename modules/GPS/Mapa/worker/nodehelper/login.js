const puppeteer = require('puppeteer-core');
const os = require('os');
const path = require('path');
const fs = require('fs');
// Ruta de Chrome: configurable por env (CHROME_PATH) para el despliegue en el servidor.
const BROWSER_CANDIDATES = [
  process.env.CHROME_PATH,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].filter(Boolean);
const CHROME = BROWSER_CANDIDATES.find(p => fs.existsSync(p));
// Carpeta de perfil de Chrome: imprescindible cuando lo lanza IIS/servicio (Sesion 0),
// que no tiene perfil de usuario. Se usa una subcarpeta por ejecucion para evitar
// bloqueos cuando varias cuentas TrackSolid refrescan al mismo tiempo.
const USER_DATA_ROOT = process.env.CHROME_USER_DATA || path.join(os.tmpdir(), 'ts-chrome-profile');
const USER_DATA = path.join(USER_DATA_ROOT, `run-${process.pid}-${Date.now()}`);
try { fs.mkdirSync(USER_DATA, { recursive: true }); } catch (e) {}
const USER = process.env.TS_USER, PWD = process.env.TS_PWD;
const sleep = ms => new Promise(r=>setTimeout(r,ms));

if (!USER || !PWD) {
  console.error('ERR faltan TS_USER/TS_PWD. En PowerShell usa: $env:TS_USER="usuario"; $env:TS_PWD="clave"; node login.js');
  process.exit(1);
}

async function visibleInputs(page) {
  const inputs = await page.$$('input');
  const out = [];
  for (const input of inputs) {
    const meta = await input.evaluate(el => {
      const r = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return {
        type: (el.getAttribute('type') || 'text').toLowerCase(),
        placeholder: el.getAttribute('placeholder') || '',
        name: el.getAttribute('name') || '',
        id: el.getAttribute('id') || '',
        autocomplete: el.getAttribute('autocomplete') || '',
        disabled: !!el.disabled,
        readonly: !!el.readOnly,
        visible: r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none',
      };
    });
    if (meta.visible && !meta.disabled && !meta.readonly && meta.type !== 'hidden') {
      out.push({ input, meta });
    }
  }
  return out;
}

function scoreAccountInput(meta) {
  if (meta.type === 'password') return -100;
  const hay = `${meta.placeholder} ${meta.name} ${meta.id} ${meta.autocomplete}`.toLowerCase();
  let score = 0;
  if (/(account|username|user|login|email|mail|cuenta|usuario)/.test(hay)) score += 20;
  if (/(account|cuenta)/.test(hay)) score += 10;
  if (['text', 'email', 'tel', 'search'].includes(meta.type)) score += 5;
  return score;
}

async function typeInto(handle, value) {
  await handle.click({ clickCount: 3 });
  await handle.press('Backspace').catch(()=>{});
  await handle.type(value, { delay: 25 });
}

async function fillLogin(page) {
  await page.waitForFunction(() => {
    return !!localStorage.getItem('token') ||
      Array.from(document.querySelectorAll('input')).some(el => {
        const r = el.getBoundingClientRect();
        const s = window.getComputedStyle(el);
        return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden';
      });
  }, { timeout: 30000 });

  const inputs = await visibleInputs(page);
  const pwd = inputs.find(x => x.meta.type === 'password');
  const account = inputs
    .filter(x => x.input !== (pwd && pwd.input))
    .sort((a, b) => scoreAccountInput(b.meta) - scoreAccountInput(a.meta))[0];

  if (!account || !pwd) {
    const seen = inputs.map(x => `${x.meta.type}:${x.meta.placeholder || x.meta.name || x.meta.id || '(sin etiqueta)'}`).join(', ');
    throw new Error('no se detectaron campos de login visibles. Inputs: ' + (seen || 'ninguno'));
  }

  await typeInto(account.input, USER);
  await typeInto(pwd.input, PWD);

  const clicked = await page.evaluate(() => {
    const candidates = Array.from(document.querySelectorAll('button, .login-button, [role="button"], input[type="button"], input[type="submit"]'));
    const visible = el => {
      const r = el.getBoundingClientRect();
      const s = window.getComputedStyle(el);
      return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden' && !el.disabled;
    };
    const score = el => {
      const text = `${el.innerText || ''} ${el.value || ''} ${el.className || ''} ${el.id || ''}`.toLowerCase();
      if (/(login|log in|sign in|entrar|ingresar|iniciar|acceder)/.test(text)) return 20;
      if (el.type === 'submit') return 10;
      return 0;
    };
    const btn = candidates.filter(visible).sort((a, b) => score(b) - score(a))[0];
    if (!btn) return false;
    btn.click();
    return true;
  });
  if (!clicked) await page.keyboard.press('Enter');
}

(async () => {
  if (!CHROME) {
    throw new Error('no se encontro Chrome/Edge. Configura CHROME_PATH con la ruta real del navegador.');
  }
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    pipe: true,                    // evita esperar el WS endpoint por stdout
    timeout: 90000,               // Sesion 0 arranca mas lento
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-gpu',                       // sin GPU en servidor
      '--disable-dev-shm-usage',
      '--disable-extensions',
      '--disable-software-rasterizer',
      '--no-first-run',
      '--no-default-browser-check',
      '--disable-blink-features=AutomationControlled',
      '--user-data-dir=' + USER_DATA,        // clave para IIS/servicio
    ],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({width:1280,height:800});
    let qBody=null, gBody=null;
    page.on('request', req => {
      if (req.method()!=='POST') return;
      const u=req.url();
      if (u.indexOf('queryEquipmentList')>-1 && !qBody) qBody=req.postData();
      if (u.indexOf('getUserGroup')>-1 && !gBody)      gBody=req.postData();
    });
    await page.goto('https://us.tracksolidpro.com/resource/dev/index.html#/login', { waitUntil:'networkidle2', timeout:60000 });
    await page.evaluate(() => {
      localStorage.removeItem('token');
      sessionStorage.clear();
    }).catch(()=>{});
    await page.goto('https://us.tracksolidpro.com/resource/dev/index.html#/login', { waitUntil:'networkidle2', timeout:60000 });
    await fillLogin(page);
    try {
      await page.waitForFunction(() => !!localStorage.getItem('token'), { timeout:25000 });
    } catch (e) {
      throw new Error('no se obtuvo token despues de enviar el login; revisa usuario/clave o si TrackSolid pidio captcha/2FA');
    }
    await sleep(1500);
    await page.reload({ waitUntil:'networkidle2', timeout:30000 }); // arranca el monitor logueado
    for (let i=0;i<30 && !qBody;i++) await sleep(500);
    const token = await page.evaluate(()=>localStorage.getItem('token'));
    let accountId=null; try{ accountId=JSON.parse(Buffer.from(token.split('.')[1],'base64').toString()).accountId; }catch(e){}
    console.log(JSON.stringify({ token, accountId, queryBody:qBody, groupBody:gBody }));
  } finally {
    await browser.close();
    try { fs.rmSync(USER_DATA, { recursive: true, force: true }); } catch (e) {}
  }
})().catch(e=>{
  try { fs.rmSync(USER_DATA, { recursive: true, force: true }); } catch (_) {}
  console.error('ERR '+e.message);
  console.error('BROWSER '+(CHROME || '(no encontrado)'));
  console.error('USER_DATA '+USER_DATA);
  process.exit(1);
});
