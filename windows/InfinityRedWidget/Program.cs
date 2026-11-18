using System.Threading;
using Microsoft.Windows.Widgets.Providers;

namespace InfinityRedWidget;

internal static class Program
{
    [MTAThread]
    private static void Main(string[] args)
    {
        if (args.Length == 0 || args[0] != "-RegisterProcessAsComServer")
        {
            return;
        }

        WinRT.ComWrappersSupport.InitializeComWrappers();
        using var registration = RegistrationManager<WidgetProvider>.RegisterProvider();
        new ManualResetEvent(false).WaitOne();
    }
}
