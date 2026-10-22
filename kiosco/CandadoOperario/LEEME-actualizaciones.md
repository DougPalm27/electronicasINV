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
