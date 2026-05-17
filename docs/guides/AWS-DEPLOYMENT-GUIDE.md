# AWS Deployment Guide - Gyanmanjari Counselling System

## 🚀 Current Setup

- **Platform:** AWS EC2 Instance
- **Instance Type:** c5.xlarge (4 vCPU, 8 GB RAM)
- **Operating System:** Windows Server 2025 Base
- **Web Stack:** XAMPP (Apache, MySQL, PHP)
- **Domain:** gyanmanjari.com
- **Elastic IP:** Attached to EC2 and pointed to domain
- **Environment:** Production (Auto-detected)

## ✅ Configuration Updated

### Files Modified:

1. `env.config.php` (root)
2. `counselling-backend/env.config.php`

### What's Auto-Configured:

#### Local Environment (WAMP/XAMPP on Windows):

```php
Database: 192.168.0.197/counselling
User: root
Password: (empty)
URLs: http://192.168.0.197/counselling
```

#### Production Environment (AWS EC2 Windows):

```php
Database: 192.168.0.197/gyanmanj_counselling
User: gyanmanj_counselling
Password: Counselling@2025  // 🔒 Update this!
URLs: https://gyanmanjari.com
```

## 🔧 AWS EC2 Windows Setup Steps

### 1. Launch EC2 Instance

**In AWS Console → EC2 → Launch Instance:**

- **Name:** Gyanmanjari-Production
- **AMI:** Microsoft Windows Server 2025 Base
- **Instance Type:** c5.xlarge (4 vCPU, 8 GB RAM)
- **Key Pair:** Create or select existing (.pem for conversion to .ppk)
- **Security Group:** Create with rules below

**Inbound Rules:**

```
Type        Port    Source          Description
RDP         3389    Your-IP/32      Remote Desktop access
HTTP        80      0.0.0.0/0       Web traffic
HTTPS       443     0.0.0.0/0       Secure web traffic
```

### 2. Connect to EC2 Instance (Remote Desktop)

```
1. Go to AWS Console → EC2 → Instances → Select your instance
2. Click "Connect" → "RDP Client" tab
3. Click "Get Password" → Upload your .pem key file → Decrypt
4. Download Remote Desktop File or note the Public DNS/IP
5. Use Remote Desktop Connection (mstsc) with:
   - Computer: your-elastic-ip or Public DNS
   - Username: Administrator
   - Password: (decrypted password from step 3)
```

### 3. Download and Install XAMPP

```powershell
# Download XAMPP (PHP 8.2+ recommended)
# Option 1: Use browser to download from https://www.apachefriends.org/download.html
# Option 2: Use PowerShell
Invoke-WebRequest -Uri "https://www.apachefriends.org/xampp-files/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe" -OutFile "C:\Users\Administrator\Downloads\xampp-installer.exe"

# Run installer with default options
# Install to: C:\xampp
```

**XAMPP Installation Steps:**

1. Run the downloaded installer
2. Accept UAC prompt
3. Select components: Apache, MySQL, PHP, phpMyAdmin
4. Install to `C:\xampp`
5. Complete installation

### 4. Configure XAMPP

**Start XAMPP Control Panel:**

```
Run: C:\xampp\xampp-control.exe
- Start Apache
- Start MySQL
```

**Configure Apache for Production:**

1. Edit `C:\xampp\apache\conf\httpd.conf`:

```apache
# DocumentRoot stays default (around line 252)
DocumentRoot "C:/xampp/htdocs"
<Directory "C:/xampp/htdocs">
    Options -Indexes +FollowSymLinks +MultiViews
    AllowOverride All
    Require all granted
</Directory>

# Enable mod_rewrite (uncomment around line 182)
LoadModule rewrite_module modules/mod_rewrite.so
```

2. Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName gyanmanjari.com
    ServerAlias www.gyanmanjari.com
    DocumentRoot "C:/xampp/htdocs"

    <Directory "C:/xampp/htdocs">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/gyanmanjari-error.log"
    CustomLog "logs/gyanmanjari-access.log" combined
</VirtualHost>
```

### 5. Configure MySQL Database

**Using XAMPP MySQL:**

1. Open phpMyAdmin: `http://192.168.0.197/phpmyadmin`
2. Go to "User accounts" → "Add user account"
3. Create database and user:

```sql
-- Create database
CREATE DATABASE gyanmanj_counselling CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user with password
CREATE USER 'gyanmanj_counselling'@'192.168.0.197' IDENTIFIED BY 'YourStrongPassword@2025';

-- Grant privileges
GRANT ALL PRIVILEGES ON gyanmanj_counselling.* TO 'gyanmanj_counselling'@'192.168.0.197';
FLUSH PRIVILEGES;
```

