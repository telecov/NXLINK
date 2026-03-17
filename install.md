# 📡 Instalación NXLink Dashboard

### Dashboard Web para Reflector NXDN

🌐 [Ver documentación en Español](install.md)  
🌐 [View documentation in English](install_en.md)

---

## 🖥️ Requisitos

⚠️ **IMPORTANTE:**
Debes tener instalado y funcionando previamente **DVReflector de NØSTAR**

👉 https://github.com/nostar/DVReflectors

Si ya tienes tu reflector funcionando, puedes ir directamente a la instalación del dashboard.

Si estás comenzando desde cero, puedes apoyarte en el video de instalación.

---

## ⚠️ Recomendación previa

Si ya tienes un dashboard web funcionando:

* Realiza un **backup completo**
* O instala NXLink en una ruta alternativa (ej: `/var/www/html/nxdn/`)

Esto evita perder configuraciones existentes.

---

## 💻 Hardware recomendado

### Requisitos mínimos

* CPU: Dual Core 1.2 GHz o superior (Intel Atom / Celeron)
* RAM: 1 GB mínimo (2 GB recomendado)
* Almacenamiento: 8 GB (SD o HDD)
* Red: Ethernet 100 Mbps o WiFi b/g/n
* Sistema operativo:

  * Debian 12 / 13
  * Ubuntu Server
  * Raspberry Pi OS
  * Armbian (Bookworm)

---

## 🧪 Plataformas probadas

NXLink ha sido probado en:

* Raspberry Pi 3 / 4
* Debian 12+
* Raspberry Pi OS
* Ubuntu Server
* Banana Pi / Armbian

---

## 📦 Software requerido

* Apache2
* PHP 8.2 o superior
* Git
* cURL

---

## 🛠 Herramientas recomendadas

* **IP Scanner** → identificar IP del equipo
* **PuTTY** → acceso SSH
* **Raspberry Pi Imager** → instalación de sistema

---

# 📡 Instalación del Reflector NXDN (DVReflector)

## 1️⃣ Actualizar repositorios

```bash
sudo apt update
```

---

## 2️⃣ Descargar DVReflector

```bash
cd /opt
sudo git clone https://github.com/nostar/DVReflectors.git
sudo chmod -R 777 DVReflectors
```

---

## 3️⃣ Compilar

```bash
cd /opt/DVReflectors/NXDNReflector
make clean
make -j4
```

---

## 4️⃣ Copiar configuración

```bash
sudo mkdir -p /etc/NXDNReflector
sudo cp NXDNReflector.ini /etc/NXDNReflector/
```

---

## 5️⃣ Crear logs

```bash
sudo mkdir -p /var/log/nxdnreflector
sudo chmod 777 /var/log/nxdnreflector
```

---

## 6️⃣ Configurar archivo

```bash
sudo nano /etc/NXDNReflector/NXDNReflector.ini
```

```ini
[General]
TG=9999
Daemon=0

[Id Lookup]
Name=nxdn.csv
Time=24

[Log]
DisplayLevel=1
FileLevel=1
FilePath=var/log/nxdnreflector
FileRoot=NXDNReflector
FileRotate=1

[Network]
Port=41400
Debug=0
```

---

## 7️⃣ Crear servicio systemd

⚠️ Cambia `User=teleco` por tu usuario real

```bash
sudo nano /etc/systemd/system/nxdnreflector.service
```

```ini
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

---

## 8️⃣ Permisos sudo (VISUDO)

```bash
sudo visudo -f /etc/sudoers.d/nxlink
```

```bash
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl start nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl stop nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl restart nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/bin/systemctl status nxdnreflector
www-data ALL=(ALL) NOPASSWD:/usr/sbin/reboot
www-data ALL=(ALL) NOPASSWD:/usr/bin/nmcli
```

---

## 9️⃣ Permisos archivo INI

```bash
sudo chown root:www-data /etc/NXDNReflector/NXDNReflector.ini
sudo chmod 664 /etc/NXDNReflector/NXDNReflector.ini
```

---

## 🔟 Activar servicio

```bash
sudo systemctl daemon-reload
sudo systemctl enable nxdnreflector
sudo systemctl start nxdnreflector
sudo systemctl status nxdnreflector
```

---

# 📦 Instalación del Dashboard NXLink

## 1️⃣ Instalar dependencias

```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-curl unzip git network-manager -y
sudo systemctl restart apache2
```

---

## 2️⃣ Clonar dashboard

```bash
cd /var/www/
sudo rm -rf html
sudo git clone https://github.com/telecov/NXLINK.git html
```

---

## 3️⃣ Permisos

```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

---

## 4️⃣ Servicio Telegram en tiempo real

```bash
sudo nano /etc/systemd/system/nxdn-telegram-realtime.service
```

```ini
[Unit]
Description=NXDN Telegram Realtime Notifier
After=network-online.target

[Service]
ExecStart=/usr/bin/php /var/www/html/scripts/nxdn_telegram_realtime.php
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nxdn-telegram-realtime
```

---

## 5️⃣ Cron para notificaciones

```bash
sudo crontab -u www-data -e
```

```bash
*/10 * * * * /usr/bin/php /var/www/html/NXDN/includes/telegram_notify.php
```

---

## 🌐 Acceso web

Accede desde tu navegador:

```
http://IP_DEL_SERVIDOR/
```

---

# ⚙️ Configuración inicial

## Panel web

```
http://IP_DEL_SERVIDOR/personalizar.php
http://IP_DEL_SERVIDOR/configuracion.php
```

---

## 🔐 Contraseña por defecto

```
nxlink2025
```

⚠️ Se recomienda cambiarla inmediatamente.

---

## ⚙️ Configuración disponible

### 🛰️ Reflector

* Nombre del sistema
* IP / dominio
* Puerto
* Estado y estadísticas

---

### 💬 Telegram (opcional)

1. Crear bot en **@BotFather**
2. Obtener token
3. Crear grupo o canal
4. Agregar bot como administrador
5. Obtener ID del grupo

👉 https://api.telegram.org/bot<TOKEN>/getUpdates

---

## ✅ Listo

Tu sistema NXLink estará operativo y accesible vía web.
