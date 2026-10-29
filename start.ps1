param(
    [int]$Port = 8000,
    [switch]$Reinstall
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
    Write-Host '未找到 php。请先把 PHP 8.0+ 加入 PATH。' -ForegroundColor Red
    exit 1
}

$modules = & $php -m
$phpArgs = @()
if ($modules -notcontains 'pdo_sqlite') {
    $phpArgs = @('-d', 'extension=pdo_sqlite')
}

if ($Reinstall -or -not (Test-Path (Join-Path $PSScriptRoot 'database\mall.sqlite'))) {
    Write-Host '正在初始化 NOVA 商城数据库…' -ForegroundColor Cyan
    $installArgs = $phpArgs + @('think', 'mall:install')
    if ($Reinstall) {
        $installArgs += '--force'
    }
    & $php @installArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Host '数据库初始化失败。请确认 pdo_sqlite 已启用，或在 .env 中配置 MySQL。' -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

Write-Host ''
Write-Host "NOVA 商城已启动：http://127.0.0.1:$Port" -ForegroundColor Green
Write-Host "后台地址：http://127.0.0.1:$Port/admin/login"
Write-Host '管理员：admin@nova.test / Admin@123'
Write-Host '演示用户：demo@nova.test / Demo@123'
Write-Host '按 Ctrl+C 停止服务。'
Write-Host ''

$serverArgs = $phpArgs + @('-S', "127.0.0.1:$Port", '-t', 'public', 'public/router.php')
& $php @serverArgs