**🔒 Important:** Update password in `env.config.php`:

```php
$password = "YourStrongPassword@2025";  // Line ~21
```

### 6. Upload Application Files

#### Option A: Using SFTP (FileZilla/WinSCP)

```
# Enable OpenSSH Server on Windows Server
# Settings → Apps → Optional Features → Add feature → OpenSSH Server → Install

Host: your-elastic-ip or gyanmanjari.com
Port: 22
Protocol: SFTP
Username: Administrator
Password: (your Windows password)
```

Upload to: `C:\xampp\htdocs\` (replace default files)

#### Option B: Using Remote Desktop (Drag & Drop)

1. Connect via RDP
2. Enable local drives in RDP settings
3. Delete default XAMPP files from `C:\xampp\htdocs\` (index.php, dashboard folder, etc.)
4. Copy your project files directly to `C:\xampp\htdocs\`

> **📝 Note:** Remove default XAMPP files (index.php, dashboard/, etc.) before uploading your project files. Your project files should be directly in `C:\xampp\htdocs\` root, so the URL will be `https://gyanmanjari.com/` instead of `https://gyanmanjari.com/counselling/`.

#### Option C: Using Git

```powershell
# Install Git for Windows (download from git-scm.com)
# First, clean the htdocs folder
Remove-Item C:\xampp\htdocs\* -Recurse -Force
cd C:\xampp\htdocs
git clone https://github.com/your-repo/counselling.git .  # Note the dot to clone into current directory

# Or pull if already exists
cd C:\xampp\htdocs
git pull origin main
```

### 7. Configure PHP Settings

Edit `C:\xampp\php\php.ini`:

```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
max_execution_time = 300
display_errors = Off
error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED
```

Restart Apache from XAMPP Control Panel after changes.

### 8. Install SSL Certificate (HTTPS)

#### Option A: Using Certify The Web (Recommended for Let's Encrypt)

```powershell
# Download Certify The Web from https://certifytheweb.com/
# Free version supports up to 5 domains

# Installation steps:
# 1. Download installer from https://certifytheweb.com/
# 2. Run installer as Administrator
# 3. Install to default location (C:\Program Files\CertifyTheWeb\)
```

**Configure SSL Certificate:**

1. Launch "Certify The Web" application
2. Click "New Certificate"
3. **Certificate Tab:**
   - **Primary Domain:** gyanmanjari.com
   - **Additional Domains:** www.gyanmanjari.com (click + to add)
4. **Authorization Tab:**
   - **Challenge Type:** http-01
   - **Website Root Path:** C:\xampp\htdocs
5. **Deployment Tab:**
   - **Deployment Mode:** Select "Certificate Store Only"
   - (We'll manually configure Apache since XAMPP isn't auto-detected)
6. Click "Request Certificate" button
7. Wait for certificate to be issued (usually takes 1-2 minutes)
8. **Enable Auto Renew:**
   - After successful certificate creation
   - Go to **Tasks** tab
   - Ensure "Auto" renewal is enabled (certificates renew automatically every 60 days)

