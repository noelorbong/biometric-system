# ZKTeco SDK connection and enrollment

The fingerprint enrollment flow has been restored to the version the user
confirmed worked before remote modal cancellation was introduced.

- Legacy TCP-capable devices, including T9, use the original TCP service.
- Devices replying with 6001, including the tested FacePro2, use the bundled
  Windows 32-bit SDK in `resources/sdk/zkteco/x86` for the encrypted handshake.
- Each SDK request connects and disconnects independently. Fingerprint user
  setup and the enrollment trigger share one short-lived SDK connection.
- The Windows environment preservation fix remains in place for HTTP requests.
- User previews/downloads use `ReadAllUserID` and `SSR_GetAllUserInfo` through
  the SDK. PIN strings are preserved, and SDK privileges 0/1/2/3 are converted
  to the application's legacy values 0/2/6/14. Card numbers are captured before
  advancing the user iterator. A failed count query or incomplete download
  produces an error instead of a misleading empty/partial preview.
- User-record uploads use `SetStrCardNumber` and `SSR_SetUserInfo` by exact PIN.
  Each write owns its SDK connection and device disable/enable pair, restoring
  the scanner in `finally`. Existing user enabled/disabled state is preserved.
  Both single and bulk upload flows report write errors without deleting the
  existing user and their biometrics as a retry strategy.
- Closing the enrollment modal stops browser polling only. Cancel an ongoing
  scan on the terminal itself, as in the previously working version.

The later persistent worker, SDK routing for all devices, automatic fingerprint
replacement, attempt-token polling and remote cancellation endpoint were removed.
No stored attendance records or fingerprint templates were deleted by the rollback.

The tests/scripts/zkteco-enrollment.ps1 test verifies the restored SDK enrollment
and template flow with a simulated device. Physical scans require user verification.

## Bundled Windows deployment

Deploy the complete `resources/sdk/zkteco/x86` directory along with
`resources/scripts/zkteco-sdk.ps1`. The bridge compiles the small `BundledSdk.cs`
loader with Windows PowerShell's `Add-Type`, loads the project's DLL by absolute
path, and calls its `DllGetClassObject` export directly. It does not register the
SDK or use `New-Object -ComObject` to find an installed SDK. No separate vendor
attendance application is needed for this loading path.

The DLLs were copied from the working SDK on the development computer;
`bundle.json` records their versions and SHA-256 hashes. These vendor binaries
remain vendor-owned; this project does not grant a redistribution license.

Requirements: Windows with 32-bit Windows PowerShell and .NET Framework, permission
for the application account to launch PowerShell and compile the loader, and TCP
access to the terminal's configured port. The SDK remains Windows-only. Keep the
bundle outside the public web root and writable only by deployment administrators.

All eight bundled DLLs must be present. Missing files cause an explicit error;
the bridge does not silently switch back to COM registration. Successful bridge
responses include `sdk.mode` and the loaded vendor DLL paths. The bridge rejects
vendor dependencies loaded outside its bundle.

Validated on the development computer: direct DLL creation, FacePro2 encrypted
connection and device information retrieval, and loaded vendor DLL paths within
the project. The computer still has the vendor SDK installed, so a clean Windows
deployment remains an acceptance check. Remote enrollment/template support is
unchanged and is not guaranteed by a successful connection test.

Run the offline loader test from the project root:

```powershell
& "$env:SystemRoot\SysWOW64\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File tests/scripts/zkteco-bundled-sdk.ps1
```

For deployment verification, use the application's Test Connection action, then
preview attendance before importing. The application's server process must have
the same access as the command-line account used for these checks.

The user-download change was verified against the FacePro2 through the actual
Preview Users controller: HTTP 200, three users, zero conflicts. Preview does
not import users or change device users. `tests/scripts/zkteco-users.ps1` covers
PIN preservation, card values, privilege conversion, empty devices and failed/
incomplete downloads. The PHP routing regression is in
`tests/Unit/ZKTecoSdkUserDownloadTest.php` (requires PHPUnit development dependencies).

User-record upload was tested through the single-user controller for the reported
FacePro2 upload: HTTP 200 and a matching read-back, with other users unchanged.
No saved fingerprint templates existed for that user. Uploading saved biometric
templates remains a separate unsupported SDK operation; user-record uploads do
not add that capability. Offline upload/routing regression checks are
`tests/scripts/zkteco-user-upload.ps1` and `tests/scripts/zkteco-user-upload.php`.
