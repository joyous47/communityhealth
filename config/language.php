<?php
class Language {
    private $lang;
    private $translations = [];
    
    public function __construct($lang = 'en') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->lang = $_SESSION['user_lang'] ?? $_COOKIE['user_lang'] ?? $lang;
        
        if (!in_array($this->lang, ['en', 'sw'])) {
            $this->lang = 'en';
        }
        
        $this->loadTranslations();
    }
    
    private function loadTranslations() {
        $lang_file = __DIR__ . "/../lang/{$this->lang}.php";
        
        if (file_exists($lang_file)) {
            $this->translations = include $lang_file;
        } else {
            $fallback = __DIR__ . "/../lang/en.php";
            $this->translations = file_exists($fallback) ? include $fallback : [];
        }
    }
    
    public function get($key) {
        return $this->translations[$key] ?? $key;
    }
    
    public function setLanguage($lang) {
        if (in_array($lang, ['en', 'sw'])) {
            $this->lang = $lang;
            $_SESSION['user_lang'] = $lang;
            setcookie('user_lang', $lang, time() + (86400 * 30), "/");
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    public function getCurrent() {
        return $this->lang;
    }
}
?>