**Certificate Files Location:**
After issuance, certificate files are stored as PFX (PKCS#12) format at:
```
C:\ProgramData\Certify\assets\gyanmanjari.com\
├── 20260413_a2e643ee.pfx   (Latest certificate bundle - includes cert + private key)
├── 20260407_i03a80ed.pfx   (Previous certificate - kept for backup)
```

**Extract PEM Files for Apache (Required):**

Since Apache needs PEM format, extract the certificate and key from PFX:

```powershell
# Run in PowerShell as Administrator
cd "C:\ProgramData\Certify\assets\gyanmanjari.com"

# Find the latest PFX file (most recent date)
$latestPfx = Get-ChildItem *.pfx | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$pfxPath = $latestPfx.FullName
Write-Host "Using certificate: $($latestPfx.Name)"

# Extract certificate (fullchain.pem)
openssl pkcs12 -in "$pfxPath" -clcerts -nokeys -out fullchain.pem -passin pass:

# Extract private key (privkey.pem)
openssl pkcs12 -in "$pfxPath" -nocerts -nodes -out privkey.pem -passin pass:
```

**Note:** If `openssl` command not found, use the one bundled with XAMPP:
```powershell
cd "C:\ProgramData\Certify\assets\gyanmanjari.com"
$latestPfx = Get-ChildItem *.pfx | Sort-Object LastWriteTime -Descending | Select-Object -First 1

# Use XAMPP's OpenSSL
& "C:\xampp\apache\bin\openssl.exe" pkcs12 -in $latestPfx.FullName -clcerts -nokeys -out fullchain.pem -passin pass:
& "C:\xampp\apache\bin\openssl.exe" pkcs12 -in $latestPfx.FullName -nocerts -nodes -out privkey.pem -passin pass:
```

After extraction, you'll have:
```
C:\ProgramData\Certify\assets\gyanmanjari.com\
├── fullchain.pem   (Certificate - extracted from PFX)
├── privkey.pem     (Private Key - extracted from PFX)
```

**Manual Apache SSL Configuration after certificate is issued:**

Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`:

```apache
<VirtualHost *:443>
    ServerName gyanmanjari.com
    ServerAlias www.gyanmanjari.com
    DocumentRoot "C:/xampp/htdocs"

    SSLEngine on
    SSLCertificateFile "C:/ProgramData/Certify/certes/assets/gyanmanjari.com/fullchain.pem"
    SSLCertificateKeyFile "C:/ProgramData/Certify/certes/assets/gyanmanjari.com/privkey.pem"

    <Directory "C:/xampp/htdocs">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable SSL in `C:\xampp\apache\conf\httpd.conf`:

```apache
# Uncomment these lines
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

Restart Apache from XAMPP Control Panel.

#### Option B: Manual SSL Configuration

1. Obtain SSL certificate files (.crt, .key)
2. Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`:

```apache
<VirtualHost *:443>
    ServerName gyanmanjari.com
    ServerAlias www.gyanmanjari.com
    DocumentRoot "C:/xampp/htdocs"

    SSLEngine on
    SSLCertificateFile "C:/xampp/apache/conf/ssl/gyanmanjari.crt"
    SSLCertificateKeyFile "C:/xampp/apache/conf/ssl/gyanmanjari.key"
    SSLCertificateChainFile "C:/xampp/apache/conf/ssl/gyanmanjari-ca.crt"

    <Directory "C:/xampp/htdocs">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Enable SSL in `C:\xampp\apache\conf\httpd.conf`:

```apache
# Uncomment these lines
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

### 9. Import Database

#### Via phpMyAdmin:

1. Open: `http://192.168.0.197/phpmyadmin`
2. Select `gyanmanj_counselling` database
3. Click "Import" tab
4. Choose `counselling.sql` file
5. Click "Go"

#### Via Command Line:

```powershell
cd C:\xampp\mysql\bin
.\mysql.exe -u gyanmanj_counselling -p gyanmanj_counselling < C:\path\to\counselling.sql
```

### 10. Configure Windows Firewall

```powershell
# Run as Administrator
# Allow HTTP
netsh advfirewall firewall add rule name="HTTP" dir=in action=allow protocol=TCP localport=80

# Allow HTTPS
netsh advfirewall firewall add rule name="HTTPS" dir=in action=allow protocol=TCP localport=443

# Allow MySQL (only if remote access needed)
# netsh advfirewall firewall add rule name="MySQL" dir=in action=allow protocol=TCP localport=3306
```

### 11. Set XAMPP to Start on Boot

**Create Scheduled Task:**

```powershell
# Run as Administrator
schtasks /create /tn "XAMPP-Apache" /tr "C:\xampp\apache\bin\httpd.exe -k start" /sc onstart /ru SYSTEM
schtasks /create /tn "XAMPP-MySQL" /tr "C:\xampp\mysql\bin\mysqld.exe" /sc onstart /ru SYSTEM
```

**Or use XAMPP Service:**

```powershell
# Install as Windows Service (run XAMPP Control Panel as Administrator)
# Click "X" next to Apache → Click "Install" when prompted
# Click "X" next to MySQL → Click "Install" when prompted
```

## 🧪 Testing Checklist

After deployment, test these:

### 1. Website Access

- [ ] http://gyanmanjari.com (should redirect to HTTPS)
- [ ] https://gyanmanjari.com (homepage loads)
- [ ] https://gyanmanjari.com/portal (student portal)
- [ ] https://gyanmanjari.com/counselling-backend (admin panel)

### 2. Database Connection

```powershell
# Test from Command Prompt
cd C:\xampp\mysql\bin
.\mysql.exe -u gyanmanj_counselling -p gyanmanj_counselling -e "SHOW TABLES;"
```

### 3. PHP Configuration

Create test file:

```powershell
echo "<?php phpinfo(); ?>" > C:\xampp\htdocs\info.php
```

Visit: https://gyanmanjari.com/info.php
Delete after checking: `del C:\xampp\htdocs\info.php`

### 4. File Upload Test

- Test student registration with photo upload
- Check uploads directory: `C:\xampp\htdocs\uploads\`

### 5. Email & WhatsApp Test

- Send test email (check SMTP config)
- Send test WhatsApp message
- Check logs: `counselling-backend\logs\`

### 6. Payment Gateway Test

- Test Easebuzz payment (ensure production keys are configured)
- Verify webhook URLs in Easebuzz dashboard

## 📊 Monitoring & Maintenance

### View Apache Logs

```powershell
# Error logs
Get-Content C:\xampp\apache\logs\gyanmanjari-error.log -Tail 50

# Access logs
Get-Content C:\xampp\apache\logs\gyanmanjari-access.log -Tail 50
```

### View Application Logs

```powershell
cd C:\xampp\htdocs\counselling-backend\logs
Get-Content error.log -Tail 50
```

### Database Backup (Automated)

**Create PowerShell backup script:**
Save as `C:\Scripts\backup-db.ps1`:

```powershell
$date = Get-Date -Format "yyyyMMdd_HHmmss"
$backupPath = "C:\Backups\counselling_$date.sql"

# Create backup
& "C:\xampp\mysql\bin\mysqldump.exe" -u gyanmanj_counselling -pYourPassword gyanmanj_counselling > $backupPath

# Keep only last 7 days
Get-ChildItem "C:\Backups" -Filter "counselling_*.sql" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-7) } | Remove-Item
```

**Schedule with Task Scheduler:**

```powershell
schtasks /create /tn "DB-Backup" /tr "powershell.exe -File C:\Scripts\backup-db.ps1" /sc daily /st 02:00 /ru SYSTEM
```

## 🔄 Update/Deployment Workflow

### For code updates:

```powershell
# Connect via RDP
cd C:\xampp\htdocs
git pull origin main

