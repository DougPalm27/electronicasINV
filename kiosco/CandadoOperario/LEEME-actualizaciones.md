# Actualizaciones automáticas del candado

Las PCs de planta se actualizan solas. Esto explica cómo publicar una versión y qué cuidar.

## Cómo funciona

1. **Cada 30 minutos** una tarea de Windows (`Candado de Operario Actualizador`, como SYSTEM) corre
   `CandadoOperario.exe --actualizar`.
2. Pregunta al servidor la última versión **firmada**. Si es mayor que la instalada, comprueba la **firma**
   con la llave pública que lleva el programa; si no es válida, la ignora.
3. Solo continúa si el candado está **bloqueado** (nunca con un turno abierto).
4. Descarga el instalador a una carpeta que solo SYSTEM puede escribir, comprueba su SHA-256 y arranca la tarea
   `Candado de Operario Instalar`, que lo instala en silencio y conserva la configuración de esa PC.
5. Al terminar, la tarea vigilante vuelve a abrir el candado en la sesión del operario.

Registro de lo que hizo: `C:\Windows\System32\config\systemprofile\AppData\Local\Honducafe\upd\actualizador.log`
(requiere administrador para leerlo).

## Publicar una versión nueva

1. Sube `AppVer` en `CandadoOperario.iss` y `AssemblyVersion`/`AssemblyFileVersion` en `Program.cs` (misma versión).
2. `compilar.bat` y luego `compilar-instalador.bat`: generan el instalador, lo **firman** y lo copian a `descargas/`
   junto con su `.firma`.
3. Commit y push de `descargas/` (el `.exe` **y** el `.firma`), y `git pull` en el servidor.

Un instalador sin `.firma` se puede descargar a mano desde `candado.php`, pero **no** se ofrece como actualización.

## La llave de firma (lo más importante)

- **Privada:** `%USERPROFILE%\.honducafe\candado-firma-privada.xml`. **Quien la tenga puede instalar cualquier programa,
  con permisos de administrador, en todas las PCs de planta.** No la subas a Git ni la mandes por correo.
- **Respáldala** (por ejemplo en una memoria USB guardada aparte). Si se pierde, las PCs ya instaladas no podrán
  recibir más actualizaciones y habrá que reinstalarlas a mano. No se puede generar otra igual.
- **Pública:** `firma-publica.xml` (va al repositorio) y está incluida dentro de `Program.cs`. Cambiar de llaves
  exige reinstalar a mano todas las PCs, porque cada una lleva la pública de la llave anterior.
- Crear las llaves la primera vez: `crear-llaves-firma.ps1` (no sobrescribe una llave existente).

## Probar sin instalar

`CandadoOperario.exe --actualizar --simular` hace todo (consulta, verifica la firma, descarga y comprueba la huella)
**menos instalar**, y deja su registro en `%TEMP%\CandadoActualizadorPrueba\`.

## Límites conocidos

- La primera versión con actualizador (1.3.0) hay que instalarla a mano en cada PC.
- Si una PC no tiene las tareas (instalación hecha con una versión anterior o falló el permiso), no se actualiza sola:
  se ve en el Programador de tareas de Windows, carpeta `Honducafe`.
- Si el instalador de una versión nueva cambia cómo se crean las tareas, esas tareas se actualizan solo en una
  instalación manual (la silenciosa no reescribe la tarea que la está ejecutando).

## Código de emergencia (seguro si el candado no deja entrar)

- Se define en el `.env` del servidor: `KIOSCO_EMERGENCIA=` con **6 dígitos**, igual para toda la planta. Vacío = desactivado.
- Cada PC recibe solo su huella en la siguiente sincronización y la guarda; después funciona **sin red**.
- Se teclea en el candado como si fuera un PIN. Abre la PC con el nombre «EMERGENCIA»: no crea turno de operario
  ni sube eventos de Delvis, pero el bloqueo por inactividad sigue funcionando.
- Cada uso queda en `logs/emergencias.log` del servidor (estación y hora); si no hay red, se avisa al reconectar.
- Límite: una PC que nunca haya sincronizado no conoce el código. Quitar o cambiar el código en `.env` se aplica a cada PC al sincronizar.

## Un instalador, dos programas (desde 1.4.0)

El mismo `Setup-CandadoOperario-X.exe` pregunta qué instalar:
- **Candado de operario** (por defecto): PCs de las máquinas. Las actualizaciones automáticas lo siguen instalando igual.
- **Cuarto de control**: PC del supervisor. Lista de máquinas por planta, si responden en la red y su pantalla por VNC.
  Pide la carpeta Planos (la del visor 3D). Silencioso: `/VERYSILENT /TYPE=control /PLANOS=C:\xampp\htdocs\Planos`.

`compilar-instalador.bat` compila también `..\CuartoControl\CuartoControl.exe`.

## Aviso de falla en rojo (desde 1.4.1, en prueba)

- Con una falla activa de Delvis, la pantalla de bloqueo parpadea en rojo con una franja que dice el código
  (grupo:codigo) y desde qué hora; durante el turno parpadea el botón flotante.
- Falla activa = `FaultSet` en `Faults.log` **desde que arrancó Delvis** (hora del proceso `Delvis.exe`) y sin
  `FaultClear`. Delvis deja fallas abiertas al cerrarse, por eso no se cuentan las anteriores a su arranque (*supuesto
  sacado de un Faults.log real*). Sin Delvis abierto no hay aviso. Solo lee el archivo, nunca escribe.
- `candado.config`: `AvisoFallas=false` lo apaga; `FallasIgnorar=19:35,24:64` calla esos códigos. La pantalla de bloqueo muestra en una línea los tipos (grupo) ignorados y marca «(activa)» si alguno tiene una falla abierta.
- Probar sin falla real: `CandadoOperario.exe --demo-falla` (15 s; dice qué fallas reales ve en ese momento).

## Nivel de Delvis automático (desde 1.4.1, en prueba)

- Delvis no tiene usuarios: tiene una contraseña por nivel (1, 2, 3) y la pide en su ventana de teclado
  («Por favor introduce el nivel de N contraseña:») al entrar a una pantalla protegida.
- En el sistema, Usuarios → Código de bloqueo → «Nivel en Delvis». Requiere `BD/migration_nivel_delvis.sql`
  (sin ella todo sigue igual y el nivel va en 0). Llega a las PCs con la sincronización.
- Con ese operario en turno, cuando Delvis pide un nivel **igual o menor** al suyo, el candado escribe la contraseña
  en esa ventana y pulsa Enter (mensajes de Windows a la caja de texto; no toca archivos de Delvis). Si pide uno mayor,
  no hace nada. Con el código de emergencia, nunca.
- Contraseñas: en cada PC, como administrador, `CandadoOperario.exe --claves-delvis`. Se guardan cifradas (DPAPI del
  equipo) en `{app}\delvis.claves`. Solo acepta las que coinciden con la huella MD5 que Delvis tiene en su
  `Config.xml`, y no escribe una guardada si Delvis la cambió después.
- Bitácora (sin contraseñas): `C:\ProgramData\CandadoOperario\nivel_delvis.log`.
- Diagnóstico: `CandadoOperario.exe --ver-delvis 15` espera 15 s (abre en ese tiempo la ventana de contraseña de
  Delvis) y abre un .txt con lo que ve, si coinciden las contraseñas y si Windows deja escribirle (si Delvis corre
  como administrador y el candado no, Windows lo bloquea).
- Al bloquear, el candado no baja el nivel de Delvis: eso lo hace el propio Delvis por inactividad.
- `NivelDelvis=false` en `candado.config` lo apaga.
