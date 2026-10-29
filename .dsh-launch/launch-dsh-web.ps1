#requires -Version 5.1
<#
    DeepSeek Harness Web launcher.

    Starts `dsh web` in this workspace (minimized console) and lets DSH open the
    default browser at its tokenized URL. If the GUI is already listening on the
    port, it only opens the browser again.
#>

$ErrorActionPreference = 'Stop'

$Port      = 3080
$Url       = "http://127.0.0.1:$Port/"
$Workspace = 'D:\Personal\Documents\deepseek-harness\default-workspace'
$Node      = 'E:\node\node.exe'
$DshBin    = 'C:\Users\Administrator\AppData\Local\npm-cache\_npx\1e7f6d9597241db0\node_modules\@deepseek-ai\dsh\lib\bin.js'

function Test-WebUp {
    param([string] $Target)

    try {
        Invoke-WebRequest -Uri $Target -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop | Out-Null
        return $true
    }
    catch {
        # Any HTTP answer (even 401) means the listener is already serving.
        return [bool] $_.Exception.Response
    }
}

# Already running: just bring the GUI back up in the default browser.
if (Test-WebUp -Target $Url) {
    Start-Process $Url
    exit 0
}

if (-not (Test-Path -LiteralPath $Node)) {
    $Node = (Get-Command node.exe -ErrorAction Stop).Source
}

if (-not (Test-Path -LiteralPath $DshBin)) {
    # Fallback when the npx cache was pruned: let npx resolve the same CLI.
    Start-Process -FilePath 'cmd.exe' `
        -ArgumentList '/c', "npx -y @deepseek-ai/dsh@0.2.1-rc.2 web --port $Port" `
        -WorkingDirectory $Workspace -WindowStyle Minimized
    exit 0
}

Start-Process -FilePath $Node `
    -ArgumentList @("`"$DshBin`"", 'web', '--port', "$Port") `
    -WorkingDirectory $Workspace -WindowStyle Minimized

# DSH opens the browser itself once the listener is ready. If it never becomes
# reachable (for example the port is taken by another program), say so.
for ($i = 0; $i -lt 60; $i++) {
    Start-Sleep -Milliseconds 500
    if (Test-WebUp -Target $Url) { exit 0 }
}

Add-Type -AssemblyName System.Windows.Forms
[void] [System.Windows.Forms.MessageBox]::Show(
    "DeepSeek Harness 没有在 30 秒内就绪。`n`n请检查端口 $Port 是否被其他程序占用。",
    'DeepSeek Harness',
    [System.Windows.Forms.MessageBoxButtons]::OK,
    [System.Windows.Forms.MessageBoxIcon]::Warning
)
