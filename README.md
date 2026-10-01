# Ajasis Marketing Tool

Ajasis Media için özel olarak geliştirilen pazarlama, potansiyel müşteri keşfi ve satış CRM yönetim uygulaması. Bu proje şu anda **Faz 4 (Sales CRM / İletişim Geçmişi & Follow-Up)** aşamasındadır.

## Projenin Kapsamı ve Modülleri

- **Faz 1 — Altyapı & Güvenlik:** Güvenli MVC mimarisi, session-based kimlik doğrulama, CSRF/XSS korumaları, dark/lime glassmorphism arayüz teması ve kurulum sihirbazı.
- **Faz 2 — Firma & Lead Yönetimi:** Veritabanı destekli firma dizini, arama, filtreleme, sayfalama, mükerrer kayıt engelleme ve detay yönetimi.
- **Faz 3 — Lead Discovery & Enrichment:** Çok kaynaklı firma keşif motoru (Konya Ticaret Odası, Konya Sanayi Odası, OpenStreetMap), Google Places API (New) otomatik eşleştirme ve güven skoru hesaplama, web sitesi crawler pipeline (e-posta, telefon, sosyal medya tespiti).
- **Faz 4 — Sales CRM & Follow-Up:** Firma bazlı iletişim zaman çizelgesi (WhatsApp, telefon, e-posta, toplantı, iç not), durum senkronizasyonu, follow-up hatırlatıcıları (gecikmiş, bugünkü, yaklaşan), global iletişim geçmişi ve satış hunisi (pipeline).


## Production Deployment Yöntemi

1. Tüm proje dosyalarını FTP/SSH ile `domains/ajasismedia.com/public_html/marketingtool/` dizinine yükleyin.
2. Production ortamındaki MariaDB'de veritabanını ve kullanıcıyı oluşturup `schema.sql` dosyasını import edin.
   *(Eğer Faz 1'den kalma bir veritabanınız varsa, `schema.sql` içindeki `companies` tablosunu elle oluşturmanız gerekebilir)*
3. `config/config.php` dosyasını açın:
   - `ENVIRONMENT` değerini `'production'` olarak değiştirin.
   - `BASE_PATH` değerini `'/marketingtool'` olarak ayarlayın.
   - Production veritabanı bilgilerinizi (`DB_NAME`, `DB_USER`, `DB_PASS`) girin.
4. `storage/logs` klasörüne PHP/web sunucusu yazma izni (chmod 755 veya 775) verin.
5. `config`, `app`, `database`, `storage` klasörlerinin içindeki `.htaccess` dosyalarının dışarıdan erişimi kapattığını test edin (Örn: `https://ajasismedia.com/marketingtool/config/config.php` 403 Forbidden vermelidir).

## Google Places API (New) Yapılandırması & Güvenlik
Lead zenginleştirme (telefon, web sitesi, adres, koordinat vb.) için Google Cloud Console üzerinden **Places API (New)** kullanılmaktadır.

1. **Google Cloud Console** üzerinde yeni veya mevcut projenizde **Places API (New)** servisini etkinleştirin (Eski Places API değil).
2. API Anahtarınızı oluştururken güvenlik kısıtlamalarını (restrictions) uygulayın:
   - **API Restrictions**: Sadece `Places API (New)` seçin.
   - **Application Restrictions**: Server-side cURL ile çalışacağı için IP kısıtlaması (production sunucunuzun IP adresi) önerilir.
3. `config/config.php` dosyasına anahtarınızı tanımlayın:
   ```php
   define('GOOGLE_PLACES_API_KEY', 'AIzaSy...');
   define('GOOGLE_PLACES_ENABLED', true);
   ```
4. **Önemli Güvenlik Notu**: API anahtarı asla frontend JavaScript'e gönderilmez, HTML sayfalarına basılmaz, loglara yazılmaz ve git commit'lerine dahil edilmez. Sistemde API anahtarı yoksa uygulama çökmez; otomatik zenginleştirme "Yapılandırılmadı" olarak işaretlenir.

## Test Paketleri

- **Normal Birim Testleri (Varsayılan):**
  ```bash
  php tests/run_all.php
  ```
  → Network-free ve çevrimdışı çalışır; ücretli harici API veya Google Places kotası tüketmez.

- **Canlı Google Places Entegrasyon Testi:**
  ```bash
  php tests/integration/google_places_live.php
  ```
  → Gerçek Google Places API çağrısı yapar ve quota/billing tüketir. Sadece manuel doğrulama gerektiğinde çalıştırılmalıdır.

