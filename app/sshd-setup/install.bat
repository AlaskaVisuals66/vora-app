@echo off
setlocal
cd /d "%~dp0"
echo Solicitando elevacao (UAC)... clique SIM na janela que abrir.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File','%~dp0setup_sshd.ps1'"
echo.
echo Quando o script terminar, leia setup_sshd.log nesta mesma pasta.
pause
