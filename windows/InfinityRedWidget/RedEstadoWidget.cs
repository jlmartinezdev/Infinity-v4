using Microsoft.Windows.Widgets.Providers;
using Windows.Storage;

namespace InfinityRedWidget;

internal sealed class RedEstadoWidget
{
    public const string DefinitionId = "Infinity_Red_Estado";

    private static string? _template;
    private readonly string _id;
    private Timer? _timer;
    private int _busy;
    private bool _active;

    public RedEstadoWidget(string id)
    {
        _id = id;
    }

    public void Activate(WidgetContext widgetContext)
    {
        _active = true;
        _timer?.Dispose();
        _timer = new Timer(_ => Tick(), null, TimeSpan.Zero, TimeSpan.FromMinutes(1));
    }

    public void Deactivate()
    {
        _active = false;
        _timer?.Dispose();
        _timer = null;
    }

    public void OnActionInvoked(WidgetActionInvokedArgs actionInvokedArgs)
    {
    }

    public void OnWidgetContextChanged(WidgetContextChangedArgs contextChangedArgs)
    {
        Push();
    }

    public void Push()
    {
        try
        {
            var update = new WidgetUpdateRequestOptions(_id)
            {
                Template = Template(),
                Data = WidgetRedClient.BuildData(),
                CustomState = string.Empty,
            };
            WidgetManager.GetDefault().UpdateWidget(update);
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine("widget red: " + ex.GetType().Name);
        }
    }

    private void Tick()
    {
        if (!_active)
        {
            return;
        }

        if (Interlocked.Exchange(ref _busy, 1) == 1)
        {
            return;
        }

        try
        {
            Push();
        }
        finally
        {
            Interlocked.Exchange(ref _busy, 0);
        }
    }

    private static string Template()
    {
        if (!string.IsNullOrEmpty(_template))
        {
            return _template;
        }

        try
        {
            var file = StorageFile.GetFileFromApplicationUriAsync(new Uri("ms-appx:///Templates/RedEstado.json")).AsTask().GetAwaiter().GetResult();
            _template = FileIO.ReadTextAsync(file).AsTask().GetAwaiter().GetResult();
        }
        catch
        {
            _template = File.ReadAllText(Path.Combine(AppContext.BaseDirectory, "Templates", "RedEstado.json"));
        }

        return _template;
    }
}
