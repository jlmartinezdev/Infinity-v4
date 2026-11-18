using System.Runtime.InteropServices;
using System.Text.Json.Nodes;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace InfinityRedFlotante;

/// <summary>
/// Ventana chica sin bordes, siempre encima, anclada a una esquina de la pantalla.
/// Dibuja wwwroot/mini.html en WebView2 y le pasa los datos de la API cada minuto.
/// </summary>
internal sealed class VentanaRed : Form
{
    private const int AnchoCss = 300;
    private const int MargenCss = 12;
    private const int ImanCss = 24;
    private const string Host = "red.infinity.local";

    private const int WS_EX_TOOLWINDOW = 0x80;
    private const int CS_DROPSHADOW = 0x20000;
    private const int WM_NCLBUTTONDOWN = 0xA1;
    private const int HTCAPTION = 2;
    private const int DWMWA_WINDOW_CORNER_PREFERENCE = 33;
    private const int DWMWCP_ROUND = 2;

    private readonly WebView2 _web = new() { Dock = DockStyle.Fill };
    private readonly NotifyIcon _bandeja = new();
    private readonly System.Windows.Forms.Timer _timer = new() { Interval = 60_000 };
    private readonly Preferencias _pref = Preferencias.Cargar();
    private bool _listo;
    private bool _actualizando;
    private bool _saliendo;
    private int _altoCss = 90;

    public VentanaRed()
    {
        Text = "Red Infinity";
        FormBorderStyle = FormBorderStyle.None;
        ShowInTaskbar = false;
        TopMost = true;
        StartPosition = FormStartPosition.Manual;
        AutoScaleMode = AutoScaleMode.None;
        BackColor = Color.FromArgb(0x20, 0x20, 0x24);
        _web.DefaultBackgroundColor = BackColor;
        Controls.Add(_web);

        ArmarBandeja();
        _timer.Tick += async (_, _) => await ActualizarAsync();
        Microsoft.Win32.SystemEvents.DisplaySettingsChanged += AlCambiarPantallas;
    }

    protected override CreateParams CreateParams
    {
        get
        {
            var cp = base.CreateParams;
            cp.ExStyle |= WS_EX_TOOLWINDOW;
            cp.ClassStyle |= CS_DROPSHADOW;
            return cp;
        }
    }

    protected override async void OnLoad(EventArgs e)
    {
        base.OnLoad(e);
        var esquina = DWMWCP_ROUND;
        _ = DwmSetWindowAttribute(Handle, DWMWA_WINDOW_CORNER_PREFERENCE, ref esquina, sizeof(int));

        Size = new Size(Escalar(AnchoCss), Escalar(_altoCss));
        UbicarInicial();
        await IniciarWebAsync();
        _timer.Start();
    }

    protected override void OnDpiChanged(DpiChangedEventArgs e)
    {
        base.OnDpiChanged(e);
        Size = new Size(Escalar(AnchoCss), Escalar(_altoCss));
    }

    protected override void OnVisibleChanged(EventArgs e)
    {
        base.OnVisibleChanged(e);
        if (Visible)
        {
            _ = ActualizarAsync();
        }
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_saliendo && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;
            Hide();
            return;
        }

