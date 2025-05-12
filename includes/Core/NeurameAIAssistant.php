<?php

namespace Neurame\Core;

use Neurame\Utils\Logger;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class NeurameAIAssistant
{
    public function __construct()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NeurameAIAssistant: Class initialized');
        }

        Logger::init();
        Logger::info('NeurameAIAssistant: Class initialized');

        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_course_metabox']);
        add_action('save_post', [$this, 'save_course_metabox']);
        add_action('woocommerce_account_menu_items', [$this, 'add_account_menus'], 40);
        add_action('woocommerce_account_neurame-dashboard_endpoint', [$this, 'render_combined_dashboard']);
        add_action('init', [$this, 'register_woocommerce_endpoints']);
        add_action('wp', [$this, 'handle_profile_form']);
        add_action('wp', [$this, 'handle_children_form']);
        add_action('admin_post_neurame_generate_headings', [$this, 'handle_generate_headings']);
        add_action('admin_post_neurame_trainer_report', [$this, 'handle_trainer_report']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('show_user_profile', [$this, 'admin_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'admin_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_admin_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_admin_user_profile_fields']);
        add_action('wp_ajax_neurame_get_children', [$this, 'ajax_get_children']);
        add_action('wp_ajax_neurame_get_child_data', [$this, 'ajax_get_child_data']);
        add_action('wp_ajax_neurame_ai_recommendation', [$this, 'handle_fetch_ai_recommendation']);
        add_action('wp_ajax_neurame_load_buyers', [$this, 'ajax_load_buyers']);
        add_shortcode('neurame_ai_recommendation', [$this, 'ai_recommendation_shortcode']);
        add_action('wp_ajax_neurame_save_children', [$this, 'ajax_save_children']);
        add_action('wp_ajax_neurame_save_trainer_report', [$this, 'ajax_save_trainer_report']);
        add_action('wp_ajax_neurame_delete_trainer_report', [$this, 'ajax_delete_trainer_report']);
        add_action('wp_ajax_neurame_update_trainer_report', [$this, 'ajax_update_trainer_report']);

        // 🔥 اضافه شده برای بروزرسانی روند پیشرفت فرزند بعد از ثبت گزارش مربی
        add_action('save_post_trainer_report', [$this, 'update_child_progress_on_report'], 10, 2);

        add_action('wp_ajax_neurame_fetch_parent_info', [$this, 'handle_fetch_parent_info']); // اضافه کردن اکشن جدید

        // اضافه کردن اکشن‌های جدید برای گزارش‌ها و گزارش هوشمند
        add_action('wp_ajax_neurame_get_reports', [$this, 'ajax_get_reports']);
        add_action('wp_ajax_neurame_get_progress_report', [$this, 'ajax_get_progress_report']);

        if (!wp_next_scheduled('neurame_clean_logs_hourly')) {
            wp_schedule_event(time(), 'hourly', 'neurame_clean_logs_hourly');
        }
        add_action('neurame_clean_logs_hourly', [$this, 'clean_log_files']);
    }

    public function register_shortcodes()
    {
        add_shortcode('neurame_profile', [$this, 'render_profile']);
        add_shortcode('neurame_children', [$this, 'render_children_management']);
        add_shortcode('neurame_smart_assistant', [$this, 'render_smart_assistant']);
        add_shortcode('neurame_child_progress', [$this, 'render_child_progress']);
    }

    public function ajax_get_reports()
    {
        check_ajax_referer('neurame_get_reports', 'nonce');

        $child_id = sanitize_text_field($_POST['child_id'] ?? '');
        if (!$child_id) {
            wp_send_json_error(['message' => 'شناسه فرزند نامعتبر است.']);
        }

        $reports = get_option('neurame_trainer_reports', []);
        if (!is_array($reports)) {
            $reports = [];
        }

        $child_reports = array_filter($reports, function ($report) use ($child_id) {
            return $report['child_id'] === $child_id;
        });

        if (empty($child_reports)) {
            wp_send_json_error(['message' => 'گزارشی برای این کودک یافت نشد.']);
        }

        ob_start();
        ?>
        <ul class="list-disc pl-5 space-y-3">
            <?php foreach ($child_reports as $report) : ?>
                <li>
                    <div><strong>تاریخ:</strong> <?php echo esc_html($report['timestamp']); ?></div>
                    <div><strong>محتوا:</strong> <?php echo esc_html($report['ai_content'] ?? $report['content']); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }


    /**
     * پاکسازی تنظیمات افزونه
     */
    public function sanitize_settings($input)
    {
        $sanitized = [];

        // حالت والدینی
        $sanitized['neurame_parent_mode'] = !empty($input['neurame_parent_mode']) ? 1 : 0;
        // فعال‌سازی تحلیل‌ها
        $sanitized['neurame_analytics'] = !empty($input['neurame_analytics']) ? 1 : 0;
        // نوع API
        $allowed_api = ['none', 'chatgpt', 'gemini'];
        $api_type = sanitize_text_field($input['neurame_api_type'] ?? 'none');
        $sanitized['neurame_api_type'] = in_array($api_type, $allowed_api, true) ? $api_type : 'none';
        // کلید ChatGPT
        $sanitized['neurame_chatgpt_api_key'] = isset($input['neurame_chatgpt_api_key'])
            ? sanitize_text_field($input['neurame_chatgpt_api_key'])
            : '';
        // کلید Gemini
        $sanitized['neurame_gemini_api_key'] = isset($input['neurame_gemini_api_key'])
            ? sanitize_text_field($input['neurame_gemini_api_key'])
            : '';

        return $sanitized;
    }

    public function render_child_progress()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('لطفاً وارد حساب کاربری خود شوید.', 'neurame-ai-assistant') . '</p>';
        }

        $user_id = get_current_user_id();
        $children = get_user_meta($user_id, 'neurame_children', true);
        $children = is_array($children) ? $children : [];

        if (empty($children)) {
            return '<p>' . esc_html__('هیچ کودکی ثبت نشده است.', 'neurame-ai-assistant') . ' <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" class="text-blue-600 hover:underline">' . esc_html__('اینجا کلیک کنید تا کودک خود را ثبت کنید.', 'neurame-ai-assistant') . '</a></p>';
        }

        ob_start();
        ?>
        <div class="neurame-child-progress space-y-6 mt-6">
            <?php foreach ($children as $index => $child) : ?>
                <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-2"><?php echo esc_html($child['name']); ?></h3>

                    <p><?php echo esc_html__('سن: ', 'neurame-ai-assistant') . esc_html($child['age']); ?></p>
                    <p><?php echo esc_html__('علاقه‌مندی‌ها: ', 'neurame-ai-assistant') . esc_html($child['interests']); ?></p>

                    <!-- تحلیل روند پیشرفت -->
                    <?php
                    $progress = get_user_meta($user_id, 'child_progress_analysis', true);
                    if ($progress && is_array($progress)) {
                        echo '<div class="mt-4">';
                        echo '<p class="font-medium">' . esc_html__('تحلیل مهارت‌ها:', 'neurame-ai-assistant') . '</p>';
                        echo '<ul class="list-disc pl-5">';
                        foreach ($progress['skills'] as $skill => $score) {
                            echo '<li>' . esc_html($skill) . ': ' . esc_html($score) . '%</li>';
                        }
                        echo '</ul>';
                        echo '<p class="mt-2">' . esc_html__('خلاصه تحلیل:', 'neurame-ai-assistant') . '</p>';
                        echo '<p>' . esc_html($progress['ai_summary'] ?? '-') . '</p>';
                        echo '</div>';
                    } else {
                        echo '<p class="text-gray-500">' . esc_html__('روند پیشرفتی ثبت نشده است.', 'neurame-ai-assistant') . '</p>';
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ai_recommendation_shortcode($atts)
    {
        global $neurame_ai_shortcode_loaded;
        $neurame_ai_shortcode_loaded = true;

        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('لطفاً وارد شوید.', 'neurame-ai-assistant') . '</p>';
        }

        $user_id = get_current_user_id();
        $children = get_user_meta($user_id, 'neurame_children', true);
        $children = is_array($children) ? $children : [];
        $parent_goals = get_user_meta($user_id, 'neurame_parent_goals', true);

        ob_start();
        ?>
        <div class="neurame-ai-recommendation">
            <form id="neurame-ai-recommendation-form" class="space-y-4">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('neurame_nonce'); ?>">

                <?php if (empty($children)): ?>
                    <p><?php esc_html_e('هیچ فرزندی ثبت نشده است. لطفاً ابتدا فرزندان خود را اضافه کنید.', 'neurame-ai-assistant'); ?></p>
                <?php else: ?>
                    <div>
                        <label for="child_select"
                               class="block mb-1 text-sm font-medium"><?php esc_html_e('انتخاب فرزند:', 'neurame-ai-assistant'); ?></label>
                        <br>
                        <select name="child_select" id="child_select" class="w-full p-2 border rounded-lg" required>
                            <option
                                value=""><?php esc_html_e('یک فرزند انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                            <?php foreach ($children as $index => $child): ?>
                                <option value="<?php echo esc_attr($user_id . '_' . $index); ?>">
                                    <?php echo esc_html($child['name']); ?> (سن: <?php echo esc_html($child['age']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="parent_goals"
                               class="block mb-1 text-sm font-medium"><?php esc_html_e('هدف:', 'neurame-ai-assistant'); ?></label>
                        <br>
                        <textarea name="parent_goals" id="parent_goals" class="w-full p-2 border rounded-lg"
                                  required><?php echo esc_textarea($parent_goals); ?></textarea>
                    </div>

                    <button type="button" id="neurame-ai-recommend"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <?php esc_html_e('دریافت پیشنهاد دوره با هوش مصنوعی', 'neurame-ai-assistant'); ?>
                    </button>
                <?php endif; ?>
            </form>
            <div id="neurame-ai-response" class="mt-4"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_scripts()
    {
        global $neurame_ai_shortcode_loaded;

        if (is_account_page() || !empty($neurame_ai_shortcode_loaded)) {
            $assets_version = '1.2.0';
            $script_dependencies = ['jquery'];

            wp_enqueue_style('neurame-frontend', NEURAMEAI_PLUGIN_URL . 'assets/css/neurame-styles.css', [], $assets_version);
            wp_enqueue_script('neurame-report-scripts', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-report.js', $script_dependencies, $assets_version, true);
            wp_enqueue_script('neurame-child-scripts', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-child.js', $script_dependencies, $assets_version, true);

            $neural_vars = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce_actions' => [
                    'load_buyers' => wp_create_nonce('neurame_load_buyers'),
                    'get_children' => wp_create_nonce('neurame_get_children'),
                    'nonce_trainer_report' => wp_create_nonce('neurame_trainer_report'),
                    'ai_recommendation' => wp_create_nonce('neurame_ai_recommendation'),
                    'get_reports' => wp_create_nonce('neurame_get_reports'),
                    'fetch_parent_info' => wp_create_nonce('neurame_fetch_parent_info')
                ],
                'user_id' => get_current_user_id(),
                'is_admin' => current_user_can('manage_options')
            ];

            wp_localize_script('neurame-report-scripts', 'neurame_vars', $neural_vars);
            wp_localize_script('neurame-child-scripts', 'neurame_vars', $neural_vars);

            wp_add_inline_script('neurame-report-scripts', 'console.log("Neurame Vars Loaded:", ' . wp_json_encode($neural_vars) . ');');
        }
    }

    public function admin_enqueue_scripts($hook)
    {
        if (strpos($hook, 'neurame-trainer-reports') === false) {
            return;
        }

        $assets_version = '1.2.0';
        $script_dependencies = ['jquery'];

        wp_enqueue_style('neurame-admin-styles', NEURAMEAI_PLUGIN_URL . 'assets/css/neurame-styles.min.css', [], $assets_version);
        wp_enqueue_script('neurame-report-scripts', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-report.js', $script_dependencies, $assets_version, true);
        wp_enqueue_script('neurame-child-scripts', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-child.js', $script_dependencies, $assets_version, true);

        $admin_vars = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_actions' => [
                'load_buyers' => wp_create_nonce('neurame_load_buyers'),
                'get_children' => wp_create_nonce('neurame_get_children'),
                'trainer_report' => wp_create_nonce('neurame_trainer_report'),
                'ai_recommendation' => wp_create_nonce('neurame_ai_recommendation'),
                'get_reports' => wp_create_nonce('neurame_get_reports'),
                'save_parent_info' => wp_create_nonce('neurame_save_parent_info')
            ],
            'user_id' => get_current_user_id(),
            'is_admin' => current_user_can('manage_options')
        ];

        wp_localize_script('neurame-report-scripts', 'neurame_admin_vars', $admin_vars);
        wp_localize_script('neurame-child-scripts', 'neurame_admin_vars', $admin_vars);

        wp_add_inline_script('neurame-report-scripts', 'console.log("Neurame Vars Loaded:", ' . wp_json_encode($admin_vars) . ');');
    }

    public function register_settings()
    {
        register_setting('neurame_settings_group', 'neurame_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        add_settings_section(
            'neurame_settings_section',
            esc_html__('تنظیمات افزونه', 'neurame-ai-assistant'),
            null,
            'neurame-ai-assistant'
        );

        add_settings_field(
            'neurame_parent_mode',
            esc_html__('فعال‌سازی حالت والدینی', 'neurame-ai-assistant'),
            [$this, 'render_parent_mode_field'],
            'neurame-ai-assistant',
            'neurame_settings_section'
        );

        add_settings_field(
            'neurame_analytics',
            esc_html__('فعال‌سازی تحلیل‌ها', 'neurame-ai-assistant'),
            [$this, 'render_analytics_field'],
            'neurame-ai-assistant',
            'neurame_settings_section'
        );

        add_settings_field(
            'neurame_api_type',
            esc_html__('نوع API هوش مصنوعی', 'neurame-ai-assistant'),
            [$this, 'render_api_type_field'],
            'neurame-ai-assistant',
            'neurame_settings_section'
        );

        // ۴) کلید ChatGPT
        add_settings_field(
            'neurame_chatgpt_api_key',
            esc_html__('کلید API ChatGPT', 'neurame-ai-assistant'),
            [$this, 'render_chatgpt_api_key_field'],
            'neurame-ai-assistant',
            'neurame_settings_section'
        );

        // ۵) کلید Gemini
        add_settings_field(
            'neurame_gemini_api_key',
            esc_html__('کلید API Gemini', 'neurame-ai-assistant'),
            [$this, 'render_gemini_api_key_field'],
            'neurame-ai-assistant',
            'neurame_settings_section'
        );
    }

    public function render_chatgpt_api_key_field()
    {
        $settings = get_option('neurame_settings', []);
        ?>
        <input type="text"
               name="neurame_settings[neurame_chatgpt_api_key]"
               value="<?php echo esc_attr($settings['neurame_chatgpt_api_key'] ?? ''); ?>"
               class="regular-text">
        <p class="description">
            <?php esc_html_e('کلید API ChatGPT خود را اینجا وارد کنید.', 'neurame-ai-assistant'); ?>
        </p>
        <?php
    }

    public function render_gemini_api_key_field()
    {
        $settings = get_option('neurame_settings', []);
        ?>
        <input type="text"
               name="neurame_settings[neurame_gemini_api_key]"
               value="<?php echo esc_attr($settings['neurame_gemini_api_key'] ?? ''); ?>"
               class="regular-text">
        <p class="description">
            <?php esc_html_e('کلید API Gemini خود را اینجا وارد کنید.', 'neurame-ai-assistant'); ?>
        </p>
        <?php
    }

    public function render_api_type_field()
    {
        $settings = get_option('neurame_settings');
        $api_type = $settings['neurame_api_type'] ?? 'none';
        ?>
        <select name="neurame_settings[neurame_api_type]" id="neurame_api_type">
            <option
                value="none" <?php selected($api_type, 'none'); ?>><?php echo esc_html__('هیچکدام', 'neurame-ai-assistant'); ?></option>
            <option
                value="chatgpt" <?php selected($api_type, 'chatgpt'); ?>><?php echo esc_html__('ChatGPT', 'neurame-ai-assistant'); ?></option>
            <option
                value="gemini" <?php selected($api_type, 'gemini'); ?>><?php echo esc_html__('Gemini', 'neurame-ai-assistant'); ?></option>
        </select>
        <p class="description"><?php echo esc_html__('انتخاب کنید از کدام سرویس هوش مصنوعی برای پیشنهاد دوره‌ها و بازنویسی گزارش‌ها استفاده شود.', 'neurame-ai-assistant'); ?></p>
        <?php
    }

    public function render_analytics_field()
    {
        $settings = get_option('neurame_settings');
        $checked = isset($settings['neurame_analytics']) && $settings['neurame_analytics'] ? 'checked' : '';
        ?>
        <input type="checkbox" name="neurame_settings[neurame_analytics]"
               id="neurame_analytics" <?php echo esc_attr($checked); ?>>
        <label
            for="neurame_analytics"><?php echo esc_html__('فعال کردن تحلیل‌های آماری برای کودکان و دوره‌ها.', 'neurame-ai-assistant'); ?></label>
        <?php
    }

    public function render_parent_mode_field()
    {
        $settings = get_option('neurame_settings');
        $checked = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'] ? 'checked' : '';
        ?>
        <input type="checkbox" name="neurame_settings[neurame_parent_mode]"
               id="neurame_parent_mode" <?php echo esc_attr($checked); ?>>
        <label
            for="neurame_parent_mode"><?php echo esc_html__('اگر فعال شود، والدین می‌توانند اطلاعات کودکان را مدیریت کنند.', 'neurame-ai-assistant'); ?></label>
        <?php
    }

    public function add_course_metabox()
    {
        add_meta_box(
            'neurame_course_metabox', // ID متاباکس
            __('اطلاعات هوش مصنوعی دوره', 'neurame-ai-assistant'), // عنوان متاباکس
            [$this, 'render_course_metabox'], // تابع رندر محتوا
            'product', // روی نوع پست محصول (ووکامرس)
            'normal', // جایگاه (normal = وسط صفحه)
            'high' // اولویت بالا
        );
    }

    public function render_course_metabox($post)
    {
        wp_nonce_field('neurame_course_metabox', 'neurame_course_nonce');

        $ai_headings = get_post_meta($post->ID, '_neurame_ai_headings', true);

        ?>
        <div class="neurame-metabox">
            <p>
                <label
                    for="neurame_ai_headings"><?php echo esc_html__('سرفصل‌های تولیدشده توسط هوش مصنوعی:', 'neurame-ai-assistant'); ?></label><br>
                <textarea name="neurame_ai_headings" id="neurame_ai_headings" rows="5"
                          class="widefat"><?php echo esc_textarea($ai_headings); ?></textarea>
            </p>
        </div>
        <?php
    }

    public function save_course_metabox($post_id)
    {
        // چک نانس برای امنیت
        if (!isset($_POST['neurame_course_nonce']) || !wp_verify_nonce($_POST['neurame_course_nonce'], 'neurame_course_metabox')) {
            return;
        }

        // جلوگیری از ذخیره اتوماتیک
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // چک سطح دسترسی
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ذخیره سرفصل‌های تولیدشده
        if (isset($_POST['neurame_ai_headings'])) {
            update_post_meta($post_id, '_neurame_ai_headings', sanitize_textarea_field($_POST['neurame_ai_headings']));
        }
    }

    /**
     * ۲) اضافه کردن آیتم منو در my-account
     */
    public function add_account_menus($items)
    {
        $new_items = [];
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'dashboard') {
                $new_items['neurame-dashboard'] = esc_html__('داشبورد هوشمند والدین', 'neurame-ai-assistant');
            }
        }
        return $new_items;
    }

    public function admin_user_profile_fields($user)
    {
        $parent_mode = get_option('neurame_settings')['neurame_parent_mode'] ?? 0;
        if (!$parent_mode) {
            return;
        }

        $children = get_user_meta($user->ID, 'neurame_children', true);
        $children = is_array($children) ? $children : [];

        ?>
        <h2><?php echo esc_html__('اطلاعات کودکان', 'neurame-ai-assistant'); ?></h2>

        <?php if (empty($children)) : ?>
        <p><?php echo esc_html__('هیچ کودکی برای این کاربر ثبت نشده است.', 'neurame-ai-assistant'); ?></p>
    <?php else : ?>
        <table class="form-table">
            <tbody>
            <?php foreach ($children as $child) : ?>
                <tr>
                    <th><?php echo esc_html__('نام کودک', 'neurame-ai-assistant'); ?></th>
                    <td><?php echo esc_html($child['name']); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('سن کودک', 'neurame-ai-assistant'); ?></th>
                    <td><?php echo esc_html($child['age']); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('علاقه‌مندی‌ها', 'neurame-ai-assistant'); ?></th>
                    <td><?php echo esc_html($child['interests']); ?></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <hr>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
    }

    public function save_admin_user_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['parent_goals'])) {
            update_user_meta(
                $user_id,
                'neurame_parent_goals',
                sanitize_textarea_field($_POST['parent_goals'])
            );
        }
    }

    public function ajax_get_children()
    {
        $user_id = absint($_POST['user_id'] ?? 0);
        check_ajax_referer('neurame_get_children', 'nonce');

        if (!$user_id) {
            wp_send_json_error(['message' => 'شناسه کاربر نامعتبر است.']);
        }

        $children = get_user_meta($user_id, 'neurame_children', true);
        $children = is_array($children) ? $children : [];

        $data = [];
        foreach ($children as $index => $child) {
            $data[] = [
                'user_id' => $user_id,
                'index' => $index,
                'name' => $child['name'],
                'age' => $child['age']
            ];
        }

        wp_send_json_success($data);
    }

    public function ajax_get_child_data()
    {
        check_ajax_referer('neurame_get_children', 'nonce');

        $child_id = sanitize_text_field($_POST['child_id'] ?? '');

        if (empty($child_id)) {
            wp_send_json_error(__('کودک انتخاب نشده است.', 'neurame-ai-assistant'));
        }

        list($user_id, $index) = explode('_', $child_id);
        $children = get_user_meta($user_id, 'neurame_children', true);

        if (!isset($children[$index])) {
            wp_send_json_error(__('کودک یافت نشد.', 'neurame-ai-assistant'));
        }

        $child = $children[$index];

        wp_send_json_success([
            'name' => $child['name'],
            'age' => $child['age'],
            'interests' => $child['interests']
        ]);
    }

    /**
     * Validates child data on the server side, similar to checkChildDataCompleteness in JS.
     */
    private function validate_child_data($child_data)
    {
        if (empty($child_data)) {
            return ['is_valid' => false, 'message' => 'لطفاً یک فرزند انتخاب کنید.'];
        }

        $errors = [];

        // بررسی نام
        if (!isset($child_data['name']) || !is_string($child_data['name'])) {
            $errors[] = 'نام فرزند معتبر نیست.';
        } elseif (trim($child_data['name']) === '') {
            $errors[] = 'نام فرزند وارد نشده است.';
        }

        // بررسی سن
        if (!isset($child_data['age']) || !is_numeric($child_data['age'])) {
            $errors[] = 'سن فرزند معتبر نیست.';
        } elseif ((int)$child_data['age'] <= 0) {
            $errors[] = 'سن فرزند وارد نشده است.';
        }

        // بررسی علاقه‌مندی‌ها
        if (!isset($child_data['interests']) || !is_string($child_data['interests'])) {
            $errors[] = 'علاقه‌مندی‌های فرزند معتبر نیست.';
        } elseif (trim($child_data['interests']) === '') {
            $errors[] = 'علاقه‌مندی‌های فرزند وارد نشده است.';
        }

        if (!empty($errors)) {
            return ['is_valid' => false, 'message' => implode(' ', $errors)];
        }

        return ['is_valid' => true, 'message' => ''];
    }

    /**
     * Handles AJAX request for AI-based course recommendations.
     */
    public function handle_fetch_ai_recommendation()
    {
        // چک کردن نانس
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_ai_recommendation')) {
            Logger::info('❌ handle_fetch_ai_recommendation: Invalid nonce');
            wp_send_json_error(['message' => __('خطای امنیتی.', 'neurame-ai-assistant')], 403);
        }

        // دریافت و اعتبارسنجی ورودی‌ها
        $user_id = absint($_POST['user_id'] ?? 0);
        $child_index = absint($_POST['child_index'] ?? -1);
        $parent_goals = sanitize_textarea_field($_POST['parent_goals'] ?? '');

        // اگر user_id و child_index جداگانه ارسال نشده باشند، از child_id استفاده کن
        if (!$user_id || $child_index < 0) {
            $child_id = sanitize_text_field($_POST['child_id'] ?? '');
            if ($child_id) {
                list($user_id, $child_index) = array_map(static function ($val) {
                    return absint($val);
                }, explode('_', $child_id));
                Logger::info("📝 handle_fetch_ai_recommendation: Parsed child_id=$child_id to user_id=$user_id, child_index=$child_index");
            }
        }

        // اعتبارسنجی اولیه
        if (!$user_id || $child_index < 0 || empty($parent_goals)) {
            Logger::info('❌ handle_fetch_ai_recommendation: Missing required fields (user_id=' . $user_id . ', child_index=' . $child_index . ', parent_goals=' . substr($parent_goals, 0, 50) . ')');
            wp_send_json_error(['message' => __('لطفاً همه‌ی فیلدها را صحیح پر کنید.', 'neurame-ai-assistant')], 400);
        }

        // دریافت اطلاعات کودکان
        $children = get_user_meta($user_id, 'neurame_children', true);
        if (!is_array($children) || !isset($children[$child_index])) {
            Logger::info('❌ handle_fetch_ai_recommendation: Invalid child data for user_id=' . $user_id . ', child_index=' . $child_index);
            wp_send_json_error(['message' => __('اطلاعات کودک یافت نشد.', 'neurame-ai-assistant')], 404);
        }

        $child = $children[$child_index];

        // اعتبارسنجی داده‌های کودک
        $validation = $this->validate_child_data($child);
        if (!$validation['is_valid']) {
            Logger::info('❌ Child Data Validation Failed: ' . $validation['message']);
            wp_send_json_error(['message' => $validation['message']], 400);
        }
        Logger::info('✅ Child Data Validated Successfully: ' . json_encode($child));

        // آماده‌سازی داده‌ها برای API
        $data = [
            'child_age' => $child['age'],
            'child_interests' => $child['interests'],
            'parent_goals' => $parent_goals,
            'child_name' => $child['name'],
        ];

        try {
            $ai_response = $this->fetch_ai_recommendation($data);
        } catch (Throwable $e) {
            Logger::info('❌ AI Recommendation Exception: ' . $e->getMessage() . ' | Stack Trace: ' . $e->getTraceAsString());
            wp_send_json_error(['message' => __('یک خطای داخلی رخ داد.', 'neurame-ai-assistant')], 500);
        }

        // @phpstan-ignore-next-line
        if (empty($ai_response['success'])) {
            Logger::info('❌ AI Response Error: ' . json_encode($ai_response));
            wp_send_json_error(['message' => $ai_response['message'] ?? __('خطا در پاسخ هوش مصنوعی.', 'neurame-ai-assistant')], 500);
        }

        // رندر دوره‌های پیشنهادی
        $html = $this->render_recommended_courses($ai_response['data']);
        Logger::info('✅ AI Recommendation Success: Rendered HTML for ' . count($ai_response['data']) . ' courses');
        wp_send_json_success(['html' => $html]);
    }

    public function fetch_ai_recommendation($data)
    {
        // Cleaning output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        $settings = get_option('neurame_settings', []);
        $api_type = $settings['neurame_api_type'] ?? 'none';

        // Logging start
        Logger::info('🚀 fetch_ai_recommendation: Starting with data - ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        // Validate AI settings
        if ($api_type === 'none') {
            Logger::info('❌ fetch_ai_recommendation: No AI API selected');
            return $this->send_json_response(false, __('هیچ API هوش مصنوعی انتخاب نشده است.', 'neurame-ai-assistant'));
        }

        $api_key = $api_type === 'chatgpt' ? ($settings['neurame_chatgpt_api_key'] ?? '') : ($settings['neurame_gemini_api_key'] ?? '');
        if (empty($api_key)) {
            Logger::info('❌ fetch_ai_recommendation: Missing API key for ' . $api_type);
            return $this->send_json_response(false, __('کلید API برای ' . $api_type . ' تنظیم نشده است.', 'neurame-ai-assistant'));
        }

        if (empty($data['child_age']) || empty($data['child_interests']) || empty($data['parent_goals'])) {
            Logger::info('❌ fetch_ai_recommendation: Invalid input data - ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->send_json_response(false, __('داده‌های ورودی ناقص یا نامعتبر هستند.', 'neurame-ai-assistant'));
        }

        $courses = wc_get_products(['limit' => -1, 'status' => 'publish', 'type' => ['simple', 'variable']]);
        $course_list = array_map(function ($course) {
            return [
                'course_id' => (string)$course->get_id(),
                'course_name' => $course->get_name(),
                'course_url' => get_permalink($course->get_id()) ?: '',
            ];
        }, $courses);

        if (empty($course_list)) {
            Logger::info('❌ fetch_ai_recommendation: No published courses found');
            return $this->send_json_response(false, __('هیچ دوره‌ای در سیستم یافت نشد.', 'neurame-ai-assistant'));
        }

        $course_list_text = implode("\n", array_map(fn($course) => "- ID: {$course['course_id']}, نام: {$course['course_name']}, URL: {$course['course_url']}", $course_list));

        $prompt = sprintf(
            "برای کودکی با سن %d سال، علاقه‌مندی‌های '%s' و اهداف والدین '%s'، دوره‌های آموزشی مناسب را پیشنهاد دهید. " .
            "فقط از دوره‌های زیر انتخاب کنید و دوره‌های دیگر را پیشنهاد ندهید:\n%s\n\n" .
            "پاسخ را حتماً به صورت JSON معتبر با فرمت زیر ارائه دهید و فقط JSON را برگردانید:\n" .
            "{\"courses\": [{\"course_id\": \"\", \"course_name\": \"\", \"course_url\": \"\"}, ...]}",
            $data['child_age'],
            $data['child_interests'],
            $data['parent_goals'],
            $course_list_text
        );

        if (!empty($data['child_name'])) {
            $prompt .= "\n\nنام کودک: " . $data['child_name'];
        }

        Logger::info('📝 AI Prompt: ' . substr($prompt, 0, 200));

        try {
            $response = $this->call_ai_api($prompt, $settings);
            if (empty($response['success'])) {
                Logger::info('❌ AI API Error: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                return $this->send_json_response(false, __('خطا در پاسخ API: ', 'neurame-ai-assistant') . ($response['data'] ?? 'خطای ناشناخته'));
            }

            Logger::info('📬 AI Raw Response: ' . substr($response['data'], 0, 200));

            $cleaned_response = trim(preg_replace('/^```json\s*|\s*```$|^```/', '', $response['data']));
            Logger::info('🧹 Cleaned AI Response: ' . substr($cleaned_response, 0, 200));

            $json = json_decode($cleaned_response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !$json || empty($json['courses']) || !is_array($json['courses'])) {
                Logger::info('❌ Invalid JSON Format: ' . json_last_error_msg());
                return $this->send_json_response(false, __('پاسخ API در فرمت JSON معتبر نیست: ', 'neurame-ai-assistant') . json_last_error_msg());
            }

            $valid_courses = [];
            $course_map = array_column($course_list, null, 'course_id');
            foreach ($json['courses'] as $item) {
                if (!isset($item['course_id']) || !isset($course_map[$item['course_id']])) {
                    Logger::info('⚠️ Invalid Course Suggested: ' . json_encode($item, JSON_UNESCAPED_UNICODE));
                    continue;
                }
                $course = $course_map[$item['course_id']];
                $valid_courses[] = [
                    'course_id' => $item['course_id'],
                    'course_name' => $item['course_name'] ?: $course['course_name'],
                    'course_url' => $course['course_url'],
                ];
            }

            if (empty($valid_courses)) {
                Logger::info('❌ No Valid Courses Found in AI Response');
                return $this->send_json_response(false, __('هیچ دوره معتبری پیشنهاد نشد.', 'neurame-ai-assistant'));
            }

            Logger::info('✅ AI Recommendation Success: ' . count($valid_courses) . ' valid courses found');
            return $this->send_json_response(true, [
                'html' => $this->render_recommended_courses($valid_courses),
                'courses' => $valid_courses,
            ]);

        } catch (Throwable $e) {
            Logger::info('❌ fetch_ai_recommendation Exception: ' . $e->getMessage());
            return $this->send_json_response(false, __('خطای داخلی در پردازش API: ', 'neurame-ai-assistant') . $e->getMessage());
        }
    }

    public function render_recommended_courses($courses)
    {
        // لاگ‌گذاری داده‌های ورودی
        Logger::info('📥 render_recommended_courses: Input courses - ' . json_encode($courses, JSON_UNESCAPED_UNICODE));

        if (empty($courses)) {
            Logger::info('⚠️ render_recommended_courses: No courses provided');
            return '<p class="text-gray-600 text-center py-4">' .
                esc_html__('هیچ دوره‌ای پیشنهاد نشد. لطفاً اهداف یا علاقه‌مندی‌های کودک را بررسی کنید.', 'neurame-ai-assistant') .
                '</p>';
        }

        ob_start();
        ?>
        <div class="neurame-ai-recommended-courses mt-6">
            <h3 class="text-xl font-semibold mb-4 text-gray-800"><?php esc_html_e('دوره‌های پیشنهادی توسط هوش مصنوعی', 'neurame-ai-assistant'); ?></h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($courses as $course) : ?>
                    <div class="course-card p-4 border rounded-lg shadow-sm hover:shadow-md transition-shadow bg-white">
                        <h4 class="text-lg font-medium text-gray-900 mb-2"><?php echo esc_html($course['course_name']); ?></h4>
                        <?php if (!empty($course['course_url'])) : ?>
                            <a href="<?php echo esc_url($course['course_url']); ?>"
                               class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <?php esc_html_e('مشاهده دوره', 'neurame-ai-assistant'); ?>
                            </a>
                            <?php Logger::info('✅ render_recommended_courses: Rendered course with URL - ID=' . $course['course_id'] . ', URL=' . $course['course_url']); ?>
                        <?php else : ?>
                            <p class="text-red-600 text-sm mt-2"><?php esc_html_e('لینک دوره در دسترس نیست. لطفاً بررسی کنید که دوره در ووکامرس منتشر شده باشد.', 'neurame-ai-assistant'); ?></p>
                            <?php Logger::info('❌ render_recommended_courses: Missing course_url for course - ID=' . $course['course_id'] . ', Name=' . $course['course_name']); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper method to send JSON response and exit
     */
    private function send_json_response($success, $data)
    {
        $response = ['success' => $success];
        if ($success) {
            $response['data'] = $data;
        } else {
            $response['message'] = $data;
        }

        // تنظیم هدر JSON
        header('Content-Type: application/json; charset=UTF-8');

        // لاگ‌گذاری پاسخ نهایی
        Logger::info('📤 AJAX Response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

        // خروجی JSON و توقف اسکریپت
        echo wp_json_encode($response);
        wp_die();
    }

    public function handle_fetch_parent_info()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_fetch_parent_info')) {
            Logger::info('❌ handle_fetch_parent_info: Invalid nonce');
            wp_send_json_error(__('خطای امنیتی.', 'neurame-ai-assistant'));
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id || $user_id !== get_current_user_id()) {
            Logger::info('❌ handle_fetch_parent_info: Unauthorized access');
            wp_send_json_error(__('دسترسی غیرمجاز.', 'neurame-ai-assistant'));
        }

        // اطلاعات والدین
        $user = get_userdata($user_id);
        $parent_goals = get_user_meta($user_id, 'neurame_parent_goals', true);

        $html = '<div>';
        $html .= '<h3>' . esc_html__('اطلاعات والدین', 'neurame-ai-assistant') . '</h3>';
        $html .= '<p><strong>' . esc_html__('نام کاربری:') . '</strong> ' . esc_html($user->user_login) . '</p>';
        $html .= '<p><strong>' . esc_html__('ایمیل:') . '</strong> ' . esc_html($user->user_email) . '</p>';
        $html .= '<p><strong>' . esc_html__('هدف:') . '</strong> ' . esc_textarea($parent_goals) . '</p>';
        $html .= '</div>';

        wp_send_json_success(['html' => $html]);
    }

    /**
     * ارسال درخواست به API هوش مصنوعی (ChatGPT یا Gemini)
     *
     * @param string $prompt متن پرامپت
     * @param array $settings تنظیمات شامل api_type و کلیدها
     * @return array ['success' => bool, 'data' => string|WP_Error]
     */
    private function call_ai_api($prompt, $settings)
    {
        $api_type = $settings['neurame_api_type'] ?? 'none';

        if ($api_type === 'chatgpt') {
            $api_key = $settings['neurame_chatgpt_api_key'] ?? '';
            if (empty($api_key)) {
                return ['success' => false, 'data' => 'کلید API ChatGPT تنظیم نشده.'];
            }

            $endpoint = 'https://api.openai.com/v1/completions';
            $body = [
                'model' => 'text-davinci-003',
                'prompt' => $prompt,
                'max_tokens' => 200,
                'temperature' => 0.7,
            ];
            $headers = [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ];

        } elseif ($api_type === 'gemini') {
            $api_key = $settings['neurame_gemini_api_key'] ?? '';
            if (empty($api_key)) {
                return ['success' => false, 'data' => 'کلید API Gemini تنظیم نشده.'];
            }

            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode($api_key);
            $body = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];
            $headers = [
                'Content-Type' => 'application/json',
            ];

        } else {
            return ['success' => false, 'data' => 'هیچ API هوش مصنوعی انتخاب نشده است.'];
        }

        // ارسال درخواست
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'data' => $response->get_error_message()];
        }

        $resp_body = wp_remote_retrieve_body($response);
        $data = json_decode($resp_body, true);

        // لاگ پاسخ برای دیباگ
        Logger::info('📦 Raw AI Response: ' . $resp_body);

        if ($api_type === 'chatgpt') {
            if (isset($data['choices'][0]['text'])) {
                return ['success' => true, 'data' => trim($data['choices'][0]['text'])];
            }
        }

        if ($api_type === 'gemini') {
            // بررسی خروجی برای Gemini
            if (isset($data['candidates'][0]['output'])) {
                return ['success' => true, 'data' => trim($data['candidates'][0]['output'])];
            }

            // بررسی اینکه پاسخ از "parts" آمده
            if (isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
                $parts = $data['candidates'][0]['content']['parts'];
                $text = '';
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'] . ' ';
                    }
                }
                return ['success' => true, 'data' => trim($text)];
            }

            // اگر هیچ کدام از این دو حالت نیامد، داده معتبر نیست
            return ['success' => false, 'data' => 'پاسخ Gemini در فرمت معتبر نیامد.'];
        }

        // اگر هیچ پاسخ معتبری دریافت نشد
        return [
            'success' => false,
            'data' => 'پاسخ معتبر از API دریافت نشد: ' . $resp_body,
        ];
    }

    /**
     * ۱) ثبت endpoint جدید
     */
    public function register_woocommerce_endpoints()
    {
        static $added = false;
        if ($added === true /*  */) {
            return;
        }
        // آدرس: /my-account/neurame-dashboard/
        add_rewrite_endpoint('neurame-dashboard', EP_PAGES);
        $added = true;
    }

    /**
     * داشبورد ترکیبی والدین در my-account/neurame-dashboard
     */
    public function render_combined_dashboard()
    {
        if (!is_user_logged_in()) {
            echo '<p class="neurame-alert">' . esc_html__('لطفاً وارد حساب کاربری شوید.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        // ساختار دو ستونه: محتوای اصلی (چپ) و پنل (راست)
        echo '<div class="neurame-combined-dashboard grid gap-8 lg:grid-cols-3 p-12">';

        // ستون اصلی (چپ) - شامل بلوک‌های اصلی
        echo '<div class="lg:col-span-2 space-y-8">';
        $blocks = [
            ['title' => '', 'content' => $this->render_profile()],
            ['title' => '', 'content' => $this->render_children_management()],
            ['title' => '', 'content' => $this->render_smart_assistant()],
        ];

        foreach ($blocks as $block) {
            echo '<section class="bg-white rounded-lg p-12">';
            echo $block['content'];
            echo '</section>';
        }
        echo '</div>';

        // ستون پنل (راست) - شامل منوی انتخاب و گزارش‌ها
        echo '<div class="lg:col-span-1 space-y-8">';

        // منوی انتخاب (فرزند یا کاربر)
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('انتخاب:', 'neurame-ai-assistant') . '</h3>';

        // بررسی پرنتال مود
        $is_parental_mode = true; // فرض می‌کنیم یه تابع یا متغیر برای بررسی پرنتال مود داریم
        // برای مثال: $is_parental_mode = some_function_to_check_parental_mode();

        if ($is_parental_mode) {
            // پرنتال مود: نمایش منوی انتخاب فرزند
            $children = get_user_meta(get_current_user_id(), 'neurame_children', true);
            if (!is_array($children)) {
                $children = [];
            }
            ?>
            <select name="report_child_select" id="report-child-select" class="w-full p-2 border rounded">
                <option value=""><?php echo esc_html__('یک کودک انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                <?php foreach ($children as $index => $child) : ?>
                    <option value="<?php echo esc_attr(get_current_user_id() . '_' . $index); ?>">
                        <?php echo esc_html($child['name'] . ' (سن: ' . $child['age'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        } else {
            // خارج از پرنتال مود: نمایش منوی انتخاب کاربر
            $users = get_users(['role__in' => ['subscriber', 'customer']]); // کاربران با نقش‌های مشخص
            ?>
            <select name="report_user_select" id="report-user-select" class="w-full p-2 border rounded">
                <option value=""><?php echo esc_html__('یک کاربر انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>">
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }
        echo '</div>';

        // بخش گزارش‌ها
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('گزارش‌ها', 'neurame-ai-assistant') . '</h3>';
        echo '<div id="reports-list" class="reports-list">';
        echo '<p class="text-gray-600">' . esc_html__('لطفاً یک گزینه انتخاب کنید تا گزارش‌ها نمایش داده شوند.', 'neurame-ai-assistant') . '</p>';
        echo '</div>';
        echo '</div>';

        // بخش گزارش هوشمند
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('گزارش هوشمند روند پیشرفت', 'neurame-ai-assistant') . '</h3>';
        echo '<div id="progress-report" class="progress-report">';
        echo '<p class="text-gray-600">' . esc_html__('لطفاً یک گزینه انتخاب کنید تا گزارش هوشمند نمایش داده شود.', 'neurame-ai-assistant') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    public function render_profile()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('لطفاً وارد حساب کاربری خود شوید.', 'neurame-ai-assistant') . '</p>';
        }

        ob_start();
        $this->profile_content();
        return ob_get_clean();
    }

    public function profile_content()
    {
        $user_id = get_current_user_id();
        $parent_goals = get_user_meta($user_id, 'neurame_parent_goals', true);

        $success_message = isset($_GET['success']) && $_GET['success'] === 'profile_updated'
            ? esc_html__('پروفایل با موفقیت به‌روزرسانی شد.', 'neurame-ai-assistant')
            : '';

        ?>
        <div class="woocommerce-account-content">
            <h2><?php echo esc_html__('پروفایل والدین', 'neurame-ai-assistant'); ?></h2>

            <?php if ($success_message) : ?>
                <div class="woocommerce-message"><?php echo esc_html($success_message); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('neurame_profile_form', 'neurame_profile_nonce'); ?>

                <div class="mb-4">
                    <label class="block mb-1 text-sm font-medium">
                        <?php echo esc_html__('هدف‌های شما برای کودک', 'neurame-ai-assistant'); ?>
                    </label>
                    <br>
                    <textarea name="parent_goals" rows="5"
                              class="w-full p-2 border rounded-lg"><?php echo esc_textarea($parent_goals); ?></textarea>
                </div>

                <button type="submit" name="submit_profile"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <?php echo esc_html__('ذخیره پروفایل', 'neurame-ai-assistant'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    public function render_children_management()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('لطفاً وارد شوید.', 'neurame-ai-assistant') . '</p>';
        }

        $user_id = get_current_user_id();
        $children = get_user_meta($user_id, 'neurame_children', true);
        $children = is_array($children) ? $children : [];

        // استفاده از متد لاگ‌گذاری سفارشی
        Logger::info('🚸 render_children_management → loaded children: ' . print_r($children, true));

        $success_message = isset($_GET['success']) && $_GET['success'] === 'children_updated'
            ? esc_html__('اطلاعات کودکان با موفقیت ذخیره شد.', 'neurame-ai-assistant')
            : '';

        ob_start();
        ?>
        <div class="neurame-children-management">
            <?php if ($success_message) : ?>
                <div class="woocommerce-message bg-green-100 text-green-800 p-4 rounded-lg mb-4">
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('neurame_children_form', 'neurame_children_nonce'); ?>
                <?php
                $form_name_prefix = 'neurame_children';
                include NEURAMEAI_PLUGIN_DIR . '/partials/children-form.php';
                ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_smart_assistant()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('لطفاً وارد حساب کاربری خود شوید.', 'neurame-ai-assistant') . '</p>';
        }

        $user_id = get_current_user_id();
        $children = get_user_meta($user_id, 'neurame_children', true);
        $children = is_array($children) ? $children : [];
        $parent_goals = get_user_meta($user_id, 'neurame_parent_goals', true);

        ob_start();
        ?>
        <div class="neurame-smart-assistant space-y-6">
            <h2 class="text-2xl font-semibold"><?php echo esc_html__('دستیار انتخاب دوره', 'neurame-ai-assistant'); ?></h2>

            <form id="neurame-info-form" class="space-y-4">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('neurame_nonce'); ?>">

                <?php if (empty($children)): ?>
                    <p><?php esc_html_e('هیچ فرزندی ثبت نشده است. لطفاً ابتدا فرزندان خود را اضافه کنید.', 'neurame-ai-assistant'); ?></p>
                <?php else: ?>
                    <div>
                        <label for="child_select"
                               class="block mb-1 text-sm font-medium"><?php esc_html_e('انتخاب فرزند', 'neurame-ai-assistant'); ?></label>
                        <br>
                        <select name="child_select" id="child_select" class="w-full p-2 border rounded-lg" required>
                            <option
                                value=""><?php esc_html_e('یک فرزند انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                            <?php foreach ($children as $index => $child): ?>
                                <option value="<?php echo esc_attr($user_id . '_' . $index); ?>">
                                    <?php echo esc_html($child['name']); ?> (سن: <?php echo esc_html($child['age']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="parent_goals"
                               class="block mb-1 text-sm font-medium"><?php esc_html_e('هدف:', 'neurame-ai-assistant'); ?></label>
                        <br>
                        <textarea name="parent_goals" id="parent_goals" rows="3"
                                  class="w-full p-2 border rounded-lg"><?php echo esc_textarea($parent_goals); ?></textarea>
                    </div>

                    <button type="button" id="neurame-ai-recommend"
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        <?php esc_html_e('دریافت پیشنهاد دوره با هوش مصنوعی', 'neurame-ai-assistant'); ?>
                    </button>
                <?php endif; ?>
            </form>
            <br>
            <div class="neurame-recommend-course">
                <div id="neurame-ai-response" class="mt-10"></div>
                <br>
                <div id="recommended-courses-list" class="courses-list">
                    <p class="text-gray-600"><?php echo esc_html__('اینجا لیست دوره‌هایی که برای کودک شما مناسب هستند نمایش داده می‌شود.', 'neurame-ai-assistant'); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_children_management_admin()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('مدیریت کودکان', 'neurame-ai-assistant') . '</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('neurame_children_form', 'neurame_children_nonce');
        $this->load_view('children-form.php');
        echo '<button type="submit" name="submit_children" class="btn-save mt-4">' . esc_html__('ذخیره تغییرات', 'neurame-ai-assistant') . '</button>';
        echo '</form>';
        echo '</div>';
    }

    public function register_admin_menu()
    {
        static $menu_added = false;
        if ($menu_added) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('NeurameAIAssistant: register_admin_menu skipped');
            }
            return;
        }

        add_menu_page(
            __('Neurame AI Assistant', 'neurame-ai-assistant'),
            __('هوش مصنوعی Neurame', 'neurame-ai-assistant'),
            'manage_options',
            'neurame-ai-assistant',
            [$this, 'admin_page'],
            'dashicons-admin-tools',
            20
        );

        // ۳) حذف آیتم خودِ منوی اصلی از زیرمنوها
        remove_submenu_page('neurame-dashboard', 'neurame-dashboard');

        $done = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NeurameAIAssistant: Admin menu registered');
        }

        add_submenu_page(
            'neurame-ai-assistant',
            __('Course Heading AI', 'neurame-ai-assistant'),
            __('سرفصل‌ساز هوشمند', 'neurame-ai-assistant'),
            'manage_options',
            'neurame-course-heading',
            [$this, 'course_heading_page']
        );

        add_submenu_page(
            'neurame-ai-assistant',
            __('Trainer Reports', 'neurame-ai-assistant'),
            __('گزارش‌های مربی', 'neurame-ai-assistant'),
            'manage_options',
            'neurame-trainer-reports',
            [$this, 'trainer_reports_page']
        );

        add_submenu_page(
            'neurame-ai-assistant',
            __('Children Management', 'neurame-ai-assistant'),
            __('مدیریت کودکان', 'neurame-ai-assistant'),
            'manage_options',
            'neurame-children-management',
            [$this, 'render_children_management_admin']
        );

        add_submenu_page(
            'neurame-ai-assistant',
            __('Analytics', 'neurame-ai-assistant'),
            __('تحلیل‌ها', 'neurame-ai-assistant'),
            'manage_options',
            'neurame-analytics',
            [$this, 'analytics_page']
        );

        $menu_added = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NeurameAIAssistant: Admin menu registered');
        }
    }

    public function admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }
        ?>
        <div class="wrap">
            <h1 class="text-2xl font-semibold"><?php echo esc_html__('تنظیمات هوش مصنوعی Neurame', 'neurame-ai-assistant'); ?></h1>
            <form method="post" action="options.php" class="mt-6 space-y-4">
                <?php
                settings_fields('neurame_settings_group');
                do_settings_sections('neurame-ai-assistant');
                submit_button(esc_html__('ذخیره تنظیمات', 'neurame-ai-assistant'), 'primary', 'submit', false, ['class' => 'bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700']);
                ?>
            </form>
        </div>
        <?php
    }

    public function course_heading_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }
        ?>
        <div class="wrap">
            <h1 class="text-2xl font-semibold"><?php echo esc_html__('سرفصل‌ساز هوشمند', 'neurame-ai-assistant'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="neurame_generate_headings">
                <?php wp_nonce_field('neurame_generate_headings', 'neurame_nonce'); ?>
                <div>
                    <label for="course_id"
                           class="block text-sm font-medium"><?php echo esc_html__('انتخاب دوره', 'neurame-ai-assistant'); ?></label>
                    <br>
                    <select name="course_id" id="course_id" class="mt-1 block w-64 p-2 border rounded-lg">
                        <option
                            value=""><?php echo esc_html__('یک دوره انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                        <?php
                        $courses = wc_get_products(['limit' => -1]);
                        foreach ($courses as $course) {
                            printf('<option value="%d">%s</option>', esc_attr($course->get_id()), esc_html($course->get_name()));
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="course_description"
                           class="block text-sm font-medium"><?php echo esc_html__('توضیحات دوره', 'neurame-ai-assistant'); ?></label>
                    <br>
                    <textarea name="course_description" id="course_description" rows="5"
                              class="mt-1 block w-full p-2 border rounded-lg"></textarea>
                </div>
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700"><?php echo esc_html__('ایجاد سرفصل‌ها', 'neurame-ai-assistant'); ?></button>
            </form>
        </div>
        <?php
    }

    public function analytics_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }

        $settings = get_option('neurame_settings');
        if (!isset($settings['neurame_analytics']) || !$settings['neurame_analytics']) {
            echo '<div class="wrap">';
            echo '<h1 class="text-2xl font-semibold">' . esc_html__('تحلیل‌ها', 'neurame-ai-assistant') . '</h1>';
            echo '<p class="text-gray-600">' . esc_html__('لطفاً تحلیل‌ها را از تنظیمات افزونه فعال کنید.', 'neurame-ai-assistant') . '</p>';
            echo '</div>';
            return;
        }

        $total_courses = count(wc_get_products(['limit' => -1]));
        $total_trainers = count(get_users(['role' => 'trainer']));
        $total_reports = count($this->get_trainer_reports());
        $children_count = 0;
        $users = get_users();
        foreach ($users as $user) {
            $children = get_user_meta($user->ID, 'neurame_children', true);
            if (is_array($children)) {
                $children_count += count($children);
            }
        }

        $total_users = count($users);
        $total_recommendations = 0;
        $ages = [];
        foreach ($users as $user) {
            $recommendations = get_user_meta($user->ID, 'neurame_smart_assistant_recommendations', true);
            if (is_array($recommendations)) {
                $total_recommendations += count($recommendations);
                foreach ($recommendations as $rec) {
                    $ages[] = $rec['child_age'];
                }
            }
        }
        $average_age = !empty($ages) ? array_sum($ages) / count($ages) : 0;
        ?>
        <div class="wrap">
            <h1 class="text-2xl font-semibold"><?php echo esc_html__('تحلیل‌ها', 'neurame-ai-assistant'); ?></h1>
            <div class="mt-6">
                <h2 class="text-xl font-semibold"><?php echo esc_html__('آمار کلی', 'neurame-ai-assistant'); ?></h2>
                <ul class="list-disc pl-6 mt-2">
                    <li><?php printf(esc_html__('تعداد دوره‌ها: %d', 'neurame-ai-assistant'), $total_courses); ?></li>
                    <li><?php printf(esc_html__('تعداد مربیان: %d', 'neurame-ai-assistant'), $total_trainers); ?></li>
                    <li><?php printf(esc_html__('تعداد گزارش‌های مربی: %d', 'neurame-ai-assistant'), $total_reports); ?></li>
                    <li><?php printf(esc_html__('تعداد کودکان ثبت‌شده: %d', 'neurame-ai-assistant'), $children_count); ?></li>
                    <li><?php printf(esc_html__('تعداد کاربران: %d', 'neurame-ai-assistant'), $total_users); ?></li>
                    <li><?php printf(esc_html__('تعداد پیشنهادات: %d', 'neurame-ai-assistant'), $total_recommendations); ?></li>
                    <li><?php printf(esc_html__('میانگین سن کودکان: %.1f', 'neurame-ai-assistant'), $average_age); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    // تابع دریافت گزارش‌های مربی
    public function get_trainer_reports()
    {
        return get_option('neurame_trainer_reports', []);
    }

    public function trainer_reports_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }

        $parent_mode = get_option('neurame_settings')['neurame_parent_mode'] ?? 0;
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        ?>
        <div class="wrap">
            <h1 class="text-3xl font-bold mb-6"><?php echo esc_html__('گزارش‌های مربی', 'neurame-ai-assistant'); ?></h1>
            <?php include NEURAMEAI_PLUGIN_DIR . 'partials/trainer-reports-template.php'; ?>

            <?php
            $reports = $this->get_trainer_reports();

            if (!is_array($reports)) {
                $reports = [];
            }

            // حذف گزارش‌های ناقص
            $reports = array_filter($reports, function ($r) {
                return isset($r['trainer_id'], $r['course_id'], $r['user_id'], $r['content']);
            });

            // فقط گزارش‌های متعلق به مربی فعلی، مگر اینکه ادمینه
            if (!$is_admin) {
                $reports = array_filter($reports, fn($r) => $r['trainer_id'] === $current_user_id);
            }

            if (!empty($reports)) {
                echo '<h2 class="text-2xl font-bold mt-8 mb-4">' . esc_html__('گزارش‌های موجود', 'neurame-ai-assistant') . '</h2>';
                echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                echo '<th>' . esc_html__('مربی') . '</th>';
                echo '<th>' . esc_html__('دوره') . '</th>';
                echo '<th>' . esc_html__('کاربر') . '</th>';
                if ($parent_mode) echo '<th>' . esc_html__('کودک') . '</th>';
                echo '<th>' . esc_html__('گزارش') . '</th>';
                echo '<th>' . esc_html__('بازنویسی‌شده') . '</th>';
                echo '<th>' . esc_html__('مدیریت') . '</th>';
                echo '</tr></thead><tbody>';

                foreach ($reports as $report) {
                    $trainer_id = $report['trainer_id'] ?? 0;
                    $course_id = $report['course_id'] ?? 0;
                    $user_id = $report['user_id'] ?? 0;
                    $child_id = $report['child_id'] ?? '';
                    $content = $report['content'] ?? '';
                    $ai_content = $report['ai_content'] ?? '';
                    $report_id = esc_attr($report['id'] ?? '');

                    $trainer = get_user_by('id', $trainer_id);
                    $course = wc_get_product($course_id);
                    $user = get_user_by('id', $user_id);
                    $child_name = ($parent_mode && !empty($child_id)) ? $this->get_child_name($child_id) : '';

                    echo '<tr>';
                    echo '<td>' . esc_html($trainer ? $trainer->display_name : 'ناشناس') . '</td>';
                    echo '<td>' . esc_html($course ? $course->get_name() : 'ناشناس') . '</td>';
                    echo '<td>' . esc_html($user ? $user->display_name : 'ناشناس') . '</td>';
                    if ($parent_mode) echo '<td>' . esc_html($child_name) . '</td>';
                    echo '<td>' . esc_html($content) . '</td>';
                    echo '<td>' . esc_html($ai_content) . '</td>';
                    echo '<td>';
                    echo '<button class="neurame-edit-report text-blue-600" data-report-id="' . $report_id . '">✏️</button> ';
                    echo '<button class="neurame-delete-report text-red-600" data-report-id="' . $report_id . '">🗑</button>';
                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
            ?>
        </div>
        <?php
    }

    // 📌 به‌روزرسانی پیشرفت کودک بعد از ذخیره گزارش
    public function update_child_progress_on_report($user_id, $child_id, $last_report_content)
    {
        $all_reports = $this->get_trainer_reports();
        $child_reports = array_filter($all_reports, function ($r) use ($child_id) {
            return isset($r['child_id']) && $r['child_id'] === $child_id;
        });

        if (empty($child_reports)) {
            return;
        }

        $combined_content = implode("\n---\n", array_map(function ($r) {
            return $r['ai_content'] ?? $r['content'];
        }, $child_reports));

        // تحلیل جدید با محتوای ترکیبی
        $skills = $this->analyze_child_skills($combined_content);
        $summary = $this->generate_ai_summary($combined_content);

        update_user_meta($user_id, 'child_progress_analysis_' . $child_id, [
            'skills' => $skills,
            'summary' => $summary,
            'last_updated' => current_time('mysql'),
        ]);
    }

    private function analyze_child_skills($child_id)
    {
        // 🔥 اینجا به صورت واقعی باید داده‌های گزارشات مربی تحلیل شود
        // برای تست به صورت تصادفی خروجی ساختیم:
        return [
            'problem_solving' => rand(50, 90),
            'teamwork' => rand(40, 80),
            'creativity' => rand(60, 95),
            'logical_thinking' => rand(50, 85),
            'communication' => rand(45, 75),
        ];
    }

    private function generate_ai_summary($skills)
    {
        // 🔥 در حالت واقعی باید اینجا از API ChatGPT یا Gemini استفاده کنیم
        return 'کودک در تفکر منطقی و خلاقیت پیشرفت خوبی داشته است. پیشنهاد می‌شود روی مهارت ارتباطی بیشتر تمرکز شود.';
    }

    public function save_child_progress_analysis($child_id, $analysis_data)
    {
        if (empty($child_id) || empty($analysis_data)) {
            return false;
        }

        update_user_meta($child_id, 'child_progress_analysis', [
            'last_update' => current_time('mysql'),
            'skills' => $analysis_data['skills'],
            'ai_summary' => $analysis_data['summary']
        ]);

        return true;
    }

    public function handle_generate_headings()
    {
        if (!isset($_POST['neurame_nonce']) || !wp_verify_nonce($_POST['neurame_nonce'], 'neurame_generate_headings')) {
            wp_die(esc_html__('خطای امنیتی.', 'neurame-ai-assistant'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
        }

        $course_id = absint($_POST['course_id'] ?? 0);
        $course_description = sanitize_textarea_field($_POST['course_description'] ?? '');

        if (!$course_id || empty($course_description)) {
            wp_die(esc_html__('داده‌های ورودی نامعتبر است.', 'neurame-ai-assistant'));
        }

        $settings = get_option('neurame_settings');
        $api_type = $settings['neurame_api_type'] ?? 'none';

        if ($api_type === 'none') {
            wp_die(esc_html__('لطفاً یک API هوش مصنوعی را از تنظیمات انتخاب کنید.', 'neurame-ai-assistant'));
        }

        // ساخت پرامپت با توضیحات دقیق‌تر
        $prompt = "بر اساس توضیحات زیر، سرفصل‌های آموزشی پیشنهاد بده:\n\n" . $course_description . "\n\n" .
            "پاسخ را حتماً به صورت یک لیست متنی ساده (نه JSON) ارائه بده و از فرمت Markdown (مثل ``` یا ```json) استفاده نکن. " .
            "هر سرفصل را با خط جدید و با فرمت زیر برگردان:\n" .
            "مثال خروجی:\n- فصل اول: مقدمه\n- فصل دوم: مفاهیم پایه\n- فصل سوم: تمرین عملی";

        // لاگ‌گذاری پرامپت برای دیباگ
        Logger::info('📝 AI Prompt for Headings: ' . substr($prompt, 0, 200));

        // صدا زدن API
        $response = $this->call_ai_api($prompt, $settings);

        if (!$response['success']) {
            Logger::info('❌ AI Response Failed: ' . ($response['data'] ?? 'No data'));
            wp_die(esc_html__('خطا در تولید سرفصل‌ها: ', 'neurame-ai-assistant') . esc_html($response['data']));
        }

        // لاگ‌گذاری پاسخ خام
        Logger::info('📬 AI Raw Response for Headings: ' . substr($response['data'], 0, 200));

        // حذف Markdown اضافی (مثل ```json یا ```)
        $cleaned_response = $response['data'];
        $cleaned_response = preg_replace('/^```json\s*|\s*```$|^```/', '', $cleaned_response); // حذف ```json یا ``` از ابتدا و انتها
        $cleaned_response = trim($cleaned_response); // حذف فضاهای خالی ابتدا و انتها

        // لاگ‌گذاری پاسخ پاک‌شده
        Logger::info('🧹 Cleaned AI Response for Headings: ' . substr($cleaned_response, 0, 200));

        // اگر پاسخ به صورت JSON بود، به متن تبدیلش کن
        $json = json_decode($cleaned_response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['headings']) && is_array($json['headings'])) {
            // اگر پاسخ JSON بود و کلید headings داشت
            $cleaned_response = implode("\n", array_map(function ($heading) {
                return "- " . trim($heading);
            }, $json['headings']));
            Logger::info('🔄 Converted JSON to Text: ' . substr($cleaned_response, 0, 200));
        } elseif (json_last_error() === JSON_ERROR_NONE && isset($json['content'])) {
            // اگر پاسخ JSON بود و کلید content داشت
            $cleaned_response = $json['content'];
            Logger::info('🔄 Converted JSON content to Text: ' . substr($cleaned_response, 0, 200));
        }

        // ذخیره سرفصل‌ها
        update_post_meta($course_id, '_neurame_ai_headings', sanitize_textarea_field($cleaned_response));

        Logger::info('✅ Headings Saved for Course ID ' . $course_id);

        wp_redirect(admin_url('post.php?post=' . $course_id . '&action=edit&message=updated'));
        exit;
    }

    // 📌 تابع ثبت گزارش مربی
    public function handle_trainer_report()
    {
        if (!current_user_can('edit_posts') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'neurame_trainer_report')) {
            wp_die(__('شما مجوز لازم را ندارید.', 'neurame'));
        }

        $trainer_id = intval($_POST['trainer_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        $child_id = sanitize_text_field($_POST['child_id'] ?? '');
        $report_content = sanitize_textarea_field($_POST['report_content'] ?? '');

        if (!$trainer_id || !$course_id || !$user_id || !$report_content) {
            wp_die(__('اطلاعات ناقص است.', 'neurame'));
        }

        $reports = get_option('neurame_trainer_reports', []);
        $reports[] = [
            'id' => uniqid('rpt_'),
            'trainer_id' => $trainer_id,
            'course_id' => $course_id,
            'user_id' => $user_id,
            'child_id' => $child_id,
            'content' => $report_content,
            'created_at' => current_time('mysql')
        ];
        update_option('neurame_trainer_reports', $reports);

        $this->update_child_progress_on_report($user_id, $child_id, $report_content);

        wp_redirect(add_query_arg(['page' => 'neurame-trainer-reports', 'message' => 'saved'], admin_url('admin.php')));
        exit;
    }

    public function handle_profile_form()
    {
        if (isset($_POST['submit_profile']) && isset($_POST['neurame_profile_nonce']) && wp_verify_nonce($_POST['neurame_profile_nonce'], 'neurame_profile_form')) {
            $user_id = get_current_user_id();
            $parent_goals = sanitize_textarea_field($_POST['parent_goals'] ?? '');
            update_user_meta($user_id, 'neurame_parent_goals', $parent_goals);
            wp_redirect(add_query_arg('success', 'profile_updated', wc_get_endpoint_url('neurame-dashboard', '', wc_get_page_permalink('myaccount'))));
            exit;
        }
    }

    public function handle_children_form()
    {
        if (
            isset($_POST['submit_children']) &&
            isset($_POST['neurame_children_nonce']) &&
            wp_verify_nonce($_POST['neurame_children_nonce'], 'neurame_children_form')
        ) {
            $user_id = get_current_user_id();
            $new_children = [];

            if (isset($_POST['neurame_children']) && is_array($_POST['neurame_children'])) {
                foreach ($_POST['neurame_children'] as $child) {
                    $name = sanitize_text_field($child['name'] ?? '');
                    $age = absint($child['age'] ?? 0);
                    $interests = sanitize_textarea_field($child['interests'] ?? '');

                    if ($name || $age || $interests) {
                        $new_children[] = compact('name', 'age', 'interests');
                    }
                }
            }

            // اگر اطلاعات جدید ثبت نشده، ولی داده‌های قدیمی وجود داره
            if (empty($new_children)) {
                $old_age = get_user_meta($user_id, 'neurame_child_age', true);
                $old_interests = get_user_meta($user_id, 'neurame_child_interests', true);
                $old_name = get_userdata($user_id)->display_name;

                if ($old_age || $old_interests) {
                    $new_children[] = [
                        'name' => $old_name,
                        'age' => absint($old_age),
                        'interests' => sanitize_textarea_field($old_interests),
                    ];

                    // حذف متای قدیمی
                    delete_user_meta($user_id, 'neurame_child_age');
                    delete_user_meta($user_id, 'neurame_child_interests');
                }
            }

            update_user_meta($user_id, 'neurame_children', $new_children);

            // ریدایرکت با پارامتر موفقیت
            wp_redirect(add_query_arg('success', 'children_updated', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    public function ajax_load_buyers()
    {
        check_ajax_referer('neurame_load_buyers', 'nonce');

        $course_id = absint($_POST['course_id'] ?? 0);
        if (!$course_id) {
            Logger::info('❌ ajax_load_buyers: Invalid course_id');
            wp_send_json_error(['message' => 'دوره انتخاب نشده است.']);
        }

        Logger::info('🧪 Buyers AJAX triggered: course_id=' . $course_id);

        Logger::info('📦 ajax_load_buyers: Loading buyers for course_id=' . $course_id);

        global $wpdb;

        // پیدا کردن سفارش‌هایی که شامل محصول موردنظر هستن
        $order_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT order_id
            FROM {$wpdb->prefix}wc_order_product_lookup
            WHERE product_id = %d",
                $course_id
            )
        );

        if (empty($order_ids)) {
            Logger::info('⚠️ ajax_load_buyers: No orders found for course_id=' . $course_id);
            wp_send_json_success([]);
        }

        // لود سفارش‌ها با وضعیت completed یا processing
        $orders = wc_get_orders([
            'limit' => -1, // محدود کردن برای بهبود عملکرد
            'status' => ['completed', 'processing'],
            'post__in' => $order_ids, // فقط سفارش‌های مرتبط با محصول
        ]);

        $buyers = [];
        foreach ($orders as $order) {
            $user = $order->get_user();
            if (!$user) {
                continue; // رد کردن سفارش‌های بدون کاربر (مثلاً مهمان)
            }

            Logger::info("✅ یافت شد: user_id={$user->ID}, name={$user->display_name}, roles=" . implode(',', $user->roles));
            Logger::info('👤 بررسی سفارش: order_id=' . $order->get_id() . ', user=' . ($user ? $user->ID : 'مهمان'));

            // فقط کاربرانی که نقش trainer ندارن
            if (!in_array('trainer', (array)$user->roles)) {
                $buyers[$user->ID] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                ];
            }
        }

        if (empty($buyers)) {
            Logger::info('⚠️ ajax_load_buyers: No buyers found for course_id=' . $course_id);
            wp_send_json_success([]);
        }

        Logger::info('✅ ajax_load_buyers: Found ' . count($buyers) . ' buyers');
        wp_send_json_success(array_values($buyers));
    }

    private function load_view($file)
    {
        $path = plugin_dir_path(__DIR__) . 'partials/' . $file;
        if (file_exists($path)) {
            include $path;
        }
    }

    public function ajax_get_progress_report()
    {
        check_ajax_referer('neurame_get_reports', 'nonce');

        $child_id = sanitize_text_field($_POST['child_id'] ?? '');
        if (!$child_id) {
            wp_send_json_error(['message' => 'شناسه کودک نامعتبر است.']);
        }

        $reports = get_option('neurame_trainer_reports', []);
        if (!is_array($reports)) {
            $reports = [];
        }

        $child_reports = array_filter($reports, function ($report) use ($child_id) {
            return $report['child_id'] === $child_id;
        });

        if (empty($child_reports)) {
            wp_send_json_success([
                'html' => '<p class="text-gray-600">برای این کودک هنوز گزارشی ثبت نشده است.</p>'
            ]);
        }

        $report_contents = array_map(function ($r) {
            return $r['ai_content'] ?? $r['content'];
        }, $child_reports);

        $combined_text = implode("\n---\n", $report_contents);

        $prompt = <<<EOD
شما یک تحلیل‌گر آموزشی هستید.

با توجه به گزارش‌های زیر:

1. میزان مهارت‌های کودک را برای این دسته‌ها بین 0 تا 100 بده: تمرکز، منطق، حل مسئله، مکالمه، خلاقیت.
2. یک خلاصه تحلیلی 2 تا 3 جمله‌ای از نقاط قوت و ضعف کودک بنویس.

خروجی را فقط و فقط در قالب JSON به فرمت زیر بده:

{
  "labels": ["تمرکز", "منطق", "حل مسئله", "مکالمه", "خلاقیت"],
  "values": [75, 80, 65, 70, 90],
  "summary": "کودک در تمرکز و منطق عملکرد خوبی دارد اما در مکالمه نیاز به تمرین بیشتر دارد."
}

متن گزارش‌ها:
$combined_text
EOD;

        $settings = get_option('neurame_settings', []);
        $response = $this->call_ai_api($prompt, $settings);

        if (empty($response['success'])) {
            wp_send_json_error(['message' => 'خطا در تحلیل مهارت‌ها توسط AI.']);
        }

        $cleaned_response = $response['data'];

        $cleaned_response = preg_replace('/^```json\s*/', '', $cleaned_response);
        $cleaned_response = preg_replace('/\s*```$/', '', $cleaned_response);
        $cleaned_response = trim($cleaned_response);

        $json_data = json_decode($cleaned_response, true);

        if (!is_array($json_data) || empty($json_data['labels']) || empty($json_data['values'])) {
            wp_send_json_error(['message' => 'پاسخ دریافتی از AI معتبر نیست.']);
        }

        ob_start();
        ?>
        <div class="space-y-4">
            <canvas id="progress-chart-<?php echo esc_attr($child_id); ?>" height="250"></canvas>
            <div class="p-4 bg-blue-50 text-blue-900 rounded-lg">
                <?php echo esc_html($json_data['summary']); ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'chart_data' => [
                'labels' => $json_data['labels'],
                'values' => $json_data['values'],
            ]
        ]);
    }

    public function ajax_save_trainer_report()
    {
        check_ajax_referer('neurame_trainer_report', 'nonce');

        Logger::info('📥 Raw POST Data: ' . print_r($_POST, true));

        $trainer_id = intval($_POST['trainer_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        $child_id = sanitize_text_field($_POST['child_id'] ?? '');
        $report_content = sanitize_textarea_field($_POST['report_content'] ?? '');
        $parent_mode = get_option('neurame_settings')['neurame_parent_mode'] ?? 0;

        if (!$trainer_id || !$course_id || ($parent_mode && (!$user_id || !$child_id)) || !$report_content) {
            Logger::info('❌ Data Missing: ' . ($trainer_id ? '' : 'trainer_id, ') . ($course_id ? '' : 'course_id, ') . ($user_id ? '' : 'user_id, ') . ($child_id ? '' : 'child_id, ') . ($report_content ? '' : 'report_content'));
            wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
        }

        $settings = get_option('neurame_settings', []);
        $api_type = $settings['neurame_api_type'] ?? 'none';
        $ai_content = $report_content;

        // دریافت نام دوره
        $course = wc_get_product($course_id);
        $course_name = $course ? $course->get_name() : 'دوره ناشناس';
        Logger::info('📚 Course Name Loaded: ' . $course_name . ' (course_id=' . $course_id . ')');

        // ساخت پرامپت برای بازنویسی گزارش
        $prompt = "این گزارش مربی را به صورت حرفه‌ای و خلاصه به زبان فارسی بازنویسی کنید:\n\n" .
            "دوره: " . $course_name . "\n" .
            "گزارش: " . $report_content;
        if ($parent_mode && !empty($child_id)) {
            list($user_id, $child_index) = explode('_', $child_id);
            $children = get_user_meta($user_id, 'neurame_children', true);
            if (isset($children[$child_index])) {
                $child = $children[$child_index];
                $prompt .= "\n\nبرای کودک: " . $child['name'] . "، سن " . $child['age'] . "، علاقه‌مندی‌ها: " . $child['interests'];
            }
        }
        $prompt .= "\n\nپاسخ را فقط به صورت متن ساده و بدون فرمت Markdown (مثل ```) ارائه دهید.";

        // لاگ‌گذاری پرامپت
        Logger::info('📝 AI Prompt for Report Rewrite: ' . substr($prompt, 0, 200));

        // ارسال درخواست به API هوش مصنوعی
        if ($api_type !== 'none') {
            $response = $this->call_ai_api($prompt, $settings);

            if ($response['success']) {
                $ai_content = trim($response['data']);
                Logger::info('✅ AI Rewritten Content: ' . substr($ai_content, 0, 200));
            } else {
                Logger::info('❌ AI Rewrite Failed: ' . ($response['data'] ?? 'No data'));
                $ai_content = $report_content; // اگر خطا داشت، از متن اصلی استفاده کن
            }
        } else {
            Logger::info('⚠️ No AI API selected, using original content');
        }

        // ذخیره گزارش
        $reports = $this->get_trainer_reports();
        $report_data = [
            'id' => uniqid('rpt_'),
            'trainer_id' => $trainer_id,
            'course_id' => $course_id,
            'user_id' => $parent_mode ? $user_id : get_current_user_id(),
            'content' => $report_content,
            'ai_content' => $ai_content,
            'timestamp' => current_time('mysql')
        ];
        if ($parent_mode && !empty($child_id)) {
            $report_data['child_id'] = $child_id;
        }

        $reports[] = $report_data;
        update_option('neurame_trainer_reports', $reports);

        // به‌روزرسانی پیشرفت کودک
        if ($parent_mode && !empty($child_id)) {
            $this->update_child_progress_on_report($user_id, $child_id, $ai_content);
        }

        Logger::info('✅ Report saved successfully: trainer_id=' . $trainer_id . ', course_id=' . $course_id . ', course_name=' . $course_name . ', ai_content_length=' . strlen($ai_content));
        wp_send_json_success(['message' => 'گزارش با موفقیت ذخیره شد.', 'ai_content' => $ai_content]);
    }

    public function ajax_delete_trainer_report()
    {
        check_ajax_referer('neurame_trainer_report', 'nonce');

        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        if (!$report_id) {
            wp_send_json_error(['message' => 'شناسه گزارش نامعتبر است.']);
        }

        $reports = get_option('neurame_trainer_reports', []);
        foreach ($reports as $index => $report) {
            if ($report['id'] === $report_id) {
                // امنیت: بررسی مالکیت
                if (!current_user_can('manage_options') && $report['trainer_id'] !== get_current_user_id()) {
                    wp_send_json_error(['message' => 'شما مجاز به حذف این گزارش نیستید.']);
                }

                unset($reports[$index]);
                update_option('neurame_trainer_reports', array_values($reports));
                wp_send_json_success(['message' => 'گزارش با موفقیت حذف شد.']);
            }
        }

        wp_send_json_error(['message' => 'گزارش یافت نشد.']);
    }

    public function ajax_update_trainer_report()
    {
        check_ajax_referer('neurame_trainer_report', 'nonce');

        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        $new_content = sanitize_textarea_field($_POST['report_content'] ?? '');

        if (!$report_id || !$new_content) {
            wp_send_json_error(['message' => 'داده‌های ورودی ناقص هستند.']);
        }

        $reports = get_option('neurame_trainer_reports', []);
        foreach ($reports as &$report) {
            if ($report['id'] === $report_id) {
                // امنیت: بررسی مالکیت
                if (!current_user_can('manage_options') && $report['trainer_id'] !== get_current_user_id()) {
                    wp_send_json_error(['message' => 'شما مجاز به ویرایش این گزارش نیستید.']);
                }

                $report['content'] = $new_content;
                $report['timestamp'] = current_time('mysql');

                // بازنویسی با AI
                $ai_response = $this->call_ai_api("بازنویسی کن: \n" . $new_content, get_option('neurame_settings'));
                $report['ai_content'] = $ai_response['success'] ? $ai_response['data'] : $new_content;

                update_option('neurame_trainer_reports', $reports);
                wp_send_json_success(['message' => 'گزارش ویرایش شد.', 'ai_content' => $report['ai_content']]);
            }
        }

        wp_send_json_error(['message' => 'گزارش مورد نظر یافت نشد.']);
    }

    public function clean_log_files()
    {
        $log_dir = plugin_dir_path(__DIR__) . 'logs/'; // مسیر دقیق پوشه لاگ‌ها

        if (!is_dir($log_dir)) {
            return;
        }

        $files = glob($log_dir . '*.log'); // همه فایل‌های .log

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        Logger::info('🧹 لاگ‌ها پاک‌سازی شدند توسط کرون');
    }

    private function get_child_name($child_id)
    {
        list($user_id, $index) = explode('_', $child_id);
        $children = get_user_meta($user_id, 'neurame_children', true);

        if (isset($children[$index])) {
            return $children[$index]['name'];
        }

        return __('ناشناس', 'neurame-ai-assistant');
    }

}