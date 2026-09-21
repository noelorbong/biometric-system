# ZKTeco SDK connection and enrollment

The fingerprint enrollment flow has been restored to the version the user
confirmed worked before remote modal cancellation was introduced.

- Legacy TCP-capable devices, including T9, use the original TCP service.
- Devices replying with 6001, including the tested FA210 Plus, use the installed
  Windows 32-bit zkemkeeper.ZKEM SDK for the encrypted handshake.
- Each SDK request connects and disconnects independently. Fingerprint user
  setup and the enrollment trigger share one short-lived SDK connection.
- The Windows environment preservation fix remains in place for HTTP requests.
- Closing the enrollment modal stops browser polling only. Cancel an ongoing
  scan on the terminal itself, as in the previously working version.

The later persistent worker, SDK routing for all devices, automatic fingerprint
replacement, attempt-token polling and remote cancellation endpoint were removed.
No stored attendance records or fingerprint templates were deleted by the rollback.

The tests/scripts/zkteco-enrollment.ps1 test verifies the restored SDK enrollment
and template flow with a simulated device. Physical scans require user verification.
