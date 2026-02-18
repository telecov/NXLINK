**Dashboard Web NXLINK para Reflector nxdn**  

---

🖥️ Requisitos
*** Tener instalado y corriendo DVREFLECTOR de NOSTAR ***
https://github.com/nostar/DVReflectors

si ya lo tienes instalado y funcionando, puedes saltar directamente a la instalacion del DASHBOARD, ahora si estas iniciando puedes seguir el paso a paso apoyando de este video

PRECAUCION, si ya tienes un dashboard web funcionando te recomiendo realizar backup, o instalar este dashboard paralelo para que lo pruebes antes, por ejemplo guardarlo en html/nxdn/ para asi no perder lo que tienes en html, si es de tu gusto puede eliminar todo y seguir el procedimieto 

###

* Hardware recomendado:

* Requisitos mínimos:
CPU: Dual Core 1.2 GHz o superior (Intel Atom / Celeron)
RAM: 1 GB mínimo (2 GB recomendado)
Almacenamiento: 8 GB (SD o HDD)
Red: Ethernet 100 Mbps o Wi-Fi b/g/n
SO: Debian 12, Debian 13, Ubuntu Server, Raspbian, Bannanian 

* Raspberry PI 3 

NXLINK ha sido probado y funciona de forma óptima en:

Distribución recomendada: Debian 12+ / Raspbian 12
Entornos compatibles: Raspberry Pi OS, Ubuntu Server, Armbian (bookwoorm)
Equipo recomendado: Computador o mini-servidor con Linux

Software necesario:

Apache2
PHP 8.2 o superior
Git
cURL

** Software necesario para configurar **
IPSCANNER - para identificar ip de equipo
PUTTY - para administrar Linux por SSH

Para instalar en Raspberry OS se recomieda Raspberry pi Imager


📡 Instalación del Reflector P25 (DVReflector)

Creacion de usuario

Descargar DVReflector NXDN

```bash
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 777 DVReflectors
```
Compilar e instalar

```bash
cd /opt/DVReflectors/NXDNReflector
make clean
make -j4
```
Copiar archivo INI a /etc/

```bash
sudo mkdir -p /etc/NXDNReflector
sudo cp /opt/DVReflectors/NXDNReflector/NXDNReflector.ini /etc/NXDNReflector/
```
Crear carpeta de logs

```bash
sudo mkdir -p /var/log/nxdnreflector
sudo chmod 777 /var/log/nxdnreflector
```

Configurar el archivo /etc/NXDNReflector/NXDNReflector.ini
```bash
sudo nano /etc/NXDNReflector/NXDNReflector.ini
```
```bash
[General]
TG=9999
Daemon=0

[Id Lookup]
Name=/opt/DVReflectors/P25Reflector/DMRIds.dat
Time=24

[Log]
# Logging levels, 0=No logging
DisplayLevel=1
FileLevel=1
FilePath=var/log/nxdnreflector
FileRoot=NXDNReflector
FileRotate=1

[Network]
Port=41400
Debug=0
```

Crear servicio Systemd para autoinicio

```bash
sudo nano /etc/systemd/system/nxdnreflector.service
```

```bash
[Unit]
Description=NXDN Reflector
After=network.target

[Service]
User=teleco
ExecStart=/opt/DVReflectors/NXDNReflector/NXDNReflector /etc/NXDNReflector/NXDNReflector.ini
Restart=always

[Install]
WantedBy=multi-user.target
```

Permisos VISUDO para ejecutar cambios en el servidor 

```bash
sudo visudo -f /etc/sudoers.d/nxlynk
```

```bash
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl start nxdnreflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl stop nxdnreflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl restart nxdnreflector.service
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl status nxdnreflector.service
www-data ALL=(ALL) NOPASSWD:/usr/sbin/reboot
www-data ALL=(ALL) NOPASSWD:/usr/bin/nmcli
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable nxdnreflector.service
sudo systemctl start nxdnreflector.service
sudo systemctl status nxdnreflector.service
```


## 📦 Instalación del Dashboard

🧰 Paso a paso

```bash
sudo apt update
sudo apt install apache2 -y
sudo apt install php libapache2-mod-php -y
sudo apt install php-curl unzip -y
sudo apt install network-manager -y
sudo apt install git -y
```

1. Copia la carpeta completa **LYNK25** a tu servidor web:  
```bash
cd /var/www/
sudo rm -rf /var/www/html
sudo git clone https://github.com/telecov/NXLINK.git html
```

2. Permisos
```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

2.1 Crear servicio Telegram Tiempo Real
```bash
sudo nano /etc/systemd/system/nxdn-telegram-realtime.service
```
escribe, guarda este servicio
```bash
[Unit]
Description=NXDN Telegram Realtime Notifier
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/bin/php /var/www/html/scripts/nxdn_telegram_realtime.php
Restart=always
RestartSec=2
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

Activa y verifica el servicio

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nxdn-telegram-realtime
sudo systemctl status nxdn-telegram-realtime --no-pager
```

Ejecucion de Cron para informes del servidor via telegram y Alerta de Reflector

```bash
sudo crontab -u www-data -e
```

```bash
*/10 * * * * /usr/bin/php /var/www/html/NXDN/includes/telegram_notify.php >> /var/www/html/NXDN/data/cron_telegram_notify.log 2>&1
```

Verifica el Crontab

```bash
sudo crontab -u www-data -l
```

3. Acceso WEB
Accede desde tu navegador:

http://ip_de_tu_servidor/


## 🧠 Configuración Inicial

Toda la configuración de LYN se realiza desde la interfaz web, sin editar archivos manualmente.
Página de Personalización y configuracion

Accede a:
http://tu-servidor/personalizar.php
http://tu-servidor/configuracion.php

Contraseña por defecto

```bash
  nxlink2025
```

Desde esta página podrás configurar:

## 🛰️ DVReflector

Nombre del sistema o reflector
Dirección IP o dominio del reflector P25
Puerto y descripción
Estado de enlace y estadísticas

## 💬 Telegram

* Activar o desactivar notificaciones

Configura Telegram (opcional)
Crea un bot en @BotFather
Obten el token http api
crea un canal o agraga tu bot como admin al grupo Telegram
buscar el ID del canal o grupo a utilizar https://api.telegram.org/bot/getUpdates
Asociar grupo o canal


## Personalizar el título y lema del proyecto

