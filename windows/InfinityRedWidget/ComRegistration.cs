using System.Runtime.InteropServices;
using Microsoft.Windows.Widgets.Providers;
using WinRT;

namespace InfinityRedWidget;

internal static class ComGuids
{
    public const string IClassFactory = "00000001-0000-0000-C000-000000000046";
    public const string IUnknown = "00000000-0000-0000-C000-000000000046";
}

[ComImport]
[InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
[Guid(ComGuids.IClassFactory)]
internal interface IClassFactory
{
    [PreserveSig]
    int CreateInstance(IntPtr pUnkOuter, ref Guid riid, out IntPtr ppvObject);

    [PreserveSig]
    int LockServer(bool fLock);
}

internal static class ComClassObject
{
    public static void Register(Guid clsid, object factory, out uint cookie)
    {
        [DllImport("ole32.dll")]
        static extern int CoRegisterClassObject(
            [MarshalAs(UnmanagedType.LPStruct)] Guid rclsid,
            [MarshalAs(UnmanagedType.IUnknown)] object pUnk,
            uint dwClsContext,
            uint flags,
            out uint lpdwRegister);

        var hr = CoRegisterClassObject(clsid, factory, 0x4, 0x1, out cookie);
        if (hr != 0)
        {
            Marshal.ThrowExceptionForHR(hr);
        }
    }

    public static void Revoke(uint cookie)
    {
        [DllImport("ole32.dll")]
        static extern int CoRevokeClassObject(uint dwRegister);

        CoRevokeClassObject(cookie);
    }
}

internal sealed class WidgetProviderFactory<T> : IClassFactory
    where T : IWidgetProvider, new()
{
    private const int ClassENoAggregation = unchecked((int)0x80040110);
    private const int ENoInterface = unchecked((int)0x80004002);

    public int CreateInstance(IntPtr pUnkOuter, ref Guid riid, out IntPtr ppvObject)
    {
        ppvObject = IntPtr.Zero;
        if (pUnkOuter != IntPtr.Zero)
        {
            Marshal.ThrowExceptionForHR(ClassENoAggregation);
        }

        if (riid == typeof(T).GUID || riid == Guid.Parse(ComGuids.IUnknown))
        {
            ppvObject = MarshalInspectable<IWidgetProvider>.FromManaged(new T());
            return 0;
        }

        Marshal.ThrowExceptionForHR(ENoInterface);
        return ENoInterface;
    }

    int IClassFactory.LockServer(bool fLock) => 0;
}

internal sealed class RegistrationManager<T> : IDisposable
    where T : IWidgetProvider, new()
{
    private uint _cookie;
    private bool _disposed;

    private RegistrationManager(uint cookie)
    {
        _cookie = cookie;
    }

    public static RegistrationManager<T> RegisterProvider()
    {
        ComClassObject.Register(typeof(T).GUID, new WidgetProviderFactory<T>(), out var cookie);
        return new RegistrationManager<T>(cookie);
    }

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        if (_cookie != 0)
        {
            ComClassObject.Revoke(_cookie);
            _cookie = 0;
        }
    }
}
