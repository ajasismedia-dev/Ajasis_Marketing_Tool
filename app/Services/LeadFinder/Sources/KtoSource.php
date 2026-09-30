<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;
use App\Helpers\StringHelper;

class KtoSource implements LeadSourceInterface
{
    private $lastStatus = 'success';
    private $cacheFile;
    private $cacheTtl = 86400; // 24 hours

    private static $defaultCommittees = [
        "1" => "ANA METAL VE DÖKÜM SANAYİ",
        "2" => "METAL İŞLEME VE KALIP SANAYİ",
        "3" => "MOTORLU TAŞIT VE YEDEK PARÇA İMALATI",
        "4" => "MOTORLU TAŞIT YEDEK PARÇA TİCARETİ",
        "5" => "GIDA İŞLEME MAKİNELERİ İMALATI",
        "6" => "TARIM MAKİNELERİ İMALATI",
        "7" => "ÖZEL AMAÇLI MAKİNELERİN İMALATI",
        "8" => "ASANSÖR VE GENEL AMAÇLI MAKİNELERİN İMALATI",
        "9" => "HİDROLİK KALDIRMA VE TAŞIMA EKİPMANLARI",
        "10" => "TREYLER, KARASÖR VE KARAVAN İMALATI",
        "11" => "DEMİR, ÇELİK VE METAL TİCARETİ",
        "12" => "AMBALAJ, PAKETLEME ÜRÜNLERİ İMALATI VE TİCARETİ",
        "13" => "PLASTİK VE KAUÇUK İMALATI VE TİCARETİ",
        "14" => "KİMYEVİ VE ZİRAİ MADDELER",
        "15" => "KONUT İNŞAATI",
        "16" => "GENEL İNŞAAT VE ALTYAPI İŞLERİ",
        "17" => "İNŞAAT TAMAMLAYICI İŞLER",
        "18" => "YAPI KOOPERATİFLERİ",
        "19" => "MADENCİLİK VE HAZIR BETON İMALATI",
        "20" => "YAPI PROJE, MÜHENDİSLİK VE DENETİM HİZMETLERİ",
        "21" => "MİMARLIK FAALİYETLERİ",
        "22" => "İNŞAAT MALZEMESİ TİCARETİ",
        "23" => "GENEL YAPI MALZEMELERİ İMALATI",
        "24" => "METAL YAPI MALZEMELERİ",
        "25" => "GAYRİMENKUL HİZMETLERİ",
        "26" => "MEKANİK SİSTEMLERİ VE TESİSAT UYGULAMALARI",
        "27" => "TEKNİK MÜHENDİSLİK HİZMETLERİ",
        "28" => "ELEKTRİK EKİPMANLARI",
        "29" => "ELEKTRONİK ÜRÜNLER VE TELEKOMİNİKASYON",
        "30" => "ENERJİ ÜRETİMİ",
        "31" => "ENERJİ DAĞITIM VE TAAHHÜT İŞLERİ",
        "32" => "BİLGİ TEKNOLOJİLERİ",
        "33" => "E-TİCARET",
        "34" => "GIDA ÜRÜNLERİ İMALATI",
        "35" => "GIDA VE GENEL TOPTAN TİCARET",
        "36" => "GIDA VE İHTİYAÇ MADDELERİ PERAKENDE TİCARETİ",
        "37" => "YİYECEK VE İÇEÇEK HİZMETLERİ",
        "38" => "UNLU MAMÜLLER",
        "39" => "TARIMSAL VE BİTKİSEL ÜRÜNLER",
        "40" => "TAHIL VE BAKLİYAT ÜRÜNLERİ",
        "41" => "UN-YEM VE TOHUMCULUK",
        "42" => "ET, TAVUK VE İŞLENMİŞ HAYVANSAL ÜRÜNLER",
        "43" => "HER TÜRLÜ HAYVAN YETİŞTİRİCİLİĞİ",
        "44" => "MOTORLU TAŞIT TİCARETİ",
        "45" => "ORMAN ÜRÜNLERİ İMALATI",
        "46" => "MOBİLYA VE AHŞAP ÜRÜNLER",
        "47" => "MATBAA-BASIM, KİTAP VE KIRTASİYE",
        "48" => "GENEL MAKİNE SATICILARI",
        "49" => "TEKNİK-HIRDAVAT",
        "50" => "KOZMETİK VE TEMİZLİK ÜRÜNLERİ",
        "51" => "AKARYAKIT VE PETROL ÜRÜNLERİ",
        "52" => "DAYANIKLI TÜKETİM ÜRÜNLERİ VE ZÜCCACİYE",
        "53" => "BANKACILIK VE FİNANSAL KURULUŞLAR",
        "54" => "SARRAFİYE VE HEDİYELİK EŞYA ÜRÜNLERİ",
        "55" => "DIŞ TİCARET",
        "56" => "SİGORTACILIK HİZMETLERİ",
        "57" => "REKLAM, İLETİŞİM VE MEDYA",
        "58" => "SAĞLIK HİZMETLERİ",
        "59" => "İLAÇ VE TIBBİ CİHAZ",
        "60" => "DERİ, AYAKKABI VE SARACİYE ÜRÜNLERİ",
        "61" => "TEKSTİL ÜRÜNLERİ İMALATI",
        "62" => "TEKSTİL ÜRÜNLERİ TİCARETİ",
        "63" => "EV TEKSTİLİ - TUHAFİYE - MEFRUŞAT",
        "64" => "ARAÇ KİRALAMA VE BAKIM-ONARIM HİZMETLERİ",
        "65" => "DANIŞMANLIK HİZMETLERİ",
        "66" => "TURİZM VE KONAKLAMA HİZMETLERİ",
        "67" => "EĞİTİM HİZMETLERİ",
        "68" => "İŞLETME DESTEK, KÜLTÜR VE SPOR HİZMETLERİ",
        "69" => "YÜK TAŞIMA HİZMETLERİ",
        "70" => "SERVİS VE YOLCU TAŞIMACILIĞI"
    ];

