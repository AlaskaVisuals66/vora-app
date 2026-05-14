$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$log  = Join-Path $here 'setup_sshd.log'
"[$(Get-Date -Format 'HH:mm:ss')] iniciado | user=$env:USERNAME | profile=$env:USERPROFILE" |
    Out-File -FilePath $log -Encoding UTF8

$id = [Security.Principal.WindowsIdentity]::GetCurrent()
$isElevated = [Security.Principal.WindowsPrincipal]::new($id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
"elevated=$isElevated" | Out-File -FilePath $log -Append -Encoding UTF8
if (-not $isElevated) {
    'ERRO: este script precisa ser executado como Administrador.' | Out-File -FilePath $log -Append -Encoding UTF8
    Write-Host 'ERRO: rode como Administrador (use install.bat ou clique direito > Executar como administrador).'
    exit 2
}

Start-Transcript -Path $log -Append -Force | Out-Null

try {
    $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILG0tQBIxrUJcKjUjgpTwz0Dej/Y3v894nqmF15+u+sI root@245812fd92b8'

    Write-Host '==> Verificando OpenSSH Server'
    $cap = Get-WindowsCapability -Online -Name 'OpenSSH.Server*'
    Write-Host ("   Estado: {0}" -f $cap.State)
    if ($cap.State -ne 'Installed') {
        Write-Host '==> Instalando OpenSSH Server (pode demorar 1-2 min)...'
        Add-WindowsCapability -Online -Name $cap.Name | Out-Null
    }

    Write-Host '==> Iniciando servico sshd'
    Start-Service sshd
    Set-Service -Name sshd -StartupType Automatic

    Write-Host '==> Configurando firewall (porta 22)'
    if (-not (Get-NetFirewallRule -Name 'sshd' -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name sshd -DisplayName 'OpenSSH Server (sshd)' `
            -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22 | Out-Null
    }

    Write-Host '==> Detectando se o usuario e administrador'
    $adminGroups = @('Administrators','Administradores')
    $userIsAdmin = $false
    foreach ($g in $adminGroups) {
        $members = Get-LocalGroupMember -Group $g -ErrorAction SilentlyContinue
        if ($members | Where-Object { $_.Name -ieq "$env:COMPUTERNAME\$env:USERNAME" }) {
            $userIsAdmin = $true; break
        }
    }
    Write-Host ("   $env:USERNAME admin? {0}" -f $userIsAdmin)

    if ($userIsAdmin) {
        $authFile = 'C:\ProgramData\ssh\administrators_authorized_keys'
        Write-Host ("==> Arquivo de chaves (admin): {0}" -f $authFile)
        if (-not (Test-Path 'C:\ProgramData\ssh')) {
            New-Item -ItemType Directory -Path 'C:\ProgramData\ssh' -Force | Out-Null
        }
        if (-not (Test-Path $authFile)) {
            New-Item -ItemType File -Path $authFile -Force | Out-Null
        }
        $existing = Get-Content $authFile -ErrorAction SilentlyContinue
        if ($existing -notcontains $key) {
            Add-Content -Path $authFile -Value $key
            Write-Host '   Chave adicionada.'
        } else {
            Write-Host '   Chave ja presente.'
        }
        Write-Host '==> ACL administrators_authorized_keys'
        icacls $authFile /inheritance:r | Out-Null
        icacls $authFile /grant 'SYSTEM:F' | Out-Null
        foreach ($g in $adminGroups) {
            icacls $authFile /grant ("{0}:F" -f $g) 2>&1 | Out-Null
        }
    } else {
        $userSsh = Join-Path $env:USERPROFILE '.ssh'
        $authFile = Join-Path $userSsh 'authorized_keys'
        Write-Host ("==> Arquivo de chaves (usuario): {0}" -f $authFile)
        if (-not (Test-Path $userSsh)) { New-Item -ItemType Directory -Path $userSsh -Force | Out-Null }
        $existing = Get-Content $authFile -ErrorAction SilentlyContinue
        if ($existing -notcontains $key) {
            Add-Content -Path $authFile -Value $key
            Write-Host '   Chave adicionada.'
        } else {
            Write-Host '   Chave ja presente.'
        }
        icacls $authFile /inheritance:r | Out-Null
        icacls $authFile /grant ("{0}:F" -f $env:USERNAME) | Out-Null
        icacls $authFile /grant 'SYSTEM:F' | Out-Null
    }

    Write-Host ''
    Write-Host '==================== STATUS FINAL ===================='
    Get-Service sshd | Format-List Name, Status, StartType | Out-String | Write-Host
    Get-NetFirewallRule -Name sshd | Format-List Name, Enabled, Direction, Action | Out-String | Write-Host
    Write-Host ('Login SSH: ' + $env:USERNAME + '@<IP-DESTA-MAQUINA>')
    Write-Host ('Arquivo de chaves: ' + $authFile)
    Write-Host '--- conteudo ---'
    Get-Content $authFile | Write-Host
    Write-Host '======================================================'
    Write-Host '==> SUCESSO. Pressione ENTER para fechar.'
    [void](Read-Host)
}
catch {
    Write-Host ('ERRO: ' + $_.Exception.Message)
    Write-Host $_.ScriptStackTrace
    Write-Host 'Pressione ENTER para fechar.'
    [void](Read-Host)
    exit 1
}
finally {
    Stop-Transcript | Out-Null
}
