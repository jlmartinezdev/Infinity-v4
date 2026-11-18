using System.Runtime.InteropServices;
using Microsoft.Windows.Widgets.Providers;

namespace InfinityRedWidget;

[ComVisible(true)]
[ComDefaultInterface(typeof(IWidgetProvider))]
[Guid("B7E4C1A2-6F38-4D0E-9A55-1C8F0E2D4A77")]
public sealed class WidgetProvider : IWidgetProvider
{
    private static readonly Dictionary<string, RedEstadoWidget> Widgets = new();
    private static bool _recovered;

    public WidgetProvider()
    {
        Recover();
    }

    public void CreateWidget(WidgetContext widgetContext)
    {
        if (widgetContext.DefinitionId != RedEstadoWidget.DefinitionId)
        {
            throw new InvalidOperationException("Definición de widget desconocida.");
        }

        var widget = new RedEstadoWidget(widgetContext.Id);
        Widgets[widgetContext.Id] = widget;
        widget.Push();
    }

    public void DeleteWidget(string widgetId, string customState)
    {
        if (Widgets.Remove(widgetId, out var widget))
        {
            widget.Deactivate();
        }
    }

    public void OnActionInvoked(WidgetActionInvokedArgs actionInvokedArgs)
    {
        if (Widgets.TryGetValue(actionInvokedArgs.WidgetContext.Id, out var widget))
        {
            widget.OnActionInvoked(actionInvokedArgs);
        }
    }

    public void OnWidgetContextChanged(WidgetContextChangedArgs contextChangedArgs)
    {
        if (Widgets.TryGetValue(contextChangedArgs.WidgetContext.Id, out var widget))
        {
            widget.OnWidgetContextChanged(contextChangedArgs);
        }
    }

    public void Activate(WidgetContext widgetContext)
    {
        if (!Widgets.TryGetValue(widgetContext.Id, out var widget))
        {
            widget = new RedEstadoWidget(widgetContext.Id);
            Widgets[widgetContext.Id] = widget;
        }

        widget.Activate(widgetContext);
    }

    public void Deactivate(string widgetId)
    {
        if (Widgets.TryGetValue(widgetId, out var widget))
        {
            widget.Deactivate();
        }
    }

    private static void Recover()
    {
        if (_recovered)
        {
            return;
        }

        _recovered = true;
        try
        {
            foreach (var info in WidgetManager.GetDefault().GetWidgetInfos())
            {
                var context = info.WidgetContext;
                if (context.DefinitionId != RedEstadoWidget.DefinitionId || Widgets.ContainsKey(context.Id))
                {
                    continue;
                }

                Widgets[context.Id] = new RedEstadoWidget(context.Id);
            }
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine("widget red: " + ex.GetType().Name);
        }
    }
}
