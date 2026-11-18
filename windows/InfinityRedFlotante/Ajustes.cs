using System.Text.Json;
using System.Text.Json.Nodes;
using Microsoft.Win32;

namespace InfinityRedFlotante;

/// <summary>
/// URL y token. Mismo orden que el widget de Win+W: variables de entorno, después
/// settings.json junto al exe y en %LocalAppData%\InfinityRedWidget.
/// </summary>
internal sealed class Ajustes
{
    public string ApiBaseUrl { get; private init; } = "";

    public string Token { get; private init; } = "";

    public static string Carpeta => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "InfinityRedWidget");

    public static Ajustes Cargar()
    {
        var api = "";
        var token = "";
        foreach (var path in new[] { Path.Combine(AppContext.BaseDirectory, "settings.json"), Path.Combine(Carpeta, "settings.json") })
        {
            if (!File.Exists(path))
            {
                continue;
            }

            try
            {
                var json = JsonNode.Parse(File.ReadAllText(path)) as JsonObject;
                if (string.IsNullOrWhiteSpace(api))
                {
                    api = Leer(json, "apiBaseUrl");
                }

                if (string.IsNullOrWhiteSpace(token))
                {
                    token = Leer(json, "token");
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine("red flotante settings: " + ex.GetType().Name);
            }
        }

        var envApi = Environment.GetEnvironmentVariable("INFINITY_WIDGET_API_BASE");
        var envToken = Environment.GetEnvironmentVariable("INFINITY_WIDGET_TOKEN");
        if (!string.IsNullOrWhiteSpace(envApi))
        {
            api = envApi;
        }

        if (!string.IsNullOrWhiteSpace(envToken))
        {
            token = envToken;
        }

        return new Ajustes { ApiBaseUrl = api.Trim(), Token = token.Trim() };
    }

    private static string Leer(JsonObject? json, string clave)
    {
        if (json?[clave] is JsonValue valor && valor.TryGetValue<string>(out var texto))
        {
            return texto ?? "";
        }

        return "";
    }
}

/// <summary>Posición y modo compacto de la ventana, en %LocalAppData%\InfinityRedWidget\flotante.json.</summary>
internal sealed class Preferencias
{
    public int? X { get; set; }

    public int? Y { get; set; }

    public bool Compacto { get; set; }

    private static string Archivo => Path.Combine(Ajustes.Carpeta, "flotante.json");

    public static Preferencias Cargar()
    {
        try
        {
            if (File.Exists(Archivo))
            {
                return JsonSerializer.Deserialize<Preferencias>(File.ReadAllText(Archivo)) ?? new Preferencias();
            }
        }
        catch (Exception ex)
        {
            System.Diagnostics.Debug.WriteLine("red flotante preferencias: " + ex.GetType().Name);
        }

        return new Preferencias();
    }

    public void Guardar()
    {
        try
        {
            Directory.CreateDirectory(Ajustes.Carpeta);
            File.WriteAllText(Archivo, JsonSerializer.Serialize(this));
        }
        catch (Exception ex)
        {
            System.Diagnostics.Debug.WriteLine("red flotante preferencias: " + ex.GetType().Name);
        }
    }
}

/// <summary>Arranque con Windows por la clave Run del usuario.</summary>
internal static class Inicio
{
    private const string Clave = @"Software\Microsoft\Windows\CurrentVersion\Run";
    private const string Nombre = "InfinityRedFlotante";

    public static bool Activo()
    {
        using var run = Registry.CurrentUser.OpenSubKey(Clave);
        return run?.GetValue(Nombre) is string;
    }

    public static void Poner(bool activo)
    {
        using var run = Registry.CurrentUser.CreateSubKey(Clave);
        if (activo && Environment.ProcessPath is { } exe)
        {
            run.SetValue(Nombre, "\"" + exe + "\"");
        }
        else
        {
            run.DeleteValue(Nombre, false);
        }
    }
}
