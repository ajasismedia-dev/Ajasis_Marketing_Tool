# Ajasis Marketing Tool

Ajasis Media için özel olarak geliştirilen pazarlama ve lead yönetim uygulaması. Bu proje şu anda **Faz 1 (Temel Altyapı)** aşamasındadır.

## Projenin Amacı
İlerleyen aşamalarda Konya'daki firmaları bulacak, firma bilgilerini yönetecek, sosyal medya ve web sitesi takibi yapacak, WhatsApp entegrasyonu sunacak ve iletişim geçmişini saklayacaktır. 
Şu anki (Faz 1) sürümü ile uygulamanın güvenli mimarisi, veritabanı bağlantısı, session-based auth sistemi, tasarım standartları ve dizin yapısı oluşturulmuştur.

## Gereksinimler
- PHP 8.4
- MariaDB 10.5
- LiteSpeed (veya Apache, mod_rewrite aktif)
- PHP cURL ve allow_url_fopen (İleriki fazlar için)
- PDO eklentisi

## Lokal Kurulum Adımları

1. Bu projeyi lokal sunucunuzun (XAMPP, MAMP, Valet vb.) ilgili dizinine kopyalayın.
2. MariaDB üzerinde `ajasis_marketing` adında boş bir veritabanı oluşturun (Karakter seti `utf8mb4_unicode_ci` olmalı).
3. Veritabanı tablolarını içeri aktarmak için terminalde veya phpMyAdmin üzerinden şu işlemi yapın:
   ```bash
   mysql -u root -p ajasis_marketing < database/schema.sql
   ```
4. `config/config.php` dosyasını açıp veritabanı bilgilerinizi ve `BASE_PATH` değişkenini lokal ortamınıza göre düzenleyin. 
   - Proje ana dizindeyse: `define('BASE_PATH', '');`
   - Proje bir alt klasördeyse (örneğin `/marketingtool`): `define('BASE_PATH', '/marketingtool');`
5. Kurulum tamamlandı. Uygulamaya tarayıcıdan erişebilirsiniz (Örn: `http://localhost/marketingtool/login`).

## İlk Kullanıcı Girişi
`schema.sql` içeri aktarıldığında hiçbir kullanıcı oluşturulmaz. İlk kullanıcıyı oluşturmak için:
- Tarayıcıdan uygulamanın `/setup` rotasına gidin (Örn: `http://localhost/marketingtool/setup`).
- Karşınıza çıkan ekrandan ilk yönetici hesabını oluşturun.
- Bu ekran yalnızca veritabanında hiç kullanıcı yoksa aktiftir. Kullanıcı oluşturulduktan sonra güvenlik sebebiyle `/setup` rotasına erişilemez.

## Production Deployment Yöntemi

1. Tüm proje dosyalarını FTP/SSH ile `domains/ajasismedia.com/public_html/marketingtool/` dizinine yükleyin.
2. Production ortamındaki MariaDB'de veritabanını ve kullanıcıyı oluşturup `schema.sql` dosyasını import edin.
3. `config/config.php` dosyasını açın:
   - `ENVIRONMENT` değerini `'production'` olarak değiştirin.
   - `BASE_PATH` değerini `'/marketingtool'` olarak ayarlayın.
   - Production veritabanı bilgilerinizi (`DB_NAME`, `DB_USER`, `DB_PASS`) girin.
4. `storage/logs` klasörüne PHP/web sunucusu yazma izni (chmod 755 veya 775) verin.
5. `config`, `app`, `database`, `storage` klasörlerinin içindeki `.htaccess` dosyalarının dışarıdan erişimi kapattığını test edin (Örn: `https://ajasismedia.com/marketingtool/config/config.php` 403 Forbidden vermelidir).
