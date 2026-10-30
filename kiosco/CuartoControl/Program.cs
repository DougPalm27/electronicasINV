// Cuarto de control: lista de las máquinas de cada planta, si responden en la red y conexión con doble clic.
// Lee y guarda las mismas conexiones que el visor 3D (salida\remoto.json de cada planta). Sin contraseñas:
// las pide TightVNC / Escritorio remoto al conectar.
// También arranca y muestra el puente de los candados (Planos\utilidades\servidor.py): versión, turno, fallas de
// Delvis y turnos por subir de cada máquina, y los reportes de diagnóstico que mandan.
//
// Compilar: compilar.bat (usa el compilador de .NET 4 que trae Windows).
// Prueba sin ventana: CuartoControl.exe --prueba   (estado de todas las máquinas en la consola y en %TEMP%\CuartoControl-prueba.txt)
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Reflection;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Web.Script.Serialization;
using System.Windows.Forms;

[assembly: AssemblyTitle("Cuarto de control")]
[assembly: AssemblyProduct("Cuarto de control - Honducafe")]
[assembly: AssemblyVersion("1.4.6.0")]
[assembly: AssemblyFileVersion("1.4.6.0")]

namespace CuartoControl
{
    // ───────────────────────── Configuración ─────────────────────────
    static class Cfg
    {
        public static string Carpeta = "";        // carpeta Planos (la que tiene salida\remoto.json)
        public static string VisorVnc = "";       // tvnviewer.exe; vacío = buscarlo
        public static int Intervalo = 30;         // segundos entre revisiones automáticas
        public static int Espera = 1500;          // ms máximos para decidir si una máquina responde
        public static string Puente = "http://127.0.0.1:8000";   // puente de los candados (utilidades\servidor.py)
        public static string Python = "";         // pythonw.exe para arrancar el puente; vacío = el del PATH

        public static string Archivo { get { return Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "CuartoControl.config"); } }

        public static void Cargar()
        {
            try
            {
                if (File.Exists(Archivo))
                    foreach (string l in File.ReadAllLines(Archivo, Encoding.UTF8))
                    {
                        int i = l.IndexOf('=');
                        if (i <= 0 || l.TrimStart().StartsWith("#")) continue;
                        string k = l.Substring(0, i).Trim().ToLowerInvariant(), v = l.Substring(i + 1).Trim();
                        int n;
                        switch (k)
                        {
                            case "carpeta": Carpeta = v; break;
                            case "visorvnc": VisorVnc = v; break;
                            case "intervalo": if (int.TryParse(v, out n) && n >= 5) Intervalo = n; break;
                            case "espera": if (int.TryParse(v, out n) && n >= 200) Espera = n; break;
                            case "puente": if (Regex.IsMatch(v, @"^https?://[^\s/]+$")) Puente = v; break;
                            case "python": Python = v; break;
                        }
                    }
            }
            catch (Exception) { }
            if (Carpeta == "" || !Directory.Exists(Carpeta)) Carpeta = BuscarCarpeta();
        }

        // Sube desde la carpeta del programa hasta encontrar salida\remoto.json; si no, prueba la carpeta habitual de XAMPP.
        // Instalado en Archivos de programa, la carpeta la escribe el instalador en CuartoControl.config.
        static string BuscarCarpeta()
        {
            string d = AppDomain.CurrentDomain.BaseDirectory;
            for (int i = 0; i < 4 && d != null; i++)
            {
                if (File.Exists(Path.Combine(d, "salida", "remoto.json"))) return d;
                DirectoryInfo p = Directory.GetParent(d.TrimEnd('\\'));
                d = p == null ? null : p.FullName;
            }
            const string xampp = @"C:\xampp\htdocs\Planos";
            return File.Exists(Path.Combine(xampp, "salida", "remoto.json")) ? xampp : "";
        }

