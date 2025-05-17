<?php
/*
Plugin Name: Neurame AI Assistant
Plugin URI: https://ramestudio.com/Neurame
Description: افزونه‌ای برای پیشنهاد دوره، مدیریت کودکان، و گزارش مربی با هوش مصنوعی
Version: 2.0.0
Author: Rame Studio
Update URI: https://github.com/Hossein4163/Neurame-AI-Assistant.git
Author URI: https://ramestudio.com
License: GPL-2.0+
Text Domain: neurame-ai-assistant
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.4
*/

if (defined('NEURAMEAI_PLUGIN_LOADED')) {
    return;
}

define('NEURAMEAI_PLUGIN_LOADED', true);
define('NEURAMEAI_DEBUG_LOG', true);
define('NEURAMEAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEURAMEAI_PLUGIN_URL', plugin_dir_url(__FILE__));

// لود فایل autoload از composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// لود فایل زبان
add_action('init', function () {
    load_plugin_textdomain('neurame-ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// استفاده از کلاس‌های Autoload شده
use Neurame\Core\NeurameAIAssistant;
use Neurame\Widgets\RecommendedCoursesWidget;
use Neurame\Widgets\TrainerReportWidget;
use Neurame\Widgets\ChildProgressWidget;

// لود ویجت‌های المنتور
add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!did_action('elementor/loaded')) {
        error_log('Neurame AI Assistant: Elementor not loaded');
        return;
    }

    $widgets_manager->register(new RecommendedCoursesWidget());
    $widgets_manager->register(new TrainerReportWidget());
    $widgets_manager->register(new ChildProgressWidget());
});

// اجرای پلاگین
new NeurameAIAssistant();
