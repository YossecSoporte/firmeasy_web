@echo off
echo Abriendo puerto 8080 en firewall para Girasol...
netsh advfirewall firewall add rule name="Girasol 8080" dir=in protocol=tcp localport=8080 action=allow
echo.
echo Hecho. El puerto 8080 ya esta abierto.
echo Desde tu Mac accede a: http://192.168.15.21:8080/index.html
pause
