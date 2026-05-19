@echo off

echo ========================================
echo   Starting Environment from Task Tool...
echo ========================================

:: 1. Run the main environment script at D:\Apps\start_all.bat
:: Use "start" so it runs in its own correct directory context
echo [1/2] Launching server environment...
start "" /d "D:\Apps" "start_all.bat"

:: 2. Wait a moment for Apache to ready up (2 seconds)
timeout /t 2 > nul

:: 3. Launch default browser and open the task tool index
echo [2/2] Opening task tool in browser...
start "" "http://localhost/GE3A31/TaskManagementToul/index.php"

echo ========================================
echo   Success! This window will close.
echo ========================================
timeout /t 2 > nul
exit