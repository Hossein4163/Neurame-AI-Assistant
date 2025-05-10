<?php
/*
Plugin Name: Neurame AI Assistant
Plugin URI: https://ramestudio.com/Neurame
Description: افزونه‌ای برای پیشنهاد دوره، مدیریت کودکان، و گزارش مربی با هوش مصنوعی
Version: 1.0.0
Author: Rame Studio
Update URI: false
Author URI: https://ramestudio.com
License: GPL-2.0+
Text Domain: neurame-ai-assistant
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 7.4
*/

// جلوگیری از لود چندباره پلاگین
if (defined('NEURAMEAI_PLUGIN_LOADED')) {
    return;
}

define('NEURAMEAI_DEBUG_LOG', true);
define('NEURAMEAI_PLUGIN_LOADED', true);

// تعریف مسیرها
define('NEURAMEAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEURAMEAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEURAMEAI_PLUGIN_PATH', plugin_dir_path(__FILE__));


// لود فایل‌های زبان
add_action('init', function () {
    load_plugin_textdomain('neurame-ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// لود کلاس اصلی
require_once NEURAMEAI_PLUGIN_DIR . 'includes/class-neurame-ai-assistant.php';

// لود ویجت‌های المنتور فقط اگر المنتور فعال باشد
add_action('elementor/widgets/register', function ($widgets_manager) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Neurame AI Assistant: Attempting to register Elementor widgets');
    }

    // چک کردن وجود المنتور
    if (!did_action('elementor/loaded')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Neurame AI Assistant: Elementor is not loaded');
        }
        return; // المنتور فعال نیست، از ثبت ویجت‌ها صرف‌نظر کن
    }

    // چک کردن وجود فایل‌ها
    $recommended_courses_file = NEURAMEAI_PLUGIN_DIR . 'includes/widgets/class-neurame-recommended-courses-widget.php';
    $trainer_report_file = NEURAMEAI_PLUGIN_DIR . 'includes/widgets/class-neurame-trainer-report-widget.php';

    // 🔥 اضافه شده برای روند پیشرفت فرزند
    $child_progress_file = NEURAMEAI_PLUGIN_DIR . 'includes/widgets/class-neurame-child-progress-widget.php';

    if (!file_exists($recommended_courses_file) || !file_exists($trainer_report_file) || !file_exists($child_progress_file)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Neurame AI Assistant: Widget files not found.');
        }
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Neurame AI Assistant: Widget files found, requiring files');
    }

    require_once $recommended_courses_file;
    require_once $trainer_report_file;
    require_once $child_progress_file; // 🔥 اضافه شده برای روند پیشرفت فرزند

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Neurame AI Assistant: Registering Widgets');
    }

    $widgets_manager->register(new NeurameAI_Recommended_Courses_Widget());
    $widgets_manager->register(new NeurameAI_Trainer_Report_Widget());
    $widgets_manager->register(new NeurameAI_Child_Progress_Widget()); // 🔥 ثبت ویجت روند پیشرفت فرزند

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Neurame AI Assistant: Widgets registered successfully');
    }
});

// اجرای پلاگین
new NeurameAIAssistant();