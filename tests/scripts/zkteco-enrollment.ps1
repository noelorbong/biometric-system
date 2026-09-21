# Run with PowerShell; exercises the bridge functions without a device or COM.
$ErrorActionPreference = 'Stop'
$bridge = Join-Path $PSScriptRoot '../../resources/scripts/zkteco-sdk.ps1'
$tokens = $null; $parseErrors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile((Resolve-Path $bridge), [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
$functions = $ast.FindAll({ param($node) $node -is [Management.Automation.Language.FunctionDefinitionAst] }, $false)
foreach ($function in $functions) { . ([scriptblock]::Create($function.Extent.Text)) }

class FakeEnrollmentDevice {
    [bool] SetCommuTimeOut([int]$timeout) { return $true }
    [Collections.Generic.List[string]]$Calls = [Collections.Generic.List[string]]::new()
    [bool]$Exists = $false
    [bool]$HasTemplate = $true
    [string]$Fail = ''
    [int]$ErrorCode = -4991
    [bool] EnableDevice([int]$machine, [bool]$enabled) {
        $this.Calls.Add("enable:$enabled")
        return $true
    }
    [bool] SSR_GetUserInfo([int]$machine, [string]$pin, [ref]$name, [ref]$password, [ref]$privilege, [ref]$enabled) {
        $this.Calls.Add("get:$pin")
        return $this.Exists
    }
    [bool] GetLastError([ref]$code) { $code.Value = $this.ErrorCode; return $true }
    [bool] SetStrCardNumber([string]$card) { $this.Calls.Add('card'); return $true }
    [bool] SSR_SetUserInfo([int]$machine, [string]$pin, [string]$name, [string]$password, [int]$privilege, [bool]$enabled) {
        $this.Calls.Add("set:$pin")
        if ($this.Fail -ne 'set') { $this.Exists = $true }
        return $this.Fail -ne 'set'
    }
    [bool] RegEvent([int]$machine, [int]$flags) { $this.Calls.Add('events'); return $true }
    [bool] CancelOperation() { $this.Calls.Add('cancel'); return $this.Fail -ne 'cancel' }
    [bool] StartEnrollEx([string]$pin, [int]$finger, [int]$flag) {
        $this.Calls.Add("enroll:${pin}:${finger}:${flag}")
        return $this.Fail -ne 'enroll'
    }
    [bool] StartIdentify() { $this.Calls.Add('identify'); return $this.Fail -ne 'identify' }
    [bool] GetUserTmpExStr([int]$machine, [string]$pin, [int]$finger, [ref]$flag, [ref]$template, [ref]$length) {
        $this.Calls.Add("template:${pin}:${finger}")
        $template.Value = 'AQID'; $length.Value = 3
        if (-not $this.HasTemplate) { $this.ErrorCode = 0 }
        return $this.Exists -and $this.HasTemplate
    }
    [bool] SSR_DelUserTmpExt([int]$machine, [string]$pin, [int]$finger) {
        $this.Calls.Add("delete:${pin}:${finger}")
        if ($this.Fail -eq 'delete') { return $false }
        $this.HasTemplate = $false
        return $true
    }
    [bool] SetUserTmpExStr([int]$machine, [string]$pin, [int]$finger, [int]$flag, [string]$template) {
        $this.Calls.Add("restore:${pin}:${finger}")
        if ($this.Fail -eq 'restore') { return $false }
        $this.HasTemplate = $true
        return $true
    }
}

function Assert-True($condition, $message) { if (-not $condition) { throw $message } }
$parameters = [pscustomobject]@{ user = [pscustomobject]@{uid=12;badgenumber='00123';name='Test';password='';card='';privilege=0}; finger_id=9 }
$zk = [FakeEnrollmentDevice]::new()
$result = Invoke-FingerprintEnrollment $zk $parameters
Assert-True $result.started 'New-user enrollment failed'
Assert-True (($zk.Calls -join ',') -eq 'enable:False,get:00123,card,set:00123,enable:True,events,cancel,enroll:00123:9:1') 'Incorrect enrollment sequence or PIN conversion'

$zk = [FakeEnrollmentDevice]::new(); $zk.Exists = $true
$result = Invoke-FingerprintEnrollment $zk $parameters
Assert-True ($zk.Calls -notcontains 'set:00123') 'Existing credentials were overwritten'
Assert-True ($zk.Calls -notcontains 'identify') 'Successful enrollment was cancelled'

foreach ($failure in @('set', 'enroll')) {
    $zk = [FakeEnrollmentDevice]::new(); $zk.Fail = $failure
    $failed = $false
    try { Invoke-FingerprintEnrollment $zk $parameters | Out-Null } catch { $failed = $true }
    Assert-True $failed "Expected $failure to fail"
    Assert-True ($zk.Calls -contains 'enable:True') 'Device left disabled after failure'
    Assert-True ($zk.Calls -contains 'identify') 'Device not restored after failure'
}

$zk = [FakeEnrollmentDevice]::new(); $zk.ErrorCode = -2
$failed = $false
try { Invoke-FingerprintEnrollment $zk $parameters | Out-Null } catch { $failed = $true }
Assert-True $failed 'Communication failure treated as missing user'
Assert-True ($zk.Calls -notcontains 'set:00123') 'User written after communication failure'

$zk = [FakeEnrollmentDevice]::new(); $parameters.finger_id = 10
$failed = $false
try { Invoke-FingerprintEnrollment $zk $parameters | Out-Null } catch { $failed = $true }
Assert-True ($failed -and $zk.Calls.Count -eq 0) 'Invalid slot reached the device'

$zk = [FakeEnrollmentDevice]::new(); $zk.Exists = $true
$result = Read-FingerprintTemplate $zk ([pscustomobject]@{user_id='00123';finger_id=9})
Assert-True ($result.found -and $result.template -eq 'AQID') 'Template was not returned'
Assert-True ($zk.Calls -contains 'template:00123:9') 'Template queried with incorrect PIN'
$zk.Exists = $false; $zk.ErrorCode = 0
$result = Read-FingerprintTemplate $zk ([pscustomobject]@{user_id='00123';finger_id=9})
Assert-True (-not $result.found) 'Missing template returned as enrolled'
Write-Output 'PASS: 8 SDK enrollment/template scenarios'
