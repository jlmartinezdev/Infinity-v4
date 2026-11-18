using System.Text.Json.Nodes;
using Windows.Storage;

namespace InfinityRedWidget;

internal sealed class WidgetSettings
{
    public string ApiBaseUrl { get; init; } = "";

    public string Token { get; init; } = "";

    public static WidgetSettings Load()
    {
        var api = "";
        var token = "";
        foreach (var path in CandidateFiles())
        {
            if (!File.Exists(path))
            {
                continue;
            }

            try
            {
                var json = JsonNode.Parse(File.ReadAllText(path))?.AsObject();
                if (string.IsNullOrWhiteSpace(api))
                {
                    api = Read(json, "apiBaseUrl");
                }

                if (string.IsNullOrWhiteSpace(token))
                {
                    token = Read(json, "token");
                }
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine("widget red settings: " + ex.GetType().Name);
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

        return new WidgetSettings
        {
            ApiBaseUrl = api.Trim(),
            Token = token.Trim(),
        };
    }

    private static IEnumerable<string> CandidateFiles()
    {
        yield return Path.Combine(AppContext.BaseDirectory, "settings.json");

        string? packaged = null;
        try
        {
            packaged = Path.Combine(ApplicationData.Current.LocalFolder.Path, "settings.json");
        }
        catch
        {
            packaged = null;
        }

        if (!string.IsNullOrEmpty(packaged))
        {
            yield return packaged;
        }

        var local = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        if (!string.IsNullOrEmpty(local))
        {
            yield return Path.Combine(local, "InfinityRedWidget", "settings.json");
        }
    }

    private static string Read(JsonObject? json, string key)
    {
        var node = json?[key];
        if (node is JsonValue value && value.TryGetValue<string>(out var text))
        {
            return text ?? "";
        }

        return "";
    }
}