    private static $keywordCommitteeMapping = [
        'mimar' => ['21'],
        'mimarlik' => ['21'],
        'reklam' => ['57'],
        'medya' => ['57'],
        'ajans' => ['57'],
        'mobilya' => ['46'],
        'ahsap' => ['46'],
        'insaat' => ['15', '16', '17', '20'],
        'konut' => ['15'],
        'muhendislik' => ['20', '27'],
        'proje' => ['20'],
        'otomotiv' => ['3', '4', '44'],
        'arac' => ['44', '64'],
        'makina' => ['5', '6', '7', '8', '48'],
        'makine' => ['5', '6', '7', '8', '48'],
        'restoran' => ['37'],
        'lokanta' => ['37'],
        'kafe' => ['37'],
        'yemek' => ['37'],
        'yazilim' => ['32'],
        'bilisim' => ['32'],
        'bilgi teknolojileri' => ['32'],
        'tekstil' => ['61', '62', '63'],
        'tarim' => ['6', '39', '41'],
        'gida' => ['34', '35', '36', '37']
    ];

    public function __construct()
    {
        $this->cacheFile = __DIR__ . '/../../../../storage/cache/kto_committees.json';
    }

    public function getCommittees()
    {
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < $this->cacheTtl)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if (!empty($cached) && is_array($cached)) {
                return $cached;
            }
        }

        // Attempt to fetch dynamically from KTO page
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.kto.org.tr/uyelerimiz.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $html = curl_exec($ch);

        if ($html && preg_match('/const\s+KOMITE_ADLARI\s*=\s*(\{.*?\});/s', $html, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (!empty($parsed) && is_array($parsed)) {
                @file_put_contents($this->cacheFile, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return $parsed;
            }
        }

        // Fallback to static list and cache it
        @file_put_contents($this->cacheFile, json_encode(self::$defaultCommittees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::$defaultCommittees;
    }

    public function search($query, $city, $district, $limit)
    {
        $this->lastStatus = 'success';
        $queryClean = trim($query);
        if (empty($queryClean)) {
            $this->lastStatus = 'empty';
            return [];
        }

        $queryNorm = StringHelper::normalizeTurkish(mb_strtolower($queryClean, 'UTF-8'));
        $committees = $this->getCommittees();

        // 1. Find matching committee IDs
        $matchingCommittees = [];
        foreach ($committees as $no => $cName) {
            $cNameNorm = StringHelper::normalizeTurkish(mb_strtolower($cName, 'UTF-8'));
            if (strpos($cNameNorm, $queryNorm) !== false) {
                $matchingCommittees[] = (string)$no;
            }
        }

        // Also check keyword mapping
        foreach (self::$keywordCommitteeMapping as $kw => $cIds) {
            if (strpos($queryNorm, $kw) !== false) {
                foreach ($cIds as $cid) {
                    if (!in_array($cid, $matchingCommittees)) {
                        $matchingCommittees[] = $cid;
                    }
                }
            }
        }

        $rawResults = [];
        $hasNetworkError = false;
        $hasHttpError = false;

        // 2. Fetch unvan search (direct company title search)
        $unvanData = $this->requestKto('unvan', $queryClean);
        if ($unvanData === false) {
            $hasNetworkError = true;
        } elseif (is_null($unvanData)) {
            $hasHttpError = true;
        } elseif (!empty($unvanData['firmalar'])) {
            foreach ($unvanData['firmalar'] as $f) {
                $rawResults[] = $f;
            }
        }

        // 3. Fetch committee search if we have matching committees and need more results
        if (!empty($matchingCommittees) && count($rawResults) < $limit) {
            // Limit to top 2 committees to keep latency low
            $targetCommittees = array_slice($matchingCommittees, 0, 2);
            foreach ($targetCommittees as $commId) {
                $commData = $this->requestKto('komite', $commId);
                if ($commData !== false && !is_null($commData) && !empty($commData['firmalar'])) {
                    foreach ($commData['firmalar'] as $f) {
                        $rawResults[] = $f;
                    }
                }
                if (count($rawResults) >= ($limit * 2)) {
                    break;
                }
            }
        }

        if (empty($rawResults)) {
            if ($hasNetworkError) {
                $this->lastStatus = 'timeout';
            } elseif ($hasHttpError) {
                $this->lastStatus = 'unavailable';
            } else {
                $this->lastStatus = 'empty';
            }
            return [];
        }

        // 4. Deduplicate, prioritize 'Faal', and format
        $seen = [];
        $leads = [];

        // Sort: "Faal" first, then others
        usort($rawResults, function($a, $b) {
            $durumA = $a['durum'] ?? '';
            $durumB = $b['durum'] ?? '';
            if ($durumA === 'Faal' && $durumB !== 'Faal') return -1;
            if ($durumB === 'Faal' && $durumA !== 'Faal') return 1;
            return 0;
        });

        foreach ($rawResults as $item) {
            $name = trim($item['unvan'] ?? '');
            if (empty($name)) continue;

            $key = StringHelper::normalizeTurkish(mb_strtolower($name, 'UTF-8'));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $komiteNo = (int)($item['komite_no'] ?? 0);
            $komiteAdi = trim($item['komite_adi'] ?? '');
            if (empty($komiteAdi) && $komiteNo > 0 && isset($committees[(string)$komiteNo])) {
                $komiteAdi = $committees[(string)$komiteNo];
            }
            if (empty($komiteAdi)) {
                $komiteAdi = 'Genel Ticaret';
            }

            $durum = trim($item['durum'] ?? 'Faal');

            $leads[] = [
                'name' => $name,
                'sector' => $komiteAdi,
                'phone' => '',
                'whatsapp' => '',
                'email' => '',
                'website' => '',
                'instagram' => '',
                'facebook' => '',
                'linkedin' => '',
                'address' => '',
                'district' => '',
                'city' => 'Konya',
                'source' => 'Konya Ticaret Odası',
                'source_url' => 'https://www.kto.org.tr/uyelerimiz.php',
                'source_meta' => [
                    'committee_no' => $komiteNo,
                    'committee_name' => $komiteAdi,
                    'status' => $durum
                ]
            ];

            if (count($leads) >= $limit) {
                break;
            }
        }

        if (empty($leads)) {
            $this->lastStatus = 'filtered_zero';
        } else {
            $this->lastStatus = 'success';
        }

        return $leads;
    }

    public function getLastStatus()
    {
        return $this->lastStatus;
    }

    private function requestKto($type, $value)
    {
        $params = [
            'ajax' => 'ara',
            'arama_tipi' => $type
        ];
        if ($type === 'unvan') {
            $params['unvan'] = $value;
        } else {
            $params['komite'] = $value;
        }

        $url = 'https://www.kto.org.tr/uyelerimiz.php?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            return false; // network error / timeout
        }
        if ($code >= 400 || !$resp) {
            return null; // HTTP error
        }

        $data = json_decode($resp, true);
        if (!$data || !is_array($data) || empty($data['ok'])) {
            return null;
        }

        // Support both data.firmalar and data.unvanlar
        if (empty($data['firmalar']) && !empty($data['unvanlar'])) {
            $committees = $this->getCommittees();
            $seciliKomiteNo = ($type === 'komite') ? (int)$value : 0;
            $seciliKomiteAdi = $committees[(string)$seciliKomiteNo] ?? '';

            $firmalar = [];
            foreach ($data['unvanlar'] as $unvan) {
                $firmalar[] = [
                    'unvan' => $unvan,
                    'komite_no' => $seciliKomiteNo,
                    'komite_adi' => $seciliKomiteAdi,
                    'durum' => 'Faal'
                ];
            }
            $data['firmalar'] = $firmalar;
        }

        return $data;
    }
}
