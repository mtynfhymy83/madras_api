<?php

namespace App\Services\Eta;

class EtaMessageService
{
    /**
     * Send message via ETA bot
     * 
     * @param string $eitaaId ETA user ID
     * @param string $message Message to send
     * @return bool
     */
    public function sendMessage(string $eitaaId, string $message): bool
    {
        try {
            $token = config('services.eitaa.token');
            
            if (!$token) {
                \Log::warning('EITA token not configured');
                return false;
            }

            $url = "https://eitaayar.ir/api/{$token}/sendMessage";
            
            $data = [
                'chat_id' => $eitaaId,
                'text' => $message,
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Exception $e) {
            \Log::error('EITA message send failed: ' . $e->getMessage());
            return false;
        }
    }
}


