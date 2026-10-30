using System;
using System.Collections;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Globalization;
using System.IO;
using System.Net;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;
using System.Xml.Linq;

[assembly: AssemblyTitle("Candado de Operario")]
[assembly: AssemblyDescription("Control de acceso por operario para las PCs de las máquinas Satake")]
[assembly: AssemblyProduct("Candado de Operario")]
[assembly: AssemblyCompany("Honducafe")]
[assembly: AssemblyCopyright("Desarrollado por Douglas Palma · © 2026")]
[assembly: AssemblyVersion("1.4.2.0")]
[assembly: AssemblyFileVersion("1.4.2.0")]

namespace CandadoOperario
{
    // ───────────────────────── Configuración ─────────────────────────
    static class Cfg
    {
        public static string Servidor = "localhost";
        public static string Estacion = "";
        public static string Token = "";
        public static string DelvisDir = @"C:\Satake\Delvis\Gui";
        public static int AutoMin = 15;
        public static bool PermitirSalir = false;
        public static bool AvisoFallas = true;    // la pantalla parpadea en rojo con una falla activa de Delvis
        public static HashSet<string> FallasIgnorar = new HashSet<string>();   // "grupo:codigo" o "grupo:codigo:contexto"
        public static bool NivelDelvis = true;    // escribe la contraseña de nivel en Delvis según el operario (ver class NivelDelvis)
        public static string Url;
        public static string Base;   // direccion del sitio, sin la ruta del endpoint (de aqui se baja el instalador)

        public static void Load()
        {
            string p = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "candado.config");
            if (File.Exists(p))
            {
                foreach (string raw in File.ReadAllLines(p))
                {
                    string l = raw.Trim();
                    if (l.Length == 0 || l.StartsWith("#")) continue;
                    int i = l.IndexOf('=');
                    if (i < 1) continue;
                    string k = l.Substring(0, i).Trim().ToLowerInvariant();
                    string v = l.Substring(i + 1).Trim();
                    switch (k)
                    {
                        case "servidor": Servidor = v; break;
                        case "estacion": Estacion = v; break;
                        case "token": Token = v; break;
                        case "delvisdir": DelvisDir = v; break;
                        case "autobloqueominutos": int.TryParse(v, out AutoMin); break;
                        case "permitirsalir": PermitirSalir = v.ToLowerInvariant() == "true" || v == "1"; break;
                        case "avisofallas": AvisoFallas = !(v.ToLowerInvariant() == "false" || v == "0"); break;
                        case "niveldelvis": NivelDelvis = !(v.ToLowerInvariant() == "false" || v == "0"); break;
                        case "fallasignorar":
                            foreach (string f in v.Split(new char[] { ',', ';', ' ' }, StringSplitOptions.RemoveEmptyEntries)) FallasIgnorar.Add(f.Trim());
                            break;
                    }
                }
            }
            if (AutoMin < 1) AutoMin = 15;
            SetServidor(Servidor);
        }

        public static void SetServidor(string s)
        {
            Servidor = s;
            Base = UrlBase(s);
            Url = Base + "/modules/Turnos/controllers/kioscoController.php";
        }

        // El servidor puede ser una IP de LAN sin TLS ("192.168.1.10") o un dominio real
        // ("electronicas.simfcoh.com" o "https://electronicas.simfcoh.com"). Si no trae
        // esquema se asume http:// (uso en LAN); para usar https hay que escribirlo explícito.
        static string UrlBase(string s)
        {
            string b = (s ?? "").Trim();
            if (!b.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
                !b.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
            {
                b = "http://" + b;
            }
            return b.TrimEnd('/');
        }
    }

    // ───────────────────────── Datos locales ─────────────────────────
    class Operario
    {
        public int id_usuario;
        public string nombre;
        public string salt;
        public string hash;
        public int nivel;   // nivel de Delvis (0 = ninguno)
    }

    class Turno
    {
        public string uid;
        public int id_usuario;
        public string nombre;
        public string estacion;
        public string inicio;
        public string fin;
        public bool sent;
    }

    class Evento
    {
        public string uid;
        public string turno_uid;
        public string fecha;
        public string tipo;
        public string codigo;
        public string detalle;
        public string anterior;
        public string nuevo;
    }

    class CacheData
    {
        public List<Operario> usuarios = new List<Operario>();
        public string sync;
        public string emerg_salt;   // huella del código de emergencia de la planta (viene en la sincronización)
        public string emerg_hash;
        public string delvis;       // contraseñas de nivel de Delvis del sistema, cifradas con llaves del token (ver NivelDelvis.DelSistema)
    }

    static class Store
    {
        public static readonly object Lock = new object();
        public static string Dir;
        public static CacheData Cache = new CacheData();
        public static List<Turno> Cola = new List<Turno>();
        public static List<Evento> Eventos = new List<Evento>();
        public static List<string> Emergencias = new List<string>();   // usos del código de emergencia aún sin avisar al servidor
        static JavaScriptSerializer js = new JavaScriptSerializer();

        public static string DirDatos
        {
            get { return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "CandadoOperario"); }
        }

        public static void Init()
        {
            Dir = DirDatos;
            Directory.CreateDirectory(Dir);
            Cache = Read<CacheData>("cache.json") ?? new CacheData();
            Cola = Read<List<Turno>>("cola.json") ?? new List<Turno>();
            Eventos = Read<List<Evento>>("eventos.json") ?? new List<Evento>();
            Emergencias = Read<List<string>>("emergencias.json") ?? new List<string>();
        }

        static T Read<T>(string f) where T : class
        {
            try
            {
                string p = Path.Combine(Dir, f);
                if (!File.Exists(p)) return null;
                return js.Deserialize<T>(File.ReadAllText(p, Encoding.UTF8));
            }
            catch (Exception) { return null; }
        }

        static void Write(string f, object o)
        {
            try
            {
                string p = Path.Combine(Dir, f);
                string t = p + ".tmp";
                File.WriteAllText(t, js.Serialize(o), Encoding.UTF8);
                if (File.Exists(p)) File.Replace(t, p, null); else File.Move(t, p);
            }
            catch (Exception) { }
        }

        // Lo lee el actualizador: solo instala una version nueva si el candado esta bloqueado
        // (nunca con un turno abierto). Se refresca cada 10 s; si deja de refrescarse no se considera valido.
        public static void Estado(string estado)
        {
            try { File.WriteAllText(Path.Combine(Dir, "estado.txt"), estado + "|" + DateTime.UtcNow.Ticks); }
            catch (Exception) { }
        }