        base.OnFormClosing(e);
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            Microsoft.Win32.SystemEvents.DisplaySettingsChanged -= AlCambiarPantallas;
            _timer.Dispose();
            _bandeja.Visible = false;
            _bandeja.Dispose();
        }

        base.Dispose(disposing);
    }

    private async Task IniciarWebAsync()
    {
        try
        {
            var entorno = await CoreWebView2Environment.CreateAsync(null, Path.Combine(Ajustes.Carpeta, "WebView2"));
            await _web.EnsureCoreWebView2Async(entorno);
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                "No se pudo abrir la vista. Instalá Microsoft Edge WebView2 Runtime.\n\n" + ex.Message,
                "Red Infinity",
                MessageBoxButtons.OK,
                MessageBoxIcon.Warning);
            Salir();
            return;
        }

        var core = _web.CoreWebView2;
        core.Settings.AreDefaultContextMenusEnabled = false;
        core.Settings.AreDevToolsEnabled = false;
        core.Settings.IsZoomControlEnabled = false;
        core.Settings.IsStatusBarEnabled = false;
        core.Settings.AreBrowserAcceleratorKeysEnabled = false;
        core.SetVirtualHostNameToFolderMapping(
            Host,
            Path.Combine(AppContext.BaseDirectory, "wwwroot"),
            CoreWebView2HostResourceAccessKind.Deny);
        core.NewWindowRequested += (_, a) => a.Handled = true;
        core.WebMessageReceived += AlRecibirMensaje;
        core.Navigate("https://" + Host + "/mini.html");
    }

    private void AlRecibirMensaje(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
    {
        if (!e.Source.StartsWith("https://" + Host + "/", StringComparison.OrdinalIgnoreCase))
        {
            return;
        }

        string? accion;
        JsonObject? mensaje;
        try
        {
            mensaje = JsonNode.Parse(e.WebMessageAsJson) as JsonObject;
            accion = mensaje?["accion"]?.GetValue<string>();
        }
        catch
        {
            return;
        }

        switch (accion)
        {
            case "listo":
                _listo = true;
                Enviar(new JsonObject { ["compacto"] = _pref.Compacto });
                _ = ActualizarAsync();
                break;
            case "alto":
                if (mensaje?["alto"] is JsonValue valor && valor.TryGetValue<double>(out var alto))
                {
                    CambiarAlto((int)Math.Ceiling(alto));
                }

                break;
            case "arrastrar":
                Arrastrar();
                break;
            case "ocultar":
                Hide();
                break;
            case "compacto":
                _pref.Compacto = mensaje?["valor"] is JsonValue v && v.TryGetValue<bool>(out var compacto) && compacto;
                _pref.Guardar();
                break;
        }
    }

    private async Task ActualizarAsync()
    {
        if (!_listo || _actualizando || !Visible)
        {
            return;
        }

        _actualizando = true;
        try
        {
            var datos = await ClienteRed.ObtenerAsync();
            Enviar(new JsonObject { ["datos"] = datos });
        }
        finally
        {
            _actualizando = false;
        }
    }

    private void Enviar(JsonObject mensaje)
    {
        _web.CoreWebView2?.PostWebMessageAsJson(mensaje.ToJsonString());
    }

    private void CambiarAlto(int altoCss)
    {
        altoCss = Math.Clamp(altoCss, 40, 900);
        var alto = Escalar(altoCss);
        _altoCss = altoCss;
        if (alto == Height)
        {
            return;
        }

        var area = Screen.FromControl(this).WorkingArea;
        var anclaAbajo = Top + Height / 2 > area.Top + area.Height / 2;
        var abajo = Bottom;
        Height = alto;
        if (anclaAbajo)
        {
            Top = abajo - alto;
        }

        Encajar(false);
    }

    private void Arrastrar()
    {
        ReleaseCapture();
        _ = SendMessage(Handle, WM_NCLBUTTONDOWN, (IntPtr)HTCAPTION, IntPtr.Zero);
        Encajar(true);
    }

    /// <summary>Mete la ventana en el área de trabajo y la pega al borde si quedó cerca.</summary>
    private void Encajar(bool guardar)
    {
        var area = Screen.FromControl(this).WorkingArea;
        var margen = Escalar(MargenCss);
        var iman = Escalar(ImanCss);
        var x = Math.Clamp(Left, area.Left, Math.Max(area.Left, area.Right - Width));
        var y = Math.Clamp(Top, area.Top, Math.Max(area.Top, area.Bottom - Height));

        if (x - area.Left < iman)
        {
            x = area.Left + margen;
        }
        else if (area.Right - (x + Width) < iman)
        {
            x = area.Right - Width - margen;
        }

        if (y - area.Top < iman)
        {
            y = area.Top + margen;
        }
        else if (area.Bottom - (y + Height) < iman)
        {
            y = area.Bottom - Height - margen;
        }

        Location = new Point(x, y);
        if (guardar)
        {
            _pref.X = x;
            _pref.Y = y;
            _pref.Guardar();
        }
    }

    private void UbicarInicial()
    {
        if (_pref.X is int x && _pref.Y is int y)
        {
            var rect = new Rectangle(x, y, Width, Height);
            if (Screen.AllScreens.Any(s => s.WorkingArea.IntersectsWith(rect)))
            {
                Location = new Point(x, y);
                Encajar(false);
                return;
            }
        }

        IrAEsquina("abajo-derecha");
    }

    private void IrAEsquina(string esquina)
    {
        var area = Screen.FromControl(this).WorkingArea;
        var margen = Escalar(MargenCss);
        var x = esquina.EndsWith("izquierda", StringComparison.Ordinal) ? area.Left + margen : area.Right - Width - margen;
        var y = esquina.StartsWith("arriba", StringComparison.Ordinal) ? area.Top + margen : area.Bottom - Height - margen;
        Location = new Point(x, y);
        _pref.X = x;
        _pref.Y = y;
        _pref.Guardar();
    }

    private void AlCambiarPantallas(object? sender, EventArgs e)
    {
        BeginInvoke(() => Encajar(true));
    }

    private void ArmarBandeja()
    {
        _bandeja.Text = "Red Infinity";
        _bandeja.Icon = CargarIcono();

        var menu = new ContextMenuStrip();
        menu.Items.Add("Mostrar", null, (_, _) => Mostrar());
        menu.Items.Add("Actualizar ahora", null, async (_, _) => await ActualizarAsync());

        var esquinas = new ToolStripMenuItem("Mover a la esquina");
        foreach (var (texto, clave) in new[]
                 {
                     ("Abajo a la derecha", "abajo-derecha"),
                     ("Abajo a la izquierda", "abajo-izquierda"),
                     ("Arriba a la derecha", "arriba-derecha"),
                     ("Arriba a la izquierda", "arriba-izquierda"),
                 })
        {
            esquinas.DropDownItems.Add(texto, null, (_, _) =>
            {
                Mostrar();
                IrAEsquina(clave);
            });
        }

        menu.Items.Add(esquinas);

        var inicio = new ToolStripMenuItem("Iniciar con Windows") { CheckOnClick = true, Checked = Inicio.Activo() };
        inicio.CheckedChanged += (_, _) => Inicio.Poner(inicio.Checked);
        menu.Items.Add(inicio);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Salir", null, (_, _) => Salir());

        _bandeja.ContextMenuStrip = menu;
        _bandeja.MouseClick += (_, a) =>
        {
            if (a.Button != MouseButtons.Left)
            {
                return;
            }

            if (Visible)
            {
                Hide();
            }
            else
            {
                Mostrar();
            }
        };
        _bandeja.Visible = true;
    }

    private void Mostrar()
    {
        Show();
        TopMost = true;
        Encajar(false);
    }

    private void Salir()
    {
        _saliendo = true;
        _bandeja.Visible = false;
        Application.Exit();
    }

    private int Escalar(int css) => (int)Math.Round(css * DeviceDpi / 96.0);

    private static Icon CargarIcono()
    {
        try
        {
            var path = Path.Combine(AppContext.BaseDirectory, "RedIcon.png");
            using var original = Image.FromFile(path);
            using var bmp = new Bitmap(original, new Size(32, 32));
            return Icon.FromHandle(bmp.GetHicon());
        }
        catch
        {
            return SystemIcons.Application;
        }
    }

    [DllImport("user32.dll")]
    private static extern bool ReleaseCapture();

    [DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hWnd, int msg, IntPtr wParam, IntPtr lParam);

    [DllImport("dwmapi.dll")]
    private static extern int DwmSetWindowAttribute(IntPtr hwnd, int attribute, ref int value, int size);
}
