using System.Net;
using System.Net.Http.Headers;
using System.Text.Json.Nodes;

namespace InfinityRedFlotante;

/// <summary>
/// Pide GET {apiBaseUrl}/api/v1/widget/red con el token. Devuelve el JSON tal cual
/// o {"error": "..."} para que la vista muestre el aviso.
/// </summary>
internal static class ClienteRed
{
    private static readonly HttpClient Http = new() { Timeout = TimeSpan.FromSeconds(20) };

    public static async Task<JsonNode> ObtenerAsync()
    {
        try
        {
            var ajustes = Ajustes.Cargar();
            if (string.IsNullOrWhiteSpace(ajustes.ApiBaseUrl) || string.IsNullOrWhiteSpace(ajustes.Token))
            {
                return Error("Falta la URL o el token");
            }

            var baseUrl = ajustes.ApiBaseUrl.TrimEnd('/');
            if (!baseUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase)
                && !baseUrl.StartsWith("http://", StringComparison.OrdinalIgnoreCase))
            {
                return Error("URL inválida");
            }

            using var pedido = new HttpRequestMessage(HttpMethod.Get, baseUrl + "/api/v1/widget/red");
            pedido.Headers.Authorization = new AuthenticationHeaderValue("Bearer", ajustes.Token);
            pedido.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

            using var respuesta = await Http.SendAsync(pedido);
            if (respuesta.StatusCode == HttpStatusCode.Unauthorized)
            {
                return Error("Token rechazado");
            }

            if (!respuesta.IsSuccessStatusCode)
            {
                return Error("Sin conexión");
            }

            var cuerpo = await respuesta.Content.ReadAsStringAsync();
            return JsonNode.Parse(cuerpo) as JsonObject ?? Error("Respuesta inválida");
        }
        catch (Exception ex)
        {
            System.Diagnostics.Debug.WriteLine("red flotante: " + ex.GetType().Name);
            return Error("Sin conexión");
        }
    }

    private static JsonObject Error(string mensaje) => new() { ["error"] = mensaje };
}
