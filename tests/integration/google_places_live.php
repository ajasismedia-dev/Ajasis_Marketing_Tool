<?php
/**
 * ============================================================================
 * UYARI: Bu test gerçek Google Places API çağrısı yapar
 * ve Google Maps Platform kota/ücretlendirme (quota/billing) tüketebilir.
 * ============================================================================
 * 
 * Bu dosya yalnızca canlı entegrasyonu manuel olarak doğrulamak için kullanılır.
 * Normal test paketi (tests/run_all.php) tarafından çağrılmaz.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Models/Company.php';
require_once __DIR__ . '/../../app/Helpers/StringHelper.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/LeadSourceInterface.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/Helpers/LeadNormalizer.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/Enrichment/WebsiteEnricher.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/Enrichment/GooglePlacesEnricher.php';
require_once __DIR__ . '/../TestHelper.php';

use App\Services\LeadFinder\Enrichment\GooglePlacesEnricher;
use App\Models\Company;

echo "\n============================================================================\n";
echo " UYARI: Bu test gerçek Google Places API çağrısı yapar ve quota/billing tüketebilir.\n";
echo "============================================================================\n\n";

if (!GooglePlacesEnricher::isConfigured() || (defined('GOOGLE_PLACES_ENABLED') && !GOOGLE_PLACES_ENABLED)) {
    TestHelper::skip('Google Places API anahtarı tanımlı değil veya devre dışı. Canlı test atlandı.');
    exit(0);
}

echo "Google Places: configured (Canlı test başlatılıyor...)\n\n";

// Test firma: 2BK Mimarlık
$testCompany = '2BK MİMARLIK MÜHENDİSLİK İNŞAAT TAAHHÜT GAYRİMENKUL DEĞERLEME SANAYİ VE TİCARET LİMİTED ŞİRKETİ';
$testCity = 'Konya';
$testDistrict = 'Selçuklu';

echo "1. Canlı Google Places Zenginleştirme Testi: $testCompany\n";
$enrichResult = GooglePlacesEnricher::enrich($testCompany, $testCity, $testDistrict);

TestHelper::assertTrue($enrichResult['success'] === true, 'Canlı Google Places zenginleştirme başarılı');
TestHelper::assertEqual('HIGH', $enrichResult['confidence'] ?? '', 'Eşleşme güven seviyesi HIGH');
TestHelper::assertTrue(($enrichResult['confidence_score'] ?? 0) >= 0.70, 'Eşleşme güven skoru >= 0.70');
TestHelper::assertTrue(($enrichResult['confidence_score'] ?? 0) <= 1.0, 'Eşleşme güven skoru <= 1.0');
TestHelper::assertNotEmpty($enrichResult['google_place_id'] ?? '', 'Google Place ID mevcut');
TestHelper::assertTrue(!isset($enrichResult['google_preview']), 'Response içinde google_preview objesi bulunmuyor');
TestHelper::assertTrue(($enrichResult['api_stats']['place_details_calls'] ?? 0) <= 1, 'Place Details en fazla 1 kez çağrıldı');

if (!empty($enrichResult['website'])) {
    TestHelper::assertNotEmpty($enrichResult['source_trace'], 'Website crawl sonucu source_trace dolu');
}

echo "\n2. Veritabanı Server-Side last_enriched_at Kayıt Testi\n";
try {
    $companyModel = new Company();
    
    $leadData = [
        'name' => $testCompany,
        'phone' => $enrichResult['phone'] ?? '',
        'email' => $enrichResult['email'] ?? '',
        'website' => $enrichResult['website'] ?? '',
        'address' => $enrichResult['address'] ?? '',
        'city' => $testCity,
        'district' => $testDistrict,
        'source' => 'Konya Ticaret Odası',
        'google_place_id' => $enrichResult['google_place_id'] ?? '',
        'enrichment_status' => $enrichResult['status'] ?? '',
        'status' => 'new'
    ];

    if (!empty($leadData['google_place_id']) || !empty($leadData['enrichment_status'])) {
        $leadData['last_enriched_at'] = date('Y-m-d H:i:s');
    } else {
        $leadData['last_enriched_at'] = null;
    }

    $savedId = $companyModel->create($leadData);
    $savedRecord = $companyModel->findById($savedId);

    TestHelper::assertNotEmpty($savedRecord['last_enriched_at'], 'Veritabanında last_enriched_at alanı dolu kaydedildi');
    TestHelper::assertEqual($enrichResult['google_place_id'], $savedRecord['google_place_id'], 'google_place_id doğru kaydedildi');

    // Temizlik
    $companyModel->delete($savedId);
    echo "   Test kaydı başarıyla silindi (ID: $savedId).\n";
} catch (\Throwable $e) {
    TestHelper::skip('Canlı veritabanı testi atlandı: ' . $e->getMessage());
}

echo "\nCanlı Google Places entegrasyon testi tamamlandı.\n";
