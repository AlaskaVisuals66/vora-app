$ErrorActionPreference = 'Stop'
$log = 'C:\Users\canal\Downloads\teste gerenciamento whatsapp\app\setup_sshd.log'
"[$(Get-Date -Format 'HH:mm:ss')] script iniciado, PID=$PID, user=$env:USERNAME, admin-context=$([Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator))" | Out-File -FilePath $log -Encoding UTF8
Start-Transcript -Path $log -Append -Force | Out-Null

try {
    Write-Host '==> Verificando OpenSSH Server'
    $cap = Get-WindowsCapability -Online -Name 'OpenSSH.Server*'
    Write-Host ("   Estado atual: {0}" -f $cap.State)
    if ($cap.State -ne 'Installed') {
        Write-Host '==> Instalando OpenSSH Server...'
        Add-WindowsCapability -Online -Name $cap.Name | Out-Null
    } else {
        Write-Host '   Ja instalado.'
    }

    Write-Host '==> Iniciando servico sshd'
    Start-Service sshd
    Set-Service -Name sshd -StartupType Automatic

    Write-Host '==> Configurando firewall (porta 22)'
    if (-not (Get-NetFirewallRule -Name 'sshd' -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name sshd -DisplayName 'OpenSSH Server (sshd)' `
            -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22 | Out-Null
        Write-Host '   Regra criada.'
    } else {
        Write-Host '   Regra ja existe.'
    }

    Write-Host '==> Detectando se canal e administrador'
    $adminGroups = @('Administrators','Administradores')
    $isAdmin = $false
    foreach ($g in $adminGroups) {
        $members = Get-LocalGroupMember -Group $g -ErrorAction SilentlyContinue
        if ($members | Where-Object { $_.Name -match '\\canal$' }) { $isAdmin = $true; break }
    }
    Write-Host ("   canal admin? {0}" -f $isAdmin)

    $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMJpfHuIwR/go0k9n/EBrwHsR9WNWNBxgLizBfbKd0Ov root@245812fd92b8'

    if ($isAdmin) {
        $authFile = 'C:\ProgramData\ssh\administrators_authorized_keys'
        Write-Host ("==> Usando arquivo de admin: {0}" -f $authFile)
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
        Write-Host '==> Ajustando ACL do administrators_authorized_keys'
        icacls $authFile /inheritance:r | Out-Null
        icacls $authFile /grant 'SYSTEM:F' | Out-Null
        $adminGrant = $false
        foreach ($g in $adminGroups) {
            $r = icacls $authFile /grant ("{0}:F" -f $g) 2>&1
            if ($LASTEXITCODE -eq 0) { $adminGrant = $true; break }
        }
        Write-Host ("   ACL aplicada (admin grant: {0})" -f $adminGrant)
    } else {
        $userSsh = 'C:\Users\canal\.ssh'
        $authFile = "$userSsh\authorized_keys"
        Write-Host ("==> Usando arquivo do usuario: {0}" -f $authFile)
        if (-not (Test-Path $userSsh)) { New-Item -ItemType Directory -Path $userSsh -Force | Out-Null }
        $existing = Get-Content $authFile -ErrorAction SilentlyContinue
        if ($existing -notcontains $key) {
            Add-Content -Path $authFile -Value $key
            Write-Host '   Chave adicionada.'
        } else {
            Write-Host '   Chave ja presente.'
        }
        Write-Host '==> Ajustando ACL do authorized_keys'
        icacls $authFile /inheritance:r | Out-Null
        icacls $authFile /grant 'canal:F' | Out-Null
        icacls $authFile /grant 'SYSTEM:F' | Out-Null
    }

    Write-Host ''
    Write-Host '==> STATUS FINAL'
    Get-Service sshd | Format-List Name, Status, StartType | Out-String | Write-Host
    Get-NetFirewallRule -Name sshd | Format-List Name, Enabled, Direction, Action | Out-String | Write-Host
    Write-Host ('Arquivo de chaves: ' + $authFile)
    Write-Host '--- conteudo ---'
    Get-Content $authFile | Write-Host
    Write-Host '==> SUCESSO'
}
catch {
    Write-Host ('ERRO: ' + $_.Exception.Message)
    Write-Host $_.ScriptStackTrace
    exit 1
}
finally {
    Stop-Transcript | Out-Null
}
