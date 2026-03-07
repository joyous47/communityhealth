<?php
/**
 * SMS Service Class
 * Handles sending and receiving SMS messages via configured provider
 */

require_once __DIR__ . '/../config/sms_config.php';

class SMSService {
    private $provider;
    private $lastError;
    
    public function __construct() {
        $this->provider = SMS_PROVIDER;
    }
    
    /**
     * Send SMS to a phone number
     * @param string $phoneNumber Recipient phone number (Kenyan format)
     * @param string $message Message to send
     * @return bool Success status
     */
    public function sendSMS($phoneNumber, $message) {
        if (!SMS_ENABLED) {
            $this->lastError = 'SMS service is disabled';
            return false;
        }
        
        // Validate and format phone number
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        if (!$phoneNumber) {
            $this->lastError = 'Invalid phone number format';
            return false;
        }
        
        // Truncate message if too long
        if (strlen($message) > SMS_MAX_LENGTH) {
            $message = substr($message, 0, SMS_MAX_LENGTH - 3) . '...';
        }
        
        switch ($this->provider) {
            case 'africastalking':
                return $this->sendViaAfricaSpeaking($phoneNumber, $message);
            case 'twilio':
                return $this->sendViaTwilio($phoneNumber, $message);
            default:
                $this->lastError = 'Unknown SMS provider';
                return false;
        }
    }
    
    /**
     * Send bulk SMS to multiple recipients
     * @param array $phoneNumbers Array of phone numbers
     * @param string $message Message to send
     * @return array Results for each recipient
     */
    public function sendBulkSMS($phoneNumbers, $message) {
        $results = [];
        foreach ($phoneNumbers as $phone) {
            $results[$phone] = $this->sendSMS($phone, $message);
        }
        return $results;
    }
    
    /**
     * Parse incoming SMS message
     * @param string $message Raw SMS message
     * @return array Parsed report data or null if invalid
     */
    public function parseReportMessage($message) {
        $message = trim(strtoupper($message));
        
        // Expected format: CHMEWS <SYMPTOMS> <LOCATION> <SEVERITY>
        // Example: CHMEWS FEVER COUGH NAIROBI HIGH
        
        $parts = explode(' ', $message);
        
        if (count($parts) < 3) {
            return null;
        }
        
        // Check for keyword
        if ($parts[0] !== SMS_REPORT_KEYWORD) {
            return null;
        }
        
        $symptoms = $parts[1] ?? '';
        $location = $parts[2] ?? '';
        $severity = $parts[3] ?? 'MEDIUM'; // Default severity
        
        // Validate severity
        if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])) {
            $severity = 'MEDIUM';
        }
        
        return [
            'symptoms' => $this->parseSymptoms($symptoms),
            'location' => $location,
            'severity' => $severity,
            'raw_message' => implode(' ', $parts)
        ];
    }
    
    /**
     * Parse symptoms from SMS
     * @param string $symptomCode Symptom code
     * @return array Array of symptoms
     */
    private function parseSymptoms($symptomCode) {
        $symptomMap = [
            'FEVER' => ['name' => 'Fever', 'code' => 'FVR'],
            'COUGH' => ['name' => 'Cough', 'code' => 'CGH'],
            'COLD' => ['name' => 'Common Cold', 'code' => 'COLD'],
            'HEADACHE' => ['name' => 'Headache', 'code' => 'HDP'],
            'DIARRHEA' => ['name' => 'Diarrhea', 'code' => 'DIA'],
            'VOMITING' => ['name' => 'Vomiting', 'code' => 'VMT'],
            'RASH' => ['name' => 'Skin Rash', 'code' => 'RSH'],
            'FATIGUE' => ['name' => 'Fatigue', 'code' => 'FTG'],
            'BREATHING' => ['name' => 'Breathing Difficulty', 'code' => 'BRTH'],
            'COVID' => ['name' => 'COVID-19 Symptoms', 'code' => 'COVID']
        ];
        
        $symptoms = [];
        $codes = explode(',', $symptomCode);
        
        foreach ($codes as $code) {
            $code = trim($code);
            if (isset($symptomMap[$code])) {
                $symptoms[] = $symptomMap[$code];
            }
        }
        
        return $symptoms;
    }
    
    /**
     * Format phone number to international format
     * @param string $phone Phone number
     * @return string Formatted phone number or null
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if valid Kenyan number
        if (preg_match(PHONE_REGEX, $phone)) {
            // Convert to international format
            if (substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } elseif (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1') {
                $phone = '254' . $phone;
            }
            return '+' . $phone;
        }
        
        return null;
    }
    
    /**
     * Send SMS via Africa's Talking
     */
    private function sendViaAfricaSpeaking($phoneNumber, $message) {
        // Check if credentials are configured
        if (AFRICASTALKING_API_KEY === 'YOUR_API_KEY' || AFRICASTALKING_USERNAME === 'YOUR_USERNAME') {
            $this->lastError = 'Africa\'s Talking API not configured. Please update sms_config.php with valid credentials.';
            
            // Log the SMS attempt for debugging
            if (SMS_DEBUG) {
                error_log("SMS Debug - Would send to: $phoneNumber, Message: $message");
            }
            return false;
        }
        
        $url = 'https://api.africastalking.com/version1/messaging';
        
        $data = [
            'username' => AFRICASTALKING_USERNAME,
            'to' => $phoneNumber,
            'message' => $message,
            'from' => AFRICASTALKING_SENDER_ID
        ];
        
        $headers = [
            'ApiKey: ' . AFRICASTALKING_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 || $httpCode === 200) {
            return true;
        }
        
        $this->lastError = 'Failed to send SMS via Africa\'s Talking';
        return false;
    }
    
    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio($phoneNumber, $message) {
        // Check if credentials are configured
        if (TWILIO_ACCOUNT_SID === 'YOUR_ACCOUNT_SID' || TWILIO_AUTH_TOKEN === 'YOUR_AUTH_TOKEN') {
            $this->lastError = 'Twilio API not configured. Please update sms_config.php with valid credentials.';
            
            // Log the SMS attempt for debugging
            if (SMS_DEBUG) {
                error_log("SMS Debug - Would send to: $phoneNumber, Message: $message");
            }
            return false;
        }
        
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
        
        $data = [
            'To' => $phoneNumber,
            'From' => TWILIO_PHONE_NUMBER,
            'Body' => $message
        ];
        
        $headers = [
            'Authorization: Basic ' . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 || $httpCode === 200) {
            return true;
        }
        
        $this->lastError = 'Failed to send SMS via Twilio';
        return false;
    }
    
    /**
     * Get last error message
     * @return string Error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Create confirmation message for citizen
     * @param string $reportId Report ID
     * @param string $language Language code (en/sw)
     * @return string Confirmation message
     */
    public function createConfirmationMessage($reportId, $language = 'en') {
        if ($language === 'sw') {
            return "Asante kwa kurekodi. Ripoti yako (#{$reportId}) imepokelewa. Utapata majibu baada ya uchunguzi. - CHMEWS";
        }
        return "Thank you for reporting. Your report (#{$reportId}) has been received. You will receive follow-up information after review. - CHMEWS";
    }
    
    /**
     * Create alert message for health workers
     * @param string $location Location name
     * @param string $severity Severity level
     * @return string Alert message
     */
    public function createAlertMessage($location, $severity) {
        return "ALERT: New {$severity} severity case reported in {$location}. Please review in CHMEWS system immediately.";
    }
}
