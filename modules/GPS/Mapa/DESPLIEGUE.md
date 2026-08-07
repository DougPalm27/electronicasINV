# Despliegue del Mapa GPS en producción (EC2 Windows + RDS SQL Server)

Guía para dejar el módulo **GPS → Mapa GPS** corriendo en el servidor de producción.
Cubre ~98% de la flota (212/216 vehículos) con 10 plataformas integradas.

---

## 0. Resumen de piezas
- **Código PHP** (el módulo + 5 archivos del core) → al webroot del EC2.
- **Base de datos** (RDS) → migraciones **ya aplicadas** (ver paso 2).
- **Node + Chrome + nodehelper** → solo para **TrackSolid** (login headless).
- **Worker de Optimus** → proceso permanente (Optimus manda posiciones por WebSocket).

---

## 1. Desplegar el código
Llevar al webroot de producción (mismo layout que local):

**Nuevos:**
- Carpeta completa `modules/GPS/Mapa/` (adapters, controllers, models, views, js, worker).
- `BD/BaseGPS/migration_mapa_gps.sql`, `migration_despachos.sql`, `migration_tracksolid_tokens.sql`.

**Modificados (reemplazar):**
- `content.php`
- `selector.php`
- `src/includes/navContext.php`
- `src/includes/scripts.php`
- `src/includes/sidenav.php`

> Si usas git: `git pull` en el EC2. Si es copia manual, sube esos archivos/carpetas.
> **No subas** `modules/GPS/Mapa/worker/nodehelper/node_modules/` (se instala en el paso 3).

---

## 2. Base de datos (RDS)

**Primero averigua qué falta.** Corre en SSMS contra la base de producción:

```
BD/BaseGPS/verificar_estado.sql
```

Es de solo lectura y lista cada tabla/columna con `OK` o `>>> FALTA`, indicando qué
migración la crea. Corre únicamente las que aparezcan como faltantes.

Orden recomendado si montas una base limpia (todas son idempotentes):

| # | Migración | Para qué |
|---|-----------|----------|
| 1 | `migration_mapa_gps.sql` | Plataformas, posiciones |
| 2 | `migration_despachos.sql` | Despachos y sus vehículos |
| 3 | `migration_recorridos_despacho.sql` | Puntos de recorrido |
| 4 | `migration_tramos_despacho.sql` | Tramos (iniciar/finalizar ruta) |
| 5 | `migration_incidencias_despacho.sql` | Incidencias manuales |
| 6 | `migration_worker_heartbeats.sql` | Latidos de los workers |
| 7 | `migration_tracksolid_tokens.sql` | Caché de tokens TrackSolid |
| 8 | `migration_motivo_remocion.sql` | Descartar vehículo agregado por error |
| 9 | `migration_alertas_despacho.sql` | Alertas guardadas (detenido / sin reportar) |
| 10 | `migration_tokens_backoff.sql` | Espera creciente entre logins fallidos |
| 11 | `migration_tokens_bloqueo.sql` | Bloqueo de cuentas con clave inválida |

Verificación rápida (debe listar 10 plataformas con motor):
```sql
SELECT nombre, tipo_integracion FROM gps.Plataformas WHERE tipo_integracion IS NOT NULL;
```

---

## 3. Node + Chrome + helper de TrackSolid
TrackSolid entra por login web headless (Chrome invisible). En el EC2:

1. **Instalar Node.js** (v18+): https://nodejs.org — anota la ruta de `node.exe`.
2. **Instalar Google Chrome** en el servidor — anota la ruta de `chrome.exe`
   (típico: `C:\Program Files\Google\Chrome\Application\chrome.exe`).
3. Instalar la dependencia del helper:
   ```
   cd C:\ruta\al\proyecto\modules\GPS\Mapa\worker\nodehelper
   npm install
   ```
4. En el **`.env` de producción**, agregar (ajusta rutas reales del EC2):
   ```
   NODE_BIN=C:\Program Files\nodejs\node.exe
   CHROME_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe
   ```
   > `NODE_BIN` es clave: el servidor web (Apache/PHP) ejecuta el helper por esa ruta.
   > Sin él, TrackSolid no podrá obtener token y saldrá "sin señal".

**Prueba manual** (opcional): con una cuenta TrackSolid real,
`set TS_USER=... & set TS_PWD=... & node login.js` debe imprimir un JSON con `token`.

---

## 4. Worker de Optimus (permanente)
Optimus transmite posiciones por WebSocket → necesita un oyente siempre encendido.

1. Edita `modules/GPS/Mapa/worker/start_optimus_worker.bat` y ajusta:
   - La ruta del proyecto (`cd /d ...`) si en el EC2 es distinta a `C:\xampp\htdocs\electronicasINV`.
   - La ruta de `php.exe` si no es `C:\xampp\php\php.exe`.
