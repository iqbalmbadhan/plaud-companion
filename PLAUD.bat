@echo off
rem PLAUD Companion - double-click to start. Checks for PHP + MySQL, starts the app, opens the browser.
if exist "%~dp0scripts\plaud.ps1" (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\plaud.ps1" %*
) else (
  echo Cannot find scripts\plaud.ps1 next to this file.
  pause
)
if errorlevel 1 pause
