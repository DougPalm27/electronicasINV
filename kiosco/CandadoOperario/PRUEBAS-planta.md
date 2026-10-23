# Guía de pruebas en planta (sin internet)

Anota en cada paso: ✅ funcionó / ❌ falló + qué mensaje salió (foto con el celular).
Al volver, me pasas las notas y las fotos.

## A. Antes de ir (con internet)

1. Servidor: `git pull origin main`.
2. Servidor, `.env`: agregar `KIOSCO_EMERGENCIA=XXXXXX` (6 dígitos; anótalo en un lugar seguro).
3. En una USB:
   - `Setup-CandadoOperario-1.3.2.exe` (de `descargas/` o de `candado.php`).
   - Instalador de **TightVNC** (servidor + visor) o **UltraVNC**, bajado de su página oficial.
4. Anota la IP o el nombre del servidor del sistema, y el token del candado.

## B. Candado 1.3.2 en una PC de planta

1. Ejecutar el instalador como administrador.
2. Pantalla de conexión: servidor + token. ¿Dice que no se pudo verificar? → anota (sin red al servidor).
3. Pantalla de máquina: ¿aparece la lista con la **serie**? ¿Es la máquina correcta?
4. Terminar e iniciar sesión de Windows. ¿Aparece el candado bloqueado?
5. ¿Se ven la **barra de tareas** o el **buscador** mientras está bloqueado? (debería ser NO).
6. Entrar con el PIN de un operario. ¿Abre? ¿Aparece su nombre arriba?
7. Bloquear de nuevo.
8. **Código de emergencia** (solo si la PC ya sincronizó con el servidor): teclearlo como PIN.
   ¿Abre como «EMERGENCIA»?
9. Repetir 8 con el cable de red / wifi desconectado. ¿Abre igual?
10. Probar instalar en una **segunda PC**: la máquina del paso 3 NO debe aparecer en la lista.

## C. VNC

1. En la PC de planta: instalar el **servidor** VNC, poner contraseña.
   Anota el nombre de la PC (Inicio > Configuración > Sistema > Acerca de) y su IP (`ipconfig` en cmd).
2. En tu PC: instalar el **visor** VNC.
3. Desde tu PC, abrir el visor y escribir la IP de la PC de planta (puerto 5900).
   ¿Conecta? ¿Ves la pantalla del operario? ¿Puedes mover el mouse?
4. Probar lo mismo con el **nombre** de la PC en lugar de la IP.
5. **Visor 3D del plano** (en tu PC, sin internet): en `Planos/salida/remoto.json` pon en la máquina
   `"protocolo": "vnc"`, `"host": "IP o nombre de la PC"`, `"puerto": ""` (vacío = 5900).
   Abre el visor > botón de la máquina. ¿Baja el archivo .vnc? ¿Abre el visor VNC y conecta?
6. Probar desde wifi y desde cable, si puedes.

## D. Si algo falla, toma foto de

- El mensaje de error completo.
- `ipconfig` de las dos PCs.
- Carpeta `C:\ProgramData\CandadoOperario\` (qué archivos hay).
- Programador de tareas > carpeta `Honducafe` (qué tareas aparecen).

## Datos para la emergencia (sin mí)

- **El candado no deja entrar a nadie:** usa el código de emergencia.
- **Si el código tampoco funciona:** Ctrl+Alt+Supr > Administrador de tareas > Archivo > Ejecutar nueva tarea >
  escribe `appwiz.cpl` > desinstala «Candado de Operario» (pide contraseña de administrador de la PC).
  Esto no se puede bloquear. Pruébalo una vez en la PC de pruebas para saber que te funciona.
