namespace InfinityRedFlotante;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        using var mutex = new Mutex(true, @"Local\InfinityRedFlotante", out var primero);
        if (!primero)
        {
            return;
        }

        ApplicationConfiguration.Initialize();
        Application.Run(new VentanaRed());
    }
}
