<?php
/**
 * OpenAI API Configuration
 * 
 * Instructions:
 * 1. Get your OpenAI API key from: https://platform.openai.com/api-keys
 * 2. Replace 'YOUR_OPENAI_API_KEY_HERE' with your actual API key
 * 3. Save this file
 * 
 * Example:
 * define('OPENAI_API_KEY', 'sk-1234567890abcdef...');
 */

// Force override any existing OPENAI_API_KEY definition
if (defined('OPENAI_API_KEY')) {
    // Undefine the existing constant (not possible in PHP, so we'll work around it)
    $GLOBALS['FORCE_OPENAI_API_KEY'] = 'YOUR_OPENAI_API_KEY_HERE';
} else {
    define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE');
    $GLOBALS['FORCE_OPENAI_API_KEY'] = OPENAI_API_KEY;
}

// OpenAI API Settings - only define if not already defined
if (!defined('OPENAI_MODEL')) {
    define('OPENAI_MODEL', 'gpt-4o-mini'); // You can change to 'gpt-4' for better responses (costs more)
}
if (!defined('OPENAI_MAX_TOKENS')) {
    define('OPENAI_MAX_TOKENS', 300);
}
if (!defined('OPENAI_TEMPERATURE')) {
    define('OPENAI_TEMPERATURE', 0.7);
}

// API Timeout Settings - only define if not already defined
if (!defined('OPENAI_TIMEOUT')) {
    define('OPENAI_TIMEOUT', 30);
}
if (!defined('OPENAI_CONNECT_TIMEOUT')) {
    define('OPENAI_CONNECT_TIMEOUT', 10);
}

/**
 * Check if OpenAI API key is configured
 */
function isOpenAIConfigured() {
    return isset($GLOBALS['FORCE_OPENAI_API_KEY']) && 
           $GLOBALS['FORCE_OPENAI_API_KEY'] !== 'YOUR_OPENAI_API_KEY_HERE' && 
           !empty($GLOBALS['FORCE_OPENAI_API_KEY']);
}

/**
 * Get OpenAI API key - always returns the correct key
 */
function getOpenAIKey() {
    if (isset($GLOBALS['FORCE_OPENAI_API_KEY']) && 
        $GLOBALS['FORCE_OPENAI_API_KEY'] !== 'YOUR_OPENAI_API_KEY_HERE') {
        return $GLOBALS['FORCE_OPENAI_API_KEY'];
    }
    return null;
}
?>
