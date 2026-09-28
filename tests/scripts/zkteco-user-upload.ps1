# Offline regression checks for SDK user writes and device recovery.
$ErrorActionPreference = 'Stop'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile(
    (Join-Path $PSScriptRoot '../../resources/scripts/zkteco-sdk.ps1'), [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($function in $ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.FunctionDefinitionAst]
}, $false)) { . ([scriptblock]::Create($function.Extent.Text)) }

class FakeUploadDevice {
    [bool] $Exists = $false
    [bool] $UserEnabled = $false
    [int] $ErrorCode = -4991
    [string] $Fail = ''
    [object] $Written = $null
    [Collections.Generic.List[string]] $Calls = [Collections.Generic.List[string]]::new()
    [bool] GetLastError([ref]$code) { $code.Value = $this.ErrorCode; return $true }
    [bool] SSR_GetUserInfo([int]$machine, [string]$pin, [ref]$name, [ref]$password, [ref]$privilege, [ref]$enabled) {
        $this.Calls.Add("get:$pin"); $enabled.Value=$this.UserEnabled; return $this.Exists
    }
    [bool] EnableDevice([int]$machine, [bool]$enabled) {
        $this.Calls.Add("enable:$enabled")
        return -not (($this.Fail -eq 'disable' -and -not $enabled) -or ($this.Fail -eq 'enable' -and $enabled))
    }
    [bool] SetStrCardNumber([string]$card) { $this.Calls.Add("card:$card"); return $this.Fail -ne 'card' }
    [bool] SSR_SetUserInfo([int]$machine, [string]$pin, [string]$name, [string]$password, [int]$privilege, [bool]$enabled) {
        $this.Calls.Add('write')
        $this.Written=@{pin=$pin;name=$name;password=$password;privilege=$privilege;enabled=$enabled}
        return $this.Fail -ne 'write'
    }
    [bool] RefreshData([int]$machine) { $this.Calls.Add('refresh'); return $this.Fail -ne 'refresh' }
}
function Assert-True($condition, $message) { if (-not $condition) { throw $message } }
$parameters = [pscustomobject]@{user=[pscustomobject]@{uid=123;badgenumber='00123';name='Test';password='secret';privilege=14;card='4294967295'}}
$zk = [FakeUploadDevice]::new()
$result = Write-DeviceUser $zk $parameters
Assert-True ($result.written -and $zk.Written.pin -ceq '00123') 'PIN lost during upload'
Assert-True ($zk.Written.privilege -eq 3 -and $zk.Written.password -ceq 'secret' -and $zk.Written.enabled) 'Incorrect user fields'
Assert-True (($zk.Calls -join ',') -eq 'get:00123,enable:False,card:4294967295,write,refresh,enable:True') 'Incorrect upload sequence'
$zk = [FakeUploadDevice]::new(); $zk.Exists=$true
Write-DeviceUser $zk $parameters | Out-Null
Assert-True (-not $zk.Written.enabled) 'Existing disabled user was re-enabled'
foreach ($pair in @(@(0,0), @(2,1), @(6,2), @(14,3))) {
    $zk = [FakeUploadDevice]::new(); $parameters.user.privilege=$pair[0]
    Write-DeviceUser $zk $parameters | Out-Null
    Assert-True ($zk.Written.privilege -eq $pair[1]) 'Incorrect privilege mapping'
}
foreach ($failure in @('card','write','refresh','enable','disable')) {
    $zk = [FakeUploadDevice]::new(); $zk.Fail=$failure
    $failed=$false
    try { Write-DeviceUser $zk $parameters | Out-Null } catch { $failed=$true }
    Assert-True $failed "Failure at $failure reported success"
    if ($failure -ne 'disable') {
        Assert-True ($zk.Calls[$zk.Calls.Count-1] -eq 'enable:True') 'Device recovery was not attempted'
    } else { Assert-True ($null -eq $zk.Written) 'Write attempted after disable failed' }
}
$zk = [FakeUploadDevice]::new(); $zk.ErrorCode=-2
$failed=$false
try { Write-DeviceUser $zk $parameters | Out-Null } catch { $failed=$true }
Assert-True ($failed -and $null -eq $zk.Written -and $zk.Calls.Count -eq 1) 'Read failure treated as a missing user'
$zk = [FakeUploadDevice]::new(); $parameters.user.privilege=99
$failed=$false
try { Write-DeviceUser $zk $parameters | Out-Null } catch { $failed=$true }
Assert-True ($failed -and $zk.Calls.Count -eq 0) 'Invalid privilege reached the device'
Write-Output 'PASS: SDK user creation/update, privilege mapping, PIN preservation and failure recovery.'
