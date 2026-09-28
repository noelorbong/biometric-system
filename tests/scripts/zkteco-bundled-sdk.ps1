# Run in 32-bit Windows PowerShell. No device connection or registry changes.
param([string]$SdkDirectory = (Join-Path $PSScriptRoot '../../resources/sdk/zkteco/x86'))
$ErrorActionPreference = 'Stop'
if ([IntPtr]::Size -ne 4) { throw 'Run this test using SysWOW64 Windows PowerShell.' }
$directory = (Resolve-Path -LiteralPath $SdkDirectory).Path
Add-Type -Path (Join-Path $directory 'BundledSdk.cs')
$device = [BundledZktecoSdk]::Create($directory)
try {
    $version = ''
    if (-not $device.GetSDKVersion([ref]$version)) { throw 'Cannot read SDK version.' }
    $module = [Diagnostics.Process]::GetCurrentProcess().Modules |
        Where-Object ModuleName -eq 'zkemkeeper.dll'
    if ($module.FileName -ne (Join-Path $directory 'zkemkeeper.dll')) {
        throw 'The SDK was not loaded from the project.'
    }
    Write-Output "PASS: bundled SDK $version loads through its DLL class factory and answers."
} finally { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($device) }
