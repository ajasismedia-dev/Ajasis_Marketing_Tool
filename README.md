# Ajasis Marketing Tool

Ajasis Media için özel olarak geliştirilen pazarlama, potansiyel müşteri keşfi, satış CRM ve mesaj merkezi yönetim uygulaması. Bu proje şu anda **Faz 5 (Mesaj Merkezi / Şablonlar / WhatsApp / Tekil E-posta)** aşamasındadır.

## Projenin Kapsamı ve Modülleri

- **Faz 1 — Altyapı & Güvenlik:** Güvenli MVC mimarisi, session-based kimlik doğrulama, CSRF/XSS korumaları, dark/lime glassmorphism arayüz teması ve kurulum sihirbazı.
- **Faz 2 — Firma & Lead Yönetimi:** Veritabanı destekli firma dizini, arama, filtreleme, sayfalama, mükerrer kayıt engelleme ve detay yönetimi.
- **Faz 3 — Lead Discovery & Enrichment:** Çok kaynaklı firma keşif motoru (Konya Ticaret Odası, Konya Sanayi Odası, OpenStreetMap), Google Places API (New) otomatik eşleştirme ve güven skoru hesaplama, web sitesi crawler pipeline (e-posta, telefon, sosyal medya tespiti).
- **Faz 4 — Sales CRM & Follow-Up:** Firma bazlı iletişim zaman çizelgesi (WhatsApp, telefon, e-posta, toplantı, iç not), durum senkronizasyonu, follow-up hatırlatıcıları (gecikmiş, bugünkü, yaklaşan), global iletişim geçmişi ve satış hunisi (pipeline).
- **Faz 5 — Mesaj Merkezi & Şablonlar:** 
  - Merkezi Mesaj Oluşturucu (`/messages`): Kanal seçimi (WhatsApp / E-posta), dinamik şablon bağlama, canlı mesaj düzenleme ve karakter sayacı.
  - Şablon Yönetimi: WhatsApp ve E-posta için kanal bazlı varsayılan şablon belirleme, transactional default yönetimi.
  - Değişken Beyaz Listesi: `{company_name}`, `{sector}`, `{district}`, `{city}`, `{website}`, `{sender_name}`, `{agency_name}` ile güvenli metin interpolasyonu (kod çalıştırma/eval riski sıfırdır).
  - WhatsApp Manuel Açma & CRM Güncelleme: `wa.me` bağlantısı, `client_message_id` ile idempotent çift tıklama korumalı zaman çizelgesi kaydı.
  - Tekil E-posta Gönderimi: PHPMailer tabanlı SMTP desteği, RFC uyumlu e-posta doğrulaması, CRLF injection temizliği, rate limiting koruması ve şifre maskeleme.
  - **Önemli Kapsam Kuralı**: Toplu mesajlaşma (bulk messaging), WhatsApp Business API, otomatik spam veya cold outreach kampanyaları KESİNLİKLE YOKTUR. Tüm iletişimler satış temsilcisinin birebir kontrolünde ve onayında gerçekleşir.

## Production Deployment Yöntemi

1. Tüm proje dosyalarını FTP/SSH ile sunucunuzun hedef dizinine yükleyin (Örn: `domains/ajasismedia.com/public_html/marketingtool/`).
2. Sunucu terminalinde bağımlılıkları yükleyin:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   *(PHPMailer kütüphanesi `vendor/` dizinine kurulur; vendor dizini git deposuna dahil edilmez)*
3. Production MariaDB veritabanında `database/schema.sql` dosyasını import edin veya migration script'lerini sırasıyla çalıştırın (`001_...` ile `005_message_center.sql`).
4. `config/config.php` dosyasını yapılandırın:
   - `ENVIRONMENT` değerini `'production'` olarak ayarlayın.
   - `BASE_PATH` değerini uygulamanın çalıştığı URL öneki ile eşleştirin (Örn: `'/marketingtool'`).
   - Production veritabanı bağlantı bilgilerini tanımlayın.
   - **Ajans & Gönderen Bilgileri:**
     ```php
     define('BUSINESS_NAME', 'Ajasis Media');
     define('SENDER_NAME', 'Muhammet Tüzün');
     ```
   - **SMTP E-posta Yapılandırması (Opsiyonel / Tekil Gönderim):**
     ```php
     define('SMTP_ENABLED', true);
     define('SMTP_HOST', 'mail.ajasismedia.com');
     define('SMTP_PORT', 587);
     define('SMTP_USERNAME', 'info@ajasismedia.com');
     define('SMTP_PASSWORD', 'GucluSifre...');
     define('SMTP_ENCRYPTION', 'tls'); // 'tls' veya 'ssl'
     define('SMTP_FROM_EMAIL', 'info@ajasismedia.com');
     define('SMTP_FROM_NAME', 'Ajasis Media');
     define('SMTP_REPLY_TO', 'destek@ajasismedia.com');
     ```
5. `storage/logs` ve `storage/cache` klasörlerine PHP/web sunucusu yazma izni (chmod 755 veya 775) verin.
6. `config`, `app`, `database`, `storage` klasörlerinin içindeki `.htaccess` dosyalarının dışarıdan erişimi engellediğini doğrulayın.

## Google Places API (New) Yapılandırması & Güvenlik
Lead zenginleştirme için Google Cloud Console üzerinden **Places API (New)** kullanılmaktadır.

1. **Google Cloud Console** üzerinde **Places API (New)** servisini etkinleştirin.
2. API Anahtarınızı kısıtlayın:
   - **API Restrictions**: Sadece `Places API (New)` seçin.
   - **Application Restrictions**: Sunucu IP kısıtlaması uygulayın.
3. `config/config.php` dosyasına anahtarınızı tanımlayın:
   ```php
   define('GOOGLE_PLACES_API_KEY', 'AIzaSy...');
   define('GOOGLE_PLACES_ENABLED', true);
   ```
4. **Önemli Güvenlik Notu**: API anahtarı ve SMTP kimlik bilgileri asla frontend JavaScript'e gönderilmez, HTML sayfalarına basılmaz, loglara açık yazılmaz ve git commit'lerine dahil edilmez.

## Test Paketleri

- **Normal Birim Testleri (Varsayılan):**
  ```bash
  php tests/run_all.php
  ```
  → 100% Deterministic & Network-free çalışır; harici portallara veya ücretli Google Places API'ye istek atmaz.
  → Şablon oluşturucu, e-posta doğrulama, normalizasyon, durum geçişleri ve CRM idempotency kontrollerini içerir.

- **Harici Portal Canlı Entegrasyon Testi (KSO / KTO):**
  ```bash
  php tests/integration/lead_sources_live.php
  ```
  → KSO ve KTO kamuya açık arama sayfalarının canlı bağlantısını ve veri ayrıştırmasını test eder.

- **Canlı Google Places Entegrasyon Testi:**
  ```bash
  php tests/integration/google_places_live.php
  ```
  → Gerçek Google Places API çağrısı yapar ve quota tüketir. Yalnızca manuel doğrulama için kullanılmalıdır.
