// Cuarto de control: lista de las máquinas de cada planta, si responden en la red y conexión con doble clic.
// Lee y guarda las mismas conexiones que el visor 3D (salida\remoto.json de cada planta). Sin contraseñas:
// las pide TightVNC / Escritorio remoto al conectar.
//
// Compilar: compilar.bat (usa el compilador de .NET 4 que trae Windows).
// Prueba sin ventana: CuartoControl.exe --prueba   (estado de todas las máquinas en la consola y en %TEMP%\CuartoControl-prueba.txt)
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Linq;
using System.Net.Sockets;
using System.Reflection;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Web.Script.Serialization;
using System.Windows.Forms;

[assembly: AssemblyTitle("Cuarto de control")]
[assembly: AssemblyProduct("Cuarto de control - Honducafe")]
[assembly: AssemblyVersion("1.4.0.0")]
[assembly: AssemblyFileVersion("1.4.0.0")]

namespace CuartoControl
{
    // ───────────────────────── Configuración ─────────────────────────
    static class Cfg
    {
        public static string Carpeta = "";        // carpeta Planos (la que tiene salida\remoto.json)
        public static string VisorVnc = "";       // tvnviewer.exe; vacío = buscarlo
        public static int Intervalo = 30;         // segundos entre revisiones automáticas
        public static int Espera = 1500;          // ms máximos para decidir si una máquina responde

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
        public string Estado = "";     // "", "ok", "mal", "revisando"
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
                new Planta { Nombre = "Planta 1", Remoto = Path.Combine(Cfg.Carpeta, "salida", "planta1", "remoto.json") },
                new Planta { Nombre = "Planta 2", Remoto = Path.Combine(Cfg.Carpeta, "salida", "remoto.json") },
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

    // ───────────────────────── Ventana principal ─────────────────────────
    class FormPrincipal : Form
    {
        readonly TabControl tabs = new TabControl { Dock = DockStyle.Fill };
        readonly ImageList iconos = new ImageList { ImageSize = new Size(16, 16), ColorDepth = ColorDepth.Depth32Bit };
        readonly Label lblEstado = new Label { Dock = DockStyle.Bottom, Height = 26, TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(8, 0, 0, 0) };
        readonly System.Windows.Forms.Timer reloj = new System.Windows.Forms.Timer();
        readonly Dictionary<string, List<Maquina>> datos = new Dictionary<string, List<Maquina>>();
        int revisando;

        static Bitmap Punto(Color c)
        {
            var b = new Bitmap(16, 16);
            using (var g = Graphics.FromImage(b))
            {
                g.SmoothingMode = System.Drawing.Drawing2D.SmoothingMode.AntiAlias;
                using (var br = new SolidBrush(c)) g.FillEllipse(br, 3, 3, 10, 10);
            }
            return b;
        }

        public FormPrincipal()
        {
            Text = "Cuarto de control";
            Font = new Font("Segoe UI", 10f);
            Size = new Size(860, 560);
            MinimumSize = new Size(560, 360);
            StartPosition = FormStartPosition.CenterScreen;
            try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch (Exception) { }

            iconos.Images.Add("nada", Punto(Color.FromArgb(160, 160, 160)));     // sin configurar
            iconos.Images.Add("ok", Punto(Color.FromArgb(34, 160, 70)));        // responde
            iconos.Images.Add("mal", Punto(Color.FromArgb(210, 50, 50)));       // no responde
            iconos.Images.Add("revisando", Punto(Color.FromArgb(230, 170, 30))); // revisando

            var barra = new FlowLayoutPanel { Dock = DockStyle.Top, AutoSize = true, Padding = new Padding(6, 6, 6, 2) };
            Button bConectar = Boton("Conectar", (s, e) => Conectar(Seleccionada()));
            Button bConfig = Boton("Configurar…", (s, e) => Configurar(Seleccionada()));
            Button bRevisar = Boton("Revisar ahora", (s, e) => RevisarTodo());
            Button bRecargar = Boton("Recargar lista", (s, e) => Cargar());
            barra.Controls.AddRange(new Control[] { bConectar, bConfig, bRevisar, bRecargar });

            Controls.Add(tabs);
            Controls.Add(barra);
            Controls.Add(lblEstado);

            reloj.Interval = Cfg.Intervalo * 1000;
            reloj.Tick += (s, e) => RevisarTodo();
            Shown += (s, e) => { Cargar(); reloj.Start(); };
        }

        static Button Boton(string t, EventHandler h)
        {
            var b = new Button { Text = t, AutoSize = true, Padding = new Padding(6, 2, 6, 2) };
            b.Click += h;
            return b;
        }

