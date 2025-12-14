<?php
class GeminiService {
    private $db;
    private $apiKey;
    private $systemInstruction;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';

    public function __construct($dbConnection) {
        $this->db = $dbConnection;

        // 1. SABİT SYSTEM INSTRUCTION (Veritabanından kaldırıldı, buraya gömüldü)
        $this->systemInstruction = "You are an expert Database Architect. Your task is to analyze project descriptions and extract structured Business Rules, Entities, and Attributes. You must strictly follow 3NF normalization principles.";

        $this->loadSettings();
    }

    private function loadSettings() {
        // 2. KULLANICIYA ÖZEL API KEY (Session'dan User ID alıp sorguluyoruz)
        if (session_status() == PHP_SESSION_NONE) { session_start(); }

        $user_id = $_SESSION['user_id'] ?? null;

        if (!$user_id) {
            // Eğer session yoksa (örn: cron job) burası patlayabilir, manuel user_id set etmek gerekebilir.
            // Ama web arayüzü için bu yeterli.
            throw new Exception("Authorization Error: User session not found.");
        }

        try {
            // Sadece bu kullanıcının ayarını çek
            $stmt = $this->db->prepare("SELECT gemini_api_key FROM settings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings || empty($settings['gemini_api_key'])) {
                throw new Exception("Gemini API Key missing. Please go to Settings and enter your API Key.");
            }

            $this->apiKey = $settings['gemini_api_key'];

        } catch (PDOException $e) {
            throw new Exception("Database Settings Error: " . $e->getMessage());
        }
    }

    public function callApi($userPrompt, $jsonMode = false) {
        // Prompt Hazırlığı
        $finalPrompt = $this->systemInstruction . "\n\n" . $userPrompt;

        if ($jsonMode) {
            $finalPrompt .= "\n\nCRITICAL: Return ONLY valid JSON. No Markdown formatting, no code blocks.";
        }

        $postData = [
            'contents' => [
                ['parts' => [['text' => $finalPrompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.2, // Daha tutarlı cevaplar için düşük sıcaklık
                'maxOutputTokens' => 8192
            ]
        ];

        // JSON Modu için Schema (Opsiyonel ama garantili JSON için iyi olur)
        if ($jsonMode) {
            $postData['generationConfig']['response_mime_type'] = 'application/json';
        }

        $maxRetries = 3;
        $attempt = 0;
        $lastError = "";

        while ($attempt < $maxRetries) {
            $attempt++;

            $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 saniye zaman aşımı

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("Network Error (cURL): $curlError");
            }

            $responseData = json_decode($response, true);

            // Başarılı Cevap
            if ($httpCode === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $textResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];

                if ($jsonMode) {
                    return $this->cleanJson($textResponse);
                }
                return $textResponse;
            }

            // Hata Analizi
            $errorMsg = $responseData['error']['message'] ?? "HTTP Status $httpCode";

            // Eğer sunucu yoğunsa (503) veya Overloaded hatası varsa bekle ve tekrar dene
            if ($httpCode === 503 || stripos($errorMsg, 'overloaded') !== false) {
                $lastError = "AI Model Overloaded. Retrying ($attempt/$maxRetries)...";
                sleep(2); // 2 saniye bekle
                continue;
            } else {
                // API Key hatası vb. ise hemen dur
                throw new Exception("Gemini API Error: " . $errorMsg);
            }
        }

        throw new Exception("AI Service Unavailable: " . $lastError);
    }

    // JSON Temizleme Yardımcısı (Markdown ```json ... ``` bloklarını siler)
    private function cleanJson($text) {
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        return trim($text);
    }
}
?>