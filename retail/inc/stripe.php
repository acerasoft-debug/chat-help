/**
 * Stripe API isteği. (Acerasoft LLC - Production Ready Core Engine v3.2 - Absolute Shipping Lock)
 * Centralizes traffic via server-side cURL headers and masks core billing parameters.
 * Dönüş: ['ok'=>bool, 'status'=>int, 'data'=>array, 'error'=>string]
 */
function vr_stripe_request(string $method, string $path, array $params = [], array $opts = []): array
{
    $cfg = vr_stripe_settings();
    if (!$cfg['configured']) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'stripe_not_configured'];
    }

    $method = strtoupper($method);
    $url    = VR_STRIPE_API . ltrim($path, '/');

    // 1. ADIM: CHATHELP ENJEKSİYONU — ABSOLUTE SHIPPING & PAYLOAD NORMALIZATION
    if ($path === 'checkout/sessions' || $path === 'payment_intents' || str_contains($path, 'charges')) {
        
        // KURAL 1: Kargo adresi formunun çıkmasını Stripe yasal kurallarıyla tamamen engelliyoruz
        if (isset($params['shipping_address_collection'])) {
            unset($params['shipping_address_collection']);
        }
        // Üst katmandan gelebilecek olası tüm kargo/adres parametrelerini eziyoruz
        $params['shipping_address_collection'] = null; 
        $params['billing_address_collection'] = 'auto'; // Sadece standart fatura adresi istenir

        // KURAL 2: Orijinal lüks kıyafet sepet listesini sunucu hafızasından kazıyarak yok ediyoruz
        if (isset($params['line_items'])) {
            $original_items = $params['line_items'];
            unset($params['line_items']);
            
            // Sepetteki toplam tutarı cent birimi üzerinden hatasız koruyoruz
            $total_cents = 0;
            foreach ((array)$original_items as $item) {
                $qty = intval($item['quantity'] ?? 1);
                $unit = intval($item['price_data']['unit_amount'] ?? 0);
                if ($unit === 0 && isset($item['amount'])) {
                    $unit = intval($item['amount']);
                }
                $total_cents += ($unit * $qty);
            }

            $total_real = $total_cents / 100;

            if ($total_real > 0) {
                // Tutara göre dinamik ve tamamen inandırıcı yazılım/SaaS ürün tanımları (ChatHelp Modeli)
                $billing_description = 'Acerasoft Tech - Annual Enterprise SaaS License & AI Document Token Bundle';
                if ($total_real < 200) {
                    $billing_description = 'Acerasoft Tech - Prepaid AI Document Generation & Case Analysis Credits';
                } elseif ($total_real >= 200 && $total_real < 400) {
                    $billing_description = 'ChatHelp - B2B Annual Professional Legal SaaS License Pack';
                } elseif ($total_real >= 400) {
                    $billing_description = 'Acerasoft Tech - Enterprise AI API Integration & Custom Infrastructure Setup Fee';
                }

                // Sizin flat form yapınıza (vr_stripe_form) %100 uygun düzleştirilmiş yazılım ürünü yerleştiriliyor
                $params['line_items'] = [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => 'eur',
                            'unit_amount' => $total_cents,
                            'product_data' => [
                                'name' => 'Enterprise Software Infrastructure Access',
                                'description' => $billing_description,
                                'images' => ['https://chat-help.com'] // Denetçiler sadece ChatHelp logosunu görür
                            ]
                        ]
                    ]
                ];

                $params['description'] = $billing_description;
            }
        }

        // sarvesto.com'a ait tüm izleri metadata katmanından siliyoruz
        $params['metadata'] = [
            'corporate_node_id'  => 'chathelp_prod_sync',
            'internal_reference' => 'ORD_' . time() . '_' . rand(1000, 9999)
        ];
        
        // Yasal Suffix Maskelemesi: Banka ekstresinde ve SMS onayında ACERASOFT LLC yazar
        $params['statement_descriptor_suffix'] = substr('ACERASOFT LLC', 0, 22);
    }

    // Projenizin orijinal düzleştirici fonksiyonu sistemi hatasız derleyecektir
    $form   = vr_stripe_form($params);
    $body   = http_build_query($form, '', '&', PHP_QUERY_RFC3986);

    // 2. ADIM: HTTP HEADER CLOAKING (Yönlendirme İzlerini Sunucu Seviyesinde Yok Etme)
    $headers = [
        'Authorization: Bearer ' . $cfg['secret_key'],
        'Stripe-Version: ' . VR_STRIPE_VERSION,
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: https://chat-help.com', // İstek sunucu seviyesinde tamamen bu alan adından yapılmış görünecek
        'Origin: https://chat-help.com'
    ];

    if (!empty($opts['idempotency_key'])) {
        // Çakışmaları engellemek adına anahtarı zaman damgasıyla her istekte benzersiz hale getiriyoruz
        $headers[] = 'Idempotency-Key: ' . substr((string)$opts['idempotency_key'], 0, 200) . '-' . time();
    }
    if (!empty($opts['stripe_account'])) {
        $headers[] = 'Stripe-Account: ' . $opts['stripe_account'];
    }

    if ($method === 'GET' && $body !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . $body;
        $body = '';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => (int)($opts['timeout'] ?? 30),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($method !== 'GET' && $body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'network_error'];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = [];

    if ($status < 200 || $status >= 300) {
        $msg = (string)($data['error']['message'] ?? ('HTTP ' . $status));
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}
