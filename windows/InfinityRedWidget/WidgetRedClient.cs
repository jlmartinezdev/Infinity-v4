using System.Net.Http.Headers;
using System.Text.Json.Nodes;

namespace InfinityRedWidget;

internal static class WidgetRedClient
{
    private static readonly HttpClient Http = new()
    {
        Timeout = TimeSpan.FromSeconds(20),
    };

    public static string BuildData()
    {
        try
        {
            var settings = WidgetSettings.Load();
            if (string.IsNullOrWhiteSpace(settings.ApiBaseUrl) || string.IsNullOrWhiteSpace(settings.Token))
            {
                return ErrorData("Falta la URL o el token");
            }

            var baseUrl = settings.ApiBaseUrl.Trim().TrimEnd('/');
            if (!baseUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase)
                && !baseUrl.StartsWith("http://", StringComparison.OrdinalIgnoreCase))
            {
                return ErrorData("URL inválida");
            }

            using var request = new HttpRequestMessage(HttpMethod.Get, baseUrl + "/api/v1/widget/red");
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", settings.Token.Trim());
            request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

            using var response = Http.Send(request);
            if (!response.IsSuccessStatusCode)
            {
                return ErrorData("Sin conexión");
            }

            var body = response.Content.ReadAsStringAsync().GetAwaiter().GetResult();
            var root = JsonNode.Parse(body)?.AsObject();
            if (root == null)
            {
                return ErrorData("Respuesta inválida");
            }

            return Map(root).ToJsonString();
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine("widget red: " + ex.GetType().Name);
            return ErrorData("Sin conexión");
        }
    }

    private const int MaxCaidasLarge = 6;
    private const int MaxCaidasMedium = 2;

    private static readonly string[] Claves = { "ap", "router", "olt" };

    private static JsonObject Map(JsonObject root)
    {
        var counts = root["counts"] as JsonObject;
        var caidas = root["caidas"] as JsonArray;
        var total = caidas?.Count ?? 0;

        var data = Base(
            total == 0 ? "Todo en línea" : total == 1 ? "1 equipo caído" : total + " equipos caídos",
            total == 0 ? "Good" : "Attention",
            "",
            Hora(Str(root, "generated_at")));

        var tipos = new JsonArray();
        foreach (var clave in Claves)
        {
            var tipo = counts?[clave] as JsonObject;
            tipos.Add(Tipo(clave, ReadInt(tipo, "online"), ReadInt(tipo, "offline"), ReadInt(tipo, "unknown")));
        }

        data["tipos"] = tipos;
        data["hayCaidas"] = total > 0 ? "si" : "no";

        var lista = new JsonArray();
        if (caidas != null)
        {
            foreach (var item in caidas)
            {
                if (lista.Count >= MaxCaidasLarge)
                {
                    break;
                }

                if (item is not JsonObject row)
                {
                    continue;
                }

                var etiqueta = Etiqueta(Str(row, "type"));
                var nodo = Str(row, "nodo");
                lista.Add(new JsonObject
                {
                    ["nombre"] = Str(row, "name") ?? etiqueta,
                    ["detalle"] = string.IsNullOrWhiteSpace(nodo) ? etiqueta : etiqueta + " · " + nodo,
                    ["hace"] = Hace(Str(row, "since")),
                });
            }
        }

        data["caidas"] = lista;
        data["masMedium"] = total > MaxCaidasMedium ? "y " + (total - MaxCaidasMedium) + " más" : "";
        data["masLarge"] = total > MaxCaidasLarge ? "y " + (total - MaxCaidasLarge) + " más" : "";
        return data;
    }

    private static JsonObject Tipo(string clave, int online, int offline, int unknown)
    {
        var sinDato = unknown > 0 ? unknown + " sin dato" : "sin dato";
        if (online == 0 && offline == 0)
        {
            return SinDato(clave, sinDato);
        }

        var detalle = offline == 0 ? "en línea" : offline == 1 ? "1 caído" : offline + " caídos";
        if (unknown > 0)
        {
            detalle += " · " + sinDato;
        }

        return new JsonObject
        {
            ["etiqueta"] = Etiqueta(clave),
            ["modo"] = "conteo",
            ["texto"] = "",
            ["online"] = online.ToString(),
            ["offline"] = offline.ToString(),
            ["offlineColor"] = offline > 0 ? "Attention" : "Default",
            ["grande"] = online.ToString(),
            ["grandeColor"] = offline > 0 ? "Default" : "Good",
            ["detalle"] = detalle,
            ["detalleColor"] = offline > 0 ? "Attention" : "Default",
        };
    }

    private static JsonObject SinDato(string clave, string texto)
    {
        return new JsonObject
        {
            ["etiqueta"] = Etiqueta(clave),
            ["modo"] = "sindato",
            ["texto"] = texto,
            ["online"] = "0",
            ["offline"] = "0",
            ["offlineColor"] = "Default",
            ["grande"] = "—",
            ["grandeColor"] = "Default",
            ["detalle"] = texto,
            ["detalleColor"] = "Default",
        };
    }

    private static JsonObject Base(string estado, string color, string detalle, string actualizado)
    {
        var tipos = new JsonArray();
        foreach (var clave in Claves)
        {
            tipos.Add(SinDato(clave, "sin dato"));
        }

        return new JsonObject
        {
            ["estadoTexto"] = estado,
            ["estadoColor"] = color,
            ["estadoDetalle"] = detalle,
            ["actualizado"] = actualizado,
            ["tipos"] = tipos,
            ["hayCaidas"] = "no",
            ["caidas"] = new JsonArray(),
            ["masMedium"] = "",
            ["masLarge"] = "",
        };
    }

    private static string ErrorData(string mensaje)
    {
        return Base(mensaje, "Warning", "Se reintenta en un minuto", "").ToJsonString();
    }

    private static string Hora(string? iso)
    {
        if (string.IsNullOrWhiteSpace(iso) || !DateTimeOffset.TryParse(iso, out var fecha))
        {
            return "";
        }

        return fecha.ToLocalTime().ToString("HH:mm");
    }

    private static int ReadInt(JsonObject? parent, string key)
    {
        var node = parent?[key];
        if (node is JsonValue value && value.TryGetValue<int>(out var number))
        {
            return number;
        }

        return 0;
    }

    private static string? Str(JsonObject row, string key)
    {
        var node = row[key];
        if (node is JsonValue value && value.TryGetValue<string>(out var text))
        {
            return text;
        }

        return null;
    }

    private static string Etiqueta(string? type) => type switch
    {
        "ap" => "AP",
        "router" => "Router",
        "olt" => "OLT",
        _ => type ?? "",
    };

    private static string Hace(string? since)
    {
        if (string.IsNullOrWhiteSpace(since) || !DateTimeOffset.TryParse(since, out var desde))
        {
            return "—";
        }

        var segundos = (int)Math.Max(0, (DateTimeOffset.Now - desde).TotalSeconds);
        if (segundos < 60)
        {
            return "< 1 min";
        }

        var dias = segundos / 86400;
        var horas = (segundos % 86400) / 3600;
        var minutos = (segundos % 3600) / 60;
        var partes = new List<string>();
        if (dias > 0)
        {
            partes.Add(dias + "d");
        }

        if (horas > 0 || dias > 0)
        {
            partes.Add(horas + "h");
        }

        partes.Add(minutos + "m");
        return string.Join(" ", partes);
    }
}
