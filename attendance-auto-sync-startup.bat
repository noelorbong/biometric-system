@echo off
setlocal

set APP_ROOT=C:xampp\biometric-system
set LOG_DIR=%APP_ROOT%\storage\logs
set LOG_FILE=%LOG_DIR%\attendance-auto-sync.log
set ERR_FILE=%LOG_DIR%\attendance-auto-sync-error.log
set PHP_EXE=

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

if exist "C:\xampp\php\php.exe" set PHP_EXE=C:\xampp\php\php.exe
if not defined PHP_EXE if exist "%APP_ROOT%\vendor\nativephp\electron\resources\js\resources\php\php.exe" set PHP_EXE=%APP_ROOT%\vendor\nativephp\electron\resources\js\resources\php\php.exe
if not defined PHP_EXE if exist "C:\php\php.exe" set PHP_EXE=C:\php\php.exe
if not defined PHP_EXE if exist "C:\laragon\bin\php\php.exe" set PHP_EXE=C:\laragon\bin\php\php.exe

if not defined PHP_EXE (
for /f "delims=" %%P in ('where php 2^>nul') do (
if not defined PHP_EXE if exist "%%P" set PHP_EXE=%%P
)
)

if not defined PHP_EXE (
echo [%date% %time%] Unable to find php.exe>>"%ERR_FILE%"
exit /b 1
)

cd /d "%APP_ROOT%"
start "Attendance Auto Sync" /min cmd /c ""%PHP_EXE%" artisan attendance:auto-sync:daemon --sleep=1 >> "%LOG_FILE%" 2>> "%ERR_FILE%""
exit /b 0