        public static string Tvnviewer()
        {
            if (VisorVnc != "" && File.Exists(VisorVnc)) return VisorVnc;
            foreach (string pf in new[] { Environment.GetEnvironmentVariable("ProgramFiles"), Environment.GetEnvironmentVariable("ProgramFiles(x86)"), Environment.GetEnvironmentVariable("ProgramW6432") })
            {
                if (string.IsNullOrEmpty(pf)) continue;
                string f = Path.Combine(pf, "TightVNC", "tvnviewer.exe");
                if (File.Exists(f)) return f;
            }
            return null;
        }
    }

    // ───────────────────────── Datos ─────────────────────────
    class Planta
    {
        public string Nombre;
        public string Remoto;    // ruta del remoto.json
        public string Layout;    // datos del plano (posiciones de las máquinas) para el mapa
    }

    class Maquina
    {
        public Planta Planta;
        public string Id;
        public string Protocolo = "vnc";
        public string Host = "";
        public string Puerto = "";
        public string Url = "";
        public string Nota = "";
        public string Estado = "";     // "", "ok" (responde el VNC/RDP), "ping" (solo ping), "mal", "revisando"
        public DateTime Revisado;

        public bool Configurada { get { return Protocolo == "url" ? Url != "" : Host != ""; } }

        public int PuertoEfectivo
        {
            get
            {
                int p;
                if (int.TryParse(Puerto, out p) && p >= 1 && p <= 65535) return p;
                return Protocolo == "rdp" ? 3389 : 5900;
            }
        }

        // Equipo y puerto a revisar en la red (para una página web, los de su dirección)
        public bool Destino(out string host, out int puerto)
        {
            host = null; puerto = 0;
            if (Protocolo == "url")
            {
                Uri u;
                if (!Uri.TryCreate(Url, UriKind.Absolute, out u) || (u.Scheme != "http" && u.Scheme != "https")) return false;
                host = u.Host; puerto = u.Port;
                return true;
            }
            if (!Datos.HostValido(Host)) return false;
            host = Host; puerto = PuertoEfectivo;
            return true;
        }
    }

    static class Datos
    {
        static readonly Regex ReHost = new Regex(@"^[A-Za-z0-9][A-Za-z0-9.\-]{0,99}$");
        public static bool HostValido(string h) { return h != null && ReHost.IsMatch(h); }

        public static List<Planta> Plantas()
        {
            return new List<Planta>
            {
                new Planta { Nombre = "Planta 1", Remoto = Path.Combine(Cfg.Carpeta, "salida", "planta1", "remoto.json"), Layout = Path.Combine(Cfg.Carpeta, "layout_planta1.json") },
                new Planta { Nombre = "Planta 2", Remoto = Path.Combine(Cfg.Carpeta, "salida", "remoto.json"), Layout = Path.Combine(Cfg.Carpeta, "layout_mm.json") },
            };
        }

        static Dictionary<string, object> Leer(string ruta)
        {
            if (!File.Exists(ruta)) return new Dictionary<string, object>();
            var o = new JavaScriptSerializer().DeserializeObject(File.ReadAllText(ruta, Encoding.UTF8)) as Dictionary<string, object>;
            return o ?? new Dictionary<string, object>();
        }

        static string Txt(Dictionary<string, object> d, string k)
        {
            object v;
            return d.TryGetValue(k, out v) && v != null ? Convert.ToString(v).Trim() : "";
        }

        public static List<Maquina> Maquinas(Planta p)
        {
            var lista = new List<Maquina>();
            var raiz = Leer(p.Remoto);
            object mq;
            if (!raiz.TryGetValue("maquinas", out mq) || !(mq is Dictionary<string, object>)) return lista;
            foreach (var kv in (Dictionary<string, object>)mq)
            {
                var d = kv.Value as Dictionary<string, object> ?? new Dictionary<string, object>();
                string pr = Txt(d, "protocolo").ToLowerInvariant();
                lista.Add(new Maquina
                {
                    Planta = p, Id = kv.Key,
                    Protocolo = pr == "rdp" || pr == "url" ? pr : "vnc",
                    Host = Txt(d, "host"), Puerto = Txt(d, "puerto"), Url = Txt(d, "url"), Nota = Txt(d, "nota"),
                });
            }
            return lista;
        }

        // Guarda UNA máquina: vuelve a leer el archivo y solo cambia esa entrada (respeta lo demás, también lo del visor)
        public static void Guardar(Maquina m)
        {
            var raiz = Leer(m.Planta.Remoto);
            object mq;
            Dictionary<string, object> maquinas;
            if (raiz.TryGetValue("maquinas", out mq) && mq is Dictionary<string, object>) maquinas = (Dictionary<string, object>)mq;
            else { maquinas = new Dictionary<string, object>(); raiz["maquinas"] = maquinas; }
            object previo;
            var d = maquinas.TryGetValue(m.Id, out previo) && previo is Dictionary<string, object> ? (Dictionary<string, object>)previo : new Dictionary<string, object>();
            d["protocolo"] = m.Protocolo;
            d["url"] = m.Url;
            d["host"] = m.Host;
            d["puerto"] = m.Puerto;
            d["nota"] = m.Nota;
            maquinas[m.Id] = d;

            string tmp = m.Planta.Remoto + ".tmp";
            File.WriteAllText(tmp, Json(raiz, 0) + "\n", new UTF8Encoding(false));
            if (File.Exists(m.Planta.Remoto)) File.Replace(tmp, m.Planta.Remoto, null); else File.Move(tmp, m.Planta.Remoto);
        }

        // JSON con sangría de 1 espacio (igual al que genera make_3d.py), para que se pueda leer y editar a mano
        static string Json(object o, int nivel)
        {
            var js = new JavaScriptSerializer();
            var dic = o as Dictionary<string, object>;
            if (dic == null) return js.Serialize(o);
            if (dic.Count == 0) return "{}";
            string sang = new string(' ', nivel + 1);
            var partes = dic.Select(kv => sang + js.Serialize(kv.Key) + ": " + Json(kv.Value, nivel + 1));
            return "{\n" + string.Join(",\n", partes) + "\n" + new string(' ', nivel) + "}";
        }
    }

    // ───────────────────────── Red y conexión ─────────────────────────
    static class Red
    {
        public static bool Responde(string host, int puerto, int espera)
        {
            try
            {
                using (var c = new TcpClient())
                {
                    IAsyncResult r = c.BeginConnect(host, puerto, null, null);
                    if (!r.AsyncWaitHandle.WaitOne(espera)) return false;
                    c.EndConnect(r);
                    return true;
                }
            }
            catch (Exception) { return false; }
        }

        public static bool Ping(string host, int espera)
        {
            try { using (var p = new System.Net.NetworkInformation.Ping()) return p.Send(host, espera).Status == IPStatus.Success; }
            catch (Exception) { return false; }
        }

        // Devuelve null si abrió; si no, el motivo
        public static string Conectar(Maquina m)
        {
            if (!m.Configurada) return "La máquina " + m.Id + " no tiene conexión configurada. Pulsa «Configurar».";
            try
            {
                if (m.Protocolo == "url")
                {
                    Uri u;
                    if (!Uri.TryCreate(m.Url, UriKind.Absolute, out u) || (u.Scheme != "http" && u.Scheme != "https"))
                        return "La dirección web debe empezar con http:// o https://";
                    Process.Start(u.AbsoluteUri);
                    return null;
                }
                if (!Datos.HostValido(m.Host)) return "El equipo de " + m.Id + " no es válido: " + m.Host;
                if (m.Protocolo == "rdp")
                {
                    Process.Start("mstsc.exe", "/v:" + m.Host + ":" + m.PuertoEfectivo);
                    return null;
                }
                string visor = Cfg.Tvnviewer();
                if (visor == null) return "No encontré TightVNC Viewer (tvnviewer.exe). Instálalo, o escribe su ruta en CuartoControl.config (VisorVnc=...).";
                Process.Start(visor, m.Host + "::" + m.PuertoEfectivo);   // formato de TightVNC: equipo::puerto
                return null;
            }
            catch (Exception ex) { return "No se pudo abrir la conexión: " + ex.Message; }
        }
    }

    // ───────────────────────── Ventana de configuración ─────────────────────────
    class FormConfig : Form
    {
        readonly Maquina m;
        readonly ComboBox cmbProto = new ComboBox { DropDownStyle = ComboBoxStyle.DropDownList, Width = 260 };
        readonly TextBox txtHost = new TextBox { Width = 260, MaxLength = 100 };
        readonly TextBox txtPuerto = new TextBox { Width = 80, MaxLength = 5 };
        readonly TextBox txtUrl = new TextBox { Width = 260, MaxLength = 300 };
        readonly TextBox txtNota = new TextBox { Width = 260, MaxLength = 200 };
        readonly Label lblHost = new Label { Text = "IP o nombre de la PC", AutoSize = true };
        readonly Label lblPuerto = new Label { Text = "Puerto (vacío = el normal)", AutoSize = true };
        readonly Label lblUrl = new Label { Text = "Dirección web", AutoSize = true };
        static readonly string[] Protos = { "vnc", "rdp", "url" };

        public FormConfig(Maquina maq)
        {
            m = maq;
            Text = "Conexión de " + m.Id + " (" + m.Planta.Nombre + ")";
            FormBorderStyle = FormBorderStyle.FixedDialog;
            MaximizeBox = MinimizeBox = false;
            StartPosition = FormStartPosition.CenterParent;
            Font = new Font("Segoe UI", 10f);
            AutoSize = true; AutoSizeMode = AutoSizeMode.GrowAndShrink;
            Padding = new Padding(14);

            cmbProto.Items.AddRange(new object[] { "VNC (TightVNC)", "Escritorio remoto (RDP)", "Página web" });
            cmbProto.SelectedIndex = Math.Max(0, Array.IndexOf(Protos, m.Protocolo));
            txtHost.Text = m.Host; txtPuerto.Text = m.Puerto; txtUrl.Text = m.Url; txtNota.Text = m.Nota;

            var t = new TableLayoutPanel { ColumnCount = 1, AutoSize = true, Dock = DockStyle.Fill };
            t.Controls.Add(new Label { Text = "Tipo de conexión", AutoSize = true });
            t.Controls.Add(cmbProto);
            t.Controls.Add(lblHost); t.Controls.Add(txtHost);
            t.Controls.Add(lblPuerto); t.Controls.Add(txtPuerto);
            t.Controls.Add(lblUrl); t.Controls.Add(txtUrl);
            t.Controls.Add(new Label { Text = "Nota (opcional)", AutoSize = true });
            t.Controls.Add(txtNota);
            t.Controls.Add(new Label { Text = "La contraseña no se guarda aquí: la pide el programa al conectar.\nDeja el equipo vacío para quitar la conexión.", AutoSize = true, ForeColor = SystemColors.GrayText, Margin = new Padding(3, 10, 3, 3) });
            var botones = new FlowLayoutPanel { FlowDirection = FlowDirection.RightToLeft, AutoSize = true, Dock = DockStyle.Fill };
            var ok = new Button { Text = "Guardar", AutoSize = true };
            var cancelar = new Button { Text = "Cancelar", AutoSize = true, DialogResult = DialogResult.Cancel };
            ok.Click += Guardar;
            botones.Controls.Add(ok); botones.Controls.Add(cancelar);
            t.Controls.Add(botones);
            Controls.Add(t);
            AcceptButton = ok; CancelButton = cancelar;

            cmbProto.SelectedIndexChanged += (s, e) => Campos();
            Campos();
        }

        void Campos()
        {
            bool url = cmbProto.SelectedIndex == 2;
            lblHost.Visible = txtHost.Visible = lblPuerto.Visible = txtPuerto.Visible = !url;
            lblUrl.Visible = txtUrl.Visible = url;
        }

        void Guardar(object s, EventArgs e)
        {
            string pr = Protos[cmbProto.SelectedIndex];
            string host = txtHost.Text.Trim(), puerto = txtPuerto.Text.Trim(), url = txtUrl.Text.Trim();
            int p;
            if (pr != "url")
            {
                if (host != "" && !Datos.HostValido(host)) { Error("Escribe una IP (ej. 10.12.134.177) o un nombre de PC: letras, números, punto y guion, sin espacios."); return; }
                if (puerto != "" && !(int.TryParse(puerto, out p) && p >= 1 && p <= 65535)) { Error("El puerto debe ser un número entre 1 y 65535."); return; }
                url = "";
            }
            else
            {
                if (!Regex.IsMatch(url, @"^https?://\S{1,300}$", RegexOptions.IgnoreCase)) { Error("La dirección debe empezar con http:// o https://"); return; }
                host = ""; puerto = "";
            }
            m.Protocolo = pr; m.Host = host; m.Puerto = puerto; m.Url = url;
            m.Nota = Regex.Replace(txtNota.Text.Trim(), @"[\r\n\t]+", " ");
            try { Datos.Guardar(m); }
            catch (Exception ex) { Error("No se pudo guardar en " + m.Planta.Remoto + ":\n" + ex.Message); return; }
            DialogResult = DialogResult.OK;
        }

        void Error(string t) { MessageBox.Show(this, t, Text, MessageBoxButtons.OK, MessageBoxIcon.Warning); }
    }

    // ───────────────────────── Puente (utilidades/servidor.py) ─────────────────────────
    // El puente corre en esta PC (Python): recibe a los candados de las máquinas y guarda el estado de cada una.
    // La app lo arranca si no está corriendo y lee su estado de http://127.0.0.1:8000/api/estado.
    static class Puente
    {
        class Cliente : WebClient
        {
            protected override WebRequest GetWebRequest(Uri u) { WebRequest r = base.GetWebRequest(u); r.Timeout = 3000; return r; }
        }

        public static string Carpeta { get { return Path.Combine(Cfg.Carpeta, "utilidades"); } }

        // null si no responde
        public static Dictionary<string, object> Estado()
        {
            try
            {
                using (var c = new Cliente { Encoding = Encoding.UTF8 })
                    return new JavaScriptSerializer { MaxJsonLength = 8 * 1024 * 1024 }.DeserializeObject(c.DownloadString(Cfg.Puente + "/api/estado")) as Dictionary<string, object>;
            }
            catch (Exception) { return null; }
        }

        public static string Reporte(string url)
        {
            using (var c = new Cliente { Encoding = Encoding.UTF8 }) return c.DownloadString(Cfg.Puente + url);
        }

        // Devuelve null si lo arrancó; si no, el motivo
        public static string Iniciar()
        {
            string srv = Path.Combine(Carpeta, "servidor.py");
            if (!File.Exists(srv)) return "No encontré " + srv;
            string py = Cfg.Python != "" ? Cfg.Python : "pythonw.exe";
            try
            {
                var psi = new ProcessStartInfo(py, "\"" + srv + "\"") { WorkingDirectory = Carpeta, UseShellExecute = false, CreateNoWindow = true };
                Process.Start(psi);
                return null;
            }
            catch (Exception ex) { return "No se pudo arrancar el puente con " + py + ": " + ex.Message + "\nInstala Python o escribe su ruta en CuartoControl.config (Python=...pythonw.exe)."; }
        }

        public static string Txt(Dictionary<string, object> d, string k)
        {
            object v;
            return d != null && d.TryGetValue(k, out v) && v != null ? Convert.ToString(v) : "";
        }

        // "2026-10-30 09:56:36" -> "hace 3 min" (respecto a la hora del puente)
        public static string Hace(string fecha, string ahora)
        {
            DateTime f, a;
            if (!DateTime.TryParseExact(fecha, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out f)) return "";
            if (!DateTime.TryParseExact(ahora, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out a)) a = DateTime.Now;
            TimeSpan t = a - f;
            if (t.TotalSeconds < 90) return "ahora";
            if (t.TotalMinutes < 60) return "hace " + (int)t.TotalMinutes + " min";
            if (t.TotalHours < 24) return "hace " + (int)t.TotalHours + " h";
            return f.ToString("dd/MM HH:mm");
        }

        public static double Minutos(string fecha, string ahora)
        {
            DateTime f, a;
            if (!DateTime.TryParseExact(fecha, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out f)) return double.MaxValue;
            if (!DateTime.TryParseExact(ahora, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out a)) a = DateTime.Now;
            return (a - f).TotalMinutes;
        }
    }

    // ───────────────────────── Tema (oscuro, sala de control) ─────────────────────────
    static class Tema
    {
        public static readonly Color Fondo = Color.FromArgb(20, 24, 29);
        public static readonly Color Panel = Color.FromArgb(27, 32, 39);
        public static readonly Color Tarjeta = Color.FromArgb(34, 40, 49);
        public static readonly Color TarjetaHover = Color.FromArgb(42, 49, 60);
        public static readonly Color Borde = Color.FromArgb(48, 56, 68);
        public static readonly Color Texto = Color.FromArgb(230, 234, 240);
        public static readonly Color Tenue = Color.FromArgb(139, 149, 163);
        public static readonly Color Verde = Color.FromArgb(61, 220, 132);
        public static readonly Color Ambar = Color.FromArgb(245, 185, 66);
        public static readonly Color Rojo = Color.FromArgb(255, 90, 90);
        public static readonly Color RojoFondo = Color.FromArgb(64, 30, 34);
        public static readonly Color Azul = Color.FromArgb(90, 169, 255);
        public static readonly Color Gris = Color.FromArgb(96, 106, 120);

        public static float S = 1f;   // escala por DPI
        public static int P(float v) { return (int)Math.Round(v * S); }

        public static Font Fuente(float tam, FontStyle est = FontStyle.Regular) { return new Font("Segoe UI", tam, est); }

        public static GraphicsPath Redondo(RectangleF r, float radio)
        {
            float d = radio * 2;
            var g = new GraphicsPath();
            g.AddArc(r.X, r.Y, d, d, 180, 90);
            g.AddArc(r.Right - d, r.Y, d, d, 270, 90);
            g.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
            g.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
            g.CloseFigure();
            return g;
        }

        [System.Runtime.InteropServices.DllImport("dwmapi.dll")]
        static extern int DwmSetWindowAttribute(IntPtr h, int attr, ref int val, int size);

        [System.Runtime.InteropServices.DllImport("uxtheme.dll", CharSet = System.Runtime.InteropServices.CharSet.Unicode)]
        static extern int SetWindowTheme(IntPtr h, string app, string id);

        // Barras de desplazamiento oscuras (Windows 10 1809+); en versiones viejas quedan las normales
        public static void Oscuro(Control c)
        {
            EventHandler f = (s, e) => { try { SetWindowTheme(c.Handle, "DarkMode_Explorer", null); } catch (Exception) { } };
            if (c.IsHandleCreated) f(c, EventArgs.Empty); else c.HandleCreated += f;
        }

        // Barra de título oscura (Windows 10 2004+ / 11); en versiones viejas no hace nada
        public static void TituloOscuro(Form f)
        {
            try { int si = 1; if (DwmSetWindowAttribute(f.Handle, 20, ref si, 4) != 0) DwmSetWindowAttribute(f.Handle, 19, ref si, 4); }
            catch (Exception) { }
        }

        public static Button Boton(string t, EventHandler h, bool principal = false)
        {
            var b = new Button
            {
                Text = t, AutoSize = true, FlatStyle = FlatStyle.Flat, Cursor = Cursors.Hand,
                ForeColor = principal ? Fondo : Texto, BackColor = principal ? Azul : Tarjeta,
                Font = Fuente(9.5f, FontStyle.Bold), Padding = new Padding(P(10), P(3), P(10), P(3)), Margin = new Padding(P(4), 0, 0, 0)
            };
            b.FlatAppearance.BorderColor = principal ? Azul : Borde;
            b.FlatAppearance.MouseOverBackColor = principal ? Color.FromArgb(120, 185, 255) : TarjetaHover;
            b.Click += h;
            return b;
        }

        // ListView con encabezado oscuro
        public static ListView Lista()
        {
            var lv = new ListView
            {
                Dock = DockStyle.Fill, View = View.Details, FullRowSelect = true, MultiSelect = false, HideSelection = false,
                BackColor = Panel, ForeColor = Texto, BorderStyle = BorderStyle.None, OwnerDraw = true, Font = Fuente(10f)
            };
            lv.DrawColumnHeader += (s, e) =>
            {
                using (var b = new SolidBrush(Fondo)) e.Graphics.FillRectangle(b, e.Bounds);
                TextRenderer.DrawText(e.Graphics, e.Header.Text.ToUpperInvariant(), Fuente(8.5f, FontStyle.Bold),
                    new Rectangle(e.Bounds.X + P(6), e.Bounds.Y, e.Bounds.Width - P(6), e.Bounds.Height), Tenue, TextFormatFlags.VerticalCenter | TextFormatFlags.Left | TextFormatFlags.EndEllipsis);
                using (var p = new Pen(Borde)) e.Graphics.DrawLine(p, e.Bounds.Left, e.Bounds.Bottom - 1, e.Bounds.Right, e.Bounds.Bottom - 1);
            };
            lv.DrawItem += (s, e) => { };
            lv.DrawSubItem += (s, e) =>
            {
                bool sel = e.Item.Selected;
                Color fondo = sel ? Color.FromArgb(40, 70, 110) : e.Item.BackColor;
                using (var b = new SolidBrush(fondo)) e.Graphics.FillRectangle(b, e.Bounds);
                TextRenderer.DrawText(e.Graphics, e.SubItem.Text, lv.Font,
                    new Rectangle(e.Bounds.X + P(6), e.Bounds.Y, e.Bounds.Width - P(6), e.Bounds.Height), e.Item.ForeColor, TextFormatFlags.VerticalCenter | TextFormatFlags.Left | TextFormatFlags.EndEllipsis);
            };
            // filas más altas
            lv.SmallImageList = new ImageList { ImageSize = new Size(1, P(30)) };
            // la última columna llena el resto del ancho (si no, el encabezado deja un pedazo blanco)
            EventHandler llenar = (s, e) =>
            {
                if (lv.Columns.Count == 0) return;
                int usado = 0;
                for (int i = 0; i < lv.Columns.Count - 1; i++) usado += lv.Columns[i].Width;
                int resto = lv.ClientSize.Width - usado;
                if (resto > P(60)) lv.Columns[lv.Columns.Count - 1].Width = resto;
            };
            Oscuro(lv);
            lv.Resize += llenar;
            lv.VisibleChanged += llenar;
            return lv;
        }
    }

    // ───────────────────────── Tarjeta de una máquina ─────────────────────────
    class Tarjeta : Control
    {
        public readonly Maquina M;
        public string Turno = "", Fallas = "", Version = "", PorSubir = "", Contacto = "", Estacion = "";
        public bool SinCandado = true;
        public static float Pulso;   // 0..1, lo mueve el formulario para las tarjetas con falla
        bool encima;

        public Tarjeta(Maquina m)
        {
            M = m;
            SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.UserPaint | ControlStyles.ResizeRedraw | ControlStyles.SupportsTransparentBackColor, true);
            Size = new Size(Tema.P(250), Tema.P(150));
            Margin = new Padding(Tema.P(7));
            Cursor = Cursors.Hand;
            BackColor = Tema.Fondo;
        }

        // "2 fallas: Bloqueo externo 17:32 (desde 08:59) · Cámara 0:23:1 (desde 13:54)" -> "2 fallas · Bloqueo externo, Cámara"
        // "Falla Limpieza manual 24:64 (desde 14:03)" -> "Limpieza manual · desde 14:03"
        public static string Resumen(string fallas)
        {
            string resto = Regex.Replace(fallas, @"^(Falla|\d+ fallas:)\s*", "");
            var nombres = new List<string>();
            string desde = "";
            foreach (string parte in resto.Split(new[] { " · " }, StringSplitOptions.RemoveEmptyEntries))
            {
                Match d = Regex.Match(parte, @"\(desde ([^)]*)\)");
                if (d.Success && desde == "") desde = d.Groups[1].Value;
                string n = Regex.Replace(parte, @"\s*\(desde [^)]*\)", "");
                n = Regex.Replace(n, @"\s+\d+:\d+(:\d+)?$", "").Trim();
                if (n != "" && !nombres.Contains(n)) nombres.Add(n);
            }
            if (nombres.Count == 0) return fallas;
            if (nombres.Count == 1) return nombres[0] + (desde != "" ? " · desde " + desde : "");
            Match c = Regex.Match(fallas, @"^(\d+) fallas");
            return (c.Success ? c.Groups[1].Value : nombres.Count.ToString()) + " fallas · " + string.Join(", ", nombres.ToArray());
        }

        protected override void OnMouseEnter(EventArgs e) { encima = true; Invalidate(); base.OnMouseEnter(e); }
        protected override void OnMouseLeave(EventArgs e) { encima = false; Invalidate(); base.OnMouseLeave(e); }

        // Color y texto de la red
        void Red(out Color c, out string t)
        {
            string h; int p;
            if (!M.Configurada) { c = Tema.Gris; t = "Sin configurar"; return; }
            if (!M.Destino(out h, out p)) { c = Tema.Rojo; t = "Dato no válido"; return; }
            switch (M.Estado)
            {
                case "ok": c = Tema.Verde; t = (M.Protocolo == "rdp" ? "RDP" : M.Protocolo == "url" ? "Web" : "VNC") + " en línea"; return;
                case "ping": c = Tema.Ambar; t = "Solo ping"; return;
                case "mal": c = Tema.Rojo; t = "Sin red"; return;
                default: c = Tema.Gris; t = "Revisando…"; return;
            }
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            var g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
            bool falla = Fallas != "";
            Color red; string redTxt;
            Red(out red, out redTxt);

            var r = new RectangleF(1, 1, Width - 3, Height - 3);
            Color fondo = falla ? Tema.RojoFondo : encima ? Tema.TarjetaHover : Tema.Tarjeta;
            using (var path = Tema.Redondo(r, Tema.P(10)))
            {
                using (var b = new SolidBrush(fondo)) g.FillPath(b, path);
                Color borde = falla ? Color.FromArgb((int)(110 + 145 * Pulso), Tema.Rojo) : Tema.Borde;
                using (var p = new Pen(borde, falla ? Tema.P(2) : 1)) g.DrawPath(p, path);
            }
            // franja de estado a la izquierda
            using (var b = new SolidBrush(falla ? Tema.Rojo : red))
            using (var path = Tema.Redondo(new RectangleF(Tema.P(10), Tema.P(16), Tema.P(4), Tema.P(26)), Tema.P(2)))
                g.FillPath(b, path);

            int x = Tema.P(22), w = Width - x - Tema.P(14);
            // nombre
            TextRenderer.DrawText(g, M.Id, Tema.Fuente(15f, FontStyle.Bold), new Rectangle(x, Tema.P(10), w, Tema.P(34)), Tema.Texto, TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
            // red (arriba a la derecha): punto + texto
            var fRed = Tema.Fuente(8.5f, FontStyle.Bold);
            Size tr = TextRenderer.MeasureText(redTxt, fRed);
            int rx = Width - Tema.P(14) - tr.Width;
            TextRenderer.DrawText(g, redTxt, fRed, new Point(rx, Tema.P(19)), red);
            using (var b = new SolidBrush(red)) g.FillEllipse(b, rx - Tema.P(13), Tema.P(22), Tema.P(8), Tema.P(8));

            // turno
            string turno = SinCandado ? "Candado sin datos" : Turno;
            Color cTurno = SinCandado ? Tema.Tenue : Turno == "Bloqueada" ? Tema.Tenue : Turno == "EMERGENCIA" ? Tema.Ambar : Tema.Texto;
            TextRenderer.DrawText(g, (SinCandado || Turno == "Bloqueada" ? "" : "● ") + turno, Tema.Fuente(10.5f, Turno == "Bloqueada" || SinCandado ? FontStyle.Regular : FontStyle.Bold),
                new Rectangle(x, Tema.P(50), w, Tema.P(24)), cTurno, TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
            // fallas: solo los nombres (el detalle con códigos y horas va en el aviso al pasar el mouse)
            string tf = falla ? "⚠ " + Resumen(Fallas) : SinCandado ? "" : "Sin fallas";
            TextRenderer.DrawText(g, tf, Tema.Fuente(9.5f, falla ? FontStyle.Bold : FontStyle.Regular), new Rectangle(x, Tema.P(76), w, Tema.P(36)),
                falla ? Tema.Rojo : Tema.Tenue, TextFormatFlags.Left | TextFormatFlags.Top | TextFormatFlags.WordBreak | TextFormatFlags.EndEllipsis);
            // pie: versión · por subir · contacto · IP
            using (var p = new Pen(Tema.Borde)) g.DrawLine(p, x, Height - Tema.P(32), Width - Tema.P(14), Height - Tema.P(32));
            var partes = new List<string>();
            if (Version != "") partes.Add("v" + Version.Replace(".0", "").TrimEnd('.'));
            if (PorSubir != "" && PorSubir != "0") partes.Add("↑" + PorSubir);
            if (Contacto != "") partes.Add(Contacto);
            if (M.Host != "") partes.Add(M.Host.Replace("192.168.11.", "."));
            Color cPie = PorSubir != "" && PorSubir != "0" ? Tema.Ambar : Tema.Tenue;
            TextRenderer.DrawText(g, string.Join("  ·  ", partes.ToArray()), Tema.Fuente(8.5f), new Rectangle(x, Height - Tema.P(29), w, Tema.P(22)), cPie,
                TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
        }
    }

    // ───────────────────────── Rejilla de tarjetas ─────────────────────────
    // Acomoda las secciones (FILA A, FILA B…) y sus tarjetas en columnas según el ancho, con desplazamiento.
    class Rejilla : Panel
    {
        public readonly List<KeyValuePair<Label, List<Tarjeta>>> Secciones = new List<KeyValuePair<Label, List<Tarjeta>>>();
        bool acomodando;

        public Rejilla()
        {
            Dock = DockStyle.Fill;
            AutoScroll = true;
            DoubleBuffered = true;
            BackColor = Tema.Fondo;
            Tema.Oscuro(this);
        }

        public void Agregar(Label titulo, List<Tarjeta> ts)
        {
            Secciones.Add(new KeyValuePair<Label, List<Tarjeta>>(titulo, ts));
            Controls.Add(titulo);
            foreach (Tarjeta t in ts) Controls.Add(t);
        }

        protected override void OnLayout(LayoutEventArgs e)
        {
            base.OnLayout(e);
            if (acomodando) return;
            acomodando = true;
            try
            {
                int pad = Tema.P(22), sep = Tema.P(14);
                int ancho = Tema.P(250), alto = Tema.P(150);
                int util = Math.Max(ancho, ClientSize.Width - 2 * pad);
                int cols = Math.Max(1, (util + sep) / (ancho + sep));
                int ox = AutoScrollPosition.X, oy = AutoScrollPosition.Y;
                int y = Tema.P(8);
                foreach (var sec in Secciones)
                {
                    sec.Key.Location = new Point(pad + ox, y + oy);
                    y += sec.Key.Height + Tema.P(8);
                    for (int i = 0; i < sec.Value.Count; i++)
                    {
                        Tarjeta t = sec.Value[i];
                        t.Size = new Size(ancho, alto);
                        t.Location = new Point(pad + (i % cols) * (ancho + sep) + ox, y + (i / cols) * (alto + sep) + oy);
                    }
                    int filas = (sec.Value.Count + cols - 1) / cols;
                    y += filas * (alto + sep) + Tema.P(14);
                }
                AutoScrollMinSize = new Size(0, y);
            }
            finally { acomodando = false; }
        }
    }

    // ───────────────────────── Mapa de la planta (vista desde arriba) ─────────────────────────
    // Posiciones de las máquinas tomadas del plano 3D: layout_planta1.json / layout_mm.json (s_mm a lo largo,
    // t_mm a través; 0 = pasillo). Cada máquina se pinta con el mismo estado que su tarjeta.
    class Mapa : Control
    {
        readonly List<Tarjeta> tarjetas;
        readonly Dictionary<Maquina, RectangleF> pos = new Dictionary<Maquina, RectangleF>();   // en mm
        readonly bool aproximado;
        readonly string error;
        readonly Dictionary<Maquina, RectangleF> pantalla = new Dictionary<Maquina, RectangleF>();
        Maquina encima;
        public event Action<Maquina> Abrir;
        public event Action<Maquina, Point> Menu;

        public Mapa(Planta p, List<Tarjeta> ts)
        {
            tarjetas = ts;
            Dock = DockStyle.Fill;
            BackColor = Tema.Fondo;
            SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.UserPaint | ControlStyles.ResizeRedraw, true);
            try
            {
                var raiz = new JavaScriptSerializer().DeserializeObject(File.ReadAllText(p.Layout, Encoding.UTF8)) as Dictionary<string, object>;
                aproximado = raiz != null && raiz.ContainsKey("_nota") && Convert.ToString(raiz["_nota"]).Contains("SUPUESTO");
                var lista = raiz != null && raiz.ContainsKey("maquinas") ? raiz["maquinas"] as object[] : null;
                if (lista != null)
                    foreach (object o in lista)
                    {
                        var d = o as Dictionary<string, object>;
                        var s = d != null && d.ContainsKey("s_mm") ? d["s_mm"] as object[] : null;
                        var t = d != null && d.ContainsKey("t_mm") ? d["t_mm"] as object[] : null;
                        if (s == null || t == null || s.Length < 2 || t.Length < 2) continue;
                        string id = Convert.ToString(d["id"]);
                        Tarjeta tj = ts.FirstOrDefault(x => x.M.Id == id);
                        if (tj == null) continue;
                        float s0 = Convert.ToSingle(s[0]), s1 = Convert.ToSingle(s[1]), t0 = Convert.ToSingle(t[0]), t1 = Convert.ToSingle(t[1]);
                        pos[tj.M] = RectangleF.FromLTRB(Math.Min(s0, s1), Math.Min(t0, t1), Math.Max(s0, s1), Math.Max(t0, t1));
                    }
                if (pos.Count == 0) error = "El plano de esta planta no tiene máquinas con posición (" + p.Layout + ").";
            }
            catch (Exception ex) { error = "No se pudo leer el plano " + p.Layout + ": " + ex.Message; }
        }

        Maquina En(Point p)
        {
            foreach (var kv in pantalla) if (kv.Value.Contains(p)) return kv.Key;
            return null;
        }

        protected override void OnMouseMove(MouseEventArgs e)
        {
            Maquina m = En(e.Location);
            if (m != encima) { encima = m; Cursor = m != null ? Cursors.Hand : Cursors.Default; Invalidate(); }
            base.OnMouseMove(e);
        }

        protected override void OnMouseLeave(EventArgs e) { encima = null; Invalidate(); base.OnMouseLeave(e); }

        protected override void OnMouseDoubleClick(MouseEventArgs e)
        {
            Maquina m = En(e.Location);
            if (m != null && Abrir != null) Abrir(m);
            base.OnMouseDoubleClick(e);
        }

        protected override void OnMouseUp(MouseEventArgs e)
        {
            Maquina m = En(e.Location);
            if (m != null && e.Button == MouseButtons.Right && Menu != null) Menu(m, e.Location);
            base.OnMouseUp(e);
        }

        public Tarjeta TarjetaDe(Maquina m) { return tarjetas.FirstOrDefault(t => t.M == m); }

        protected override void OnPaint(PaintEventArgs e)
        {
            var g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
            pantalla.Clear();
            if (error != null)
            {
                TextRenderer.DrawText(g, error, Tema.Fuente(10f), new Rectangle(Tema.P(22), Tema.P(16), Width - Tema.P(44), Tema.P(60)), Tema.Rojo, TextFormatFlags.WordBreak);
                return;
            }

            // Encaje: todo el plano con margen, sin deformar
            RectangleF tot = pos.Values.Aggregate((a, b) => RectangleF.Union(a, b));
            tot.Inflate(600, 600);
            int pie = Tema.P(46);
            float area_w = Width - Tema.P(44), area_h = Height - Tema.P(30) - pie;
            float k = Math.Min(area_w / tot.Width, area_h / tot.Height);
            float ox = Tema.P(22) + (area_w - tot.Width * k) / 2, oy = Tema.P(14) + (area_h - tot.Height * k) / 2;
            // s hacia la derecha; t hacia arriba (fila de t positivo arriba)
            Func<float, float> X = s => ox + (s - tot.Left) * k;
            Func<float, float> Y = t => oy + (tot.Bottom - t) * k;

            // Piso
            var piso = new RectangleF(X(tot.Left), Y(tot.Bottom), tot.Width * k, tot.Height * k);
            using (var path = Tema.Redondo(piso, Tema.P(12)))
            {
                using (var b = new SolidBrush(Tema.Panel)) g.FillPath(b, path);
                using (var p = new Pen(Tema.Borde)) g.DrawPath(p, path);
            }
            // Cuadrícula de 1 m
            using (var p = new Pen(Color.FromArgb(14, 255, 255, 255)))
            {
                for (float s = (float)Math.Ceiling(tot.Left / 1000) * 1000; s < tot.Right; s += 1000) g.DrawLine(p, X(s), piso.Top, X(s), piso.Bottom);
                for (float t = (float)Math.Ceiling(tot.Top / 1000) * 1000; t < tot.Bottom; t += 1000) g.DrawLine(p, piso.Left, Y(t), piso.Right, Y(t));
            }
            // Pasillo (t = 0) si hay máquinas a los dos lados
            if (pos.Values.Any(r => r.Bottom <= 0) && pos.Values.Any(r => r.Top >= 0))
            {
                using (var p = new Pen(Color.FromArgb(70, Tema.Tenue), 1) { DashStyle = DashStyle.Dash }) g.DrawLine(p, piso.Left + Tema.P(10), Y(0), piso.Right - Tema.P(10), Y(0));
                TextRenderer.DrawText(g, "PASILLO", Tema.Fuente(8f, FontStyle.Bold), new Point((int)piso.Left + Tema.P(14), (int)Y(0) - Tema.P(18)), Tema.Gris);
            }

            // Máquinas
            foreach (var kv in pos)
            {
                Tarjeta t = TarjetaDe(kv.Key);
                if (t == null) continue;
                var r = RectangleF.FromLTRB(X(kv.Value.Left), Y(kv.Value.Bottom), X(kv.Value.Right), Y(kv.Value.Top));
                pantalla[kv.Key] = r;
                bool falla = t.Fallas != "";
                Color estado = !t.M.Configurada ? Tema.Gris : t.M.Estado == "ok" ? Tema.Verde : t.M.Estado == "ping" ? Tema.Ambar : t.M.Estado == "mal" ? Tema.Rojo : Tema.Gris;
                Color fondo = falla ? Tema.RojoFondo : encima == kv.Key ? Tema.TarjetaHover : Tema.Tarjeta;
                using (var path = Tema.Redondo(r, Tema.P(6)))
                {
                    if (falla)   // halo que pulsa
                        using (var halo = new Pen(Color.FromArgb((int)(40 + 90 * Tarjeta.Pulso), Tema.Rojo), Tema.P(8))) g.DrawPath(halo, path);
                    using (var b = new SolidBrush(fondo)) g.FillPath(b, path);
                    using (var p = new Pen(falla ? Tema.Rojo : estado, Tema.P(2))) g.DrawPath(p, path);
                }
                // punto de estado de la red
                using (var b = new SolidBrush(estado)) g.FillEllipse(b, r.Right - Tema.P(14), r.Top + Tema.P(6), Tema.P(8), Tema.P(8));

                var cuadro = Rectangle.Round(RectangleF.Inflate(r, -Tema.P(5), -Tema.P(4)));
                float tamNombre = Math.Max(8f, Math.Min(14f, r.Width / (7.5f * Tema.S)));
                var lineas = new List<KeyValuePair<string, Color>>();
                lineas.Add(new KeyValuePair<string, Color>(t.M.Id, Tema.Texto));
                string turno = t.SinCandado ? "" : t.Turno == "Bloqueada" ? "Bloqueada" : t.Turno.Split(' ')[0];
                if (turno != "") lineas.Add(new KeyValuePair<string, Color>(turno, t.Turno == "Bloqueada" ? Tema.Tenue : Tema.Texto));
                if (falla) lineas.Add(new KeyValuePair<string, Color>("⚠ " + Tarjeta.Resumen(t.Fallas).Split(new[] { " · " }, StringSplitOptions.None)[0], Tema.Rojo));
                int y = cuadro.Top + (cuadro.Height - lineas.Count * Tema.P(tamNombre * 1.7f)) / 2;
                for (int i = 0; i < lineas.Count; i++)
                {
                    var f = i == 0 ? Tema.Fuente(tamNombre, FontStyle.Bold) : Tema.Fuente(Math.Max(7f, tamNombre * 0.68f), i == 2 ? FontStyle.Bold : FontStyle.Regular);
                    TextRenderer.DrawText(g, lineas[i].Key, f, new Rectangle(cuadro.Left, y, cuadro.Width, Tema.P(tamNombre * 1.7f)), lineas[i].Value,
                        TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
                    y += Tema.P(tamNombre * 1.7f);
                }
            }

            // Pie: nota, escala y leyenda
            int yp = Height - pie + Tema.P(8);
            TextRenderer.DrawText(g, "Vista desde arriba · posiciones del plano 3D" + (aproximado ? " (aproximadas*: faltan medidas)" : "") + " · doble clic = VNC",
                Tema.Fuente(8.5f), new Point(Tema.P(22), yp + Tema.P(4)), Tema.Tenue);
            float m5 = 5000 * k;
            int xe = Tema.P(22), ye = yp + Tema.P(26);
            using (var p = new Pen(Tema.Tenue, Tema.P(2))) { g.DrawLine(p, xe, ye, xe + m5, ye); g.DrawLine(p, xe, ye - Tema.P(4), xe, ye + Tema.P(4)); g.DrawLine(p, xe + m5, ye - Tema.P(4), xe + m5, ye + Tema.P(4)); }
            TextRenderer.DrawText(g, "5 m", Tema.Fuente(8f), new Point(xe + (int)m5 + Tema.P(6), ye - Tema.P(8)), Tema.Tenue);
            var ley = new[] { new { c = Tema.Verde, t = "VNC en línea" }, new { c = Tema.Ambar, t = "Solo ping" }, new { c = Tema.Rojo, t = "Sin red / falla" }, new { c = Tema.Gris, t = "Sin configurar" } };
            int xl = Width - Tema.P(22);
            for (int i = ley.Length - 1; i >= 0; i--)
            {
                var f = Tema.Fuente(8.5f);
                int w = TextRenderer.MeasureText(ley[i].t, f).Width;
                xl -= w;
                TextRenderer.DrawText(g, ley[i].t, f, new Point(xl, yp + Tema.P(4)), Tema.Tenue);
                using (var b = new SolidBrush(ley[i].c)) g.FillEllipse(b, xl - Tema.P(13), yp + Tema.P(8), Tema.P(8), Tema.P(8));
                xl -= Tema.P(30);
            }
        }
    }

    // ───────────────────────── Visor 3D (el mismo de Planos\salida, dentro de la app) ─────────────────────────
    // Usa WebView2 (el motor de Microsoft Edge). La app le manda el estado de cada máquina y el visor las pinta;
    // «Abrir pantalla» y «Configurar» del visor vuelven a la app (VNC directo y la ventana de configuración).
    class Visor3D : Panel
    {
        readonly Planta planta;
        public string Nombre;
        readonly Label aviso = new Label { Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleCenter, ForeColor = Tema.Tenue, Text = "Cargando el visor 3D…" };
        Microsoft.Web.WebView2.WinForms.WebView2 web;
        bool listo, iniciado;
        string pendiente;
        public event Action<string> Abrir, Configurar;

        public Visor3D(Planta p)
        {
            planta = p;
            Dock = DockStyle.Fill;
            BackColor = Tema.Fondo;
            aviso.Font = Tema.Fuente(11f);
            Controls.Add(aviso);
        }

        public string Archivo { get { return Path.Combine(Path.GetDirectoryName(planta.Remoto), "visor_3d.html"); } }

        public async void Iniciar()
        {
            if (iniciado) return;
            iniciado = true;
            if (!File.Exists(Archivo)) { aviso.Text = "No existe " + Archivo + "\nGenera el visor de esta planta con make_3d.py."; return; }
            try
            {
                string datos = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "CuartoControl", "WebView2");
                var env = await Microsoft.Web.WebView2.Core.CoreWebView2Environment.CreateAsync(null, datos);
                web = new Microsoft.Web.WebView2.WinForms.WebView2 { Dock = DockStyle.Fill, DefaultBackgroundColor = Tema.Fondo };
                Controls.Add(web);
                web.BringToFront();
                await web.EnsureCoreWebView2Async(env);
                var c = web.CoreWebView2;
                c.Settings.IsStatusBarEnabled = false;
                c.Settings.AreDevToolsEnabled = false;
                c.Settings.IsZoomControlEnabled = true;
                try { c.Profile.PreferredColorScheme = Microsoft.Web.WebView2.Core.CoreWebView2PreferredColorScheme.Dark; } catch (Exception) { }
                c.WebMessageReceived += (s, e) =>
                {
                    try
                    {
                        var d = new JavaScriptSerializer().DeserializeObject(e.WebMessageAsJson) as Dictionary<string, object>;
                        if (d == null) return;
                        if (d.ContainsKey("abrir") && Abrir != null) Abrir(Convert.ToString(d["abrir"]));
                        if (d.ContainsKey("configurar") && Configurar != null) Configurar(Convert.ToString(d["configurar"]));
                    }
                    catch (Exception) { }
                };
                c.NavigationCompleted += (s, e) => { listo = true; if (pendiente != null) Enviar(pendiente); };
                // Ventanas nuevas (enlaces del visor) en el navegador normal, no dentro de la app
                c.NewWindowRequested += (s, e) => { e.Handled = true; try { Process.Start(e.Uri); } catch (Exception) { } };
                c.Navigate(new Uri(Archivo).AbsoluteUri);
            }
            catch (Exception ex)
            {
                aviso.ForeColor = Tema.Rojo;
                aviso.Text = "No se pudo abrir el visor 3D: " + ex.Message +
                             "\nHace falta «Microsoft Edge WebView2 Runtime» (viene con Windows 11; en Windows 10 se instala desde microsoft.com).";
                aviso.BringToFront();
            }
        }

        public void Enviar(string json)
        {
            pendiente = json;
            if (listo && web != null && web.CoreWebView2 != null)
                try { web.CoreWebView2.PostWebMessageAsJson(json); } catch (Exception) { }
        }
    }

    // ───────────────────────── Ventana principal ─────────────────────────
    class FormPrincipal : Form
    {
        readonly Panel cuerpo = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fondo, Padding = new Padding(0) };
        readonly Label lblTitulo = new Label { AutoSize = true, ForeColor = Tema.Texto, BackColor = Color.Transparent };
        readonly Label lblSub = new Label { AutoSize = true, ForeColor = Tema.Tenue, BackColor = Color.Transparent };
        readonly Label lblPuente = new Label { AutoSize = true, ForeColor = Tema.Tenue, BackColor = Color.Transparent };
        readonly Label lblEstado = new Label { Dock = DockStyle.Bottom, TextAlign = ContentAlignment.MiddleLeft, ForeColor = Tema.Tenue, BackColor = Tema.Panel };
        readonly Button bPuente;
        readonly FlowLayoutPanel nav = new FlowLayoutPanel { Dock = DockStyle.Top, AutoSize = true, BackColor = Tema.Fondo, WrapContents = false };
        readonly System.Windows.Forms.Timer reloj = new System.Windows.Forms.Timer();
        readonly System.Windows.Forms.Timer relojPuente = new System.Windows.Forms.Timer { Interval = 10000 };
        readonly System.Windows.Forms.Timer relojPulso = new System.Windows.Forms.Timer { Interval = 60 };
        readonly Dictionary<string, List<Maquina>> datos = new Dictionary<string, List<Maquina>>();
        readonly Dictionary<string, Control> vistas = new Dictionary<string, Control>();
        readonly List<Tarjeta> tarjetas = new List<Tarjeta>();
        readonly List<Mapa> mapas = new List<Mapa>();
        readonly Dictionary<string, Control[]> modos = new Dictionary<string, Control[]>();   // planta -> [tarjetas, mapa, visor 3D]
        readonly List<Visor3D> visores = new List<Visor3D>();
        int modo;   // 0 tarjetas, 1 mapa, 2 visor 3D
        Label[] bModos;
        readonly ListView lvEstaciones;
        readonly ListView lvReportes;
        readonly TextBox txtReporte;
        readonly ContextMenuStrip menu = new ContextMenuStrip();
        readonly ToolTip tip = new ToolTip();
        Dictionary<string, object> puente;      // último /api/estado (null = el puente no responde)
        DateTime arrancado = DateTime.MinValue;
        string vista = "";
        int revisando;
        double fase;
        string reporteVisto = "";
        Tarjeta tarjetaMenu;

        public FormPrincipal()
        {
            Text = "Cuarto de control";
            BackColor = Tema.Fondo;
            ForeColor = Tema.Texto;
            Font = Tema.Fuente(10f);
            Size = new Size(Tema.P(1280), Tema.P(760));
            MinimumSize = new Size(Tema.P(760), Tema.P(480));
            StartPosition = FormStartPosition.CenterScreen;
            try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch (Exception) { }
            HandleCreated += (s, e) => Tema.TituloOscuro(this);

            lvEstaciones = Tema.Lista();
            lvReportes = Tema.Lista();
            txtReporte = new TextBox { Dock = DockStyle.Fill, Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Both, WordWrap = false,
                                       Font = new Font("Consolas", 9.5f), BackColor = Tema.Panel, ForeColor = Tema.Texto, BorderStyle = BorderStyle.None };
            Tema.Oscuro(txtReporte);

            // ── Encabezado ──
            var cab = new Panel { Dock = DockStyle.Top, Height = Tema.P(78), BackColor = Tema.Panel };
            cab.Paint += (s, e) =>
            {
                using (var p = new Pen(Tema.Borde)) e.Graphics.DrawLine(p, 0, cab.Height - 1, cab.Width, cab.Height - 1);
                using (var b = new SolidBrush(Tema.Azul)) e.Graphics.FillRectangle(b, Tema.P(20), Tema.P(20), Tema.P(5), Tema.P(38));
            };
            lblTitulo.Text = "CUARTO DE CONTROL";
            lblTitulo.Font = Tema.Fuente(15f, FontStyle.Bold);
            lblTitulo.Location = new Point(Tema.P(34), Tema.P(14));
            lblSub.Font = Tema.Fuente(9.5f);
            lblSub.Location = new Point(Tema.P(36), Tema.P(44));
            cab.Controls.Add(lblTitulo);
            cab.Controls.Add(lblSub);

            var derecha = new FlowLayoutPanel { Dock = DockStyle.Right, AutoSize = true, WrapContents = false, BackColor = Color.Transparent, Padding = new Padding(0, Tema.P(22), Tema.P(16), 0) };
            lblPuente.Font = Tema.Fuente(9.5f, FontStyle.Bold);
            lblPuente.Margin = new Padding(0, Tema.P(6), Tema.P(10), 0);
            bPuente = Tema.Boton("Iniciar puente", (s, e) => IniciarPuente(), true);
            derecha.Controls.Add(lblPuente);
            derecha.Controls.Add(bPuente);
            derecha.Controls.Add(Tema.Boton("⟳  Revisar", (s, e) => { RevisarTodo(); LeerPuente(); }));
            derecha.Controls.Add(Tema.Boton("Utilidades", (s, e) => { try { Process.Start(Cfg.Puente); } catch (Exception) { } }));
            cab.Controls.Add(derecha);

            nav.Padding = new Padding(Tema.P(18), Tema.P(10), 0, Tema.P(4));
            lblEstado.Height = Tema.P(28);
            lblEstado.Padding = new Padding(Tema.P(18), 0, 0, 0);
            lblEstado.Font = Tema.Fuente(9f);

            Controls.Add(cuerpo);
            Controls.Add(nav);
            Controls.Add(cab);
            Controls.Add(lblEstado);

            menu.Items.Add("Conectar (VNC)", null, (s, e) => { if (tarjetaMenu != null) Conectar(tarjetaMenu.M); });
            menu.Items.Add("Configurar…", null, (s, e) => { if (tarjetaMenu != null) Configurar(tarjetaMenu.M); });
            menu.Items.Add("Copiar IP", null, (s, e) => { if (tarjetaMenu != null && tarjetaMenu.M.Host != "") try { Clipboard.SetText(tarjetaMenu.M.Host); } catch (Exception) { } });

            reloj.Interval = Cfg.Intervalo * 1000;
            reloj.Tick += (s, e) => RevisarTodo();
            relojPuente.Tick += (s, e) => LeerPuente();
            relojPulso.Tick += (s, e) =>
            {
                fase += 0.12;
                Tarjeta.Pulso = (float)(0.5 + 0.5 * Math.Sin(fase));
                foreach (Tarjeta t in tarjetas) if (t.Fallas != "") t.Invalidate();
                foreach (Mapa m in mapas) if (m.Visible && tarjetas.Any(t => t.Fallas != "")) m.Invalidate();
            };
            modo = Program.ModoInicial;
            Shown += (s, e) =>
            {
                Cargar();
                reloj.Start();
                LeerPuente();
                relojPuente.Start();
                relojPulso.Start();
            };
        }

        // ── Navegación ──
        void Pestana(string nombre)
        {
            var b = new Label
            {
                Text = nombre, AutoSize = true, Cursor = Cursors.Hand, Font = Tema.Fuente(10.5f, FontStyle.Bold),
                Padding = new Padding(Tema.P(12), Tema.P(6), Tema.P(12), Tema.P(8)), Margin = new Padding(0, 0, Tema.P(4), 0), Tag = nombre
            };
            b.Paint += (s, e) =>
            {
                if (vista != nombre) return;
                using (var br = new SolidBrush(Tema.Azul)) e.Graphics.FillRectangle(br, Tema.P(10), b.Height - Tema.P(3), b.Width - Tema.P(20), Tema.P(3));
            };
            b.Click += (s, e) => Mostrar(nombre);
            nav.Controls.Add(b);
        }

        void Mostrar(string nombre)
        {
            if (!vistas.ContainsKey(nombre)) return;
            vista = nombre;
            cuerpo.SuspendLayout();
            cuerpo.Controls.Clear();
            cuerpo.Controls.Add(vistas[nombre]);
            cuerpo.ResumeLayout();
            foreach (Control c in nav.Controls) if (c.Tag is string) { c.ForeColor = (string)c.Tag == nombre ? Tema.Texto : Tema.Tenue; c.Invalidate(); }
            Modo();
            Subtitulo();
        }

        // Tarjetas o mapa (para las pestañas de planta)
        void Modo()
        {
            bool planta = modos.ContainsKey(vista);
            if (bModos != null) for (int i = 0; i < bModos.Length; i++) { bModos[i].Visible = planta; bModos[i].ForeColor = i == modo ? Tema.Azul : Tema.Tenue; }
            if (!planta) return;
            Control[] v = modos[vista];
            for (int i = 0; i < v.Length; i++) v[i].Visible = i == modo;
            if (modo == 1) v[1].Invalidate();
            if (modo == 2) { var vz = (Visor3D)v[2]; vz.Iniciar(); EnviarEstado(); }
        }

        Label Interruptor(string texto, int m)
        {
            var b = new Label { Text = texto, AutoSize = true, Cursor = Cursors.Hand, Font = Tema.Fuente(9.5f, FontStyle.Bold), Padding = new Padding(Tema.P(10), Tema.P(7), Tema.P(10), Tema.P(7)), Margin = new Padding(0) };
            b.Click += (s, e) => { modo = m; Modo(); };
            return b;
        }

        // Estado de cada máquina para el visor 3D: {id: {red, falla, turno}}
        void EnviarEstado()
        {
            var js = new JavaScriptSerializer();
            foreach (Visor3D vz in visores)
            {
                var d = new Dictionary<string, object>();
                foreach (Tarjeta t in tarjetas)
                {
                    if (!datos.ContainsKey(vz.Nombre) || !datos[vz.Nombre].Contains(t.M)) continue;
                    d[t.M.Id] = new Dictionary<string, object>
                    {
                        { "red", t.M.Configurada ? t.M.Estado : "" },
                        { "falla", t.Fallas == "" ? "" : Tarjeta.Resumen(t.Fallas).Split(new[] { " · " }, StringSplitOptions.None)[0] + (t.Fallas.Contains(" · ") || t.Fallas.Contains(" fallas") ? "…" : "") },
                        { "turno", t.SinCandado ? "" : t.Turno == "Bloqueada" ? "" : t.Turno },
                    };
                }
                vz.Enviar(js.Serialize(d));
            }
        }

        void Cargar()
        {
            if (Cfg.Carpeta == "")
            {
                lblEstado.Text = "No encontré la carpeta Planos (salida\\remoto.json). Escríbela en CuartoControl.config: Carpeta=C:\\xampp\\htdocs\\Planos";
                return;
            }
            string antes = vista;
            nav.Controls.Clear();
            vistas.Clear();
            tarjetas.Clear();
            mapas.Clear();
            modos.Clear();
            visores.Clear();
            datos.Clear();
            foreach (Planta p in Datos.Plantas())
            {
                List<Maquina> lista;
                string error = null;
                try { lista = Datos.Maquinas(p); }
                catch (Exception ex) { lista = new List<Maquina>(); error = ex.Message; }
                datos[p.Nombre] = lista;
                int antesT = tarjetas.Count;
                Control rej = VistaPlanta(p, lista, error);
                var mapa = new Mapa(p, tarjetas.Skip(antesT).ToList()) { Visible = false };
                mapa.Abrir += m => Conectar(m);
                mapa.Menu += (m, pt) => { tarjetaMenu = mapa.TarjetaDe(m); menu.Show(mapa, pt); };
                mapas.Add(mapa);
                var visor = new Visor3D(p) { Visible = false, Nombre = p.Nombre };
                List<Maquina> deLaPlanta = lista;
                visor.Abrir += id => { Maquina m = deLaPlanta.FirstOrDefault(x => x.Id == id); if (m != null) Conectar(m); };
                visor.Configurar += id => { Maquina m = deLaPlanta.FirstOrDefault(x => x.Id == id); if (m != null) Configurar(m); };
                visores.Add(visor);
                var cont = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fondo };
                cont.Controls.Add(rej);
                cont.Controls.Add(mapa);
                cont.Controls.Add(visor);
                modos[p.Nombre] = new Control[] { rej, mapa, visor };
                vistas[p.Nombre] = cont;
                Pestana(p.Nombre);
            }

            // Todas las estaciones que hablan con el puente (también las que no están en el plano)
            if (lvEstaciones.Columns.Count == 0)
                foreach (var c in new[] { new { t = "Estación", w = 130 }, new { t = "IP", w = 120 }, new { t = "Candado", w = 80 }, new { t = "Último contacto", w = 130 },
                                          new { t = "Turno", w = 170 }, new { t = "Fallas de Delvis", w = 300 }, new { t = "Por subir", w = 80 }, new { t = "Última sincronización", w = 160 } })
                    lvEstaciones.Columns.Add(c.t, Tema.P(c.w));
            vistas["Candados"] = Envolver(lvEstaciones);
            Pestana("Candados");

            if (lvReportes.Columns.Count == 0)
            {
                lvReportes.Columns.Add("Equipo", Tema.P(230));
                lvReportes.Columns.Add("Fecha", Tema.P(130));
                lvReportes.Columns.Add("Tamaño", Tema.P(70));
                lvReportes.SelectedIndexChanged += (s, e) => VerReporte();
            }
            var division = new SplitContainer { Dock = DockStyle.Fill, Orientation = Orientation.Vertical, FixedPanel = FixedPanel.Panel1, BackColor = Tema.Borde };
            division.Panel1.BackColor = Tema.Panel;
            division.Panel2.BackColor = Tema.Panel;
            bool repartido = false;
            division.SizeChanged += (s, e) => { if (!repartido && division.Width > Tema.P(600)) { division.SplitterDistance = Tema.P(450); repartido = true; } };   // lista angosta, reporte ancho
            division.Panel1.Controls.Add(lvReportes);
            division.Panel2.Padding = new Padding(Tema.P(10));
            division.Panel2.Controls.Add(txtReporte);
            vistas["Reportes"] = Envolver(division);
            Pestana("Reportes");
            nav.Controls.Add(new Label { Width = Tema.P(40), Height = 1, Margin = new Padding(0) });
            bModos = new[] { Interruptor("▦  Tarjetas", 0), Interruptor("⌖  Mapa", 1), Interruptor("◈  3D", 2) };
            nav.Controls.AddRange(bModos);

            Mostrar(vistas.ContainsKey(antes) ? antes : vistas.ContainsKey(Program.VistaInicial) ? Program.VistaInicial : Datos.Plantas()[0].Nombre);
            PintarPuente();
            RevisarTodo();
        }

        Control Envolver(Control c)
        {
            var p = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fondo, Padding = new Padding(Tema.P(18), Tema.P(8), Tema.P(18), Tema.P(14)) };
            p.Controls.Add(c);
            return p;
        }

        // Tarjetas en filas: las que terminan en A, las que terminan en B y el resto
        Control VistaPlanta(Planta p, List<Maquina> lista, string error)
        {
            var rej = new Rejilla();
            if (error != null || lista.Count == 0)
            {
                rej.Agregar(new Label { AutoSize = true, ForeColor = Tema.Rojo,
                    Text = error != null ? "No se pudo leer " + p.Remoto + ": " + error : "Sin máquinas: genera el visor de esta planta (make_3d.py) para crear " + p.Remoto }, new List<Tarjeta>());
                return rej;
            }
            var usadas = new HashSet<Maquina>();
            Action<string, List<Maquina>> seccion = (titulo, ms) =>
            {
                if (ms.Count == 0) return;
                var l = new Label { Text = titulo + "  ·  " + ms.Count, AutoSize = true, ForeColor = Tema.Tenue, Font = Tema.Fuente(8.5f, FontStyle.Bold) };
                var ts = new List<Tarjeta>();
                foreach (Maquina m in ms)
                {
                    var t = new Tarjeta(m);
                    t.DoubleClick += (s, e) => Conectar(m);
                    t.MouseUp += (s, e) => { if (e.Button == MouseButtons.Right) { tarjetaMenu = t; menu.Show(t, e.Location); } };
                    tarjetas.Add(t);
                    ts.Add(t);
                    usadas.Add(m);
                }
                rej.Agregar(l, ts);
            };
            seccion("FILA A", lista.Where(m => m.Id.EndsWith("A", StringComparison.OrdinalIgnoreCase)).ToList());
            seccion("FILA B", lista.Where(m => m.Id.EndsWith("B", StringComparison.OrdinalIgnoreCase)).ToList());
            seccion(usadas.Count == 0 ? "MÁQUINAS" : "OTRAS", lista.Where(m => !usadas.Contains(m)).ToList());
            return rej;
        }

        // Estado que mandó el candado de esa máquina (por su IP), o null
        Dictionary<string, object> DelPuente(Maquina m)
        {
            var maq = puente == null ? null : puente.ContainsKey("maquinas") ? puente["maquinas"] as Dictionary<string, object> : null;
            if (maq == null || m.Host == "") return null;
            Dictionary<string, object> mejor = null;
            foreach (var kv in maq)
            {
                var d = kv.Value as Dictionary<string, object>;
                if (d == null || kv.Key == "instalador" || Puente.Txt(d, "ip") != m.Host) continue;
                if (mejor == null || string.CompareOrdinal(Puente.Txt(d, "ultima"), Puente.Txt(mejor, "ultima")) > 0) { mejor = d; mejor["_estacion"] = kv.Key; }
            }
            return mejor;
        }

        void Pintar(Tarjeta t)
        {
            var c = DelPuente(t.M);
            string ahora = Puente.Txt(puente, "ahora");
            t.SinCandado = c == null || !c.ContainsKey("turno");
            t.Turno = c == null ? "" : !c.ContainsKey("turno") ? "" : Puente.Txt(c, "turno") == "" ? "Bloqueada" : Puente.Txt(c, "turno");
            if (c != null && !c.ContainsKey("turno")) { t.SinCandado = true; }
            t.Fallas = Puente.Txt(c, "fallas");
            t.Version = Puente.Txt(c, "version");
            t.PorSubir = Puente.Txt(c, "pendientes");
            t.Contacto = c == null ? "" : Puente.Hace(Puente.Txt(c, "ultima"), ahora);
            t.Estacion = c == null ? "" : Puente.Txt(c, "_estacion");
            tip.SetToolTip(t, t.M.Id + (t.Estacion != "" ? "  ·  candado «" + t.Estacion + "»" : "") + (t.M.Host != "" ? "  ·  " + t.M.Host : "") +
                              (t.Fallas != "" ? "\n" + t.Fallas.Replace(" · ", "\n") : "") +
                              (t.M.Revisado != DateTime.MinValue ? "\nRed revisada a las " + t.M.Revisado.ToString("HH:mm:ss") : "") + "\nDoble clic: conectar · clic derecho: más");
            t.Invalidate();
        }

        void Refrescar(Maquina m)
        {
            foreach (Tarjeta t in tarjetas) if (t.M == m) Pintar(t);
            foreach (Mapa mp in mapas) if (mp.Visible) mp.Invalidate();
            EnviarEstado();
        }

        void Subtitulo()
        {
            List<Maquina> ms;
            if (datos.TryGetValue(vista, out ms))
            {
                int enLinea = ms.Count(x => x.Estado == "ok"), fallas = tarjetas.Count(t => ms.Contains(t.M) && t.Fallas != "");
                lblSub.Text = vista + "  ·  " + ms.Count + " máquinas  ·  " + enLinea + " en línea" + (fallas > 0 ? "  ·  " + fallas + " con falla" : "");
                lblSub.ForeColor = fallas > 0 ? Tema.Rojo : Tema.Tenue;
            }
            else { lblSub.Text = vista; lblSub.ForeColor = Tema.Tenue; }
        }

        void Conectar(Maquina m)
        {
            if (m == null) return;
            string err = Red.Conectar(m);
            if (err != null) MessageBox.Show(this, err, Text, MessageBoxButtons.OK, MessageBoxIcon.Warning);
            else lblEstado.Text = "Abriendo " + m.Id + "…";
        }

        void Configurar(Maquina m)
        {
            if (m == null) return;
            using (var f = new FormConfig(m))
                if (f.ShowDialog(this) == DialogResult.OK)
                {
                    m.Estado = "";
                    Refrescar(m);
                    Revisar(m);
                    lblEstado.Text = "Guardado: " + m.Id + " (" + m.Planta.Nombre + ").";
                }
        }

        void RevisarTodo()
        {
            var todas = datos.Values.SelectMany(x => x).Where(x => x.Configurada).ToList();
            if (todas.Count == 0) { lblEstado.Text = "Ninguna máquina tiene conexión configurada todavía. Clic derecho en una tarjeta → Configurar."; return; }
            foreach (Maquina m in todas) Revisar(m);
        }

        void Revisar(Maquina m)
        {
            string host; int puerto;
            if (!m.Configurada || !m.Destino(out host, out puerto)) { m.Estado = m.Configurada ? "mal" : ""; Refrescar(m); return; }   // dato no válido: en rojo
            Interlocked.Increment(ref revisando);
            ThreadPool.QueueUserWorkItem(_ =>
            {
                bool ok = Red.Responde(host, puerto, Cfg.Espera);
                bool ping = ok || Red.Ping(host, Cfg.Espera);
                try
                {
                    BeginInvoke(new Action(() =>
                    {
                        m.Estado = ok ? "ok" : ping ? "ping" : "mal";
                        m.Revisado = DateTime.Now;
                        Refrescar(m);
                        if (Interlocked.Decrement(ref revisando) == 0) Resumen();
                    }));
                }
                catch (InvalidOperationException) { }   // la ventana ya se cerró
            });
        }

        void Resumen()
        {
            var todas = datos.Values.SelectMany(x => x).Where(x => x.Configurada).ToList();
            lblEstado.Text = "Red revisada a las " + DateTime.Now.ToString("HH:mm:ss") + "  ·  " + todas.Count(x => x.Estado == "ok") + " en línea  ·  " +
                             todas.Count(x => x.Estado == "ping") + " solo ping  ·  " + todas.Count(x => x.Estado == "mal") + " sin red  ·  cada " + Cfg.Intervalo + " s" +
                             "  ·  doble clic en una máquina para ver su pantalla";
            Subtitulo();
        }

        // ── Puente ──
        void LeerPuente()
        {
            ThreadPool.QueueUserWorkItem(_ =>
            {
                var e = Puente.Estado();
                try { BeginInvoke(new Action(() => { puente = e; PintarPuente(); })); }
                catch (InvalidOperationException) { }
            });
        }

        void IniciarPuente()
        {
            string err = Puente.Iniciar();
            if (err != null) { MessageBox.Show(this, err, Text, MessageBoxButtons.OK, MessageBoxIcon.Warning); return; }
            arrancado = DateTime.Now;
            lblPuente.Text = "●  Arrancando el puente…";
            lblPuente.ForeColor = Tema.Ambar;
            var t = new System.Windows.Forms.Timer { Interval = 2500 };
            t.Tick += (s, e) => { t.Stop(); t.Dispose(); LeerPuente(); };
            t.Start();
        }

        void PintarPuente()
        {
            if (puente == null)
            {
                bPuente.Visible = true;
                bool arrancando = (DateTime.Now - arrancado).TotalSeconds < 15;
                lblPuente.ForeColor = arrancando ? Tema.Ambar : Tema.Rojo;
                lblPuente.Text = arrancando ? "●  Arrancando el puente…" : "●  Puente apagado: las máquinas no sincronizan";
            }
            else
            {
                bPuente.Visible = false;
                var p = puente.ContainsKey("puente") ? puente["puente"] as Dictionary<string, object> : null;
                string ahora = Puente.Txt(puente, "ahora");
                int pend;
                int.TryParse(Puente.Txt(p, "pendientes"), out pend);
                string copia = Puente.Txt(p, "copia_operarios");
                lblPuente.ForeColor = pend > 0 || copia == "" ? Tema.Ambar : Tema.Verde;
                lblPuente.Text = "●  Puente activo   ↑ " + pend + " por subir   ·   copia " + (copia == "" ? "—" : Puente.Hace(copia, ahora)) +
                                 "   ·   v" + (Puente.Txt(p, "version") == "" ? "?" : Puente.Txt(p, "version"));
            }
            foreach (Tarjeta t in tarjetas) Pintar(t);
            foreach (Mapa mp in mapas) mp.Invalidate();
            EnviarEstado();
            PintarEstaciones();
            PintarReportes();
            Subtitulo();
        }

        void PintarEstaciones()
        {
            var maq = puente == null ? null : puente.ContainsKey("maquinas") ? puente["maquinas"] as Dictionary<string, object> : null;
            string ahora = Puente.Txt(puente, "ahora");
            lvEstaciones.BeginUpdate();
            lvEstaciones.Items.Clear();
            if (maq != null)
                foreach (var kv in maq.OrderBy(x => x.Key))
                {
                    var d = kv.Value as Dictionary<string, object>;
                    if (d == null || kv.Key == "instalador") continue;
                    string fallas = Puente.Txt(d, "fallas");
                    var it = new ListViewItem(new[] {
                        kv.Key, Puente.Txt(d, "ip"), Puente.Txt(d, "version"), Puente.Hace(Puente.Txt(d, "ultima"), ahora),
                        !d.ContainsKey("turno") ? "(candado < 1.4.3)" : Puente.Txt(d, "turno") == "" ? "Bloqueada" : Puente.Txt(d, "turno"),
                        fallas, Puente.Txt(d, "pendientes"), Puente.Hace(Puente.Txt(d, "ultima_sync"), ahora) }) { BackColor = Tema.Panel, ForeColor = Tema.Texto };
                    if (fallas != "") { it.BackColor = Tema.RojoFondo; it.ForeColor = Tema.Rojo; }
                    else if (Puente.Minutos(Puente.Txt(d, "ultima"), ahora) > 15) it.ForeColor = Tema.Tenue;   // hace rato que no habla
                    lvEstaciones.Items.Add(it);
                }
            lvEstaciones.EndUpdate();
        }

        void PintarReportes()
        {
            var lista = puente == null ? null : puente.ContainsKey("reportes") ? puente["reportes"] as object[] : null;
            string sel = lvReportes.SelectedItems.Count > 0 ? (string)lvReportes.SelectedItems[0].Tag : null;
            lvReportes.BeginUpdate();
            lvReportes.Items.Clear();
            if (lista != null)
                foreach (object o in lista)
                {
                    var d = o as Dictionary<string, object>;
                    if (d == null) continue;
                    string nombre = Puente.Txt(d, "nombre");
                    long tam;
                    long.TryParse(Puente.Txt(d, "tam"), out tam);
                    var it = new ListViewItem(new[] { Regex.Replace(nombre, @"_\d{8}-\d{6}\.txt$", ""), Puente.Txt(d, "fecha"), (tam / 1024.0).ToString("0.0") + " KB" })
                    { Tag = Puente.Txt(d, "url"), BackColor = Tema.Panel, ForeColor = Tema.Texto };
                    lvReportes.Items.Add(it);
                    if ((string)it.Tag == sel) it.Selected = true;
                }
            lvReportes.EndUpdate();
        }

        void VerReporte()
        {
            if (lvReportes.SelectedItems.Count == 0) return;
            string url = (string)lvReportes.SelectedItems[0].Tag;
            if (url == reporteVisto) return;
            reporteVisto = url;
            try { txtReporte.Text = Puente.Reporte(url).Replace("\r\n", "\n").Replace("\n", "\r\n"); }
            catch (Exception ex) { txtReporte.Text = "No se pudo leer el reporte: " + ex.Message; }
        }
    }

    static class Program
    {
        public static string VistaInicial = "";   // "--vista Candados": abre en esa pestaña
        public static int ModoInicial;             // "--vista \"Planta 1\" --mapa" o "--3d": abre en el mapa o en el visor 3D
        [STAThread]
        static int Main(string[] args)
        {
            Cfg.Cargar();
            if (args.Length > 0 && args[0] == "--prueba") return Prueba();
            if (args.Length > 1 && args[0] == "--vista") VistaInicial = args[1];
            if (args.Length > 2 && args[2] == "--mapa") ModoInicial = 1;
            if (args.Length > 2 && args[2] == "--3d") ModoInicial = 2;
            SetProcessDPIAware();   // nítido en pantallas con escala; los tamaños se multiplican por Tema.S
            using (var g = Graphics.FromHwnd(IntPtr.Zero)) Tema.S = g.DpiX / 96f;
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new FormPrincipal());
            return 0;
        }

        [System.Runtime.InteropServices.DllImport("user32.dll")]
        static extern bool SetProcessDPIAware();

        // Sin ventana: lista las máquinas y si responden (para revisar desde la consola)
        [System.Runtime.InteropServices.DllImport("kernel32.dll")]
        static extern bool AttachConsole(int pid);

        static int Prueba()
        {
            AttachConsole(-1);   // es un programa de ventanas: escribe en la consola desde la que se llamó
            var sb = new StringBuilder();
            Console.SetOut(new StringWriter(sb));
            try { PruebaListar(); }
            finally
            {
                File.WriteAllText(Path.Combine(Path.GetTempPath(), "CuartoControl-prueba.txt"), sb.ToString(), Encoding.UTF8);
                var salida = new StreamWriter(Console.OpenStandardOutput()) { AutoFlush = true };
                salida.Write(sb.ToString());
            }
            return 0;
        }

        static void PruebaListar()
        {
            Console.WriteLine("Carpeta: " + (Cfg.Carpeta == "" ? "(no encontrada)" : Cfg.Carpeta));
            Console.WriteLine("TightVNC Viewer: " + (Cfg.Tvnviewer() ?? "(no encontrado)"));
            foreach (Planta p in Datos.Plantas())
            {
                List<Maquina> lista = Datos.Maquinas(p);
                Console.WriteLine(p.Nombre + ": " + lista.Count + " máquinas (" + p.Remoto + ")");
                foreach (Maquina m in lista)
                {
                    string host; int puerto;
                    string estado = !m.Configurada ? "sin configurar"
                        : !m.Destino(out host, out puerto) ? "dato no válido"
                        : (Red.Responde(host, puerto, Cfg.Espera) ? "RESPONDE" : "no responde") + " (" + host + ":" + puerto + ")";
                    Console.WriteLine("  " + m.Id.PadRight(8) + " " + estado);
                }
            }
        }
    }
}