        void Cargar()
        {
            if (Cfg.Carpeta == "")
            {
                lblEstado.Text = "No encontré la carpeta Planos (salida\\remoto.json). Escríbela en CuartoControl.config: Carpeta=C:\\xampp\\htdocs\\Planos";
                return;
            }
            string sel = tabs.SelectedTab != null ? tabs.SelectedTab.Text : null;
            tabs.TabPages.Clear();
            datos.Clear();
            foreach (Planta p in Datos.Plantas())
            {
                List<Maquina> lista;
                string error = null;
                try { lista = Datos.Maquinas(p); }
                catch (Exception ex) { lista = new List<Maquina>(); error = ex.Message; }
                datos[p.Nombre] = lista;

                var lv = new ListView { Dock = DockStyle.Fill, View = View.Details, FullRowSelect = true, MultiSelect = false, HideSelection = false, SmallImageList = iconos };
                lv.Columns.Add("Máquina", 120);
                lv.Columns.Add("Estado", 130);
                lv.Columns.Add("Conexión", 90);
                lv.Columns.Add("Equipo", 220);
                lv.Columns.Add("Nota", 240);
                foreach (Maquina m in lista) lv.Items.Add(new ListViewItem(new[] { "", "", "", "", "" }) { Tag = m });
                lv.DoubleClick += (s, e) => Conectar(Seleccionada());
                lv.KeyDown += (s, e) => { if (e.KeyCode == Keys.Enter) Conectar(Seleccionada()); };
                var tp = new TabPage(p.Nombre);
                tp.Controls.Add(lv);
                if (error != null || lista.Count == 0)
                    tp.Controls.Add(new Label { Dock = DockStyle.Top, Height = 40, ForeColor = Color.Firebrick, Padding = new Padding(6),
                        Text = error != null ? "No se pudo leer " + p.Remoto + ": " + error : "Sin máquinas: genera el visor de esta planta (make_3d.py) para crear " + p.Remoto });
                tabs.TabPages.Add(tp);
                foreach (ListViewItem it in lv.Items) Pintar(it);
            }
            if (sel != null) foreach (TabPage t in tabs.TabPages) if (t.Text == sel) tabs.SelectedTab = t;
            RevisarTodo();
        }

        static void Pintar(ListViewItem it)
        {
            var m = (Maquina)it.Tag;
            it.Text = m.Id;
            string h; int pu;
            it.SubItems[1].Text = !m.Configurada ? "Sin configurar"
                : !m.Destino(out h, out pu) ? "Dato no válido"
                : m.Estado == "ok" ? "Responde"
                : m.Estado == "mal" ? "No responde"
                : m.Estado == "revisando" ? "Revisando…" : "";
            it.SubItems[2].Text = !m.Configurada ? "" : m.Protocolo == "rdp" ? "RDP" : m.Protocolo == "url" ? "Web" : "VNC";
            it.SubItems[3].Text = !m.Configurada ? "" : m.Protocolo == "url" ? m.Url : m.Host + ":" + m.PuertoEfectivo;
            it.SubItems[4].Text = m.Nota;
            it.ImageKey = m.Configurada && m.Estado != "" ? m.Estado : "nada";
            it.ToolTipText = m.Configurada && m.Revisado != DateTime.MinValue ? "Revisado a las " + m.Revisado.ToString("HH:mm:ss") : "";
        }

        Maquina Seleccionada()
        {
            var lv = tabs.SelectedTab == null ? null : tabs.SelectedTab.Controls.OfType<ListView>().FirstOrDefault();
            if (lv == null || lv.SelectedItems.Count == 0) { lblEstado.Text = "Elige una máquina de la lista."; return null; }
            return (Maquina)lv.SelectedItems[0].Tag;
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

        void Refrescar(Maquina m)
        {
            foreach (TabPage tp in tabs.TabPages)
                foreach (ListView lv in tp.Controls.OfType<ListView>())
                    foreach (ListViewItem it in lv.Items)
                        if (it.Tag == m) Pintar(it);
        }

        void RevisarTodo()
        {
            var todas = datos.Values.SelectMany(x => x).Where(x => x.Configurada).ToList();
            if (todas.Count == 0) { lblEstado.Text = "Ninguna máquina tiene conexión configurada todavía. Elige una y pulsa «Configurar»."; return; }
            foreach (Maquina m in todas) Revisar(m);
        }

        void Revisar(Maquina m)
        {
            string host; int puerto;
            if (!m.Configurada || !m.Destino(out host, out puerto)) { m.Estado = m.Configurada ? "mal" : ""; Refrescar(m); return; }   // dato no válido: en rojo
            if (m.Estado == "") { m.Estado = "revisando"; Refrescar(m); }
            Interlocked.Increment(ref revisando);
            ThreadPool.QueueUserWorkItem(_ =>
            {
                bool ok = Red.Responde(host, puerto, Cfg.Espera);
                try
                {
                    BeginInvoke(new Action(() =>
                    {
                        m.Estado = ok ? "ok" : "mal";
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
            string h; int pu;
            int malas = todas.Count(x => !x.Destino(out h, out pu));
            lblEstado.Text = "Revisado a las " + DateTime.Now.ToString("HH:mm:ss") + ": " + todas.Count(x => x.Estado == "ok") + " responden, " +
                             (todas.Count(x => x.Estado == "mal") - malas) + " no responden" + (malas > 0 ? ", " + malas + " con dato no válido" : "") + ". Se revisa cada " + Cfg.Intervalo + " s. Doble clic para conectar.";
        }
    }

    static class Program
    {
        [STAThread]
        static int Main(string[] args)
        {
            Cfg.Cargar();
            if (args.Length > 0 && args[0] == "--prueba") return Prueba();
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new FormPrincipal());
            return 0;
        }

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