# Restart Apache from XAMPP Control Panel
```

### For database changes:

```powershell
cd C:\xampp\mysql\bin
.\mysql.exe -u gyanmanj_counselling -p gyanmanj_counselling < C:\path\to\migration.sql
```

## 🆘 Troubleshooting

### Issue: Site not loading

```powershell
# Check if Apache is running
Get-Service -Name "Apache*"

# Check XAMPP Control Panel for errors
# View error log
Get-Content C:\xampp\apache\logs\error.log -Tail 50
```

### Issue: Database connection failed

```powershell
# Check if MySQL is running
Get-Service -Name "mysql*"

# Test MySQL connection
cd C:\xampp\mysql\bin
.\mysql.exe -u gyanmanj_counselling -p
```

### Issue: Port 80/443 already in use

```powershell
# Find what's using the port
netstat -ano | findstr :80
netstat -ano | findstr :443

# Common culprits: IIS, Skype, other web servers
# Stop IIS if running
iisreset /stop
```

### Issue: Environment not detected correctly

Edit env.config.php and force production mode:

```php
define('FORCE_PRODUCTION_MODE', true);  // Line 12
```

## 📝 Important Configuration Files

1. **Database:** `env.config.php` (Line 19-23)
2. **SMTP:** `counselling-backend/env.config.php` (Line 75-84)
3. **WhatsApp:** `counselling-backend/env.config.php` (Line 100-108)
4. **Payment Gateway:** Already configured with production keys
5. **URLs:** Auto-detected based on domain
6. **Apache Config:** `C:\xampp\apache\conf\httpd.conf`
7. **PHP Config:** `C:\xampp\php\php.ini`

## 🔐 Security Recommendations

1. ✅ Use strong database passwords
2. ✅ Keep Windows Server updated (Windows Update)
3. ✅ Enable Windows Firewall with proper rules
4. ✅ Restrict RDP access to your IP only (Security Group)
5. ✅ Regular database backups
6. ✅ Monitor server logs
7. ✅ Use HTTPS (SSL certificate installed)
8. ⚠️ Never commit env.config.php to Git with production passwords
9. ✅ Change default phpMyAdmin URL or restrict access
10. ✅ Disable directory listing in Apache

## 🎯 Next Steps

1. Update database password in env.config.php
2. Update SMTP password (currently placeholder)
3. Test all functionalities
4. Setup automated backups
5. Configure monitoring/alerts
6. Document any custom configurations
7. Consider setting up a secondary security group rule for backup access

---

**Deployment Date:** January 11, 2026  
**Server:** AWS EC2 c5.xlarge with Elastic IP  
**OS:** Windows Server 2025 Base  
**Stack:** XAMPP (Apache, MySQL, PHP)  
**Domain:** gyanmanjari.com  
**Status:** Ready for Production

For support: Check logs in `C:\xampp\apache\logs\` and `counselling-backend\logs\`
