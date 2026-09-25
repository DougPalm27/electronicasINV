using System;
using System.Collections;
using System.Collections.Generic;
using System.Drawing;
using System.Globalization;
using System.IO;
using System.Net;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
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
[assembly: AssemblyVersion("1.1.1.0")]
[assembly: AssemblyFileVersion("1.1.1.0")]

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
        public static string Url;

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
                    }
                }
            }
            if (AutoMin < 1) AutoMin = 15;
            SetServidor(Servidor);
        }

        public static void SetServidor(string s)
        {
            Servidor = s;
            Url = "http://" + s + "/electronicasINV/modules/Turnos/controllers/kioscoController.php";
        }
    }

    // ───────────────────────── Datos locales ─────────────────────────
    class Operario
    {
        public int id_usuario;
        public string nombre;
        public string salt;
        public string hash;
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
    }

    static class Store
    {
        public static readonly object Lock = new object();
        public static string Dir;
        public static CacheData Cache = new CacheData();
        public static List<Turno> Cola = new List<Turno>();
        public static List<Evento> Eventos = new List<Evento>();
        static JavaScriptSerializer js = new JavaScriptSerializer();

        public static void Init()
        {
            Dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "CandadoOperario");
            Directory.CreateDirectory(Dir);
            Cache = Read<CacheData>("cache.json") ?? new CacheData();
            Cola = Read<List<Turno>>("cola.json") ?? new List<Turno>();
            Eventos = Read<List<Evento>>("eventos.json") ?? new List<Evento>();
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

        public static void SaveCache() { lock (Lock) { Write("cache.json", Cache); } }
        public static void SaveCola() { lock (Lock) { Write("cola.json", Cola); } }
        public static void SaveEventos() { lock (Lock) { Write("eventos.json", Eventos); } }
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
                lista.Add(op);
            }
            lock (Store.Lock)
            {
                Store.Cache.usuarios = lista;
                Store.Cache.sync = Now();
            }
            Store.SaveCache();
            msg = lista.Count + (lista.Count == 1 ? " operario sincronizado." : " operarios sincronizados.");
            return true;
        }

        // Sube al servidor los turnos pendientes (abiertos aún no enviados y cerrados).
        public static bool Push(out string msg)
        {
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
    static class Native
    {
        [DllImport("user32.dll")] public static extern bool SetProcessDPIAware();
        [DllImport("user32.dll")] static extern bool SetWindowPos(IntPtr h, IntPtr after, int x, int y, int cx, int cy, uint flags);
        [DllImport("user32.dll")] static extern bool GetLastInputInfo(ref LASTINPUTINFO p);

        [StructLayout(LayoutKind.Sequential)]
        struct LASTINPUTINFO { public uint cbSize; public uint dwTime; }

        static readonly IntPtr HWND_TOPMOST = new IntPtr(-1);
        const uint SWP_NOSIZE = 0x1, SWP_NOMOVE = 0x2, SWP_NOACTIVATE = 0x10;

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
        Label dots, err, status;
        System.Windows.Forms.Timer keep;
        bool busy;
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

            Label est = new Label();
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

            Label sub = new Label();
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

            keep = new System.Windows.Forms.Timer();
            keep.Interval = 500;
            keep.Tick += delegate
            {
                if (IsDisposed) { keep.Stop(); return; }
                if (!Visible) return;
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

        // Solo para --preview
        public void PonerPin(string p) { pin = p; UpdateDots(); }

        public void ShowLocked()
        {
            Bounds = SystemInformation.VirtualScreen;
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
        Turno cur;
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
            poll.Tick += delegate { if (cur != null) Vigia.Sondear(); };
            poll.Start();

            sync.Interval = 60000;
            sync.Tick += delegate
            {
                ticks++;
                SyncAsync(false, ticks % 10 == 0);
            };
            sync.Start();

            cover.SetStatus(StatusText());
            cover.ShowLocked();
            SyncAsync(false, true);
        }

        void CrearUi()
        {
            cover = new CoverForm();
            widget = new WidgetForm();
            cover.PinSubmitted += OnPin;
            cover.SyncRequested += delegate { SyncAsync(true, true); };
            cover.ExitRequested += delegate { ExitThread(); };
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
            else { widget.SetName(nombre); widget.Show(); Native.KeepOnTop(widget); }
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
            Vigia.Iniciar(t);

            cover.SetInfo("", false);
            cover.Hide();
            widget.SetName(op.nombre);
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
        }

        void DoLock()
        {
            CerrarTurnoActual();
            widget.Hide();
            cover.ResetPin();
            cover.SetInfo("", false);
            cover.SetStatus(StatusText());
            cover.ShowLocked();
            SyncAsync(false, false);
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

        [STAThread]
        static void Main(string[] args)
        {
            Cfg.Load();
            Store.Init();
            if (args.Length > 1 && args[0] == "--preview") { Preview(args[1], args.Length > 2 && args[2] == "100"); return; }
            if (args.Length > 0 && args[0] == "--selftest") { SelfTest(args.Length > 1 ? args[1] : "1234"); return; }

            bool nuevo;
            using (Mutex m = new Mutex(true, "Honducafe.CandadoOperario", out nuevo))
            {
                if (!nuevo) return;
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