        public static void SaveCache() { lock (Lock) { Write("cache.json", Cache); } }
        public static void SaveCola() { lock (Lock) { Write("cola.json", Cola); } }
        public static void SaveEventos() { lock (Lock) { Write("eventos.json", Eventos); } }
        public static void SaveEmergencias() { lock (Lock) { Write("emergencias.json", Emergencias); } }
    }

    // ───────────────────────── Servidor ─────────────────────────
    class Res
    {
        public bool Ok;
        public bool Offline;
        public string Msg = "";
        public Dictionary<string, object> Data;
    }

    class TimeoutClient : WebClient
    {
        protected override WebRequest GetWebRequest(Uri address)
        {
            WebRequest r = base.GetWebRequest(address);
            r.Timeout = 5000;
            return r;
        }
    }

    static class Api
    {
        public static Res Post(Dictionary<string, string> fields)
        {
            Res res = new Res();
            try
            {
                var data = new System.Collections.Specialized.NameValueCollection();
                foreach (KeyValuePair<string, string> kv in fields) data[kv.Key] = kv.Value;
                data["estacion"] = Cfg.Estacion;
                data["token"] = Cfg.Token;
                string json;
                using (var wc = new TimeoutClient())
                {
                    json = Encoding.UTF8.GetString(wc.UploadValues(Cfg.Url, "POST", data));
                }
                int i = json.IndexOf("{\"ok\"");
                if (i > 0) json = json.Substring(i);
                var d = new JavaScriptSerializer().Deserialize<Dictionary<string, object>>(json);
                res.Ok = d.ContainsKey("ok") && d["ok"] is bool && (bool)d["ok"];
                res.Msg = d.ContainsKey("mensaje") && d["mensaje"] != null ? d["mensaje"].ToString() : "";
                res.Data = d.ContainsKey("data") ? d["data"] as Dictionary<string, object> : null;
            }
            catch (Exception)
            {
                res.Ok = false;
                res.Offline = true;
                res.Msg = "Sin conexión con el servidor.";
            }
            return res;
        }
    }

    static class Sincro
    {
        public static string Now()
        {
            return DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss", System.Globalization.CultureInfo.InvariantCulture);
        }

        static string Sha(string s)
        {
            using (SHA256 h = SHA256.Create())
            {
                byte[] b = h.ComputeHash(Encoding.UTF8.GetBytes(s));
                StringBuilder sb = new StringBuilder();
                foreach (byte x in b) sb.Append(x.ToString("x2"));
                return sb.ToString();
            }
        }

        public static Operario Buscar(string pin)
        {
            lock (Store.Lock)
            {
                foreach (Operario o in Store.Cache.usuarios)
                    if (Sha(o.salt + pin) == o.hash) return o;
            }
            return null;
        }

        // Código de emergencia fijo, para cuando la PC nunca sincronizó (sin lista ni código del servidor).
        // Solo su huella PBKDF2 (lenta a propósito): el programa y el repo se pueden leer.
        static readonly byte[] EmergenciaFijaSal = Encoding.UTF8.GetBytes("Honducafe.Candado.EmergenciaFija");
        const string EmergenciaFijaHuella = "9dc52860ef975d6f4d3e10b4a8128826ccea2940";

        public static bool EsEmergenciaFija(string pin)
        {
            if (pin == null || pin.Length != 6) return false;
            using (Rfc2898DeriveBytes k = new Rfc2898DeriveBytes(Encoding.UTF8.GetBytes(pin), EmergenciaFijaSal, 200000))
            {
                StringBuilder sb = new StringBuilder();
                foreach (byte x in k.GetBytes(20)) sb.Append(x.ToString("x2"));
                return sb.ToString() == EmergenciaFijaHuella;
            }
        }

        public static bool EsEmergencia(string pin)
        {
            lock (Store.Lock)
            {
                string s = Store.Cache.emerg_salt, h = Store.Cache.emerg_hash;
                if (!string.IsNullOrEmpty(s) && !string.IsNullOrEmpty(h) && Sha(s + pin) == h) return true;
            }
            return EsEmergenciaFija(pin);
        }

        // Avisa al servidor de los usos del código de emergencia; lo que no llega se reintenta en la próxima sincronización.
        static void PushEmergencias()
        {
            List<string> fechas;
            lock (Store.Lock) { fechas = new List<string>(Store.Emergencias); }
            foreach (string f in fechas)
            {
                Res r = Api.Post(new Dictionary<string, string> { { "accion", "emergencia" }, { "fecha", f } });
                if (!r.Ok) { if (r.Offline) return; continue; }
                lock (Store.Lock) { Store.Emergencias.Remove(f); }
                Store.SaveEmergencias();
            }
        }

        public static int Pendientes()
        {
            lock (Store.Lock) { return Store.Cola.Count; }
        }

        // Baja del servidor la lista de operarios y reemplaza la copia local.
        public static bool Pull(out string msg)
        {
            Res r = Api.Post(new Dictionary<string, string> { { "accion", "sync" } });
            if (!r.Ok)
            {
                msg = r.Offline ? "Sin conexión con el servidor." : (r.Msg.Length > 0 ? r.Msg : "Error al sincronizar.");
                return false;
            }
            IEnumerable arr = (r.Data != null && r.Data.ContainsKey("usuarios")) ? r.Data["usuarios"] as IEnumerable : null;
            if (arr == null) { msg = "Respuesta inesperada del servidor."; return false; }

            List<Operario> lista = new List<Operario>();
            foreach (object o in arr)
            {
                Dictionary<string, object> d = o as Dictionary<string, object>;
                if (d == null) continue;
                Operario op = new Operario();
                op.id_usuario = Convert.ToInt32(d["id_usuario"]);
                op.nombre = Convert.ToString(d["nombre"]);
                op.salt = Convert.ToString(d["salt"]);
                op.hash = Convert.ToString(d["hash"]);
                int nv;
                op.nivel = d.ContainsKey("nivel") && int.TryParse(Convert.ToString(d["nivel"]), out nv) && nv >= 0 && nv <= 3 ? nv : 0;
                lista.Add(op);
            }
            lock (Store.Lock)
            {
                Store.Cache.usuarios = lista;
                Dictionary<string, object> em = (r.Data.ContainsKey("emergencia") ? r.Data["emergencia"] : null) as Dictionary<string, object>;
                Store.Cache.emerg_salt = em != null ? Convert.ToString(em["salt"]) : null;
                Store.Cache.emerg_hash = em != null ? Convert.ToString(em["hash"]) : null;
                Store.Cache.delvis = r.Data.ContainsKey("delvis") ? Convert.ToString(r.Data["delvis"]) : null;
                Store.Cache.sync = Now();
            }
            Store.SaveCache();
            msg = lista.Count + (lista.Count == 1 ? " operario sincronizado." : " operarios sincronizados.");
            return true;
        }

        // Sube al servidor los turnos pendientes (abiertos aún no enviados y cerrados).
        public static bool Push(out string msg)
        {
            PushEmergencias();
            List<Dictionary<string, object>> payload = new List<Dictionary<string, object>>();
            Dictionary<string, bool> conFin = new Dictionary<string, bool>();
            lock (Store.Lock)
            {
                foreach (Turno t in Store.Cola)
                {
                    if (t.fin == null && t.sent) continue;
                    Dictionary<string, object> d = new Dictionary<string, object>();
                    d["uid"] = t.uid;
                    d["id_usuario"] = t.id_usuario;
                    d["estacion"] = t.estacion;
                    d["inicio"] = t.inicio;
                    d["fin"] = t.fin;
                    payload.Add(d);
                    conFin[t.uid] = t.fin != null;
                }
            }
            List<Dictionary<string, object>> evPayload = new List<Dictionary<string, object>>();
            lock (Store.Lock)
            {
                foreach (Evento e in Store.Eventos)
                {
                    if (evPayload.Count >= 300) break;
                    Dictionary<string, object> d = new Dictionary<string, object>();
                    d["uid"] = e.uid;
                    d["turno_uid"] = e.turno_uid;
                    d["fecha"] = e.fecha;
                    d["tipo"] = e.tipo;
                    d["codigo"] = e.codigo;
                    d["detalle"] = e.detalle;
                    d["anterior"] = e.anterior;
                    d["nuevo"] = e.nuevo;
                    evPayload.Add(d);
                }
            }
            if (payload.Count == 0 && evPayload.Count == 0) { msg = "Nada pendiente."; return true; }

            Dictionary<string, string> campos = new Dictionary<string, string>();
            campos["accion"] = "subirTurnos";
            campos["turnos"] = new JavaScriptSerializer().Serialize(payload);
            if (evPayload.Count > 0) campos["eventos"] = new JavaScriptSerializer().Serialize(evPayload);
            Res r = Api.Post(campos);
            if (!r.Ok)
            {
                msg = r.Offline ? "Sin conexión con el servidor." : (r.Msg.Length > 0 ? r.Msg : "Error al subir turnos.");
                return false;
            }

            HashSet<string> hechos = new HashSet<string>();
            foreach (string k in new string[] { "procesados", "rechazados" })
            {
                IEnumerable arr = (r.Data != null && r.Data.ContainsKey(k)) ? r.Data[k] as IEnumerable : null;
                if (arr == null) continue;
                foreach (object o in arr) hechos.Add(Convert.ToString(o));
            }
            lock (Store.Lock)
            {
                for (int i = Store.Cola.Count - 1; i >= 0; i--)
                {
                    Turno t = Store.Cola[i];
                    if (!hechos.Contains(t.uid) || !conFin.ContainsKey(t.uid)) continue;
                    if (conFin[t.uid] && t.fin != null) Store.Cola.RemoveAt(i);
                    else t.sent = true;
                }
            }
            Store.SaveCola();

            HashSet<string> hechosEv = new HashSet<string>();
            foreach (string k in new string[] { "eventos_procesados", "eventos_rechazados" })
            {
                IEnumerable arr = (r.Data != null && r.Data.ContainsKey(k)) ? r.Data[k] as IEnumerable : null;
                if (arr == null) continue;
                foreach (object o in arr) hechosEv.Add(Convert.ToString(o));
            }
            if (hechosEv.Count > 0)
            {
                lock (Store.Lock) { Store.Eventos.RemoveAll(delegate(Evento x) { return hechosEv.Contains(x.uid); }); }
                Store.SaveEventos();
            }
            msg = payload.Count + " turno(s) y " + evPayload.Count + " evento(s) enviados.";
            return true;
        }
    }

    // ───────────────────────── Bitácoras de Delvis ─────────────────────────
    // Durante un turno recoge lo que Delvis mismo registra (fallas, ajustes de
    // configuración, cierres inesperados, mantenimiento) y lo guarda como
    // eventos del turno. No observa pantallas, teclas ni clics.
    static class Vigia
    {
        static Turno turno;
        static DateTime desde;
        static HashSet<string> vistas = new HashSet<string>();
        static Dictionary<string, Dictionary<string, string>> snaps = new Dictionary<string, Dictionary<string, string>>();
        static Dictionary<string, DateTime> mtimes = new Dictionary<string, DateTime>();
        static readonly string[] Xmls = { "Config.xml", "Presets.xml" };
        static readonly CultureInfo Us = new CultureInfo("en-US");

        static bool Disponible { get { return Cfg.DelvisDir.Length > 0 && Directory.Exists(Cfg.DelvisDir); } }

        public static void Iniciar(Turno t)
        {
            turno = t;
            vistas.Clear(); snaps.Clear(); mtimes.Clear();
            DateTime n = DateTime.Now;
            desde = new DateTime(n.Year, n.Month, n.Day, n.Hour, n.Minute, n.Second);
            if (!Disponible) return;
            foreach (string f in Xmls)
            {
                try
                {
                    string p = Path.Combine(Cfg.DelvisDir, f);
                    if (!File.Exists(p)) continue;
                    snaps[f] = Aplanar(p);
                    mtimes[f] = File.GetLastWriteTime(p);
                }
                catch (Exception) { }
            }
        }

        public static void Sondear()
        {
            if (turno == null || !Disponible) return;
            List<Evento> nuevos = new List<Evento>();
            try { Bitacoras(nuevos); } catch (Exception) { }
            try { Crashes(nuevos); } catch (Exception) { }
            try { Ajustes(nuevos); } catch (Exception) { }
            if (nuevos.Count == 0) return;
            lock (Store.Lock)
            {
                Store.Eventos.AddRange(nuevos);
                if (Store.Eventos.Count > 5000) Store.Eventos.RemoveRange(0, Store.Eventos.Count - 5000);
            }
            Store.SaveEventos();
        }

        public static void Detener()
        {
            Sondear();
            turno = null;
        }

        static string Cortar(string s, int n) { return (s != null && s.Length > n) ? s.Substring(0, n) : s; }

        static Evento Nuevo(DateTime f, string tipo, string codigo, string detalle, string ant, string nuevo)
        {
            Evento e = new Evento();
            e.uid = Guid.NewGuid().ToString();
            e.turno_uid = turno.uid;
            e.fecha = f.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture);
            e.tipo = tipo;
            e.codigo = Cortar(codigo, 200);
            e.detalle = Cortar(detalle, 400);
            e.anterior = Cortar(ant, 200);
            e.nuevo = Cortar(nuevo, 200);
            return e;
        }

        static string[] Lineas(string file)
        {
            string p = Path.Combine(Cfg.DelvisDir, file);
            if (!File.Exists(p)) return new string[0];
            using (FileStream fs = new FileStream(p, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete))
            using (StreamReader sr = new StreamReader(fs, Encoding.Default))
            {
                return sr.ReadToEnd().Split(new char[] { '\n' }, StringSplitOptions.RemoveEmptyEntries);
            }
        }

        // Formato de Delvis: fecha,Tipo,grupo,codigo,contexto,mensaje
        static void Bitacoras(List<Evento> salida)
        {
            foreach (string file in new string[] { "Faults.log", "Settings.log", "Maintain.log", "Application.log" })
            {
                foreach (string raw in Lineas(file))
                {
                    string line = raw.Trim();
                    string[] p = line.Split(new char[] { ',' }, 6);
                    if (p.Length < 6) continue;
                    DateTime ts;
                    if (!DateTime.TryParse(p[0], Us, DateTimeStyles.None, out ts)) continue;
                    if (ts < desde) continue;
                    if (!vistas.Add(file + "|" + line)) continue;

                    string type = p[1], grupo = p[2], code = p[3], ctx = p[4], msg = p[5];
                    string ctxTxt = (ctx != "0" && ctx != "") ? "contexto " + ctx : "";
                    switch (file)
                    {
                        case "Faults.log":
                            if (type == "FaultSet") salida.Add(Nuevo(ts, "falla", grupo + ":" + code, ctxTxt, null, null));
                            else if (type == "FaultClear") salida.Add(Nuevo(ts, "falla_ok", grupo + ":" + code, ctxTxt, null, null));
                            break;
                        case "Settings.log":
                            salida.Add(Nuevo(ts, "calibracion", type, msg, null, null));
                            break;
                        case "Maintain.log":
                            salida.Add(Nuevo(ts, "mantenimiento", type, (ctxTxt + " " + msg).Trim(), null, null));
                            break;
                        default:
                            salida.Add(Nuevo(ts, "aplicacion", null, msg, null, null));
                            break;
                    }
                }
            }
        }

        static void Crashes(List<Evento> salida)
        {
            string dir = Path.Combine(Cfg.DelvisDir, "Crashes");
            if (!Directory.Exists(dir)) return;
            foreach (string f in Directory.GetFiles(dir, "*.txt"))
            {
                if (!vistas.Add("crash|" + f)) continue;
                DateTime m = File.GetLastWriteTime(f);
                if (m < desde) continue;
                string detalle = "";
                try
                {
                    foreach (string l in File.ReadAllLines(f))
                    {
                        string t = l.Trim();
                        if (t.Length > 0 && !t.StartsWith("---")) { detalle = t; break; }
                    }
                }
                catch (Exception) { }
                salida.Add(Nuevo(m, "crash", Path.GetFileName(f), detalle, null, null));
            }
        }

        // Compara Config.xml / Presets.xml contra su estado al inicio del turno (o al último cambio).
        static void Ajustes(List<Evento> salida)
        {
            foreach (string f in Xmls)
            {
                string p = Path.Combine(Cfg.DelvisDir, f);
                if (!File.Exists(p)) continue;
                DateTime m = File.GetLastWriteTime(p);
                DateTime prev;
                if (mtimes.TryGetValue(f, out prev) && m == prev) continue;

                Dictionary<string, string> nuevo;
                try { nuevo = Aplanar(p); }
                catch (Exception) { continue; } // se está escribiendo: se reintenta en el próximo sondeo

                Dictionary<string, string> viejo;
                if (snaps.TryGetValue(f, out viejo))
                {
                    List<string> claves = new List<string>(viejo.Keys);
                    foreach (string k in nuevo.Keys) if (!viejo.ContainsKey(k)) claves.Add(k);
                    int n = 0;
                    foreach (string k in claves)
                    {
                        if (k.EndsWith("SavedFileVersion")) continue;
                        string a, b;
                        viejo.TryGetValue(k, out a);
                        nuevo.TryGetValue(k, out b);
                        if (a == b) continue;
                        if (n++ >= 100)
                        {
                            salida.Add(Nuevo(m, "ajuste", f + "/(varios)", "Se omitieron más cambios de este archivo.", null, null));
                            break;
                        }
                        salida.Add(Nuevo(m, "ajuste", k, null, a, b));
                    }
                }
                snaps[f] = nuevo;
                mtimes[f] = m;
            }
        }

        static Dictionary<string, string> Aplanar(string path)
        {
            XDocument doc;
            using (FileStream fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete))
                doc = XDocument.Load(fs);
            Dictionary<string, string> d = new Dictionary<string, string>();
            Aplanar(doc.Root, doc.Root.Name.LocalName, d);
            return d;
        }

        static void Aplanar(XElement e, string ruta, Dictionary<string, string> d)
        {
            foreach (XAttribute a in e.Attributes())
                d[a.Name.LocalName == "V" ? ruta : ruta + "@" + a.Name.LocalName] = a.Value;
            if (!e.HasElements && !e.HasAttributes && e.Value.Length > 0) d[ruta] = e.Value;
            Dictionary<string, int> cont = new Dictionary<string, int>();
            foreach (XElement c in e.Elements())
            {
                string n = c.Name.LocalName;
                int i;
                cont.TryGetValue(n, out i);
                cont[n] = i + 1;
                Aplanar(c, ruta + "/" + n + (i > 0 ? "[" + i + "]" : ""), d);
            }
        }
    }

    // ───────────────────────── Windows ─────────────────────────
    // ───────────────────────── Fallas activas de Delvis ─────────────────────────
    // Para el aviso en rojo. Faults.log no basta por sí solo: Delvis no escribe FaultClear de lo que
    // estaba activo al cerrarse, así que quedan fallas "abiertas" de hace meses. Por eso cuenta solo lo
    // que Delvis abrió desde su último arranque (hora de inicio del proceso) y no ha cerrado.
    static class Fallas
    {
        public class Activa { public string Clave; public DateTime Desde; }

        public static List<Activa> Activas = new List<Activa>();
        static HashSet<string> gruposIgnoradosActivos = new HashSet<string>();   // grupos de FallasIgnorar con una falla abierta ahora
        public static DateTime? InicioForzado;   // solo para --selftest

        static DateTime leidoMtime;
        static long leidoLargo = -1;
        static DateTime? leidoInicio;
        static readonly CultureInfo Us = new CultureInfo("en-US");

        // Hora en que arrancó Delvis; null si no está corriendo (sin Delvis no hay fallas que mostrar)
        static DateTime? InicioDelvis()
        {
            if (InicioForzado.HasValue) return InicioForzado;
            DateTime? r = null;
            foreach (Process p in Process.GetProcessesByName("Delvis"))
            {
                try { DateTime t = p.StartTime; if (!r.HasValue || t > r.Value) r = t; }
                catch (Exception) { if (!r.HasValue) r = DateTime.Now.AddHours(-12); }   // sin permiso para leerla: últimas 12 h
                finally { p.Dispose(); }
            }
            return r;
        }

        // true si cambió la lista de fallas activas
        public static bool Revisar()
        {
            string antes = Firma();
            try { Leer(); } catch (Exception) { }
            return Firma() != antes;
        }

        static string Firma()
        {
            StringBuilder sb = new StringBuilder();
            foreach (Activa a in Activas) sb.Append(a.Clave).Append('|');
            foreach (string g in gruposIgnoradosActivos) sb.Append("i").Append(g).Append('|');
            return sb.ToString();
        }

        static void Leer()
        {
            string p = Path.Combine(Cfg.DelvisDir, "Faults.log");
            DateTime? inicio = Cfg.AvisoFallas ? InicioDelvis() : null;
            if (!inicio.HasValue || !File.Exists(p))
            {
                Activas = new List<Activa>();
                gruposIgnoradosActivos = new HashSet<string>();
                leidoLargo = -1;
                return;
            }
            FileInfo fi = new FileInfo(p);
            if (fi.Length == leidoLargo && fi.LastWriteTime == leidoMtime && inicio == leidoInicio) return;

            string[] lineas;
            using (FileStream fs = new FileStream(p, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete))
            using (StreamReader sr = new StreamReader(fs, Encoding.Default))
                lineas = sr.ReadToEnd().Split(new char[] { '\n' }, StringSplitOptions.RemoveEmptyEntries);

            // Formato: fecha,FaultSet|FaultClear,grupo,codigo,contexto,mensaje
            DateTime desde = inicio.Value.AddMinutes(-1);
            Dictionary<string, DateTime> abiertas = new Dictionary<string, DateTime>();
            foreach (string raw in lineas)
            {
                string[] c = raw.Trim().Split(new char[] { ',' }, 6);
                if (c.Length < 5) continue;
                DateTime ts;
                if (!DateTime.TryParse(c[0], Us, DateTimeStyles.None, out ts) || ts < desde) continue;
                string clave = c[2] + ":" + c[3] + ":" + c[4];
                if (c[1] == "FaultSet") { if (!abiertas.ContainsKey(clave)) abiertas[clave] = ts; }
                else if (c[1] == "FaultClear") abiertas.Remove(clave);
            }

            List<Activa> nuevas = new List<Activa>();
            HashSet<string> ignoradas = new HashSet<string>();
            foreach (KeyValuePair<string, DateTime> kv in abiertas)
            {
                string corta = kv.Key.Substring(0, kv.Key.LastIndexOf(':'));
                if (Cfg.FallasIgnorar.Contains(kv.Key) || Cfg.FallasIgnorar.Contains(corta))
                {
                    ignoradas.Add(kv.Key.Split(':')[0]);
                    continue;
                }
                Activa a = new Activa();
                a.Clave = kv.Key.EndsWith(":0") ? corta : kv.Key;
                a.Desde = kv.Value;
                nuevas.Add(a);
            }
            nuevas.Sort(delegate(Activa x, Activa y) { return x.Desde.CompareTo(y.Desde); });
            Activas = nuevas;
            gruposIgnoradosActivos = ignoradas;
            leidoLargo = fi.Length; leidoMtime = fi.LastWriteTime; leidoInicio = inicio;
        }

        // Para la pantalla de bloqueo: los tipos (grupo, el primer número del código) que están en FallasIgnorar,
        // sin la lista completa. "activa" = ese tipo tiene ahora una falla abierta que no avisa por estar ignorada.
        public static string TextoIgnoradas()
        {
            if (Cfg.FallasIgnorar.Count == 0) return "";
            List<string> grupos = new List<string>();
            foreach (string f in Cfg.FallasIgnorar)
            {
                string g = f.Split(':')[0];
                if (!grupos.Contains(g)) grupos.Add(g);
            }
            grupos.Sort(delegate(string x, string y)
            {
                int a, b;
                return int.TryParse(x, out a) && int.TryParse(y, out b) ? a.CompareTo(b) : string.CompareOrdinal(x, y);
            });
            List<string> partes = new List<string>();
            foreach (string g in grupos) partes.Add("tipo " + g + (gruposIgnoradosActivos.Contains(g) ? " (activa)" : ""));
            return "Fallas ignoradas: " + string.Join(" · ", partes.ToArray());
        }

        public static string Texto()
        {
            if (Activas.Count == 0) return "";
            List<string> partes = new List<string>();
            foreach (Activa a in Activas)
                partes.Add(a.Clave + " (desde " + a.Desde.ToString(a.Desde.Date == DateTime.Today ? "HH:mm" : "dd/MM HH:mm") + ")");
            return (Activas.Count == 1 ? "Falla " : Activas.Count + " fallas: ") + string.Join(" · ", partes.ToArray());
        }
    }

    // ───────────────────────── Nivel de Delvis automático ─────────────────────────
    // Delvis no tiene usuarios: tiene una contraseña por nivel (1, 2 y 3) y la pide en su ventana
    // de teclado ("Por favor introduce el nivel de N contraseña:") al entrar a una pantalla protegida.
    // Con el candado abierto por un operario que tiene nivel (viene del servidor), cuando Delvis pide
    // un nivel igual o menor al suyo, el candado escribe la contraseña en esa ventana y la acepta.
    // Las contraseñas se ponen en el sistema (Usuarios → Contraseñas de Delvis) y llegan con la
    // sincronización, cifradas con llaves que salen del token; también se pueden cargar solo en esta PC
    // con "--claves-delvis" (DPAPI, delvis.claves). Las del sistema mandan. Solo se escriben si coinciden
    // con la huella MD5 que Delvis guarda en su Config.xml (que solo se lee): nunca se teclea una vieja.
    static class NivelDelvis
    {
        static readonly byte[] Entropia = Encoding.UTF8.GetBytes("Honducafe.CandadoOperario.Delvis");
        public static string Ruta = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "delvis.claves");

        // Textos de la ventana de Delvis que pide un nivel (inglés y español). No coinciden con
        // "Enter a new level N password" (cambiar contraseña), que también usa esa ventana.
        static readonly Regex Pide = new Regex(@"enter the level ([123]) password|introduce el nivel de ([123]) contrase", RegexOptions.IgnoreCase);

        public static int NivelPedido(string texto)
        {
            Match m = Pide.Match(texto ?? "");
            if (!m.Success) return 0;
            return int.Parse(m.Groups[1].Success ? m.Groups[1].Value : m.Groups[2].Value);
        }

        // Huella como la calcula Delvis (MachineSettings.EncryptString): MD5 de la contraseña en mayúsculas
        // seguida de 5 × (nivel − 1): "…0" para el nivel 1, "…5" para el 2 y "…10" para el 3.
        public static string Huella(string clave, int nivel)
        {
            return Md5(clave.ToUpperInvariant() + (5 * (nivel - 1)).ToString(CultureInfo.InvariantCulture));
        }

        public static string Md5(string s)
        {
            using (MD5 h = MD5.Create())
            {
                StringBuilder sb = new StringBuilder();
                foreach (byte x in h.ComputeHash(Encoding.UTF8.GetBytes(s))) sb.Append(x.ToString("x2"));
                return sb.ToString();
            }
        }

        // ── Contraseñas guardadas ──
        public static Dictionary<int, string> Leer()
        {
            Dictionary<int, string> r = new Dictionary<int, string>();
            try
            {
                if (!File.Exists(Ruta)) return r;
                byte[] claro = ProtectedData.Unprotect(File.ReadAllBytes(Ruta), Entropia, DataProtectionScope.LocalMachine);
                foreach (string l in Encoding.UTF8.GetString(claro).Split('\n'))
                {
                    int i = l.IndexOf('=');
                    int n;
                    if (i > 0 && int.TryParse(l.Substring(0, i), out n) && n >= 1 && n <= 3) r[n] = l.Substring(i + 1);
                }
                Array.Clear(claro, 0, claro.Length);
            }
            catch (Exception) { }
            return r;
        }

        // Lanza excepción si no se puede escribir (la carpeta del programa pide administrador)
        public static void Guardar(Dictionary<int, string> claves)
        {
            if (claves.Count == 0) { if (File.Exists(Ruta)) File.Delete(Ruta); return; }
            StringBuilder sb = new StringBuilder();
            foreach (KeyValuePair<int, string> kv in claves) sb.Append(kv.Key).Append('=').Append(kv.Value).Append('\n');
            File.WriteAllBytes(Ruta, ProtectedData.Protect(Encoding.UTF8.GetBytes(sb.ToString()), Entropia, DataProtectionScope.LocalMachine));
        }

        // ── Contraseñas del sistema ──
        // Mismo formato que config/kioscoCifrado.php: base64(iv | AES-256-CBC | HMAC-SHA256(iv|cifrado)),
        // llaves = HMAC-SHA256(token, "delvis-kiosco-cifrado" / "delvis-kiosco-firma").
        static byte[] Hmac(byte[] llave, byte[] datos, int largo)
        {
            using (HMACSHA256 h = new HMACSHA256(llave)) return h.ComputeHash(datos, 0, largo);
        }

        public static Dictionary<int, string> Descifrar(string b64, string token)
        {
            Dictionary<int, string> r = new Dictionary<int, string>();
            if (string.IsNullOrEmpty(b64) || string.IsNullOrEmpty(token) || token.Length < 16) return r;
            try
            {
                byte[] raw = Convert.FromBase64String(b64);
                if (raw.Length < 64) return r;
                byte[] tk = Encoding.UTF8.GetBytes(token);
                byte[] ue = Encoding.UTF8.GetBytes("delvis-kiosco-cifrado"), um = Encoding.UTF8.GetBytes("delvis-kiosco-firma");
                byte[] ke = Hmac(tk, ue, ue.Length), km = Hmac(tk, um, um.Length);
                int n = raw.Length - 48;
                byte[] mac = Hmac(km, raw, 16 + n);
                int dif = 0;
                for (int i = 0; i < 32; i++) dif |= mac[i] ^ raw[16 + n + i];
                if (dif != 0) return r;   // alterado o de otro token
                byte[] iv = new byte[16];
                Array.Copy(raw, 0, iv, 0, 16);
                byte[] claro;
                using (AesCryptoServiceProvider a = new AesCryptoServiceProvider())
                {
                    a.Key = ke; a.IV = iv; a.Mode = CipherMode.CBC; a.Padding = PaddingMode.PKCS7;
                    using (ICryptoTransform d = a.CreateDecryptor()) claro = d.TransformFinalBlock(raw, 16, n);
                }
                foreach (string l in Encoding.UTF8.GetString(claro).Split('\n'))
                {
                    int i = l.IndexOf('=');
                    int nv;
                    if (i > 0 && int.TryParse(l.Substring(0, i), out nv) && nv >= 1 && nv <= 3) r[nv] = l.Substring(i + 1);
                }
                Array.Clear(claro, 0, claro.Length);
            }
            catch (Exception) { }
            return r;
        }

        public static Dictionary<int, string> DelSistema()
        {
            string b64;
            lock (Store.Lock) { b64 = Store.Cache.delvis; }
            return Descifrar(b64, Cfg.Token);
        }

        // Las de esta PC, reemplazadas por las del sistema donde las haya
        public static Dictionary<int, string> Todas()
        {
            Dictionary<int, string> r = Leer();
            foreach (KeyValuePair<int, string> kv in DelSistema()) r[kv.Key] = kv.Value;
            return r;
        }

        // Huellas que Delvis tiene ahora por nivel (vacío si no se pudo leer Config.xml)
        public static Dictionary<int, string> Huellas()
        {
            Dictionary<int, string> r = new Dictionary<int, string>();
            try
            {
                string p = Path.Combine(Cfg.DelvisDir, "Config.xml");
                string xml;
                using (FileStream fs = new FileStream(p, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete))
                using (StreamReader sr = new StreamReader(fs)) xml = sr.ReadToEnd();
                foreach (Match m in Regex.Matches(xml, @"<_passwordLevel([123])>\s*([0-9a-fA-F]{32})\s*<"))
                    r[int.Parse(m.Groups[1].Value)] = m.Groups[2].Value.ToLowerInvariant();
            }
            catch (Exception) { }
            return r;
        }

        // "ok", "distinta" (Delvis la cambió), "sin guardar" o "sin huella" (no se pudo leer Config.xml)
        public static string Estado(int nivel, Dictionary<int, string> claves, Dictionary<int, string> huellas)
        {
            if (!claves.ContainsKey(nivel)) return "sin guardar";
            if (!huellas.ContainsKey(nivel)) return "sin huella";
            return Huella(claves[nivel], nivel) == huellas[nivel] ? "ok" : "distinta";
        }

        // Contraseña a escribir cuando Delvis pide «nivel»: Delvis acepta la de ese nivel o la de uno mayor,
        // así que sirve cualquier guardada de nivel..tope (tope = nivel del operario) que coincida con Delvis.
        // Sin Config.xml legible se usa la del nivel pedido sin comprobar. null si ninguna sirve.
        public static string Elegir(int nivel, int tope, Dictionary<int, string> claves, Dictionary<int, string> huellas, out string por)
        {
            por = "";
            if (huellas.Count == 0)
            {
                por = "sin huella";
                return claves.ContainsKey(nivel) ? claves[nivel] : null;
            }
            for (int n = nivel; n <= Math.Min(3, tope); n++)
            {
                // Una misma contraseña puede estar guardada en otro nivel del sistema («la misma para los 3»)
                foreach (KeyValuePair<int, string> kv in claves)
                    if (huellas.ContainsKey(n) && Huella(kv.Value, n) == huellas[n]) { por = n == nivel ? "" : "la de nivel " + n; return kv.Value; }
            }
            return null;
        }

        // ── Ventanas de Delvis ──
        delegate bool EnumProc(IntPtr h, IntPtr l);
        [DllImport("user32.dll")] static extern bool EnumWindows(EnumProc f, IntPtr l);
        [DllImport("user32.dll")] static extern bool EnumChildWindows(IntPtr padre, EnumProc f, IntPtr l);
        [DllImport("user32.dll")] static extern uint GetWindowThreadProcessId(IntPtr h, out uint pid);
        [DllImport("user32.dll")] static extern bool IsWindowVisible(IntPtr h);
        [DllImport("user32.dll")] static extern bool IsWindow(IntPtr h);
        [DllImport("user32.dll", CharSet = CharSet.Unicode)] static extern int GetClassName(IntPtr h, StringBuilder sb, int max);
        [DllImport("user32.dll", CharSet = CharSet.Unicode)] static extern IntPtr SendMessageTimeout(IntPtr h, uint msg, IntPtr w, StringBuilder l, uint flags, uint ms, out IntPtr res);
        [DllImport("user32.dll", SetLastError = true)] static extern bool PostMessage(IntPtr h, uint msg, IntPtr w, IntPtr l);
        const uint WM_GETTEXT = 0x0D, WM_KEYDOWN = 0x100, WM_KEYUP = 0x101, WM_CHAR = 0x102, EM_SETSEL = 0xB1, SMTO_ABORTIFHUNG = 2;

        public class Ventana
        {
            public IntPtr Hwnd;
            public string Clase, Titulo;
            public List<string> Textos = new List<string>();      // títulos y etiquetas (no se lee lo escrito en cajas de texto)
            public List<IntPtr> Cajas = new List<IntPtr>();        // cajas de texto visibles
            public List<string> Hijos = new List<string>();        // para el diagnóstico
            public int Nivel;                                      // nivel que pide, 0 si no es la ventana de contraseña
        }

        static string Clase(IntPtr h) { StringBuilder sb = new StringBuilder(256); GetClassName(h, sb, sb.Capacity); return sb.ToString(); }

        static string Texto(IntPtr h)
        {
            StringBuilder sb = new StringBuilder(512);
            IntPtr r;
            SendMessageTimeout(h, WM_GETTEXT, (IntPtr)sb.Capacity, sb, SMTO_ABORTIFHUNG, 300, out r);
            return sb.ToString();
        }

        // Procesos de Delvis: se buscan cada 10 s, no en cada revisión (listar procesos cuesta más que mirar ventanas)
        static HashSet<uint> pidsDelvis = new HashSet<uint>();
        static DateTime pidsFecha = DateTime.MinValue;

        static HashSet<uint> PidsDelvis()
        {
            if ((DateTime.UtcNow - pidsFecha).TotalSeconds >= 10)
            {
                HashSet<uint> pids = new HashSet<uint>();
                foreach (Process p in Process.GetProcessesByName("Delvis")) { pids.Add((uint)p.Id); p.Dispose(); }
                pidsDelvis = pids;
                pidsFecha = DateTime.UtcNow;
            }
            return pidsDelvis;
        }

        // completo = false (cada medio segundo durante el turno): solo se mira la clase de los controles, que Windows
        // contesta sin molestar a Delvis; los textos se le piden únicamente a una ventana con caja de texto (la de
        // contraseña) que aún no se haya atendido. Así, con Delvis en su pantalla normal, no recibe ningún mensaje.
        // completo = true (diagnóstico): textos de todo.
        public static List<Ventana> Ventanas(bool completo)
        {
            HashSet<uint> pids = completo ? new HashSet<uint>() : PidsDelvis();
            if (completo) { foreach (Process p in Process.GetProcessesByName("Delvis")) { pids.Add((uint)p.Id); p.Dispose(); } }
            List<Ventana> r = new List<Ventana>();
            if (pids.Count == 0) return r;
            EnumWindows(delegate(IntPtr h, IntPtr l)
            {
                uint pid;
                GetWindowThreadProcessId(h, out pid);
                if (!pids.Contains(pid) || !IsWindowVisible(h)) return true;
                Ventana v = new Ventana();
                v.Hwnd = h; v.Clase = Clase(h);
                List<IntPtr> otros = new List<IntPtr>();
                EnumChildWindows(h, delegate(IntPtr c, IntPtr l2)
                {
                    if (!IsWindowVisible(c)) return true;
                    if (Clase(c).IndexOf("EDIT", StringComparison.OrdinalIgnoreCase) >= 0) v.Cajas.Add(c); else otros.Add(c);
                    return true;
                }, IntPtr.Zero);
                bool leer = completo || (v.Cajas.Count > 0 && !atendidas.Contains(h));
                if (leer)
                {
                    v.Titulo = Texto(h);
                    v.Textos.Add(v.Titulo);
                    foreach (IntPtr c in otros)
                    {
                        string t = Texto(c);
                        if (t.Length > 0) v.Textos.Add(t);
                        if (completo) v.Hijos.Add(Clase(c) + (t.Length > 0 ? "  \"" + t.Replace("\r", " ").Replace("\n", " ") + "\"" : ""));
                    }
                    if (completo) foreach (IntPtr c in v.Cajas) v.Hijos.Add(Clase(c) + "  [caja de texto]");
                    v.Nivel = NivelPedido(string.Join(" | ", v.Textos.ToArray()));
                }
                else v.Titulo = "";
                r.Add(v);
                return true;
            }, IntPtr.Zero);
            return r;
        }

        // ── Llenado ──
        static HashSet<IntPtr> atendidas = new HashSet<IntPtr>();   // ventanas ya llenadas (o descartadas): una sola vez cada una

        public static void Olvidar() { atendidas.Clear(); }

        // Se llama cada medio segundo mientras hay un operario con nivel. Devuelve qué hizo (para la bitácora) o null.
        public static string Revisar(int nivelOperario)
        {
            atendidas.RemoveWhere(delegate(IntPtr h) { return !IsWindow(h); });
            List<Ventana> vs = Ventanas(false);
            atendidas.RemoveWhere(delegate(IntPtr h) { return !IsWindow(h); });
            foreach (Ventana v in vs)
            {
                if (v.Cajas.Count == 0 || atendidas.Contains(v.Hwnd)) continue;
                atendidas.Add(v.Hwnd);   // otras ventanas con caja (teclado numérico de Delvis) también: se leen una sola vez
                if (v.Nivel == 0) continue;
                if (v.Nivel > nivelOperario) return "Delvis pide nivel " + v.Nivel + "; el operario tiene nivel " + nivelOperario + ": no se llena.";

                // Solo se lee cuando Delvis pide una contraseña (pocas veces), así que no se guarda en memoria
                Dictionary<int, string> claves = Todas(), huellas = Huellas();

                if (claves.Count == 0) return "Delvis pide nivel " + v.Nivel + " y no hay contraseñas guardadas.";
                string por;
                string clave = Elegir(v.Nivel, nivelOperario, claves, huellas, out por);
                if (clave == null) return "Delvis pide nivel " + v.Nivel + ": ninguna contraseña guardada coincide con Delvis (¿la cambiaron?). No se escribe.";
                return Escribir(v.Cajas[0], clave)
                    ? "Nivel " + v.Nivel + " escrito en Delvis" + (por == "sin huella" ? " (sin poder comprobar Config.xml)." : por.Length > 0 ? " (con " + por + ")." : ".")
                    : "Windows no dejó escribir en Delvis (error " + Marshal.GetLastWin32Error() + "; 5 = Delvis corre como administrador).";
            }
            return null;
        }

        // Como si se tecleara: selecciona lo que haya, escribe cada letra y pulsa Enter
        static bool Escribir(IntPtr caja, string clave)
        {
            if (!PostMessage(caja, EM_SETSEL, IntPtr.Zero, (IntPtr)(-1))) return false;
            foreach (char c in clave) PostMessage(caja, WM_CHAR, (IntPtr)c, (IntPtr)1);
            PostMessage(caja, WM_KEYDOWN, (IntPtr)0x0D, (IntPtr)0x001C0001);
            return PostMessage(caja, WM_KEYUP, (IntPtr)0x0D, unchecked((IntPtr)(int)0xC01C0001));
        }

        // Prueba de envío sin escribir nada (WM_NULL): false si Windows lo bloquea
        [DllImport("user32.dll", SetLastError = true)] static extern bool PostMessage(IntPtr h, uint msg, IntPtr w, int l);
        public static string PuedeEnviar(IntPtr h)
        {
            return PostMessage(h, 0, IntPtr.Zero, 0) ? "sí" : "NO (error " + Marshal.GetLastWin32Error() + "; 5 = Delvis corre como administrador y el candado no)";
        }

        public static void Bitacora(string s)
        {
            try
            {
                string log = Path.Combine(Store.DirDatos, "nivel_delvis.log");
                if (File.Exists(log) && new FileInfo(log).Length > 200000) File.Delete(log);
                File.AppendAllText(log, DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "  " + s + "\r\n");
            }
            catch (Exception) { }
        }

        // Por qué no coincide: qué Config.xml hay (la carpeta configurada y la del Delvis.exe que corre),
        // cómo es lo que guarda cada nivel y con qué forma de huella coincide la contraseña guardada.
        // Nunca escribe contraseñas ni huellas en el reporte.
        static string DiagnosticoHuellas(Dictionary<int, string> claves)
        {
            StringBuilder sb = new StringBuilder();
            List<string> rutas = new List<string>();
            rutas.Add(Path.Combine(Cfg.DelvisDir, "Config.xml"));
            foreach (Process p in Process.GetProcessesByName("Delvis"))
            {
                try
                {
                    string exe = p.MainModule.FileName;
                    sb.AppendLine("Delvis.exe corriendo desde: " + exe);
                    string r = Path.Combine(Path.GetDirectoryName(exe), "Config.xml");
                    if (!rutas.Exists(delegate(string x) { return string.Equals(x, r, StringComparison.OrdinalIgnoreCase); })) rutas.Add(r);
                }
                catch (Exception ex) { sb.AppendLine("Delvis.exe (pid " + p.Id + "): no se pudo leer la ruta (" + ex.GetType().Name + ")"); }
                finally { p.Dispose(); }
            }
            foreach (string ruta in rutas)
            {
                sb.AppendLine("Config.xml: " + ruta);
                if (!File.Exists(ruta)) { sb.AppendLine("   no existe"); continue; }
                sb.AppendLine("   modificado " + File.GetLastWriteTime(ruta).ToString("yyyy-MM-dd HH:mm"));
                string xml;
                try
                {
                    using (FileStream fs = new FileStream(ruta, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete))
                    using (StreamReader sr = new StreamReader(fs)) xml = sr.ReadToEnd();
                }
                catch (Exception ex) { sb.AppendLine("   no se pudo leer: " + ex.Message); continue; }
                for (int n = 1; n <= 3; n++)
                {
                    Match m = Regex.Match(xml, "<_passwordLevel" + n + @">([^<]*)<");
                    if (!m.Success) { sb.AppendLine("   nivel " + n + ": sin _passwordLevel" + n); continue; }
                    string v = m.Groups[1].Value.Trim();
                    string forma = Regex.IsMatch(v, "^[0-9a-fA-F]{32}$") ? "32 hex (MD5)" : Regex.IsMatch(v, "^[0-9a-fA-F]+$") ? v.Length + " hex"
                        : Regex.IsMatch(v, "^[A-Za-z0-9+/=]+$") ? v.Length + " car. base64" : v.Length + " caracteres";
                    string coincide = "sin contraseña guardada para comparar";
                    if (claves.ContainsKey(n))
                    {
                        string k = claves[n];
                        List<string> ok = new List<string>();
                        Func<byte[], string> md5 = delegate(byte[] d) { using (MD5 h = MD5.Create()) { StringBuilder x = new StringBuilder(); foreach (byte y in h.ComputeHash(d)) x.Append(y.ToString("x2")); return x.ToString(); } };
                        Func<byte[], string> sha1 = delegate(byte[] d) { using (SHA1 h = SHA1.Create()) { StringBuilder x = new StringBuilder(); foreach (byte y in h.ComputeHash(d)) x.Append(y.ToString("x2")); return x.ToString(); } };
                        string vl = v.ToLowerInvariant();
                        if (Huella(k, n) == vl) ok.Add("como Delvis");
                        if (md5(Encoding.UTF8.GetBytes(k)) == vl) ok.Add("MD5 tal cual");
                        if (md5(Encoding.UTF8.GetBytes(k.ToUpperInvariant())) == vl) ok.Add("MD5 en mayúsculas");
                        if (md5(Encoding.UTF8.GetBytes(k.ToLowerInvariant())) == vl) ok.Add("MD5 en minúsculas");
                        if (md5(Encoding.Unicode.GetBytes(k)) == vl) ok.Add("MD5 UTF-16");
                        if (sha1(Encoding.UTF8.GetBytes(k)) == vl) ok.Add("SHA1");
                        if (v == k) ok.Add("texto sin cifrar");
                        coincide = ok.Count > 0 ? "COINCIDE (" + string.Join(", ", ok.ToArray()) + ")" : "no coincide con la contraseña guardada";
                    }
                    sb.AppendLine("   nivel " + n + ": " + forma + " · " + coincide);
                }
            }
            return sb.ToString();
        }

        // "--ver-delvis": espera unos segundos (para abrir la ventana de contraseña) y apunta lo que ve
        public static string Diagnostico()
        {
            StringBuilder sb = new StringBuilder();
            sb.AppendLine("Candado " + Assembly.GetExecutingAssembly().GetName().Version + " · " + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + " · " + Environment.MachineName);
            sb.AppendLine("Carpeta de Delvis: " + Cfg.DelvisDir);
            Dictionary<int, string> c = Todas(), h = Huellas(), sis = DelSistema();
            for (int n = 1; n <= 3; n++) sb.AppendLine("  Nivel " + n + ": " + Estado(n, c, h) + (sis.ContainsKey(n) ? " (del sistema)" : c.ContainsKey(n) ? " (de esta PC)" : ""));
            sb.Append(DiagnosticoHuellas(c));
            List<Ventana> vs = Ventanas(true);
            sb.AppendLine("Ventanas visibles de Delvis: " + vs.Count);
            foreach (Ventana v in vs)
            {
                sb.AppendLine();
                sb.AppendLine("== " + v.Clase + "  \"" + v.Titulo + "\"");
                sb.AppendLine("   pide nivel: " + (v.Nivel > 0 ? v.Nivel.ToString() : "no") + " · cajas de texto: " + v.Cajas.Count + " · se le puede escribir: " + PuedeEnviar(v.Hwnd));
                foreach (string x in v.Hijos) sb.AppendLine("   - " + x);
            }
            return sb.ToString();
        }
    }

    // "--claves-delvis": carga las contraseñas de nivel de Delvis en esta PC (como administrador)
    class ClavesForm : Form
    {
        TextBox[] cajas = new TextBox[3];
        Label[] estados = new Label[3];
        Label info;
        CheckBox misma;

        public ClavesForm()
        {
            Text = "Contraseñas de Delvis · Candado";
            Font = new Font("Segoe UI", 11f);
            AutoScaleMode = AutoScaleMode.Font;
            FormBorderStyle = FormBorderStyle.FixedDialog;
            MaximizeBox = MinimizeBox = false;
            StartPosition = FormStartPosition.CenterScreen;
            ClientSize = new Size(520, 332);
            TopMost = true;

            Controls.Add(new Label { Text = "Escribe solo las que quieras guardar o cambiar. En blanco = se deja como está.", Location = new Point(16, 12), Size = new Size(490, 44) });
            for (int i = 0; i < 3; i++)
            {
                int y = 62 + i * 44;
                Controls.Add(new Label { Text = "Nivel " + (i + 1), Location = new Point(16, y + 4), Size = new Size(70, 26) });
                cajas[i] = new TextBox { Location = new Point(90, y), Size = new Size(190, 28), UseSystemPasswordChar = true };
                estados[i] = new Label { Location = new Point(292, y + 4), Size = new Size(220, 26) };
                Controls.Add(cajas[i]);
                Controls.Add(estados[i]);
            }
            // En la planta los 3 niveles usan la misma: se escribe una vez
            misma = new CheckBox { Text = "La misma para los 3 niveles", Location = new Point(90, 192), Size = new Size(400, 28), Checked = true };
            Controls.Add(misma);
            EventHandler copiar = delegate
            {
                for (int i = 1; i < 3; i++) { cajas[i].Enabled = !misma.Checked; if (misma.Checked) cajas[i].Text = cajas[0].Text; }
            };
            misma.CheckedChanged += copiar;
            cajas[0].TextChanged += copiar;
            copiar(null, EventArgs.Empty);
            info = new Label { Location = new Point(16, 228), Size = new Size(490, 48), ForeColor = Color.FromArgb(160, 30, 30) };
            Controls.Add(info);
            Button guardar = new Button { Text = "Guardar", Location = new Point(300, 282), Size = new Size(100, 36) };
            Button borrar = new Button { Text = "Borrar todas", Location = new Point(16, 282), Size = new Size(130, 36) };
            Button cerrar = new Button { Text = "Cerrar", Location = new Point(408, 282), Size = new Size(96, 36), DialogResult = DialogResult.Cancel };
            Controls.Add(guardar); Controls.Add(borrar); Controls.Add(cerrar);
            AcceptButton = guardar; CancelButton = cerrar;
            guardar.Click += delegate { Guardar(); };
            borrar.Click += delegate
            {
                try { NivelDelvis.Guardar(new Dictionary<int, string>()); info.Text = "Borradas."; }
                catch (Exception ex) { info.Text = NoSePudo(ex); }
                Pintar();
            };
            Pintar();
        }

        static string NoSePudo(Exception ex)
        {
            return ex is UnauthorizedAccessException ? "No se pudo guardar: abre esto como administrador." : "No se pudo guardar: " + ex.Message;
        }

        void Pintar()
        {
            Dictionary<int, string> c = NivelDelvis.Leer(), h = NivelDelvis.Huellas();
            for (int i = 0; i < 3; i++)
            {
                string e = NivelDelvis.Estado(i + 1, c, h);
                estados[i].Text = e == "ok" ? "✓ guardada, coincide" : e == "distinta" ? "✗ guardada, NO coincide" : e == "sin huella" ? "guardada (sin Config.xml)" : "— sin guardar";
                estados[i].ForeColor = e == "ok" ? Color.FromArgb(20, 120, 60) : e == "distinta" ? Color.FromArgb(180, 30, 30) : Color.DimGray;
            }
            if (h.Count == 0) info.Text = "No se pudo leer " + Path.Combine(Cfg.DelvisDir, "Config.xml") + ": no se puede comprobar lo que escribas.";
        }

        void Guardar()
        {
            Dictionary<int, string> c = NivelDelvis.Leer(), h = NivelDelvis.Huellas();
            for (int i = 0; i < 3; i++)
            {
                string v = cajas[i].Text;
                if (v.Length == 0) continue;
                if (h.ContainsKey(i + 1) && NivelDelvis.Huella(v, i + 1) != h[i + 1])
                {
                    info.Text = "La del nivel " + (i + 1) + " no coincide con la de Delvis. No se guardó nada.";
                    cajas[i].Focus(); cajas[i].SelectAll();
                    return;
                }
                c[i + 1] = v;
            }
            try { NivelDelvis.Guardar(c); info.Text = "Guardado."; foreach (TextBox t in cajas) t.Clear(); }
            catch (Exception ex) { info.Text = NoSePudo(ex); }
            Pintar();
        }
    }

    static class Native
    {
        [DllImport("user32.dll")] public static extern bool SetProcessDPIAware();
        [DllImport("user32.dll")] static extern bool SetWindowPos(IntPtr h, IntPtr after, int x, int y, int cx, int cy, uint flags);
        [DllImport("user32.dll")] static extern bool GetLastInputInfo(ref LASTINPUTINFO p);
        [DllImport("user32.dll", CharSet = CharSet.Auto)] static extern IntPtr FindWindow(string clase, string nombre);
        [DllImport("user32.dll", CharSet = CharSet.Auto)] static extern IntPtr FindWindowEx(IntPtr padre, IntPtr despuesDe, string clase, string nombre);
        [DllImport("user32.dll")] static extern bool ShowWindow(IntPtr h, int modo);
        [DllImport("user32.dll")] static extern bool EnableWindow(IntPtr h, bool activar);
        [DllImport("user32.dll", SetLastError = true)] static extern IntPtr SetWindowsHookEx(int idHook, GanchoTeclado proc, IntPtr hMod, uint hilo);
        [DllImport("user32.dll")] static extern bool UnhookWindowsHookEx(IntPtr h);
        [DllImport("user32.dll")] static extern IntPtr CallNextHookEx(IntPtr h, int codigo, IntPtr wParam, IntPtr lParam);
        [DllImport("kernel32.dll", CharSet = CharSet.Auto)] static extern IntPtr GetModuleHandle(string modulo);
        [DllImport("user32.dll")] static extern short GetKeyState(int tecla);

        [StructLayout(LayoutKind.Sequential)]
        struct LASTINPUTINFO { public uint cbSize; public uint dwTime; }

        static readonly IntPtr HWND_TOPMOST = new IntPtr(-1);
        const uint SWP_NOSIZE = 0x1, SWP_NOMOVE = 0x2, SWP_NOACTIVATE = 0x10;
        const int SW_HIDE = 0, SW_SHOW = 5;

        public static void KeepOnTop(Form f)
        {
            SetWindowPos(f.Handle, HWND_TOPMOST, 0, 0, 0, 0, SWP_NOMOVE | SWP_NOSIZE | SWP_NOACTIVATE);
        }

        public static uint IdleMs()
        {
            LASTINPUTINFO li = new LASTINPUTINFO();
            li.cbSize = (uint)Marshal.SizeOf(li);
            GetLastInputInfo(ref li);
            return (uint)Environment.TickCount - li.dwTime;
        }

        // El candado cubre la pantalla, pero por sí solo eso no impide usar la barra de
        // tareas (Windows la mantiene encima incluso de una ventana "TopMost"). Se oculta
        // aparte mientras está bloqueado, y se restaura al desbloquear o si el programa falla.
        // Nunca debe quedar oculta por error: si algo sale mal aquí, no se bloquea nada más.
        //
        // Ocultarla una sola vez no basta: al arrancar Windows el candado se abre a la vez que el
        // Explorador y puede intentarlo antes de que la barra exista, y el Explorador también la
        // vuelve a mostrar por su cuenta (se reinicia, un aviso, un toque en el borde). Por eso se
        // repite mientras está bloqueado. Además se desactiva, para que aunque se vea un instante
        // (incluida su casilla de búsqueda) no responda a clics ni toques.
        public static void OcultarBarraTareas(bool ocultar)
        {
            try
            {
                int modo = ocultar ? SW_HIDE : SW_SHOW;
                IntPtr principal = FindWindow("Shell_TrayWnd", null);
                if (principal != IntPtr.Zero) { EnableWindow(principal, !ocultar); ShowWindow(principal, modo); }

                // Una barra adicional por cada monitor, si Windows está configurado para mostrarla en todos
                IntPtr sec = IntPtr.Zero;
                for (int i = 0; i < 8; i++)
                {
                    sec = FindWindowEx(IntPtr.Zero, sec, "Shell_SecondaryTrayWnd", null);
                    if (sec == IntPtr.Zero) break;
                    EnableWindow(sec, !ocultar);
                    ShowWindow(sec, modo);
                }
            }
            catch (Exception) { }
        }

        // Ocultar la barra de tareas no evita abrir Inicio: la tecla Windows (o el gesto
        // de deslizar desde el borde en pantallas tactiles, que el sistema traduce a esa
        // misma tecla) y Ctrl+Esc lo siguen haciendo. Se interceptan con un gancho de
        // teclado mientras el candado esta bloqueado, y se retira al desbloquear.
        // Ctrl+Alt+Supr no se puede bloquear desde una aplicacion: lo filtra Windows antes
        // de que llegue aqui (es la "secuencia seria de atencion", a proposito).
        delegate IntPtr GanchoTeclado(int codigo, IntPtr wParam, IntPtr lParam);
        const int WH_KEYBOARD_LL = 13, WM_KEYDOWN = 0x100, WM_SYSKEYDOWN = 0x104;
        const int VK_LWIN = 0x5B, VK_RWIN = 0x5C, VK_CONTROL = 0x11, VK_ESCAPE = 0x1B, VK_BROWSER_SEARCH = 0xAA;
        static IntPtr gancho = IntPtr.Zero;
        static readonly GanchoTeclado ganchoProc = GanchoCallback; // referencia viva: si el GC la recoge, el hook crashea

        static IntPtr GanchoCallback(int codigo, IntPtr wParam, IntPtr lParam)
        {
            if (codigo >= 0 && (wParam == (IntPtr)WM_KEYDOWN || wParam == (IntPtr)WM_SYSKEYDOWN))
            {
                int vk = Marshal.ReadInt32(lParam);
                bool esWin = vk == VK_LWIN || vk == VK_RWIN || vk == VK_BROWSER_SEARCH;   // incluye la tecla de búsqueda
                bool esCtrlEsc = vk == VK_ESCAPE && (GetKeyState(VK_CONTROL) & 0x8000) != 0;
                if (esWin || esCtrlEsc) return (IntPtr)1; // se traga la tecla: no abre Inicio
            }
            return CallNextHookEx(gancho, codigo, wParam, lParam);
        }

        public static void BloquearTeclaWindows(bool bloquear)
        {
            try
            {
                if (bloquear)
                {
                    if (gancho == IntPtr.Zero)
                        gancho = SetWindowsHookEx(WH_KEYBOARD_LL, ganchoProc, GetModuleHandle(null), 0);
                }
                else if (gancho != IntPtr.Zero)
                {
                    UnhookWindowsHookEx(gancho);
                    gancho = IntPtr.Zero;
                }
            }
            catch (Exception) { }
        }
    }

    static class Ui
    {
        public static float Scale = 1f;
        public static int S(int v) { return (int)Math.Round(v * Scale); }

        // Colores tomados del logo de Honducafe
        public static readonly Color Marca = Color.FromArgb(98, 14, 15);        // vino
        public static readonly Color MarcaHover = Color.FromArgb(70, 9, 11);
        public static readonly Color Tinte = Color.FromArgb(246, 236, 236);
        public static readonly Color Verde = Color.FromArgb(63, 125, 92);       // hojas
        public static readonly Color Texto = Color.FromArgb(38, 26, 27);
        public static readonly Color Texto2 = Color.FromArgb(105, 90, 91);
        public static readonly Color Texto3 = Color.FromArgb(154, 139, 140);
        public static readonly Color Borde = Color.FromArgb(231, 220, 221);
        public static readonly Color Fondo = Color.White;
        public static readonly Color Error = Color.FromArgb(190, 40, 30);
        public static readonly Color Alarma = Color.FromArgb(200, 24, 24);       // franja de falla
        public static readonly Color AlarmaFondo = Color.FromArgb(248, 196, 191); // punto más rojo del pulso de fondo
        public const int PulsoMs = 2400;                                          // duración de un pulso (blanco-rojo-blanco)

        public static Color Mezcla(Color a, Color b, double k)
        {
            return Color.FromArgb((int)(a.R + (b.R - a.R) * k), (int)(a.G + (b.G - a.G) * k), (int)(a.B + (b.B - a.B) * k));
        }

        static Bitmap logo;
        public static Bitmap Logo
        {
            get
            {
                if (logo == null)
                {
                    using (Stream st = Assembly.GetExecutingAssembly().GetManifestResourceStream("CandadoOperario.logo.png"))
                    {
                        if (st != null) using (Bitmap b = new Bitmap(st)) logo = new Bitmap(b);
                    }
                }
                return logo;
            }
        }
    }

    class KeyButton : Button
    {
        public KeyButton()
        {
            SetStyle(ControlStyles.Selectable, false);
            TabStop = false;
            FlatStyle = FlatStyle.Flat;
        }
    }

    // ───────────────────────── Pantalla de bloqueo ─────────────────────────
    class CoverForm : Form
    {
        public event Action<string> PinSubmitted;
        public event Action SyncRequested;
        public event Action ExitRequested;

        // Solo para --preview: simula otra resolución de pantalla
        public static Rectangle? Forzar;

        string pin = "";
        Label dots, err, status, aviso, est, sub, ignoradas;
        System.Windows.Forms.Timer keep, parpadeo;
        bool busy;
        double angulo;
        float fit = 1f;

        // Medidas y fuentes se reducen si la pantalla es pequeña o la escala de Windows es alta
        int F(int v) { return (int)Math.Round(v * Ui.Scale * fit); }
        Font Fn(string name, float size) { return new Font(name, size * fit * Ui.Scale * 96f / 72f, GraphicsUnit.Pixel); }

        public CoverForm()
        {
            FormBorderStyle = FormBorderStyle.None;
            StartPosition = FormStartPosition.Manual;
            Rectangle pb = Forzar.HasValue ? Forzar.Value : Screen.PrimaryScreen.Bounds;
            Bounds = Forzar.HasValue ? Forzar.Value : SystemInformation.VirtualScreen;
            TopMost = true;
            ShowInTaskbar = false;
            BackColor = Ui.Fondo;
            KeyPreview = true;
            DoubleBuffered = true;

            fit = Math.Min(1f, Math.Min(pb.Width / (float)Ui.S(960), pb.Height / (float)Ui.S(600)));
            int ox = pb.X - Bounds.X, oy = pb.Y - Bounds.Y;

            // ── Medidas ──
            int rightW = F(360);
            int gap = F(90);
            int leftW = Math.Min(F(470), Math.Max(F(280), pb.Width - rightW - gap - F(80)));
            int totalW = leftW + gap + rightW;
            int x0 = ox + Math.Max(F(20), (pb.Width - totalW) / 2);
            int xr = x0 + leftW + gap;

            Bitmap lg = Ui.Logo;
            int logoH = lg != null ? (int)(leftW * (double)lg.Height / lg.Width) : F(60);
            int leftH = logoH + F(150);
            int rightH = F(520);
            int contentH = Math.Max(leftH, rightH);
            int y0 = oy + Math.Max(F(16), (pb.Height - contentH) / 2);

            // ── Columna izquierda: logo, estación y sincronización ──
            PictureBox pic = new PictureBox();
            pic.Image = lg;
            pic.SizeMode = PictureBoxSizeMode.Zoom;
            pic.SetBounds(x0, y0 + (contentH - leftH) / 2, leftW, logoH);
            Controls.Add(pic);

            int ly = pic.Bottom + F(20);

            est = new Label();
            est.Text = Cfg.Estacion;
            est.Font = Fn("Segoe UI Semibold", 18f);
            est.ForeColor = Ui.Marca;
            est.TextAlign = ContentAlignment.MiddleCenter;
            est.AutoEllipsis = true;
            est.SetBounds(x0, ly, leftW, F(40));
            Controls.Add(est);

            status = new Label();
            status.Font = Fn("Segoe UI", 9f);
            status.ForeColor = Ui.Texto3;
            status.TextAlign = ContentAlignment.MiddleCenter;
            status.SetBounds(x0, ly + F(40), leftW, F(22));
            Controls.Add(status);

            KeyButton sync = new KeyButton();
            sync.Text = "⟳  Sincronizar";
            sync.Font = Fn("Segoe UI Symbol", 10f);
            sync.BackColor = Ui.Fondo;
            sync.ForeColor = Ui.Marca;
            sync.FlatAppearance.BorderColor = Ui.Marca;
            sync.FlatAppearance.MouseOverBackColor = Ui.Tinte;
            sync.SetBounds(x0 + (leftW - F(170)) / 2, ly + F(70), F(170), F(34));
            sync.Click += delegate { if (SyncRequested != null) SyncRequested(); };
            Controls.Add(sync);

            ignoradas = new Label();
            ignoradas.Font = Fn("Segoe UI", 9f);
            ignoradas.ForeColor = Ui.Texto3;
            ignoradas.TextAlign = ContentAlignment.TopCenter;
            ignoradas.AutoEllipsis = true;
            ignoradas.SetBounds(x0, ly + F(114), leftW, F(40));
            Controls.Add(ignoradas);

            // ── Divisor ──
            Label linea = new Label();
            linea.BackColor = Ui.Borde;
            linea.SetBounds(x0 + leftW + gap / 2, y0 + F(30), 1, contentH - F(60));
            Controls.Add(linea);

            // ── Columna derecha: teclado ──
            int ry = y0 + (contentH - rightH) / 2;

            Label title = new Label();
            title.Text = "Estación bloqueada";
            title.Font = Fn("Segoe UI Semibold", 18f);
            title.ForeColor = Ui.Texto;
            title.TextAlign = ContentAlignment.MiddleCenter;
            title.SetBounds(xr, ry + F(6), rightW, F(44));
            Controls.Add(title);

            sub = new Label();
            sub.Text = "Ingresa tu código para comenzar tu turno";
            sub.Font = Fn("Segoe UI", 10f);
            sub.ForeColor = Ui.Texto3;
            sub.TextAlign = ContentAlignment.MiddleCenter;
            sub.SetBounds(xr, ry + F(50), rightW, F(28));
            Controls.Add(sub);

            dots = new Label();
            dots.Font = Fn("Segoe UI Symbol", 18f);
            dots.ForeColor = Ui.Marca;
            dots.TextAlign = ContentAlignment.MiddleCenter;
            dots.SetBounds(xr, ry + F(90), rightW, F(44));
            Controls.Add(dots);

            string[] keys = { "1", "2", "3", "4", "5", "6", "7", "8", "9", "←", "0", "✓" };
            int kw = F(104), kh = F(66), kg = F(10);
            int startX = xr + (rightW - (3 * kw + 2 * kg)) / 2;
            int startY = ry + F(148);
            for (int i = 0; i < keys.Length; i++)
            {
                KeyButton b = new KeyButton();
                b.Text = keys[i];
                b.Tag = keys[i];
                b.SetBounds(startX + (i % 3) * (kw + kg), startY + (i / 3) * (kh + kg), kw, kh);
                b.Font = Fn(i == 9 || i == 11 ? "Segoe UI Symbol" : "Segoe UI Semibold", 18f);
                b.FlatAppearance.BorderColor = Ui.Borde;
                b.FlatAppearance.BorderSize = 1;
                if (i == 11)
                {
                    b.BackColor = Ui.Marca; b.ForeColor = Color.White;
                    b.FlatAppearance.BorderColor = Ui.Marca;
                    b.FlatAppearance.MouseOverBackColor = Ui.MarcaHover;
                }
                else
                {
                    b.BackColor = Color.White; b.ForeColor = Ui.Texto;
                    b.FlatAppearance.MouseOverBackColor = Ui.Tinte;
                }
                b.Click += delegate(object sender, EventArgs e) { Press((string)((Button)sender).Tag); };
                Controls.Add(b);
            }

            err = new Label();
            err.Font = Fn("Segoe UI Semibold", 10f);
            err.ForeColor = Ui.Error;
            err.TextAlign = ContentAlignment.MiddleCenter;
            err.SetBounds(xr, startY + 4 * (kh + kg) + F(4), rightW, F(46));
            Controls.Add(err);

            UpdateDots();

            // Falla activa en la máquina: franja arriba con el código y fondo que parpadea en rojo
            aviso = new Label();
            aviso.Font = Fn("Segoe UI Semibold", 15f);
            aviso.ForeColor = Color.White;
            aviso.BackColor = Ui.Alarma;
            aviso.TextAlign = ContentAlignment.MiddleCenter;
            aviso.AutoEllipsis = true;
            aviso.SetBounds(ox, oy, pb.Width, F(64));
            aviso.Visible = false;
            Controls.Add(aviso);
            parpadeo = new System.Windows.Forms.Timer();
            parpadeo.Interval = 80;
            parpadeo.Tick += delegate
            {
                // Pulso suave: sube y baja en Ui.PulsoMs, sin saltos de color
                angulo += 2 * Math.PI * parpadeo.Interval / Ui.PulsoMs;
                if (angulo > 2 * Math.PI) angulo -= 2 * Math.PI;
                Colores((1 - Math.Cos(angulo)) / 2);
            };

            keep = new System.Windows.Forms.Timer();
            keep.Interval = 500;
            keep.Tick += delegate
            {
                if (IsDisposed) { keep.Stop(); return; }
                if (!Visible) return;
                Native.OcultarBarraTareas(true);   // Windows la puede volver a mostrar: se oculta de nuevo
                Native.KeepOnTop(this);
                if (Form.ActiveForm != this) Activate();
            };
            keep.Start();

            KeyDown += OnKey;
            FormClosing += delegate(object s, FormClosingEventArgs e)
            {
                if (e.CloseReason == CloseReason.UserClosing) e.Cancel = true;
            };
        }

        void OnKey(object s, KeyEventArgs e)
        {
            if (Cfg.PermitirSalir && e.Control && e.Alt && e.Shift && e.KeyCode == Keys.Q)
            {
                if (ExitRequested != null) ExitRequested();
                return;
            }
            if (e.KeyCode >= Keys.D0 && e.KeyCode <= Keys.D9 && !e.Shift) Press(((char)('0' + (e.KeyCode - Keys.D0))).ToString());
            else if (e.KeyCode >= Keys.NumPad0 && e.KeyCode <= Keys.NumPad9) Press(((char)('0' + (e.KeyCode - Keys.NumPad0))).ToString());
            else if (e.KeyCode == Keys.Back) Press("←");
            else if (e.KeyCode == Keys.Enter) Press("✓");
            else return;
            e.SuppressKeyPress = true;
            e.Handled = true;
        }

        void Press(string k)
        {
            if (busy) return;
            if (k == "←") { if (pin.Length > 0) pin = pin.Substring(0, pin.Length - 1); }
            else if (k == "✓")
            {
                if (pin.Length >= 4 && PinSubmitted != null) { SetBusy(true); PinSubmitted(pin); }
                return;
            }
            else if (pin.Length < 6) pin += k;
            err.Text = "";
            UpdateDots();
        }

        void UpdateDots()
        {
            int n = Math.Max(4, pin.Length);
            StringBuilder sb = new StringBuilder();
            for (int i = 0; i < n; i++) sb.Append(i < pin.Length ? "●" : "○").Append(' ');
            dots.Text = sb.ToString().Trim();
        }

        public void SetBusy(bool b) { busy = b; }
        public void SetInfo(string m, bool error)
        {
            err.ForeColor = error ? Ui.Error : Ui.Verde;
            err.Text = m;
        }
        public void SetStatus(string s) { status.Text = s; }
        public void ResetPin() { pin = ""; UpdateDots(); SetBusy(false); }

        // texto vacío = sin fallas
        public void SetFallas(string texto)
        {
            bool hay = texto.Length > 0;
            aviso.Text = hay ? "⚠  FALLA EN LA MÁQUINA  ·  " + texto : "";
            aviso.Visible = hay;
            if (hay) { aviso.BringToFront(); if (!parpadeo.Enabled) parpadeo.Start(); }
            else { parpadeo.Stop(); angulo = 0; Colores(0); }
        }

        // k: 0 = blanco, 1 = punto más rojo del pulso. Los textos grises se oscurecen un poco para seguir legibles.
        void Colores(double k)
        {
            BackColor = Ui.Mezcla(Ui.Fondo, Ui.AlarmaFondo, k);
            status.ForeColor = sub.ForeColor = ignoradas.ForeColor = Ui.Mezcla(Ui.Texto3, Ui.Texto2, k);
        }

        public void SetIgnoradas(string texto) { ignoradas.Text = texto; }

        // Solo para --preview: deja el fondo en el punto más rojo
        public void FaseRoja() { Colores(1); }

        // Solo para --preview
        public void PonerPin(string p) { pin = p; UpdateDots(); }

        public void ShowLocked()
        {
            Bounds = SystemInformation.VirtualScreen;
            Native.OcultarBarraTareas(true);
            Native.BloquearTeclaWindows(true);
            Show();
            Native.KeepOnTop(this);
            Activate();
        }
    }

    // ───────────────────────── Botón flotante ─────────────────────────
    class WidgetForm : Form
    {
        public event Action LockClicked;
        public event Action SyncClicked;
        Label name;
        KeyButton syncBtn;
        Point dragFrom;
        bool dragging;
        float wf = 1f;

        // Proporcional a la pantalla: referencia 1024x768 (la de Satake), con tope para que no
        // quede diminuta ni gigante. La escala de Windows ya está aparte, en Ui.Scale.
        int W(int v) { return (int)Math.Round(v * Ui.Scale * wf); }
        Font Fn(string name, float size) { return new Font(name, size * wf * Ui.Scale * 96f / 72f, GraphicsUnit.Pixel); }

        protected override CreateParams CreateParams
        {
            get
            {
                CreateParams cp = base.CreateParams;
                cp.ExStyle |= 0x08000000 | 0x80; // NOACTIVATE | TOOLWINDOW
                return cp;
            }
        }
        protected override bool ShowWithoutActivation { get { return true; } }

        static string PosFile
        {
            get
            {
                string d = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "CandadoOperario");
                Directory.CreateDirectory(d);
                return Path.Combine(d, "pos.txt");
            }
        }

        public WidgetForm()
        {
            Rectangle pant = CoverForm.Forzar.HasValue ? CoverForm.Forzar.Value : Screen.PrimaryScreen.Bounds;
            wf = Math.Max(0.85f, Math.Min(1.5f, pant.Height / Ui.Scale / 768f));
            FormBorderStyle = FormBorderStyle.None;
            StartPosition = FormStartPosition.Manual;
            TopMost = true;
            ShowInTaskbar = false;
            BackColor = Ui.Texto;
            Size = new Size(W(312), W(44));

            name = new Label();
            name.Font = Fn("Segoe UI Semibold", 10f);
            name.ForeColor = Color.White;
            name.AutoEllipsis = true;
            name.TextAlign = ContentAlignment.MiddleLeft;
            name.SetBounds(W(12), 0, W(136), W(44));
            name.Cursor = Cursors.SizeAll;
            name.MouseDown += delegate(object s, MouseEventArgs e) { dragging = true; dragFrom = Cursor.Position; };
            name.MouseMove += delegate(object s, MouseEventArgs e)
            {
                if (!dragging) return;
                Point p = Cursor.Position;
                Location = new Point(Location.X + p.X - dragFrom.X, Location.Y + p.Y - dragFrom.Y);
                dragFrom = p;
            };
            name.MouseUp += delegate { dragging = false; SavePos(); };
            Controls.Add(name);

            syncBtn = new KeyButton();
            syncBtn.Text = "⟳";
            syncBtn.Font = Fn("Segoe UI Symbol", 11f);
            syncBtn.BackColor = Color.FromArgb(52, 60, 70);
            syncBtn.ForeColor = Color.White;
            syncBtn.FlatAppearance.BorderSize = 0;
            syncBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(72, 82, 94);
            syncBtn.SetBounds(W(152), W(6), W(38), W(32));
            syncBtn.Click += delegate { if (SyncClicked != null) SyncClicked(); };
            Controls.Add(syncBtn);

            KeyButton btn = new KeyButton();
            btn.Text = "Bloquear";
            btn.Font = Fn("Segoe UI Semibold", 10f);
            btn.BackColor = Ui.Marca;
            btn.ForeColor = Color.White;
            btn.FlatAppearance.BorderSize = 0;
            btn.FlatAppearance.MouseOverBackColor = Ui.MarcaHover;
            btn.SetBounds(W(196), W(6), W(108), W(32));
            btn.Click += delegate { if (LockClicked != null) LockClicked(); };
            Controls.Add(btn);

            System.Windows.Forms.Timer keep = new System.Windows.Forms.Timer();
            keep.Interval = 1000;
            keep.Tick += delegate { if (IsDisposed) { keep.Stop(); return; } if (Visible) Native.KeepOnTop(this); };
            keep.Start();

            Rectangle wa = Screen.PrimaryScreen.WorkingArea;
            Location = new Point(wa.Right - Width - W(12), wa.Top + W(8));
            try
            {
                if (File.Exists(PosFile))
                {
                    string[] xy = File.ReadAllText(PosFile).Split(',');
                    Point p = new Point(int.Parse(xy[0]), int.Parse(xy[1]));
                    if (SystemInformation.VirtualScreen.Contains(p)) Location = p;
                }
            }
            catch (Exception) { }
            Location = Encajar(Location, Size, SystemInformation.VirtualScreen);

            FormClosing += delegate(object s, FormClosingEventArgs e)
            {
                if (e.CloseReason == CloseReason.UserClosing) e.Cancel = true;
            };
        }

        // Deja la ventana completa dentro del área visible (posición guardada de otra resolución, etc.)
        public static Point Encajar(Point p, Size tam, Rectangle vs)
        {
            int x = Math.Max(vs.Left, Math.Min(p.X, vs.Right - tam.Width));
            int y = Math.Max(vs.Top, Math.Min(p.Y, vs.Bottom - tam.Height));
            return new Point(x, y);
        }

        void SavePos()
        {
            try { File.WriteAllText(PosFile, Location.X + "," + Location.Y); } catch (Exception) { }
        }

        public void SetName(string n) { name.Text = "●  " + n; }

        // Durante el turno también avisa: el botón flotante pulsa suave hacia rojo
        System.Windows.Forms.Timer parpadeo;
        double angulo;
        public void SetFalla(bool hay)
        {
            if (parpadeo == null)
            {
                parpadeo = new System.Windows.Forms.Timer();
                parpadeo.Interval = 80;
                parpadeo.Tick += delegate
                {
                    angulo += 2 * Math.PI * parpadeo.Interval / Ui.PulsoMs;
                    if (angulo > 2 * Math.PI) angulo -= 2 * Math.PI;
                    BackColor = Ui.Mezcla(Ui.Texto, Ui.Alarma, (1 - Math.Cos(angulo)) / 2);
                };
            }
            if (hay) { if (!parpadeo.Enabled) parpadeo.Start(); }
            else { parpadeo.Stop(); angulo = 0; BackColor = Ui.Texto; }
        }

        public void FlashSync(bool ok)
        {
            syncBtn.Text = ok ? "✓" : "!";
            System.Windows.Forms.Timer t = new System.Windows.Forms.Timer();
            t.Interval = 3000;
            t.Tick += delegate { t.Stop(); t.Dispose(); syncBtn.Text = "⟳"; };
            t.Start();
        }
    }

    // ───────────────────────── Control principal ─────────────────────────
    class Ctl : ApplicationContext
    {
        CoverForm cover;
        WidgetForm widget;
        Rectangle vsActual, prActual;
        System.Windows.Forms.Timer pantalla = new System.Windows.Forms.Timer();
        System.Windows.Forms.Timer idle = new System.Windows.Forms.Timer();
        System.Windows.Forms.Timer sync = new System.Windows.Forms.Timer();
        System.Windows.Forms.Timer poll = new System.Windows.Forms.Timer();
        System.Windows.Forms.Timer fallas = new System.Windows.Forms.Timer();
        System.Windows.Forms.Timer delvis = new System.Windows.Forms.Timer();
        Turno cur;
        int nivel;   // nivel de Delvis del operario en turno
        int syncing;
        int ticks;
        int fails;
        DateTime blockedUntil = DateTime.MinValue;

        public Ctl()
        {
            // Turnos que quedaron abiertos por un reinicio o corte: se cierran ahora.
            lock (Store.Lock)
            {
                foreach (Turno t in Store.Cola) if (t.fin == null) t.fin = Sincro.Now();
            }
            Store.SaveCola();

            CrearUi();
            Application.ApplicationExit += delegate { CerrarTurnoActual(); };

            pantalla.Interval = 2000;
            pantalla.Tick += delegate { RevisarPantalla(); };
            pantalla.Start();

            idle.Interval = 15000;
            idle.Tick += delegate
            {
                if (cur != null && Native.IdleMs() >= (uint)Cfg.AutoMin * 60000u) DoLock();
            };
            idle.Start();

            poll.Interval = 10000;
            poll.Tick += delegate { if (cur != null) Vigia.Sondear(); Store.Estado(cur != null ? "turno" : "bloqueado"); };
            poll.Start();

            // Solo mira si Faults.log cambió (tamaño y fecha); lo lee completo únicamente entonces
            fallas.Interval = 3000;
            fallas.Tick += delegate { if (Fallas.Revisar()) MostrarFallas(); };
            fallas.Start();

            // Ventana de contraseña de Delvis: solo se mira con un operario con nivel en turno
            delvis.Interval = 500;
            delvis.Tick += delegate
            {
                if (cur == null || nivel == 0 || !Cfg.NivelDelvis) return;
                string r = NivelDelvis.Revisar(nivel);
                if (r != null) NivelDelvis.Bitacora(cur.nombre + ": " + r);
            };
            delvis.Start();

            sync.Interval = 60000;
            sync.Tick += delegate
            {
                ticks++;
                SyncAsync(false, ticks % 10 == 0);
            };
            sync.Start();

            cover.SetStatus(StatusText());
            cover.ShowLocked();
            Store.Estado("bloqueado");
            Fallas.Revisar();
            MostrarFallas();
            SyncAsync(false, true);
        }

        void CrearUi()
        {
            cover = new CoverForm();
            widget = new WidgetForm();
            cover.PinSubmitted += OnPin;
            cover.SyncRequested += delegate { SyncAsync(true, true); };
            cover.ExitRequested += delegate { Native.OcultarBarraTareas(false); Native.BloquearTeclaWindows(false); Program.Pausar(60); ExitThread(); };
            widget.LockClicked += DoLock;
            widget.SyncClicked += delegate { SyncAsync(true, true); };
            vsActual = SystemInformation.VirtualScreen;
            prActual = Screen.PrimaryScreen.Bounds;
        }

        // Si cambia la resolución o los monitores, las ventanas se rehacen para la pantalla nueva
        void RevisarPantalla()
        {
            if (SystemInformation.VirtualScreen == vsActual && Screen.PrimaryScreen.Bounds == prActual) return;
            CoverForm viejaC = cover;
            WidgetForm viejaW = widget;
            string nombre = cur != null ? cur.nombre : null;
            CrearUi();
            viejaC.Dispose();
            viejaW.Dispose();
            if (cur == null) { cover.SetStatus(StatusText()); cover.ShowLocked(); }
            else { widget.SetName(nombre + (nivel > 0 ? "  · N" + nivel : "")); widget.Show(); Native.KeepOnTop(widget); }
            MostrarFallas();
        }

        void MostrarFallas()
        {
            string t = Fallas.Texto();
            cover.SetFallas(t);
            cover.SetIgnoradas(Fallas.TextoIgnoradas());
            widget.SetFalla(t.Length > 0);
        }

        string StatusText()
        {
            string s;
            lock (Store.Lock)
            {
                s = Store.Cache.sync == null
                    ? "Aún sin sincronizar"
                    : Store.Cache.usuarios.Count + " operarios · sincronizado " + Store.Cache.sync.Substring(11, 5);
                if (Store.Cola.Count > 0) s += " · " + Store.Cola.Count + " turno(s) por enviar";
            }
            return s;
        }

        void SyncAsync(bool manual, bool pull)
        {
            if (Interlocked.Exchange(ref syncing, 1) == 1)
            {
                if (manual) cover.SetInfo("Sincronización en curso…", false);
                return;
            }
            if (manual) cover.SetInfo("Sincronizando…", false);
            Task.Run(delegate
            {
                string mPush, mPull = "";
                bool okPush = Sincro.Push(out mPush);
                bool okPull = true;
                if (pull) okPull = Sincro.Pull(out mPull);
                Interlocked.Exchange(ref syncing, 0);
                cover.BeginInvoke(new Action(delegate
                {
                    cover.SetStatus(StatusText());
                    bool ok = okPush && okPull;
                    if (manual)
                    {
                        cover.SetInfo(ok ? "Listo. " + (pull ? mPull : mPush) : (!okPull ? mPull : mPush), !ok);
                        widget.FlashSync(ok);
                    }
                    else if (ok) cover.SetInfo("", false);
                }));
            });
        }

        void OnPin(string pin)
        {
            cover.ResetPin();
            if (DateTime.Now < blockedUntil)
            {
                int s = (int)Math.Ceiling((blockedUntil - DateTime.Now).TotalSeconds);
                cover.SetInfo("Demasiados intentos. Espera " + s + " s.", true);
                return;
            }
            Operario op = Sincro.Buscar(pin);
            if (op != null) { fails = 0; Unlock(op); return; }

            if (Sincro.EsEmergencia(pin)) { fails = 0; UnlockEmergencia(); return; }

            bool vacia;
            lock (Store.Lock) { vacia = Store.Cache.usuarios.Count == 0; }
            if (vacia) { cover.SetInfo("No hay operarios cargados. Presiona Sincronizar.", true); return; }

            fails++;
            if (fails >= 5)
            {
                fails = 0;
                blockedUntil = DateTime.Now.AddSeconds(30);
                cover.SetInfo("Demasiados intentos. Espera 30 s.", true);
            }
            else cover.SetInfo("Código incorrecto.", true);
        }

        void Unlock(Operario op)
        {
            Turno t = new Turno();
            t.uid = Guid.NewGuid().ToString();
            t.id_usuario = op.id_usuario;
            t.nombre = op.nombre;
            t.estacion = Cfg.Estacion;
            t.inicio = Sincro.Now();
            lock (Store.Lock) { Store.Cola.Add(t); }
            Store.SaveCola();
            cur = t;
            nivel = op.nivel;
            NivelDelvis.Olvidar();
            Store.Estado("turno");
            Vigia.Iniciar(t);

            cover.SetInfo("", false);
            cover.Hide();
            Native.OcultarBarraTareas(false);
            Native.BloquearTeclaWindows(false);
            widget.SetName(op.nombre + (op.nivel > 0 ? "  · N" + op.nivel : ""));
            widget.Show();
            Native.KeepOnTop(widget);
            SyncAsync(false, false);
        }

        // Seguro por si el candado no deja entrar a nadie (sin lista de operarios, PIN olvidado, falla del servidor).
        // Abre la PC sin turno de operario (no se suben turnos ni eventos de Delvis) y deja aviso al servidor.
        void UnlockEmergencia()
        {
            lock (Store.Lock) { Store.Emergencias.Add(Sincro.Now()); }
            Store.SaveEmergencias();
            Turno t = new Turno();   // solo para que el candado sepa que está abierto; no entra en la cola de turnos
            t.uid = Guid.NewGuid().ToString();
            t.nombre = "EMERGENCIA";
            t.estacion = Cfg.Estacion;
            t.inicio = Sincro.Now();
            cur = t;
            nivel = 0;   // con el código de emergencia no se escribe ningún nivel en Delvis
            Store.Estado("turno");

            cover.SetInfo("", false);
            cover.Hide();
            Native.OcultarBarraTareas(false);
            Native.BloquearTeclaWindows(false);
            widget.SetName("EMERGENCIA");
            widget.Show();
            Native.KeepOnTop(widget);
            SyncAsync(false, false);
        }

        void CerrarTurnoActual()
        {
            if (cur == null) return;
            Vigia.Detener();
            lock (Store.Lock) { cur.fin = Sincro.Now(); }
            Store.SaveCola();
            cur = null;
            nivel = 0;
            NivelDelvis.Olvidar();
        }

        void DoLock()
        {
            CerrarTurnoActual();
            widget.Hide();
            cover.ResetPin();
            cover.SetInfo("", false);
            cover.SetStatus(StatusText());
            cover.ShowLocked();
            Store.Estado("bloqueado");
            SyncAsync(false, false);
        }
    }

    // ───────────────────────── Actualización automática ─────────────────────────
    // Una tarea de Windows (SYSTEM, cada 30 min) corre "CandadoOperario.exe --actualizar":
    //   1. pide al servidor la última versión publicada con firma;
    //   2. comprueba la FIRMA (llave pública incluida aquí) antes de bajar nada;
    //   3. espera a que el candado esté bloqueado: nunca actualiza con un turno abierto;
    //   4. descarga el instalador a una carpeta que solo SYSTEM y los administradores pueden escribir,
    //      comprueba su SHA-256 y arranca la tarea "Instalar", que lo corre aparte de esta.
    // Confiar solo en el servidor (y menos con http://) dejaría que quien lo alterara instalara cualquier
    // programa, con permisos de administrador, en todas las PCs. Con la firma solo se instala lo que firmó
    // el desarrollador con su llave privada, que no está en el servidor.
    static class Actualizador
    {
        // Llave PÚBLICA (firma-publica.xml). La privada vive solo en la PC del desarrollador.
        const string ClavePublica = "<RSAKeyValue><Modulus>uhkmYo7xbEObz8R8T7WmFkWQKgWnweU8MopR3eo+Nw2Go2sW4eAAkI/3Dn/a8uWLbksXVBvR6i04TWsMumErFxicqWqSKrcBd82Fs5Knup4kmYZeFoy2SgQyz3w14WDJKl/M1kKFLCjPDL/bzVWVf+MK2CmWTGvdG0alJdKOC1XIIpWBM/tmoZEyVJS8wf6Fe8arMV/agTTTJJi2oKB27M74P4HIIbw4EwXEdf5E9ilaBRa1LZzT7DOrmlyoB16AgYEl8DC4Ssrp9io0oXzMHYbPg3ib0SMUPuj5kXBRAjAP53IeSVwzAyL6VGdUzKUs9/grcG1r12IF2KnlDeqLIQ==</Modulus><Exponent>AQAB</Exponent></RSAKeyValue>";
        const string TareaInstalar = "\\Honducafe\\Candado de Operario Instalar";
        const string NombreDescarga = "Setup-actual.exe";
        static string dirLog;

        class DescargaClient : WebClient
        {
            protected override WebRequest GetWebRequest(Uri address)
            {
                WebRequest r = base.GetWebRequest(address);
                r.Timeout = 120000;
                return r;
            }
        }

        // El log va en la carpeta protegida: una carpeta donde cualquier usuario pueda escribir
        // permitiría que un enlace simbólico hiciera escribir a SYSTEM en otro archivo.
        static void Log(string m)
        {
            try
            {
                if (dirLog == null) return;
                string p = Path.Combine(dirLog, "actualizador.log");
                if (File.Exists(p) && new FileInfo(p).Length > 200000) File.Delete(p);
                File.AppendAllText(p, DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "  " + m + "\r\n");
            }
            catch (Exception) { }
        }

        static Version Norm(Version v)
        {
            return new Version(v.Major, Math.Max(v.Minor, 0), Math.Max(v.Build, 0), Math.Max(v.Revision, 0));
        }

        public static Version Actual()
        {
            return Norm(Assembly.GetExecutingAssembly().GetName().Version);
        }

        static bool TryVersion(string s, out Version v)
        {
            v = null;
            if (s == null || !Regex.IsMatch(s, @"^\d+(\.\d+){1,3}$")) return false;
            try { v = Norm(new Version(s)); return true; }
            catch (Exception) { return false; }
        }

        static string Txt(Dictionary<string, object> d, string k)
        {
            object o;
            return d.TryGetValue(k, out o) && o != null ? o.ToString() : "";
        }

        // Lo firmado es "CANDADO|versión|sha256": así la versión también queda autenticada
        // y nadie puede hacer pasar un instalador viejo (con fallas conocidas) por uno nuevo.
        public static bool FirmaValida(string version, string sha256, string firmaBase64)
        {
            try
            {
                using (RSACryptoServiceProvider rsa = new RSACryptoServiceProvider())
                {
                    rsa.PersistKeyInCsp = false;
                    rsa.FromXmlString(ClavePublica);
                    byte[] datos = Encoding.UTF8.GetBytes("CANDADO|" + version + "|" + sha256.ToLowerInvariant());
                    return rsa.VerifyData(datos, "SHA256", Convert.FromBase64String(firmaBase64));
                }
            }
            catch (Exception) { return false; }
        }

        static string Sha256(string ruta)
        {
            using (FileStream fs = File.OpenRead(ruta))
            using (SHA256 h = SHA256.Create())
            {
                StringBuilder sb = new StringBuilder();
                foreach (byte b in h.ComputeHash(fs)) sb.Append(b.ToString("x2"));
                return sb.ToString();
            }
        }

        static bool EsSistema()
        {
            try { return WindowsIdentity.GetCurrent().IsSystem; }
            catch (Exception) { return false; }
        }

        // Carpeta de trabajo: el perfil de SYSTEM (nadie más entra). En una prueba sin SYSTEM, una carpeta temporal.
        static string DirTrabajo(bool simular)
        {
            if (simular && !EsSistema()) return Path.Combine(Path.GetTempPath(), "CandadoActualizadorPrueba");
            return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Honducafe", "upd");
        }

        // Solo SYSTEM y administradores, sin heredar permisos: así nadie puede cambiar el instalador
        // entre que se verifica y que se ejecuta.
        static void Proteger(string dir)
        {
            Directory.CreateDirectory(dir);
            DirectorySecurity ds = new DirectorySecurity();
            ds.SetAccessRuleProtection(true, false);
            InheritanceFlags herencia = InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit;
            ds.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null),
                FileSystemRights.FullControl, herencia, PropagationFlags.None, AccessControlType.Allow));
            ds.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null),
                FileSystemRights.FullControl, herencia, PropagationFlags.None, AccessControlType.Allow));
            Directory.SetAccessControl(dir, ds);
        }

        // Es buen momento si el candado no está corriendo, o si avisó hace menos de 2 min que está bloqueado
        static bool BuenMomento()
        {
            int yo = Process.GetCurrentProcess().Id;
            bool corriendo = false;
            foreach (Process p in Process.GetProcessesByName("CandadoOperario"))
                if (p.Id != yo) corriendo = true;
            if (!corriendo) return true;
            try
            {
                string ruta = Path.Combine(Store.DirDatos, "estado.txt");
                if (!File.Exists(ruta)) return false;
                string[] f = File.ReadAllText(ruta).Trim().Split('|');
                long ticks;
                if (f.Length < 2 || f[0] != "bloqueado" || !long.TryParse(f[1], out ticks)) return false;
                return (DateTime.UtcNow - new DateTime(ticks, DateTimeKind.Utc)).TotalSeconds < 120;
            }
            catch (Exception) { return false; }
        }

        // 0 = nada que hacer o todo bien | 1 = error | 2 = firma o archivo no válidos | 3 = sin permisos | 4 = no se pudo iniciar la instalación
        public static int Ejecutar(bool simular)
        {
            bool sistema = EsSistema();
            string dir = DirTrabajo(simular);
            try
            {
                if (sistema) Proteger(dir); else Directory.CreateDirectory(dir);
                dirLog = dir;

                Cfg.Load();
                if (Cfg.Estacion.Length == 0 || Cfg.Token.Length == 0) { Log("Falta Estacion o Token en candado.config."); return 0; }
                if (!sistema && !simular) { Log("Solo se instala desde la tarea del sistema."); return 3; }

                Version actual = Actual();
                Res r = Api.Post(new Dictionary<string, string> { { "accion", "actualizacion" }, { "version", actual.ToString() } });
                if (r.Offline) return 0;                       // sin red: se reintenta en 30 min, sin llenar el log
                if (!r.Ok) { Log("El servidor no entregó la información de versión: " + r.Msg); return 0; }
                if (r.Data == null || !r.Data.ContainsKey("version")) return 0;   // no hay ninguna versión firmada publicada

                string vTxt = Txt(r.Data, "version"), archivo = Txt(r.Data, "archivo"), sha = Txt(r.Data, "sha256"), firma = Txt(r.Data, "firma");
                Version remota;
                if (!TryVersion(vTxt, out remota)) { Log("Versión no válida en la respuesta del servidor."); return 2; }
                if (remota <= actual) return 0;                // al día

                Log("Hay una versión nueva: " + remota + " (instalada: " + actual + ").");
                if (!Regex.IsMatch(archivo, @"^Setup-CandadoOperario-\d+(\.\d+){1,3}\.exe$") || !Regex.IsMatch(sha, "^[0-9a-fA-F]{64}$"))
                { Log("Nombre de archivo o huella no válidos: se ignora."); return 2; }
                if (!FirmaValida(vTxt, sha, firma)) { Log("LA FIRMA NO ES VÁLIDA: se ignora esta actualización."); return 2; }
                if (!BuenMomento()) { Log("Hay un turno abierto: se reintenta más tarde."); return 0; }

                string tmp = Path.Combine(dir, "descarga.tmp");
                string destino = Path.Combine(dir, NombreDescarga);
                if (File.Exists(tmp)) File.Delete(tmp);
                using (DescargaClient wc = new DescargaClient()) wc.DownloadFile(Cfg.Base + "/descargas/" + archivo, tmp);
                if (!string.Equals(Sha256(tmp), sha, StringComparison.OrdinalIgnoreCase))
                {
                    File.Delete(tmp);
                    Log("El archivo descargado no coincide con la huella firmada: se descarta.");
                    return 2;
                }
                if (File.Exists(destino)) File.Delete(destino);
                File.Move(tmp, destino);
                Log("Descargado y verificado: " + archivo);

                if (simular) { Log("(simulación) no se instala."); return 0; }

                // La instalación corre en otra tarea: el instalador cierra CandadoOperario.exe y no debe llevarse a esta
                ProcessStartInfo psi = new ProcessStartInfo("schtasks.exe", "/Run /TN \"" + TareaInstalar + "\"");
                psi.CreateNoWindow = true;
                psi.UseShellExecute = false;
                using (Process p = Process.Start(psi))
                {
                    p.WaitForExit(30000);
                    if (!p.HasExited || p.ExitCode != 0) { Log("No se pudo iniciar la tarea de instalación."); return 4; }
                }
                Log("Instalación iniciada: " + actual + " -> " + remota);
                return 0;
            }
            catch (Exception ex)
            {
                Log("Error: " + ex.Message);
                return 1;
            }
        }
    }

    // ───────────────────────── Arranque ─────────────────────────
    static class Program
    {
        // Prueba sin interfaz: sincroniza, valida PIN, y ejercita la cola con y sin conexión.
        static void SelfTest(string pinReal)
        {
            StringBuilder sb = new StringBuilder();
            Store.Dir = Path.Combine(Path.GetTempPath(), "candado-selftest-data");
            Directory.CreateDirectory(Store.Dir);
            Store.Cache = new CacheData(); Store.Cola = new List<Turno>(); Store.Eventos = new List<Evento>();
            Cfg.Estacion = "SelfTest";
            string m;
            sb.AppendLine("pull: " + Sincro.Pull(out m) + " - " + m);
            Operario ok = Sincro.Buscar(pinReal);
            sb.AppendLine("buscar " + pinReal + ": " + (ok == null ? "NO ENCONTRADO" : ok.nombre));
            sb.AppendLine("buscar 0000: " + (Sincro.Buscar("0000") == null ? "no existe (correcto)" : "ENCONTRADO (mal)"));

            Turno t = new Turno();
            t.uid = Guid.NewGuid().ToString(); t.id_usuario = ok != null ? ok.id_usuario : 2;
            t.estacion = "SelfTest"; t.inicio = Sincro.Now();
            lock (Store.Lock) { Store.Cola.Add(t); }
            string real = Cfg.Servidor;
            Cfg.SetServidor("10.255.255.1");
            sb.AppendLine("push SIN servidor: " + Sincro.Push(out m) + " - " + m + " | en cola: " + Sincro.Pendientes());
            Cfg.SetServidor(real);
            sb.AppendLine("push abierto: " + Sincro.Push(out m) + " - " + m + " | en cola: " + Sincro.Pendientes());
            lock (Store.Lock) { t.fin = Sincro.Now(); }
            sb.AppendLine("push cerrado: " + Sincro.Push(out m) + " - " + m + " | en cola: " + Sincro.Pendientes());

            Point e1 = WidgetForm.Encajar(new Point(1257, 11), new Size(312, 44), new Rectangle(0, 0, 1024, 768));
            Point e2 = WidgetForm.Encajar(new Point(-500, 900), new Size(312, 44), new Rectangle(0, 0, 1024, 768));
            Point e3 = WidgetForm.Encajar(new Point(300, 100), new Size(312, 44), new Rectangle(0, 0, 1024, 768));
            sb.AppendLine("encajar fuera->(" + e1.X + "," + e1.Y + ") esperado (712,11) | -500,900->(" + e2.X + "," + e2.Y + ") esperado (0,724) | dentro->(" + e3.X + "," + e3.Y + ") esperado (300,100)");

            // Bitácoras de Delvis: carpeta simulada
            string tmp = Path.Combine(Path.GetTempPath(), "candado-test-delvis");
            Directory.CreateDirectory(Path.Combine(tmp, "Crashes"));
            string cfgPath = Path.Combine(tmp, "Config.xml");
            string flog = Path.Combine(tmp, "Faults.log");
            File.WriteAllText(cfgPath, "<Root><Vel V=\"10\" /><Arr A=\"1,2\" /></Root>");
            File.WriteAllText(flog, "1/1/2020 8:00:00 AM,FaultSet,2,2,0,\r\n");
            Cfg.DelvisDir = tmp;

            Turno t2 = new Turno();
            t2.uid = Guid.NewGuid().ToString(); t2.id_usuario = t.id_usuario;
            t2.estacion = "SelfTest"; t2.inicio = Sincro.Now();
            lock (Store.Lock) { Store.Cola.Add(t2); }
            Vigia.Iniciar(t2);

            string ahora = DateTime.Now.ToString("M/d/yyyy h:mm:ss tt", new CultureInfo("en-US"));
            File.AppendAllText(flog, ahora + ",FaultSet,3,3,0,\r\n" + ahora + ",FaultClear,3,3,0,\r\n");
            File.WriteAllText(Path.Combine(tmp, "Crashes", "Exception-prueba.txt"), "--------\r\nSystem.Exception: prueba\r\n   en X\r\n");
            File.WriteAllText(cfgPath, "<Root><Vel V=\"25\" /><Arr A=\"1,9\" /></Root>");
            File.SetLastWriteTime(cfgPath, DateTime.Now.AddSeconds(3));
            Vigia.Sondear();
            lock (Store.Lock)
            {
                foreach (Evento ev in Store.Eventos)
                    if (ev.turno_uid == t2.uid)
                        sb.AppendLine("evento: " + ev.tipo + " | " + ev.codigo + " | " + ev.detalle + " | " + ev.anterior + " -> " + ev.nuevo);
            }
            Vigia.Detener();
            lock (Store.Lock) { t2.fin = Sincro.Now(); }
            sb.AppendLine("push turno+eventos: " + Sincro.Push(out m) + " - " + m + " | turnos en cola: " + Sincro.Pendientes() + " | eventos en cola: " + Store.Eventos.Count);

            // Fallas activas: solo cuenta lo abierto desde que arrancó Delvis y sin FaultClear
            CultureInfo us = new CultureInfo("en-US");
            DateTime arranque = DateTime.Now.AddMinutes(-30);
            Func<DateTime, string> f = delegate(DateTime d) { return d.ToString("M/d/yyyy h:mm:ss tt", us); };
            File.WriteAllText(flog,
                "1/1/2020 8:00:00 AM,FaultSet,2,2,0,\r\n" +                                  // vieja: antes del arranque
                f(arranque.AddMinutes(5)) + ",FaultSet,19,35,0,\r\n" +                       // activa
                f(arranque.AddMinutes(6)) + ",FaultSet,0,23,1,,H\r\n" +                      // activa, con contexto
                f(arranque.AddMinutes(7)) + ",FaultSet,24,64,0,\r\n" +
                f(arranque.AddMinutes(8)) + ",FaultClear,24,64,0,\r\n");                     // cerrada
            Fallas.InicioForzado = arranque;
            Fallas.Revisar();
            sb.AppendLine("fallas activas (esperado 19:35 y 0:23:1): " + Fallas.Texto());
            Cfg.FallasIgnorar.Add("19:35");
            File.SetLastWriteTime(flog, DateTime.Now.AddSeconds(5));
            Fallas.Revisar();
            sb.AppendLine("ignorando 19:35 (esperado solo 0:23:1): " + Fallas.Texto());
            Cfg.FallasIgnorar.Add("2:1");
            sb.AppendLine("linea de ignoradas (esperado tipo 2 · tipo 19 (activa)): " + Fallas.TextoIgnoradas());
            File.AppendAllText(flog, f(DateTime.Now) + ",FaultClear,0,23,1,\r\n");
            Fallas.Revisar();
            sb.AppendLine("tras FaultClear (esperado vacío): [" + Fallas.Texto() + "]");
            Fallas.InicioForzado = null;

            // Nivel de Delvis: textos de la ventana, huellas de Config.xml y contraseñas cifradas
            sb.AppendLine("md5('0') (esperado cfcd208495d565ef66e7dff9f98764da): " + NivelDelvis.Md5("0"));
            sb.AppendLine("pide nivel (esperado 2, 3, 0, 0): " + NivelDelvis.NivelPedido("Please enter the level 2 password:") + ", "
                + NivelDelvis.NivelPedido("Teclado | Por favor introduce el nivel de 3 contraseña:") + ", "
                + NivelDelvis.NivelPedido("Enter a new level 2 password:") + ", " + NivelDelvis.NivelPedido("Entrar nueva contraseña del Nivel 2"));
            sb.AppendLine("huella Delvis nivel 1 vacía (esperado cfcd208495d565ef66e7dff9f98764da): " + NivelDelvis.Huella("", 1));
            File.WriteAllText(cfgPath, "<Root>" + Environment.NewLine + "  <_passwordLevel1>" + NivelDelvis.Huella("0", 1) + "</_passwordLevel1>" + Environment.NewLine
                + "  <_passwordLevel2>" + NivelDelvis.Huella("dos2", 2) + "</_passwordLevel2>" + Environment.NewLine
                + "  <_passwordLevel3>" + NivelDelvis.Huella("abc3", 3) + "</_passwordLevel3>" + Environment.NewLine + "</Root>");
            string rutaReal = NivelDelvis.Ruta;
            NivelDelvis.Ruta = Path.Combine(tmp, "delvis.claves");
            NivelDelvis.Guardar(new Dictionary<int, string> { { 1, "0" }, { 2, "vieja" }, { 3, "tres" } });
            Dictionary<int, string> cl = NivelDelvis.Leer(), hu = NivelDelvis.Huellas();
            string porQue;
            Dictionary<int, string> soloTres = new Dictionary<int, string> { { 3, "ABC3" } };
            sb.AppendLine("elegir: pide 1 con la de nivel 3 (esperado abc3 vía nivel 3): " + NivelDelvis.Elegir(1, 3, soloTres, NivelDelvis.Huellas(), out porQue) + " / " + porQue
                + " | operario nivel 2 (esperado nada): [" + NivelDelvis.Elegir(1, 2, soloTres, NivelDelvis.Huellas(), out porQue) + "]");
            sb.AppendLine("claves (esperado ok, distinta, distinta): " + NivelDelvis.Estado(1, cl, hu) + ", " + NivelDelvis.Estado(2, cl, hu) + ", " + NivelDelvis.Estado(3, cl, hu)
                + " | archivo cifrado sin texto claro: " + (Encoding.UTF8.GetString(File.ReadAllBytes(NivelDelvis.Ruta)).Contains("vieja") ? "NO" : "sí"));
            NivelDelvis.Guardar(new Dictionary<int, string>());
            sb.AppendLine("borrar (esperado sin archivo): " + (File.Exists(NivelDelvis.Ruta) ? "queda" : "sin archivo"));
            NivelDelvis.Ruta = rutaReal;

            File.WriteAllText(Path.Combine(Path.GetTempPath(), "candado-selftest.txt"), sb.ToString());
        }

        // Dibuja las pantallas a PNG para revisar el diseño sin tomar la pantalla.
        static void Preview(string dir, bool escala100)
        {
            Directory.CreateDirectory(dir);
            Native.SetProcessDPIAware();
            using (Graphics g = Graphics.FromHwnd(IntPtr.Zero)) Ui.Scale = g.DpiX / 96f;
            if (escala100) Ui.Scale = 1f;
            Application.EnableVisualStyles();
            Cfg.Estacion = "Evolution-Linea1";
            int[][] tamanos = { new int[] { 800, 600 }, new int[] { 1024, 768 }, new int[] { 1280, 1024 }, new int[] { 1366, 768 }, new int[] { 1920, 1080 } };
            foreach (int[] t in tamanos)
            {
                CoverForm.Forzar = new Rectangle(0, 0, t[0], t[1]);
                using (CoverForm c = new CoverForm())
                {
                    c.SetStatus("2 operarios · sincronizado 14:32");
                    c.PonerPin("98");
                    c.Location = new Point(-30000, -30000);
                    c.Show();
                    Application.DoEvents();
                    using (Bitmap bmp = new Bitmap(t[0], t[1]))
                    {
                        c.DrawToBitmap(bmp, new Rectangle(0, 0, t[0], t[1]));
                        bmp.Save(Path.Combine(dir, "cover-" + t[0] + "x" + t[1] + ".png"), System.Drawing.Imaging.ImageFormat.Png);
                    }
                }
            }
            // Con falla activa: las dos fases del parpadeo
            foreach (bool faseRoja in new bool[] { true, false })
            {
                CoverForm.Forzar = new Rectangle(0, 0, 1024, 768);
                using (CoverForm c = new CoverForm())
                {
                    c.SetStatus("2 operarios · sincronizado 14:32");
                    c.SetFallas("Falla 24:64 (desde 14:03)");
                    c.SetIgnoradas("Fallas ignoradas: tipo 0 · tipo 19 (activa)");
                    if (faseRoja) c.FaseRoja();
                    c.Location = new Point(-30000, -30000);
                    c.Show();
                    Application.DoEvents();
                    using (Bitmap bmp = new Bitmap(1024, 768))
                    {
                        c.DrawToBitmap(bmp, new Rectangle(0, 0, 1024, 768));
                        bmp.Save(Path.Combine(dir, "cover-falla-" + (faseRoja ? "rojo" : "blanco") + ".png"), System.Drawing.Imaging.ImageFormat.Png);
                    }
                }
            }
            foreach (int[] t in tamanos)
            {
                CoverForm.Forzar = new Rectangle(0, 0, t[0], t[1]);
                using (WidgetForm w = new WidgetForm())
                {
                    w.SetName("Douglas Palma");
                    w.Location = new System.Drawing.Point(-30000, -30000);
                    w.Show();
                    Application.DoEvents();
                    using (Bitmap bmp = new Bitmap(w.Width, w.Height))
                    {
                        w.DrawToBitmap(bmp, new Rectangle(0, 0, w.Width, w.Height));
                        bmp.Save(Path.Combine(dir, "widget-" + t[0] + "x" + t[1] + ".png"), System.Drawing.Imaging.ImageFormat.Png);
                    }
                }
            }
        }

        // Reapertura automática: si falla, deja el error en errores.log y se vuelve a abrir.
        // null = no reabrir (pruebas). El vigilante (tarea de Windows) cubre el resto.
        public static string ArgsReinicio = "";

        static string RutaPausa { get { return Path.Combine(Store.DirDatos, "pausa.txt"); } }

        // Salida a propósito (modo prueba): el vigilante no lo reabre durante ese tiempo
        public static void Pausar(int minutos)
        {
            try
            {
                Directory.CreateDirectory(Store.DirDatos);
                File.WriteAllText(RutaPausa, DateTime.Now.AddMinutes(minutos).ToString("o"));
            }
            catch (Exception) { }
        }

        static bool EnPausa()
        {
            try
            {
                if (!File.Exists(RutaPausa)) return false;
                DateTime h;
                return DateTime.TryParse(File.ReadAllText(RutaPausa), null, DateTimeStyles.RoundtripKind, out h) && DateTime.Now < h;
            }
            catch (Exception) { return false; }
        }

        static void BorrarPausa()
        {
            try { if (File.Exists(RutaPausa)) File.Delete(RutaPausa); } catch (Exception) { }
        }

        static void Fallo(Exception ex)
        {
            // Si el programa se cae estando bloqueado, que al menos quede la barra de
            // tareas disponible y la tecla Windows libre, en vez de perderlas hasta que
            // el vigilante lo reabra.
            Native.OcultarBarraTareas(false);
            Native.BloquearTeclaWindows(false);
            try
            {
                Directory.CreateDirectory(Store.DirDatos);
                string log = Path.Combine(Store.DirDatos, "errores.log");
                if (File.Exists(log) && new FileInfo(log).Length > 500000) File.Delete(log);
                File.AppendAllText(log, DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "  " +
                                        (ex != null ? ex.ToString() : "error desconocido") + "\r\n\r\n");
            }
            catch (Exception) { }

            // No reabre si ya había caído hace menos de 30 s (evita un ciclo de fallas); ahí lo hará el vigilante.
            bool reabrir = ArgsReinicio != null;
            try
            {
                string marca = Path.Combine(Store.DirDatos, "ultimo_fallo.txt");
                DateTime u;
                if (File.Exists(marca) && DateTime.TryParse(File.ReadAllText(marca), null, DateTimeStyles.RoundtripKind, out u)
                    && (DateTime.Now - u).TotalSeconds < 30) reabrir = false;
                File.WriteAllText(marca, DateTime.Now.ToString("o"));
            }
            catch (Exception) { }

            if (reabrir)
            {
                try
                {
                    ProcessStartInfo psi = new ProcessStartInfo("cmd.exe",
                        "/c ping -n 4 127.0.0.1 >nul & start \"\" \"" + Application.ExecutablePath + "\" " + ArgsReinicio);
                    psi.CreateNoWindow = true;
                    psi.UseShellExecute = false;
                    Process.Start(psi);
                }
                catch (Exception) { }
            }
            Environment.Exit(1);
        }

        // El instalador registra una tarea de Windows que abre el programa cada 5 minutos con --vigilante.
        // Si ya hay una copia corriendo, esta sale enseguida sin leer nada.
        [STAThread]
        static void Main(string[] args)
        {
            // Por si el servidor se configura con https://: en .NET 4.0 el valor por
            // defecto no siempre incluye TLS 1.2. 3072 = SecurityProtocolType.Tls12
            // (numérico porque ese miembro del enum no existe en este framework).
            try { ServicePointManager.SecurityProtocol |= (SecurityProtocolType)3072; } catch (Exception) { }

            string modo = args.Length > 0 ? args[0] : "";

            if (modo == "--preview" || modo == "--selftest")
            {
                Cfg.Load();
                Store.Init();
                if (modo == "--preview" && args.Length > 1) Preview(args[1], args.Length > 2 && args[2] == "100");
                else if (modo == "--selftest") SelfTest(args.Length > 1 ? args[1] : "1234");
                return;
            }

            if (modo == "--demo-falla")
            {
                // Muestra 15 s la pantalla con el aviso en rojo, aunque no haya falla, y dice qué fallas
                // reales ve ahora en Delvis. No toca el candado que esté corriendo ni los archivos de Delvis.
                Cfg.Load();
                Native.SetProcessDPIAware();
                using (Graphics g = Graphics.FromHwnd(IntPtr.Zero)) Ui.Scale = g.DpiX / 96f;
                Application.EnableVisualStyles();
                Fallas.Revisar();
                bool delvis = Process.GetProcessesByName("Delvis").Length > 0;
                CoverForm c = new CoverForm();
                c.SetStatus("Demostración: se cierra sola en 15 s");
                c.SetFallas("PRUEBA (desde " + DateTime.Now.ToString("HH:mm") + ")");
                c.SetInfo(!delvis ? "Delvis no está abierto: sin él no hay aviso real."
                    : !Directory.Exists(Cfg.DelvisDir) ? "No existe la carpeta de Delvis: " + Cfg.DelvisDir
                    : Fallas.Activas.Count == 0 ? "Delvis abierto. Fallas activas ahora: ninguna."
                    : "Fallas activas ahora: " + Fallas.Texto(), delvis && Directory.Exists(Cfg.DelvisDir) ? Fallas.Activas.Count > 0 : true);
                System.Windows.Forms.Timer fin = new System.Windows.Forms.Timer();
                fin.Interval = 15000;
                fin.Tick += delegate { fin.Stop(); Application.ExitThread(); };
                fin.Start();
                c.Show();
                Application.Run();
                Native.OcultarBarraTareas(false);   // la pantalla la oculta mientras está visible
                return;
            }

            if (modo == "--claves-delvis")
            {
                // Carga las contraseñas de nivel de Delvis (cifradas, solo para esta PC). Pide administrador para guardar.
                Cfg.Load();
                Application.EnableVisualStyles();
                Application.Run(new ClavesForm());
                return;
            }

            if (modo == "--ver-delvis")
            {
                // Diagnóstico: espera N s (para abrir la ventana de contraseña en Delvis) y deja en un .txt lo que ve.
                // No escribe nada en Delvis.
                Cfg.Load();
                Store.Init();   // para leer las contraseñas que llegaron del sistema
                int seg;
                if (args.Length < 2 || !int.TryParse(args[1], out seg)) seg = 15;
                Thread.Sleep(seg * 1000);
                string txt = Path.Combine(Path.GetTempPath(), "candado-ver-delvis.txt");
                File.WriteAllText(txt, NivelDelvis.Diagnostico(), Encoding.UTF8);
                try { Process.Start("notepad.exe", "\"" + txt + "\""); } catch (Exception) { }
                return;
            }

            if (modo == "--actualizar")
            {
                // Lo corre la tarea de Windows (SYSTEM): sin ventanas y sin tomar el candado de la PC.
                // "--actualizar --simular" hace todo menos instalar (para probar).
                Environment.Exit(Actualizador.Ejecutar(args.Length > 1 && args[1] == "--simular"));
                return;
            }

            bool nuevo;
            using (Mutex m = new Mutex(true, "Honducafe.CandadoOperario", out nuevo))
            {
                if (!nuevo) return;

                bool vigilante = modo == "--vigilante";
                if (vigilante && EnPausa()) return;
                if (!vigilante) BorrarPausa();

                Application.SetUnhandledExceptionMode(UnhandledExceptionMode.CatchException);
                Application.ThreadException += delegate(object s, ThreadExceptionEventArgs e) { Fallo(e.Exception); };
                AppDomain.CurrentDomain.UnhandledException += delegate(object s, UnhandledExceptionEventArgs e) { Fallo(e.ExceptionObject as Exception); };

                Cfg.Load();
                Store.Init();

                // Prueba de la captura de errores: provoca una falla a propósito (no abre el candado)
                if (modo == "--crashtest")
                {
                    ArgsReinicio = args.Length > 1 ? args[1] : null;
                    System.Windows.Forms.Timer t = new System.Windows.Forms.Timer();
                    t.Interval = 800;
                    t.Tick += delegate { throw new InvalidOperationException("falla de prueba"); };
                    t.Start();
                    Application.Run(new ApplicationContext());
                    return;
                }

                Native.SetProcessDPIAware();
                using (Graphics g = Graphics.FromHwnd(IntPtr.Zero)) Ui.Scale = g.DpiX / 96f;
                Application.EnableVisualStyles();
                if (Cfg.Estacion.Length == 0)
                {
                    MessageBox.Show("Falta definir Estacion en candado.config.", "Candado de operario");
                    return;
                }
                Application.Run(new Ctl());
            }
        }
    }
}
