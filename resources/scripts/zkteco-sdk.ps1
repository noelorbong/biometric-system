$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Console]::InputEncoding = [Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
$zk = $null
$result = $null

function Get-SdkError {
    $code = 0
    [void]$zk.GetLastError([ref]$code)
    return $code
}

function Read-DeviceUsers($device) {
    $userCount = 0
    if (-not $device.GetDeviceStatus(1, 2, [ref]$userCount)) {
        throw "Cannot verify device user count (SDK error $(Get-SdkError))."
    }
    if ($userCount -lt 0) { throw 'The SDK returned an invalid user count.' }
    if ($userCount -eq 0) { return }
    if (-not $device.ReadAllUserID(1)) {
        throw "Cannot download device users (SDK error $(Get-SdkError))."
    }

    $rows = [Collections.Generic.List[object]]::new()
    $seen = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
    while ($true) {
        $pin = ''; $name = ''; $password = ''; $privilege = 0; $enabled = $true
        if (-not $device.SSR_GetAllUserInfo(1, [ref]$pin, [ref]$name, [ref]$password,
            [ref]$privilege, [ref]$enabled)) { break }
        if ([string]::IsNullOrWhiteSpace($pin) -or -not $seen.Add($pin)) {
            throw 'The SDK returned an empty or duplicate user PIN.'
        }
        # Cardnumber refers to the user most recently read; capture it before
        # advancing the iterator. Never silently replace an unread card with 0.
        $card = ''
        if (-not $device.GetStrCardNumber([ref]$card)) {
            throw "Cannot read a device user's card number (SDK error $(Get-SdkError))."
        }
        $cardNumber = [long]0
        if ($card -ne '' -and (-not [long]::TryParse($card, [ref]$cardNumber) -or $cardNumber -lt 0)) {
            throw 'The SDK returned an invalid card number.'
        }
        # The SSR API exposes PINs, not legacy internal record indexes. Preserve
        # the exact PIN (including leading zeros); use its numeric value only
        # as the existing application's local-user lookup hint.
        $uid = [long]0
        if ($pin -cmatch '^[0-9]+$') { [void][long]::TryParse($pin, [ref]$uid) }
        $mappedPrivilege = switch ($privilege) {
            0 { 0 } 1 { 2 } 2 { 6 } 3 { 14 }
            default { throw "Unsupported SDK user privilege: $privilege." }
        }
        $rows.Add(@{ uid = $uid; pin = $pin; name = $name; password = $password;
            privilege = $mappedPrivilege; card = $cardNumber; enabled = $enabled })
    }
    if ($rows.Count -lt $userCount) {
        throw "Incomplete user download: expected at least $userCount users, received $($rows.Count)."
    }
    return $rows.ToArray()
}

function Write-DeviceUser($device, $parameters) {
    $user = $parameters.user
    $pin = [string]$user.badgenumber
    if ([string]::IsNullOrWhiteSpace($pin)) { $pin = [string]$user.uid }
    if ([string]::IsNullOrWhiteSpace($pin)) { throw 'A user PIN is required for upload.' }
    $privilege = switch ([int]$user.privilege) {
        0 { 0 } 2 { 1 } 6 { 2 } 14 { 3 }
        default { throw 'Unsupported user privilege.' }
    }
    $card = [string]$user.card
    if ([string]::IsNullOrWhiteSpace($card)) { $card = '0' }
    if ($card -cnotmatch '^[0-9]+$') { throw 'The card number must contain digits only.' }

    # Update by exact PIN, preserving enrolled biometrics and an existing user's
    # disabled state. Upload intentionally applies the supplied local user fields.
    $name = ''; $password = ''; $existingPrivilege = 0; $enabled = $true
    $exists = $device.SSR_GetUserInfo(1, $pin, [ref]$name, [ref]$password,
        [ref]$existingPrivilege, [ref]$enabled)
    if (-not $exists) {
        $code = Get-SdkError
        if ($code -notin @(0, -8, -4991)) { throw "Cannot check device user (SDK error $code)." }
        $enabled = $true
    }
    $disabled = $false
    try {
        if (-not $device.EnableDevice(1, $false)) { throw "Cannot prepare device (SDK error $(Get-SdkError))." }
        $disabled = $true
        if (-not $device.SetStrCardNumber($card)) { throw "Cannot set user card (SDK error $(Get-SdkError))." }
        if (-not $device.SSR_SetUserInfo(1, $pin, [string]$user.name,
            [string]$user.password, $privilege, $enabled)) {
            throw "Cannot upload device user (SDK error $(Get-SdkError))."
        }
        if (-not $device.RefreshData(1)) {
            throw "User was written, but device refresh failed (SDK error $(Get-SdkError))."
        }
        return @{ written = $true }
    } finally {
        if ($disabled -and -not $device.EnableDevice(1, $true)) {
            throw "Could not re-enable the device after user upload (SDK error $(Get-SdkError))."
        }
    }
}

function Invoke-FingerprintEnrollment($device, $parameters) {
    $user = $parameters.user
    $pin = [string]$user.badgenumber
    if ([string]::IsNullOrWhiteSpace($pin)) { $pin = [string]$user.uid }
    if ([string]::IsNullOrWhiteSpace($pin) -or $null -eq $parameters.finger_id -or
        [int]$parameters.finger_id -lt 0 -or [int]$parameters.finger_id -gt 9) {
        throw 'A user PIN and fingerprint slot from 0 to 9 are required.'
    }

    $disabled = $false
    $started = $false
    try {
        if (-not $device.EnableDevice(1, $false)) { throw "Cannot prepare device (SDK error $(Get-SdkError))." }
        $disabled = $true
        # Existing users retain their password, card and privilege. Enrollment
        # should not reset credentials or delete other enrolled fingers.
        $name = ''; $userPassword = ''; $privilege = 0; $enabled = $true
        $exists = $device.SSR_GetUserInfo(1, $pin, [ref]$name, [ref]$userPassword, [ref]$privilege, [ref]$enabled)
        if (-not $exists) {
            $code = Get-SdkError
            # FA210 Plus returns -4991 for an absent PIN (verified on-device).
            if ($code -notin @(0, -8, -4991)) { throw "Cannot check device user (SDK error $code)." }
            $privilege = switch ([int]$user.privilege) { 14 {3} 6 {2} 2 {1} default {[int]$user.privilege} }
            if ($privilege -lt 0 -or $privilege -gt 3) { throw 'Unsupported user privilege.' }
            $card = [string]$user.card
            if ([string]::IsNullOrWhiteSpace($card)) { $card = '0' }
            if (-not $device.SetStrCardNumber($card)) { throw "Cannot set user card (SDK error $(Get-SdkError))." }
            if (-not $device.SSR_SetUserInfo(1, $pin, [string]$user.name, [string]$user.password, $privilege, $true)) {
                throw "Cannot create device user (SDK error $(Get-SdkError))."
            }
        }
        if (-not $device.RefreshData(1)) {
            throw "Cannot refresh user data before face enrollment (SDK error $(Get-SdkError))."
        }
        if (-not $device.EnableDevice(1, $true)) { throw "Cannot enable scanner (SDK error $(Get-SdkError))." }
        $disabled = $false
        if (-not $device.RegEvent(1, 65535)) { throw "Cannot register enrollment events (SDK error $(Get-SdkError))." }
        if (-not $device.CancelOperation()) { throw "Cannot prepare enrollment (SDK error $(Get-SdkError))." }
        if (-not $device.StartEnrollEx($pin, [int]$parameters.finger_id, 1)) {
            throw "Device rejected enrollment (SDK error $(Get-SdkError)). If this slot is already enrolled, select an unused finger slot."
        }
        $started = $true
        return @{ started = $true }
    } finally {
        if ($disabled) { [void]$device.EnableDevice(1, $true) }
        if (-not $started) { [void]$device.StartIdentify() }
        # Do not CancelOperation/StartIdentify after success: it would cancel
        # the enrollment prompt before the user can place their finger.
    }
}

function Invoke-FaceEnrollment($device, $parameters) {
    $user = $parameters.user
    $pin = [string]$user.badgenumber
    if ([string]::IsNullOrWhiteSpace($pin)) { $pin = [string]$user.uid }
    if ([string]::IsNullOrWhiteSpace($pin)) {
        throw 'A user PIN is required for face enrollment.'
    }

    # FacePro2 uses slot 50 for camera capture; retain legacy slots as fallbacks.
    $faceBackupCandidates = @(50, 111, 12, 15)

    $disabled = $false
    $started = $false
    try {
        if (-not $device.EnableDevice(1, $false)) { throw "Cannot prepare device (SDK error $(Get-SdkError))." }
        $disabled = $true
        $name = ''; $userPassword = ''; $privilege = 0; $enabled = $true
        $exists = $device.SSR_GetUserInfo(1, $pin, [ref]$name, [ref]$userPassword, [ref]$privilege, [ref]$enabled)
        if (-not $exists) {
            $code = Get-SdkError
            # FA210 Plus returns -4991 for an absent PIN (verified on-device).
            if ($code -notin @(0, -8, -4991)) { throw "Cannot check device user (SDK error $code)." }
            $privilege = switch ([int]$user.privilege) { 14 {3} 6 {2} 2 {1} default {[int]$user.privilege} }
            if ($privilege -lt 0 -or $privilege -gt 3) { throw 'Unsupported user privilege.' }
            $card = [string]$user.card
            if ([string]::IsNullOrWhiteSpace($card)) { $card = '0' }
            if (-not $device.SetStrCardNumber($card)) { throw "Cannot set user card (SDK error $(Get-SdkError))." }
            if (-not $device.SSR_SetUserInfo(1, $pin, [string]$user.name, [string]$user.password, $privilege, $true)) {
                throw "Cannot create device user (SDK error $(Get-SdkError))."
            }
        }
        if (-not $device.EnableDevice(1, $true)) { throw "Cannot enable scanner (SDK error $(Get-SdkError))." }
        $disabled = $false
        if (-not $device.RegEvent(1, 65535)) { throw "Cannot register enrollment events (SDK error $(Get-SdkError))." }
        if (-not $device.CancelOperation()) { throw "Cannot prepare enrollment (SDK error $(Get-SdkError))." }

        $lastError = 0
        foreach ($backupNumber in $faceBackupCandidates) {
            if ($device.StartEnrollEx($pin, $backupNumber, 1)) {
                $started = $true
                break
            }
            $lastError = Get-SdkError
        }
        if (-not $started) {
            throw "Device rejected face enrollment (SDK error $lastError). This firmware may not support remote-triggered face capture."
        }
        return @{ started = $true }
    } finally {
        if ($disabled) { [void]$device.EnableDevice(1, $true) }
        if (-not $started) { [void]$device.StartIdentify() }
        # Do not CancelOperation/StartIdentify after success: it would cancel
        # the enrollment prompt before the user can complete face capture.
    }
}

function Read-FingerprintTemplate($device, $parameters) {
    if ([string]::IsNullOrWhiteSpace([string]$parameters.user_id) -or
        $null -eq $parameters.finger_id -or [int]$parameters.finger_id -lt 0 -or [int]$parameters.finger_id -gt 9) {
        throw 'A user PIN and fingerprint slot from 0 to 9 are required.'
    }
    $flag = 0; $template = ''; $length = 0
    $found = $device.GetUserTmpExStr(1, [string]$parameters.user_id, [int]$parameters.finger_id,
        [ref]$flag, [ref]$template, [ref]$length)
    if (-not $found) {
        $code = Get-SdkError
        if ($code -notin @(0, -8)) { throw "Cannot read fingerprint template (SDK error $code)." }
        return @{ found = $false }
    }
    if ($length -le 0 -or [string]::IsNullOrEmpty($template)) { throw 'The SDK returned an empty fingerprint template.' }
    # The SDK string representation is Base64; PHP decodes it for the existing
    # binary template columns. Never write template contents to diagnostics.
    return @{ found = $true; template = $template }
}

function Stop-FingerprintEnrollment($device) {
    if (-not $device.CancelOperation()) {
        throw "Cannot cancel enrollment (SDK error $(Get-SdkError))."
    }
    if (-not $device.StartIdentify()) {
        throw "Cannot restore identification after cancellation (SDK error $(Get-SdkError))."
    }
    if (-not $device.EnableDevice(1, $true)) {
        throw "Cannot enable scanner after cancellation (SDK error $(Get-SdkError))."
    }
    return @{ cancelled = $true }
}

try {
    $request = [Console]::In.ReadToEnd() | ConvertFrom-Json
    if ($request.operation -notin @('info', 'users', 'write_user', 'attendance', 'enroll_fingerprint', 'enroll_face', 'fingerprint_template', 'cancel_enrollment')) {
        throw 'Unsupported SDK operation.'
    }

    $sdkDirectory = [string]$request.sdk_directory
    if ([string]::IsNullOrWhiteSpace($sdkDirectory) -or -not (Test-Path -LiteralPath $sdkDirectory -PathType Container)) {
        throw 'The bundled SDK directory is missing. Include resources/sdk/zkteco/x86 in the deployment.'
    }
    $sdkFiles = @('zkemkeeper.dll', 'zkemsdk.dll', 'commpro.dll', 'zkemsdkutils.dll',
        'plcommpro.dll', 'plcommutils.dll', 'ZKEMCrypto.dll', 'ZKCommuCryptoClient.dll')
    foreach ($file in $sdkFiles) {
        if (-not (Test-Path -LiteralPath (Join-Path $sdkDirectory $file) -PathType Leaf)) {
            throw "Bundled SDK file is missing: $file. Include the complete resources/sdk/zkteco/x86 directory."
        }
    }
    Add-Type -Path (Join-Path $sdkDirectory 'BundledSdk.cs')
    $zk = [BundledZktecoSdk]::Create($sdkDirectory)

    if (-not $zk.SetCommPassword([int]$request.password)) {
        throw 'The SDK could not set the communication key.'
    }
    if (-not $zk.Connect_Net([string]$request.ip, [int]$request.port)) {
        throw "Connection failed (SDK error $(Get-SdkError))."
    }

    # Machine number 1 is the SDK handle for this direct network connection.
    if ($request.operation -eq 'enroll_fingerprint') {
        $result = @{ ok = $true; data = (Invoke-FingerprintEnrollment $zk $request.parameters) }
    } elseif ($request.operation -eq 'enroll_face') {
        $result = @{ ok = $true; data = (Invoke-FaceEnrollment $zk $request.parameters) }
        $holdSeconds = [Math]::Min(55, [Math]::Max(0, [int]$request.parameters.capture_hold_seconds))
        if ($holdSeconds -gt 0) {
            # FacePro2 cancels remote face enrollment when the SDK session closes.
            # Keep this request connected while the user completes on-device capture.
            [Threading.Thread]::Sleep($holdSeconds * 1000)
        }
    } elseif ($request.operation -eq 'cancel_enrollment') {
        $result = @{ ok = $true; data = (Stop-FingerprintEnrollment $zk) }
    } elseif ($request.operation -eq 'fingerprint_template') {
        $result = @{ ok = $true; data = (Read-FingerprintTemplate $zk $request.parameters) }
    } elseif ($request.operation -eq 'users') {
        $result = @{ ok = $true; data = @(Read-DeviceUsers $zk) }
    } elseif ($request.operation -eq 'write_user') {
        $result = @{ ok = $true; data = (Write-DeviceUser $zk $request.parameters) }
    } elseif ($request.operation -eq 'info') {
        $info = @{}
        $value = ''
        if ($zk.GetSerialNumber(1, [ref]$value)) { $info.SerialNumber = $value }
        $value = ''
        if ($zk.GetFirmwareVersion(1, [ref]$value)) { $info.FirmVer = $value }

        foreach ($field in @(
            @('DeviceName', 'DeviceName'), @('~DeviceName', 'DeviceName'),
            @('~Platform', 'Platform'), @('~OEMVendor', 'Manufacturer'),
            @('WorkCode', 'WorkCode')
        )) {
            $value = ''
            try {
                if ($zk.GetSysOption(1, $field[0], [ref]$value) -and $value -ne '' -and -not $info.ContainsKey($field[1])) {
                    $info[$field[1]] = $value
                }
            } catch { } # Optional firmware-specific fields.
        }
        foreach ($field in @(@(1, 'ManagerCount'), @(2, 'UserCount'), @(3, 'FPCount'))) {
            $value = 0
            if ($zk.GetDeviceStatus(1, $field[0], [ref]$value)) { $info[$field[1]] = $value }
        }
        $result = @{ ok = $true; data = $info }
    } else {
        $rows = [Collections.Generic.List[object]]::new()
        $recordCount = 0
        $countKnown = $zk.GetDeviceStatus(1, 6, [ref]$recordCount)
        # This firmware returns an error when reading an empty log. Only return
        # an empty result when the separate record-count query confirms it.
        if (-not $countKnown -or $recordCount -gt 0) {
            if (-not $zk.ReadAllGLogData(1)) {
                throw "Attendance download failed (SDK error $(Get-SdkError))."
            }
            while ($true) {
                $pin = ''; $verify = 0; $punch = 0
                $year = 0; $month = 0; $day = 0
                $hour = 0; $minute = 0; $second = 0; $workCode = 0
                $found = $zk.SSR_GetGeneralLogData(1, [ref]$pin, [ref]$verify, [ref]$punch,
                    [ref]$year, [ref]$month, [ref]$day, [ref]$hour, [ref]$minute,
                    [ref]$second, [ref]$workCode)
                if (-not $found) { break }
                $timestamp = [DateTime]::new($year, $month, $day, $hour, $minute, $second)
                $rows.Add(@{
                    pin = $pin
                    check_time = $timestamp.ToString('yyyy-MM-dd HH:mm:ss')
                    check_type = $(if ($punch -in @(1, 5)) { 'O' } else { 'I' })
                    verify_code = $verify
                    work_code = $workCode
                })
            }
            if ($countKnown -and $rows.Count -lt $recordCount) {
                throw "Incomplete attendance download: expected at least $recordCount records, received $($rows.Count)."
            }
        }
        $result = @{ ok = $true; data = @($rows.ToArray()) }
    }
    $loadedSdkFiles = @([Diagnostics.Process]::GetCurrentProcess().Modules |
        Where-Object { $_.ModuleName -in $sdkFiles } | ForEach-Object {
            $expected = [IO.Path]::GetFullPath((Join-Path $sdkDirectory $_.ModuleName))
            if (-not [string]::Equals($_.FileName, $expected, [StringComparison]::OrdinalIgnoreCase)) {
                throw "SDK dependency loaded outside the bundle: $($_.ModuleName)."
            }
            $_.FileName
        })
    $result.sdk = @{ mode = 'bundled'; modules = $loadedSdkFiles }
} catch {
    $result = @{ ok = $false; error = $_.Exception.Message }
} finally {
    if ($null -ne $zk) {
        try { $zk.Disconnect() } catch { }
        [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($zk)
    }
}

[Console]::Out.WriteLine(($result | ConvertTo-Json -Depth 6 -Compress))
