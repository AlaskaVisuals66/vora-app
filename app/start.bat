@echo off
setlocal
set PHP=C:\Users\canal\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
cd /d "%~dp0"
echo Using PHP: %PHP%
"%PHP%" -v
echo.
echo Starting artisan serve on http://127.0.0.1:8000
"%PHP%" artisan serve --host=127.0.0.1 --port=8000
endlocal
