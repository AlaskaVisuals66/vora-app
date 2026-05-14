$script = 'C:\Users\canal\Downloads\teste gerenciamento whatsapp\app\setup_sshd.ps1'
$log = 'C:\Users\canal\Downloads\teste gerenciamento whatsapp\app\setup_sshd.log'
if (Test-Path $log) { Remove-Item $log -Force }
$p = Start-Process powershell -Verb RunAs -PassThru -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',$script
$p.WaitForExit()
Write-Host ('elevated_exit=' + $p.ExitCode)
if (Test-Path $log) {
    Write-Host '--- LOG ---'
    Get-Content $log
} else {
    Write-Host 'NO_LOG_FILE: o script elevado nao chegou a rodar (UAC negado/cancelado?)'
}
