# تشغيل النظام على PHP وMySQL

## Hostinger أو cPanel

1. أنشئ قاعدة MySQL ومستخدمًا لها.
2. افتح phpMyAdmin واستورد `database.sql`.
3. انسخ `config/database.example.php` إلى `config/database.php` وأدخل بيانات القاعدة.
4. ارفع الملفات إلى `public_html`.
5. افتح `https://YOUR-DOMAIN/setup.php` وأنشئ حساب المدير.
6. سجّل الدخول ثم احذف `setup.php` من الاستضافة.

المتطلبات: PHP 8.1+ مع `PDO` و`pdo_mysql`، وMySQL 5.7+/MariaDB 10.4+.
ملف `manifest.json` خاص بتثبيت الموقع كتطبيق وليس قاعدة بيانات.
