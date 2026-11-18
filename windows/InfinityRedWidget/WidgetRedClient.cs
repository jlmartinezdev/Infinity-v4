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

    private static JsonObject Map(JsonObject root)
    {
        var counts = root["counts"]?.AsObject();
        var ap = counts?["ap"]?.AsObject();
        var router = counts?["router"]?.AsObject();
        var olt = counts?["olt"]?.AsObject();
        var caidas = root["caidas"]?.AsArray();
        var total = caidas?.Count ?? 0;

        var data = Base(total == 0 ? "Sin caídas" : total == 1 ? "1 caída" : total + " caídas", "en vivo", "");
        FillTipo(data, "ap", ReadInt(ap, "online"), ReadInt(ap, "offline"), ReadInt(ap, "unknown"));
        FillTipo(data, "router", ReadInt(router, "online"), ReadInt(router, "offline"), ReadInt(router, "unknown"));
        FillTipo(data, "olt", ReadInt(olt, "online"), ReadInt(olt, "offline"), ReadInt(olt, "unknown"));
        data["sinCaidas"] = total == 0 ? "si" : "no";

        var lista = new JsonArray();
        var shown = 0;
        if (caidas != null)
        {
            foreach (var item in caidas)
            {
                if (shown >= 8)
                {
                    break;
                }

                if (item is not JsonObject)
                {
                    continue;
                }

                var row = item.AsObject();
                lista.Add(new JsonObject
                {
                    ["tipo"] = Etiqueta(Str(row, "type")),
                    ["nombre"] = Str(row, "name") ?? "",
                    ["nodo"] = Str(row, "nodo") ?? "",
                    ["hace"] = Hace(Str(row, "since")),
                });
                shown++;
            }
        }

        data["caidas"] = lista;
        data["masCaidas"] = total > shown ? "y " + (total - shown) + " más" : "";
        return data;
    }

    private static void FillTipo(JsonObject data, string prefix, int online, int offline, int unknown)
    {
        var sindato = online == 0 && offline == 0;
        data[prefix + "Modo"] = sindato ? "sindato" : "conteo";
        data[prefix + "Online"] = online.ToString();
        data[prefix + "Offline"] = offline.ToString();
        data[prefix + "Texto"] = unknown > 0 ? unknown + " sin dato" : "sin dato";
    }

    private static JsonObject Base(string resumen, string subtitulo, string aviso)
    {
        return new JsonObject
        {
            ["subtitulo"] = subtitulo,
            ["aviso"] = aviso,
            ["resumenCaidas"] = resumen,
            ["sinCaidas"] = aviso == "" ? "si" : "no",
            ["caidas"] = new JsonArray(),
            ["masCaidas"] = "",
            ["apModo"] = "sindato",
            ["apOnline"] = "0",
            ["apOffline"] = "0",
            ["apTexto"] = "sin dato",
            ["routerModo"] = "sindato",
            ["routerOnline"] = "0",
            ["routerOffline"] = "0",
            ["routerTexto"] = "sin dato",
            ["oltModo"] = "sindato",
            ["oltOnline"] = "0",
            ["oltOffline"] = "0",
            ["oltTexto"] = "sin dato",
        };
    }

    private static string ErrorData(string mensaje)
    {
        var data = Base(mensaje, mensaje, mensaje);
        data["apTexto"] = "—";
        data["routerTexto"] = "—";
        data["oltTexto"] = "sin dato";
        return data.ToJsonString();
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
