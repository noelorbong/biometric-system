using System;
using System.ComponentModel;
using System.IO;
using System.Runtime.InteropServices;

// Each bridge process owns one SDK instance. Keep the DLL loaded until process
// exit: releasing it earlier could invalidate COM wrappers/finalizers.
public static class BundledZktecoSdk
{
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern IntPtr LoadLibraryEx(string path, IntPtr file, uint flags);

    [DllImport("kernel32.dll", CharSet = CharSet.Ansi, SetLastError = true)]
    private static extern IntPtr GetProcAddress(IntPtr module, string name);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool SetDllDirectory(string path);

    [UnmanagedFunctionPointer(CallingConvention.StdCall)]
    private delegate int GetClassObject(ref Guid clsid, ref Guid iid, out IntPtr factory);

    [ComImport, Guid("00000001-0000-0000-C000-000000000046"),
     InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
    private interface IClassFactory
    {
        [PreserveSig]
        int CreateInstance(IntPtr outer, ref Guid iid, out IntPtr instance);
        [PreserveSig]
        int LockServer([MarshalAs(UnmanagedType.Bool)] bool value);
    }

    public static object Create(string directory)
    {
        if (IntPtr.Size != 4)
            throw new InvalidOperationException("The bundled ZKTeco SDK needs 32-bit Windows PowerShell.");
        directory = Path.GetFullPath(directory);
        if (!SetDllDirectory(directory))
            throw new Win32Exception(Marshal.GetLastWin32Error());
        IntPtr module = LoadLibraryEx(Path.Combine(directory, "zkemkeeper.dll"), IntPtr.Zero, 0x1100);
        if (module == IntPtr.Zero)
            throw new Win32Exception(Marshal.GetLastWin32Error(), "Cannot load the bundled ZKTeco SDK or its dependencies.");
        IntPtr entry = GetProcAddress(module, "DllGetClassObject");
        if (entry == IntPtr.Zero)
            throw new Win32Exception(Marshal.GetLastWin32Error());
        var getFactory = (GetClassObject)Marshal.GetDelegateForFunctionPointer(entry, typeof(GetClassObject));
        Guid clsid = new Guid("00853A19-BD51-419B-9269-2DABE57EB61F");
        Guid factoryId = new Guid("00000001-0000-0000-C000-000000000046");
        Guid dispatchId = new Guid("00020400-0000-0000-C000-000000000046");
        IntPtr factoryPointer;
        Marshal.ThrowExceptionForHR(getFactory(ref clsid, ref factoryId, out factoryPointer));
        IClassFactory factory = null;
        try
        {
            factory = (IClassFactory)Marshal.GetObjectForIUnknown(factoryPointer);
            IntPtr instance;
            Marshal.ThrowExceptionForHR(factory.CreateInstance(IntPtr.Zero, ref dispatchId, out instance));
            try { return Marshal.GetObjectForIUnknown(instance); }
            finally { Marshal.Release(instance); }
        }
        finally
        {
            if (factory != null) Marshal.FinalReleaseComObject(factory);
            Marshal.Release(factoryPointer);
        }
    }
}
