const puppeteer = require('puppeteer-core');
const os = require('os');
const path = require('path');
const fs = require('fs');
// Ruta de Chrome: configurable por env (CHROME_PATH) para el despliegue en el servidor.
const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
// Carpeta de perfil de Chrome: imprescindible cuando lo lanza IIS/servicio (Sesion 0),
// que no tiene perfil de usuario. Configurable con CHROME_USER_DATA.
const USER_DATA = process.env.CHROME_USER_DATA || path.join(os.tmpdir(), 'ts-chrome-profile');
try { fs.mkdirSync(USER_DATA, { recursive: true }); } catch (e) {}
const USER = process.env.TS_USER, PWD = process.env.TS_PWD;
const sleep = ms => new Promise(r=>setTimeout(r,ms));
(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
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
    await page.goto('https://us.tracksolidpro.com/', { waitUntil:'networkidle2', timeout:45000 });
    await page.waitForSelector('input[placeholder="Account"]', { timeout:20000 });
    await page.type('input[placeholder="Account"]', USER, {delay:25});
    await page.type('input[type="password"]', PWD, {delay:25});
    await page.click('.login-button');
    await page.waitForFunction(() => !!localStorage.getItem('token'), { timeout:25000 });
    await sleep(1500);
    await page.reload({ waitUntil:'networkidle2', timeout:30000 }); // arranca el monitor logueado
    for (let i=0;i<30 && !qBody;i++) await sleep(500);
    const token = await page.evaluate(()=>localStorage.getItem('token'));
    let accountId=null; try{ accountId=JSON.parse(Buffer.from(token.split('.')[1],'base64').toString()).accountId; }catch(e){}
    console.log(JSON.stringify({ token, accountId, queryBody:qBody, groupBody:gBody }));
  } finally { await browser.close(); }
})().catch(e=>{ console.error('ERR '+e.message); process.exit(1); });