2. Regístralo en el **Programador de tareas** (como administrador):
   - Desencadenador: **Al iniciar el sistema** (para que arranque sin login).
   - "**Ejecutar aunque el usuario no haya iniciado sesión**" + "Ejecutar con privilegios más altos".
   - Acción: ejecutar `...\worker\start_optimus_worker.bat`.
   - En "Configuración": "Si la tarea falla, reiniciar cada 1 min".
3. El `.bat` ya trae un bucle que reinicia el worker si se cae.

> El worker solo escucha carros de Optimus que estén en **despachos activos**.
> Requiere que el EC2 tenga **salida a internet** (HTTPS 443 y el broker CloudAMQP).

---

## 4b. Worker de tokens de TrackSolid (cada 30 min) — OBLIGATORIO

Bajo IIS el login headless de TrackSolid falla o agota el tiempo de la petición web.
Este worker lo hace **fuera de IIS** y deja los tokens frescos en `gps.CuentaTokens`;
la web solo lee caché y nunca lanza Chrome.

1. Prueba manual primero:
   ```
   C:\inetpub\wwwroot\electronicasINV\modules\GPS\Mapa\worker\start_tracksolid_tokens.bat
   ```
   Debe imprimir `token RENOVADO` para varias cuentas.

2. Regístralo en el **Programador de tareas** (Task Scheduler):
   - **General**: nombre `Worker Tokens TrackSolid`, "Ejecutar aunque el usuario no
     haya iniciado sesión" + "Ejecutar con privilegios más altos".
   - **Desencadenador**: una vez, y en avanzado **repetir cada 30 minutos**,
     duración **indefinidamente**.
   - **Acción**: iniciar `...\worker\start_tracksolid_tokens.bat`.
   - **Configuración**: detener si dura más de **1 hora**;
     "Si la tarea ya se está ejecutando" → **No iniciar una nueva instancia**.

> Si con la cuenta SYSTEM fallan todos los logins, cambia la tarea a un usuario
> administrador con contraseña guardada: Chrome headless a veces no arranca en Sesión 0.

### Cuentas con contraseña vencida
El worker distingue el motivo del fallo:

- **Credenciales inválidas** → tras 4 intentos la cuenta se **bloquea** y deja de
  abrir Chrome. Al corregir la clave en **GPS → Cuentas GPS** el bloqueo se
  levanta solo (se borra el token y el historial de fallos).
- **Fallo técnico** (Chrome, red, timeout) → nunca bloquea; reintenta con espera
  creciente hasta 12 h.

Para ver cuáles necesitan clave nueva:
```sql
SELECT usuario, intentos_fallidos, ultimo_error, fecha_error
FROM gps.CuentaTokens
WHERE tipo_error = 'credenciales' AND intentos_fallidos >= 4;
```

### Perfiles temporales de Chrome
Cada login crea un perfil en `%TEMP%\ts-chrome-profile\run-*` y lo borra al terminar.
Si un proceso muere de golpe, el barrido automático del siguiente arranque limpia
todo lo de más de 1 hora. Para vaciar a mano (con la tarea detenida):

```powershell
Remove-Item -Recurse -Force "C:\Windows\Temp\ts-chrome-profile","$env:TEMP\ts-chrome-profile" -ErrorAction SilentlyContinue
```

---

## 5. Verificación
1. Entra a producción → **GPS → Mapa GPS** (con un usuario que tenga el permiso `gpsMapa`).
2. **Preparar despacho** → **Agregar vehículos** → prueba una cuenta de cada motor:
   - NavSat / rastreohn (gps-server), TrackSolid, Optimus, Detektor, skytek, GPSWOX, globaltechn.
3. Refresca el mapa:
   - Casi todos → en vivo al momento.
   - TrackSolid → primera lectura ~10s (login), luego cacheado (rápido) por 90 min.
   - Optimus → en vivo si el worker está corriendo.

---

## 6. Pendientes / mejoras (opcionales, no bloquean)
- **Refrescador de tokens de TrackSolid**: un proceso que renueva los tokens en segundo
  plano para que el mapa nunca espere el login de 10s. Recomendado si usas muchas cuentas
  TrackSolid a la vez.
- **4 plataformas sin integrar** (~4 vehículos): GOLAN (ASP.NET+captcha), cymsagt, sherutgps.
  Dejarlas como "acceso rápido" o integrarlas después.
- **Contraseñas desactualizadas** en Credenciales GPS (algunas cuentas Detektor y globaltechn):
  corregirlas para que esos vehículos conecten.

---

## 7. Notas de seguridad
- Las credenciales de las cuentas GPS viven en `gps.CuentasGPS` (texto plano, por diseño del
  sistema previo). El token de TrackSolid se cachea en `gps.CuentaTokens`.
- El broker de Optimus usa credenciales públicas del frontend de esa plataforma; si Optimus
  las rota, se actualizan con `OPTIMUS_MQTT_*` en el `.env`.
