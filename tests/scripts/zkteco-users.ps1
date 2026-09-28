# Offline tests: extract bridge functions without opening a device connection.
$ErrorActionPreference = 'Stop'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile(
    (Join-Path $PSScriptRoot '../../resources/scripts/zkteco-sdk.ps1'), [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($function in $ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.FunctionDefinitionAst]
}, $false)) { . ([scriptblock]::Create($function.Extent.Text)) }

class FakeUserDevice {
    [int] $Count = 1
    [int] $Index = 0
    [string] $Fail = ''
    [object[]] $Rows = @(@{pin='00123';name='Test';password='secret';privilege=3;card='4294967295';enabled=$false})
    [bool] GetDeviceStatus([int]$machine, [int]$kind, [ref]$count) {
        $count.Value = $this.Count; return $this.Fail -ne 'count'
    }
    [bool] ReadAllUserID([int]$machine) { return $this.Fail -ne 'download' }
    [bool] GetLastError([ref]$code) { $code.Value = -2; return $true }
    [bool] SSR_GetAllUserInfo([int]$machine, [ref]$pin, [ref]$name, [ref]$password, [ref]$privilege, [ref]$enabled) {
        if ($this.Index -ge $this.Rows.Count) { return $false }
        $row = $this.Rows[$this.Index++]
        $pin.Value=$row.pin; $name.Value=$row.name; $password.Value=$row.password
        $privilege.Value=$row.privilege; $enabled.Value=$row.enabled
        return $true
    }
    [bool] GetStrCardNumber([ref]$card) {
        $card.Value=$this.Rows[$this.Index-1].card; return $this.Fail -ne 'card'
    }
}
function Assert-True($condition, $message) { if (-not $condition) { throw $message } }
function Assert-Failure($expected) {
    $message = ''
    try { @(Read-DeviceUsers $zk) | Out-Null } catch { $message = $_.Exception.Message }
    Assert-True ($message -like "*$expected*") "Expected '$expected', received '$message'"
}

$zk = [FakeUserDevice]::new()
$rows = @(Read-DeviceUsers $zk)
Assert-True ($rows.Count -eq 1 -and $rows[0].pin -ceq '00123' -and $rows[0].uid -eq 123) 'PIN changed or wrong numeric lookup hint'
Assert-True ($rows[0].card -eq 4294967295 -and $rows[0].password -ceq 'secret' -and -not $rows[0].enabled) 'User data changed'
Assert-True ((@{ok=$true;data=$rows} | ConvertTo-Json -Compress) -match '"data":\[') 'Single user must serialize as a list'

foreach ($pair in @(@(0,0), @(1,2), @(2,6), @(3,14))) {
    $zk = [FakeUserDevice]::new(); $zk.Rows[0].privilege = $pair[0]
    $rows = @(Read-DeviceUsers $zk)
    Assert-True ($rows[0].privilege -eq $pair[1]) 'Incorrect SDK privilege mapping'
}
$zk = [FakeUserDevice]::new(); $zk.Rows[0].pin = 'ABC12'
$rows = @(Read-DeviceUsers $zk)
Assert-True ($rows[0].pin -ceq 'ABC12' -and $rows[0].uid -eq 0) 'Alphanumeric PIN was coerced'
$zk = [FakeUserDevice]::new(); $zk.Count = 0; $zk.Fail = 'download'
$rows = @(Read-DeviceUsers $zk)
Assert-True ($rows.Count -eq 0) 'Known empty device must return an empty list'
foreach ($failure in @('count','download','card')) {
    $zk = [FakeUserDevice]::new(); $zk.Fail = $failure
    Assert-Failure 'SDK error'
}
$zk = [FakeUserDevice]::new(); $zk.Count = 2
Assert-Failure 'Incomplete user download'
$zk = [FakeUserDevice]::new(); $zk.Count = 2; $zk.Rows = @($zk.Rows[0], $zk.Rows[0])
Assert-Failure 'duplicate user PIN'
$zk = [FakeUserDevice]::new(); $zk.Rows[0].pin = ''
Assert-Failure 'empty or duplicate user PIN'
$zk = [FakeUserDevice]::new(); $zk.Rows[0].card = 'invalid'
Assert-Failure 'invalid card number'
Write-Output 'PASS: user downloads preserve data and reject failed, partial, or invalid results.'
