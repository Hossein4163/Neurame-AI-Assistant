<?php
if (!defined('ABSPATH')) {
    exit;
}

class NeurameAIAssistant
{
    // متد لاگ‌گذاری سفارشی
    private function log($message)
    {
        if (!defined('NEURAMEAI_DEBUG_LOG') || !NEURAMEAI_DEBUG_LOG) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/neurame-debug.log';
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] $message\n";

        if (!file_exists($log_file)) {
            if (!is_writable(WP_CONTENT_DIR)) {
                // به جای error_log، می‌توانیم خطا را نادیده بگیریم یا در جای دیگری مدیریت کنیم
                return;
            }
            file_put_contents($log_file, '');
            chmod($log_file, 0644);
        }

        if (is_writable($log_file)) {
            file_put_contents($log_file, $formatted_message, FILE_APPEND);
        }
    }

    public function __construct()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NeurameAIAssistant: Class initialized');
        }

        $this->log('NeurameAIAssistant: Class initialized');

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
        add_action('wp_ajax_neurame_load_courses', [$this, 'ajax_load_courses']);
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

        // 🔥 اضافه شده برای بروزرسانی روند پیشرفت فرزند بعد از ثبت گزارش مربی
        add_action('save_post_trainer_report', [$this, 'update_child_progress_on_report'], 10, 2);

        add_action('wp_ajax_neurame_fetch_parent_info', [$this, 'handle_fetch_parent_info']); // اضافه کردن اکشن جدید

        // اضافه کردن اکشن‌های جدید برای گزارش‌ها و گزارش هوشمند
        add_action('wp_ajax_neurame_get_reports', [$this, 'ajax_get_reports']);
        add_action('wp_ajax_neurame_get_progress_report', [$this, 'ajax_get_progress_report']);
    }

    public function register_shortcodes()
    {
        $settings = get_option('neurame_settings');
        $parent_mode = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'];

        add_shortcode('neurame_profile', [$this, 'render_profile']);
        if ($parent_mode)
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

    public function ajax_load_courses()
    {
        // بررسی nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_load_courses')) {
            $this->log('❌ ajax_load_courses: Invalid nonce');
            wp_send_json_error(['message' => __('خطای امنیتی.', 'neurame-ai-assistant')], 403);
        }

        $trainer_id = isset($_POST['trainer_id']) ? absint($_POST['trainer_id']) : 0;
        if (!$trainer_id) {
            $this->log('❌ ajax_load_courses: Invalid trainer ID');
            wp_send_json_error(['message' => __('شناسه مربی نامعتبر است.', 'neurame-ai-assistant')], 400);
        }

        // دریافت دوره‌های مرتبط با مربی (فرض می‌کنیم دوره‌ها در ووکامرس ذخیره شده‌اند)
        $courses = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'type' => ['simple', 'variable'],
            // اگر مربی به دوره‌ها مرتبط است، باید فیلتر اضافه شود (مثلاً با متادیتا)
            'meta_query' => [
                [
                    'key' => 'neurame_trainer_id',
                    'value' => $trainer_id,
                    'compare' => '='
                ]
            ]
        ]);

        $course_list = [];
        foreach ($courses as $course) {
            $course_list[] = [
                'id' => $course->get_id(),
                'name' => $course->get_name()
            ];
        }

        if (empty($course_list)) {
            $this->log('❌ ajax_load_courses: No courses found for trainer ' . $trainer_id);
            wp_send_json_error(['message' => __('هیچ دوره‌ای برای این مربی یافت نشد.', 'neurame-ai-assistant')], 404);
        }

        $this->log('✅ ajax_load_courses: Loaded ' . count($course_list) . ' courses');
        wp_send_json_success(['courses' => $course_list]);
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
        $settings = get_option('neurame_settings');
        $parent_mode = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'];

        ob_start();
        ?>
        <div class="neurame-child-progress space-y-6 mt-6">
            <?php if ($parent_mode): ?>
                <?php
                $children = get_user_meta($user_id, 'neurame_children', true);
                $children = is_array($children) ? $children : [];
                if (empty($children)) {
                    return '<p>' . esc_html__('هیچ کودکی ثبت نشده است.', 'neurame-ai-assistant') . ' <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" class="text-blue-600 hover:underline">' . esc_html__('اینجا کلیک کنید تا کودک خود را ثبت کنید.', 'neurame-ai-assistant') . '</a></p>';
                }
                foreach ($children as $index => $child):
                    ?>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold mb-2"><?php echo esc_html($child['name']); ?></h3>
                        <p><?php echo esc_html__('سن: ', 'neurame-ai-assistant') . esc_html($child['age']); ?></p>
                        <p><?php echo esc_html__('علاقه‌مندی‌ها: ', 'neurame-ai-assistant') . esc_html($child['interests']); ?></p>
                        <?php
                        $progress = get_user_meta($user_id, 'child_progress_analysis_' . ($user_id . '_' . $index), true);
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
            <?php else: ?>
                <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-2"><?php echo esc_html(get_userdata($user_id)->display_name); ?></h3>
                    <?php
                    $progress = get_user_meta($user_id, 'user_progress_analysis', true);
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
                        echo '<p class="text-gray-500">' . esc_html__('روند پیشرفتی برای شما ثبت نشده است.', 'neurame-ai-assistant') . '</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
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
            wp_enqueue_style('neurame-frontend', NEURAMEAI_PLUGIN_URL . 'assets/css/neurame-styles.css', [], '1.2.0');
            wp_enqueue_script('neurame-child', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-child.js', ['jquery'], '1.2.0', true);
            wp_enqueue_script('neurame-report', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-report.js', ['jquery'], '1.2.0', true);

            $settings = get_option('neurame_settings', []);
            $neurame_vars = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce_load_buyers' => wp_create_nonce('neurame_load_buyers'),
                'nonce_get_children' => wp_create_nonce('neurame_get_children'),
                'nonce_trainer_report' => wp_create_nonce('neurame_trainer_report'),
                'nonce_load_courses' => wp_create_nonce('neurame_load_courses'),
                'ai_nonce' => wp_create_nonce('neurame_ai_recommendation'),
                'nonce_get_reports' => wp_create_nonce('neurame_get_reports'),
                'nonce_fetch_parent_info' => wp_create_nonce('neurame_fetch_parent_info'),
                'user_id' => get_current_user_id(),
                'is_admin' => current_user_can('manage_options'),
                'is_parent_mode' => !empty($settings['neurame_parent_mode']),
                'i18n' => [
                    'select_course' => __('یک دوره انتخاب کنید', 'neurame-ai-assistant')
                ]
            ];

            wp_localize_script('neurame-child', 'neurame_vars', $neurame_vars);
            wp_localize_script('neurame-report', 'neurame_vars', $neurame_vars);
        }
    }

    public function admin_enqueue_scripts($hook)
    {
        if (is_admin()) {
            wp_enqueue_style('neurame-admin', NEURAMEAI_PLUGIN_URL . 'assets/css/neurame-styles.min.css', [], '1.2.0');
            wp_enqueue_script('neurame-child', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-child.js', ['jquery'], '1.2.0', true);
            wp_enqueue_script('neurame-report', NEURAMEAI_PLUGIN_URL . 'assets/js/neurame-report.js', ['jquery'], '1.2.0', true);

            $neurame_vars = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce_load_buyers' => wp_create_nonce('neurame_load_buyers'), // اضافه کردن nonce
                'nonce_get_children' => wp_create_nonce('neurame_get_children'),
                'nonce_trainer_report' => wp_create_nonce('neurame_trainer_report'),
                'ai_nonce' => wp_create_nonce('neurame_ai_recommendation'),
                'nonce_get_reports' => wp_create_nonce('neurame_get_reports'),
                'nonce_fetch_parent_info' => wp_create_nonce('neurame_fetch_parent_info'),
                'nonce_save_parent_info' => wp_create_nonce('neurame_save_parent_info'),
                'user_id' => get_current_user_id(),
                'is_admin' => true,
            ];

            wp_localize_script('neurame-child', 'neurame_vars', $neurame_vars);
            wp_localize_script('neurame-report', 'neurame_vars', $neurame_vars);

            wp_add_inline_script('neurame-report', 'console.log("Neurame Vars Loaded (admin):", ' . wp_json_encode($neurame_vars) . ');');
        }
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_ai_recommendation')) {
            $this->log('❌ handle_fetch_ai_recommendation: Invalid nonce');
            wp_send_json_error(['message' => __('خطای امنیتی.', 'neurame-ai-assistant')], 403);
        }

        $user_id = absint($_POST['user_id'] ?? get_current_user_id());
        $parent_goals = sanitize_textarea_field($_POST['parent_goals'] ?? '');
        $settings = get_option('neurame_settings', []);
        $parent_mode = !empty($settings['neurame_parent_mode']);

        if (!$user_id || empty($parent_goals)) {
            $this->log('❌ handle_fetch_ai_recommendation: Missing required fields');
            wp_send_json_error(['message' => __('لطفاً همه‌ی فیلدها را پر کنید.', 'neurame-ai-assistant')], 400);
        }

        $data = ['parent_goals' => $parent_goals];

        if ($parent_mode) {
            $child_id = sanitize_text_field($_POST['child_id'] ?? '');
            if (!$child_id) {
                $this->log('❌ handle_fetch_ai_recommendation: Missing child ID');
                wp_send_json_error(['message' => __('شناسه کودک نامعتبر است.', 'neurame-ai-assistant')], 400);
            }

            list($child_user_id, $child_index) = array_map('absint', explode('_', $child_id));
            $children = get_user_meta($child_user_id, 'neurame_children', true);
            if (!is_array($children) || !isset($children[$child_index])) {
                $this->log('❌ handle_fetch_ai_recommendation: Invalid child data');
                wp_send_json_error(['message' => __('اطلاعات کودک یافت نشد.', 'neurame-ai-assistant')], 404);
            }

            $child = $children[$child_index];
            $data['child_age'] = $child['age'] ?? 0;
            $data['child_interests'] = $child['interests'] ?? '';
            $data['child_name'] = $child['name'] ?? '';
        } else {
            $user = get_userdata($user_id);
            $data['user_name'] = $user ? $user->display_name : __('کاربر ناشناس', 'neurame-ai-assistant');
            $data['user_interests'] = get_user_meta($user_id, 'neurame_user_interests', true) ?: __('علایق عمومی', 'neurame-ai-assistant');
        }

        try {
            $ai_response = $this->fetch_ai_recommendation($data);
            if (empty($ai_response['success'])) {
                $this->log('❌ AI Response Error: ' . json_encode($ai_response));
                wp_send_json_error(['message' => $ai_response['message'] ?? __('خطا در پاسخ هوش مصنوعی.', 'neurame-ai-assistant')], 500);
            }

            $html = $this->render_recommended_courses($ai_response['data']);
            wp_send_json_success(['html' => $html]);
        } catch (\Throwable $e) {
            $this->log('❌ AI Recommendation Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => __('خطای داخلی رخ داد.', 'neurame-ai-assistant')], 500);
        }
    }

    public function render_recommended_courses($courses)
    {
        if (empty($courses)) {
            return '<p class="text-gray-600">' . esc_html__('هیچ دوره‌ای پیشنهاد نشد.', 'neurame-ai-assistant') . '</p>';
        }

        ob_start();
        ?>
        <div class="neurame-recommended-courses space-y-4">
            <?php foreach ($courses as $course): ?>
                <div class="bg-gray-100 p-4 rounded-lg">
                    <h4 class="font-semibold"><?php echo esc_html($course['course_name']); ?></h4>
                    <a href="<?php echo esc_url($course['course_url']); ?>" class="text-blue-600 hover:underline">
                        <?php esc_html_e('مشاهده دوره', 'neurame-ai-assistant'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function fetch_ai_recommendation($data)
    {
        if (ob_get_length()) {
            ob_clean();
        }

        $settings = get_option('neurame_settings', []);
        $api_type = $settings['neurame_api_type'] ?? 'none';
        $parent_mode = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'];

        if ($api_type === 'none') {
            $this->log('❌ fetch_ai_recommendation: No AI API selected');
            return $this->send_json_response(false, __('هیچ API هوش مصنوعی انتخاب نشده است.', 'neurame-ai-assistant'));
        }

        $api_key = $api_type === 'chatgpt' ? ($settings['neurame_chatgpt_api_key'] ?? '') : ($settings['neurame_gemini_api_key'] ?? '');
        if (empty($api_key)) {
            $this->log('❌ fetch_ai_recommendation: Missing API key for ' . $api_type);
            return $this->send_json_response(false, __('کلید API برای ' . $api_type . ' تنظیم نشده است.', 'neurame-ai-assistant'));
        }

        $courses = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'type' => ['simple', 'variable'],
        ]);
        $course_list = [];
        foreach ($courses as $course) {
            $course_id = $course->get_id();
            $permalink = get_permalink($course_id) ?: '';
            $course_list[] = [
                'course_id' => (string)$course_id,
                'course_name' => $course->get_name(),
                'course_url' => $permalink
            ];
        }

        if (empty($course_list)) {
            $this->log('❌ fetch_ai_recommendation: No published courses found');
            return $this->send_json_response(false, __('هیچ دوره‌ای در سیستم یافت نشد.', 'neurame-ai-assistant'));
        }

        $course_list_text = implode("\n", array_map(function ($course) {
            return "- ID: {$course['course_id']}, نام: {$course['course_name']}, URL: {$course['course_url']}";
        }, $course_list));

        if ($parent_mode) {
            $prompt = sprintf(
                "برای کودکی با سن %d سال، علاقه‌مندی‌های '%s' و اهداف والدین '%s'، دوره‌های آموزشی مناسب را پیشنهاد دهید. " .
                "فقط از دوره‌های زیر انتخاب کنید:\n%s\n\n" .
                "پاسخ را به صورت JSON معتبر با فرمت زیر ارائه دهید:\n" .
                "{\n  \"courses\": [\n    {\"course_id\": \"\", \"course_name\": \"\", \"course_url\": \"\"},\n    ...\n  ]\n}",
                $data['child_age'],
                $data['child_interests'],
                $data['parent_goals'],
                $course_list_text
            );
            if (!empty($data['child_name'])) {
                $prompt .= "\n\nنام کودک: " . $data['child_name'];
            }
        } else {
            $prompt = sprintf(
                "برای کاربری با علاقه‌مندی‌های '%s' و اهداف '%s'، دوره‌های آموزشی مناسب را پیشنهاد دهید. " .
                "فقط از دوره‌های زیر انتخاب کنید:\n%s\n\n" .
                "پاسخ را به صورت JSON معتبر با فرمت زیر ارائه دهید:\n" .
                "{\n  \"courses\": [\n    {\"course_id\": \"\", \"course_name\": \"\", \"course_url\": \"\"},\n    ...\n  ]\n}",
                $data['user_interests'],
                $data['parent_goals'],
                $course_list_text
            );
            if (!empty($data['user_name'])) {
                $prompt .= "\n\nنام کاربر: " . $data['user_name'];
            }
        }

        $this->log('📝 AI Prompt: ' . substr($prompt, 0, 200));

        try {
            $response = $this->call_ai_api($prompt, $settings);
            if (empty($response['success'])) {
                $this->log('❌ AI API Error: ' . json_encode($response));
                return $this->send_json_response(false, __('خطا در پاسخ API: ', 'neurame-ai-assistant') . ($response['data'] ?? 'خطای ناشناخته'));
            }

            $cleaned_response = preg_replace('/^```json\s*|\s*```$|^```/', '', $response['data']);
            $cleaned_response = trim($cleaned_response);

            $json = json_decode($cleaned_response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !$json || !isset($json['courses']) || !is_array($json['courses'])) {
                $this->log('❌ Invalid JSON Format: ' . json_last_error_msg());
                return $this->send_json_response(false, __('پاسخ API در فرمت JSON معتبر نیست: ', 'neurame-ai-assistant') . json_last_error_msg());
            }

            $valid_courses = [];
            $course_map = array_column($course_list, null, 'course_id');
            foreach ($json['courses'] as $item) {
                if (!isset($item['course_id']) || !isset($course_map[$item['course_id']])) {
                    continue;
                }
                $course_data = $course_map[$item['course_id']];
                $valid_courses[] = [
                    'course_id' => $item['course_id'],
                    'course_name' => $item['course_name'] ?: $course_data['course_name'],
                    'course_url' => $course_data['course_url']
                ];
            }

            if (empty($valid_courses)) {
                $this->log('❌ No Valid Courses Found in AI Response');
                return $this->send_json_response(false, __('هیچ دوره معتبری پیشنهاد نشد.', 'neurame-ai-assistant'));
            }

            return $this->send_json_response(true, $valid_courses);
        } catch (\Throwable $e) {
            $this->log('❌ fetch_ai_recommendation Exception: ' . $e->getMessage());
            return $this->send_json_response(false, __('خطای داخلی در پردازش API: ', 'neurame-ai-assistant') . $e->getMessage());
        }
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
        $this->log('📤 AJAX Response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

        // خروجی JSON و توقف اسکریپت
        echo wp_json_encode($response);
        wp_die();
    }

    public function handle_fetch_parent_info()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_fetch_parent_info')) {
            $this->log('❌ handle_fetch_parent_info: Invalid nonce');
            wp_send_json_error(__('خطای امنیتی.', 'neurame-ai-assistant'));
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id || $user_id !== get_current_user_id()) {
            $this->log('❌ handle_fetch_parent_info: Unauthorized access');
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
        $this->log('📦 Raw AI Response: ' . $resp_body);

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
        if ($added) {
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

        $settings = get_option('neurame_settings', []);
        $parent_mode = !empty($settings['neurame_parent_mode']);
        $dashboard_title = $parent_mode ? __('داشبورد هوشمند والدین', 'neurame-ai-assistant') : __('داشبورد هوشمند کاربر', 'neurame-ai-assistant');

        echo '<div class="neurame-combined-dashboard grid gap-8 lg:grid-cols-3 p-12">';
        echo '<h2 class="text-2xl font-bold mb-6">' . esc_html($dashboard_title) . '</h2>';

        echo '<div class="lg:col-span-2 space-y-8">';
        $blocks = [
            ['title' => '', 'content' => $this->render_profile()],
            ['title' => '', 'content' => $this->render_smart_assistant()],
        ];
        if ($parent_mode) {
            $blocks[] = ['title' => '', 'content' => $this->render_children_management()];
        }

        foreach ($blocks as $block) {
            echo '<section class="bg-white rounded-lg p-12">';
            echo $block['content'];
            echo '</section>';
        }
        echo '</div>';

        echo '<div class="lg:col-span-1 space-y-8">';

        // بخش انتخاب
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('انتخاب:', 'neurame-ai-assistant') . '</h3>';
        if ($parent_mode) {
            $children = get_user_meta(get_current_user_id(), 'neurame_children', true) ?: [];
            ?>
            <select name="report_child_select" id="report-child-select" class="w-full p-2 border rounded">
                <option value=""><?php echo esc_html__('یک کودک انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                <?php foreach ($children as $index => $child): ?>
                    <option value="<?php echo esc_attr(get_current_user_id() . '_' . $index); ?>">
                        <?php echo esc_html($child['name'] . ' (سن: ' . $child['age'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        } else {
            $user = wp_get_current_user();
            echo '<p class="text-gray-600">' . esc_html__('کاربر: ' . $user->display_name, 'neurame-ai-assistant') . '</p>';
        }
        echo '</div>';

        // بخش گزارش‌ها
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('گزارش‌ها', 'neurame-ai-assistant') . '</h3>';
        echo '<div id="reports-list" class="reports-list">';
        echo '<p class="text-gray-600">' . esc_html__('گزارش‌های شما نمایش داده می‌شوند.', 'neurame-ai-assistant') . '</p>';
        echo '</div>';
        echo '</div>';

        // بخش تحلیل پیشرفت
        echo '<div class="bg-white rounded-lg p-6">';
        echo '<h3 class="text-lg font-semibold mb-4">' . esc_html__('تحلیل پیشرفت', 'neurame-ai-assistant') . '</h3>';
        echo '<div id="progress-report" class="progress-report">';
        echo '<p class="text-gray-600">' . esc_html__('تحلیل پیشرفت شما نمایش داده می‌شود.', 'neurame-ai-assistant') . '</p>';
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
        $this->log('🚸 render_children_management → loaded children: ' . print_r($children, true));

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
        $settings = get_option('neurame_settings');
        $parent_mode = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'];
        $children = $parent_mode ? get_user_meta($user_id, 'neurame_children', true) : [];
        $children = is_array($children) ? $children : [];
        $parent_goals = get_user_meta($user_id, 'neurame_parent_goals', true);

        ob_start();
        ?>
        <div class="neurame-smart-assistant space-y-6">
            <h2 class="text-2xl font-semibold"><?php echo esc_html__('دستیار انتخاب دوره', 'neurame-ai-assistant'); ?></h2>

            <form id="neurame-info-form" class="space-y-4">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('neurame_nonce'); ?>">

                <?php if ($parent_mode && empty($children)): ?>
                    <p><?php esc_html_e('هیچ فرزندی ثبت نشده است. لطفاً ابتدا فرزندان خود را اضافه کنید.', 'neurame-ai-assistant'); ?></p>
                <?php else: ?>
                    <?php if ($parent_mode): ?>
                        <div>
                            <label for="child_select"
                                   class="block mb-1 text-sm font-medium"><?php esc_html_e('انتخاب فرزند', 'neurame-ai-assistant'); ?></label>
                            <br>
                            <select name="child_select" id="child_select" class="w-full p-2 border rounded-lg" required>
                                <option
                                    value=""><?php esc_html_e('یک فرزند انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                                <?php foreach ($children as $index => $child): ?>
                                    <option value="<?php echo esc_attr($user_id . '_' . $index); ?>">
                                        <?php echo esc_html($child['name']); ?>
                                        (سن: <?php echo esc_html($child['age']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

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
                    <p class="text-gray-600"><?php echo esc_html__('اینجا لیست دوره‌هایی که برای شما مناسب هستند نمایش داده می‌شود.', 'neurame-ai-assistant'); ?></p>
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

        $settings = get_option('neurame_settings');
        $parent_mode = isset($settings['neurame_parent_mode']) && $settings['neurame_parent_mode'];

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

        if ($parent_mode) {
            add_submenu_page(
                'neurame-ai-assistant',
                __('Children Management', 'neurame-ai-assistant'),
                __('مدیریت کودکان', 'neurame-ai-assistant'),
                'manage_options',
                'neurame-children-management',
                [$this, 'render_children_management_admin']
            );
        }

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

        $parent_mode = get_option('neurameai_parent_mode', 0);
        ?>
        <div class="wrap">
            <h1 class="text-3xl font-bold mb-6"><?php echo esc_html__('گزارش‌های مربی', 'neurame-ai-assistant'); ?></h1>
            <?php
            // لود قالب فرم
            include NEURAMEAI_PLUGIN_DIR . 'partials/trainer-reports-template.php';
            ?>

            <!-- نمایش گزارش‌های موجود -->
            <?php
            $reports = $this->get_trainer_reports();
            if (!empty($reports)) {
                echo '<h2 class="text-2xl font-bold mt-8 mb-4">' . esc_html__('گزارش‌های موجود', 'neurame-ai-assistant') . '</h2>';
                echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>مربی</th><th>دوره</th><th>کاربر</th>';
                if ($parent_mode) echo '<th>کودک</th>';
                echo '<th>گزارش</th><th>بازنویسی‌شده توسط هوش مصنوعی</th></tr></thead><tbody>';
                foreach ($reports as $report) {
                    $trainer = get_user_by('id', $report['trainer_id']);
                    $course = wc_get_product($report['course_id']);
                    $user = get_user_by('id', $report['user_id']);
                    $child_name = $parent_mode && !empty($report['child_id']) ? $this->get_child_name($report['child_id']) : '';
                    printf(
                        '<tr><td>%s</td><td>%s</td><td>%s</td>',
                        esc_html($trainer ? $trainer->display_name : 'ناشناس'),
                        esc_html($course ? $course->get_name() : 'ناشناس'),
                        esc_html($user ? $user->display_name : 'ناشناس')
                    );
                    if ($parent_mode) printf('<td>%s</td>', esc_html($child_name));
                    printf('<td>%s</td><td>%s</td></tr>', esc_html($report['content']), esc_html($report['ai_content'] ?? $report['content']));
                }
                echo '</tbody></table>';
            }
            ?>
        </div>
        <?php
    }

    // 📌 به‌روزرسانی پیشرفت کودک بعد از ذخیره گزارش
    public function update_child_progress_on_report($user_id, $child_id, $report_content)
    {
        $skills = $this->analyze_child_skills($report_content);
        $summary = $this->generate_ai_summary($skills);

        update_user_meta($user_id, 'child_progress_analysis_' . $child_id, [
            'skills' => $skills,
            'ai_summary' => $summary,
            'last_updated' => current_time('mysql'),
        ]);
    }

    public function update_user_progress_on_report($user_id, $report_content)
    {
        $skills = $this->analyze_user_skills($report_content);
        $summary = $this->generate_ai_summary($skills);

        update_user_meta($user_id, 'user_progress_analysis', [
            'skills' => $skills,
            'ai_summary' => $summary,
            'last_updated' => current_time('mysql'),
        ]);
    }

    private function analyze_user_skills($report_content)
    {
        // مشابه analyze_child_skills اما برای کاربر
        return [
            'focus' => rand(50, 90),
            'logic' => rand(40, 80),
            'problem_solving' => rand(60, 95),
            'communication' => rand(50, 85),
            'creativity' => rand(45, 75),
        ];
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
        $this->log('📝 AI Prompt for Headings: ' . substr($prompt, 0, 200));

        // صدا زدن API
        $response = $this->call_ai_api($prompt, $settings);

        if (!$response['success']) {
            $this->log('❌ AI Response Failed: ' . ($response['data'] ?? 'No data'));
            wp_die(esc_html__('خطا در تولید سرفصل‌ها: ', 'neurame-ai-assistant') . esc_html($response['data']));
        }

        // لاگ‌گذاری پاسخ خام
        $this->log('📬 AI Raw Response for Headings: ' . substr($response['data'], 0, 200));

        // حذف Markdown اضافی (مثل ```json یا ```)
        $cleaned_response = $response['data'];
        $cleaned_response = preg_replace('/^```json\s*|\s*```$|^```/', '', $cleaned_response); // حذف ```json یا ``` از ابتدا و انتها
        $cleaned_response = trim($cleaned_response); // حذف فضاهای خالی ابتدا و انتها

        // لاگ‌گذاری پاسخ پاک‌شده
        $this->log('🧹 Cleaned AI Response for Headings: ' . substr($cleaned_response, 0, 200));

        // اگر پاسخ به صورت JSON بود، به متن تبدیلش کن
        $json = json_decode($cleaned_response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['headings']) && is_array($json['headings'])) {
            // اگر پاسخ JSON بود و کلید headings داشت
            $cleaned_response = implode("\n", array_map(function ($heading) {
                return "- " . trim($heading);
            }, $json['headings']));
            $this->log('🔄 Converted JSON to Text: ' . substr($cleaned_response, 0, 200));
        } elseif (json_last_error() === JSON_ERROR_NONE && isset($json['content'])) {
            // اگر پاسخ JSON بود و کلید content داشت
            $cleaned_response = $json['content'];
            $this->log('🔄 Converted JSON content to Text: ' . substr($cleaned_response, 0, 200));
        }

        // ذخیره سرفصل‌ها
        update_post_meta($course_id, '_neurame_ai_headings', sanitize_textarea_field($cleaned_response));

        $this->log('✅ Headings Saved for Course ID ' . $course_id);

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
            $this->log('❌ ajax_load_buyers: Invalid course_id');
            wp_send_json_error(['message' => 'دوره انتخاب نشده است.']);
        }

        $this->log('📦 ajax_load_buyers: Loading buyers for course_id=' . $course_id);

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
            $this->log('⚠️ ajax_load_buyers: No orders found for course_id=' . $course_id);
            wp_send_json_success([]);
        }

        // لود سفارش‌ها با وضعیت completed یا processing
        $orders = wc_get_orders([
            'limit' => 50, // محدود کردن برای بهبود عملکرد
            'status' => ['completed', 'processing'],
            'post__in' => $order_ids, // فقط سفارش‌های مرتبط با محصول
        ]);

        $buyers = [];
        foreach ($orders as $order) {
            $user = $order->get_user();
            if (!$user) {
                continue; // رد کردن سفارش‌های بدون کاربر (مثلاً مهمان)
            }

            // فقط کاربرانی که نقش trainer ندارن
            if (!in_array('trainer', (array)$user->roles)) {
                $buyers[$user->ID] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                ];
            }
        }

        if (empty($buyers)) {
            $this->log('⚠️ ajax_load_buyers: No buyers found for course_id=' . $course_id);
            wp_send_json_success([]);
        }

        $this->log('✅ ajax_load_buyers: Found ' . count($buyers) . ' buyers');
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
        // بررسی nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_get_reports')) {
            $this->log('❌ ajax_get_progress_report: Invalid nonce');
            wp_send_json_error(['message' => __('خطای امنیتی.', 'neurame-ai-assistant')], 403);
        }

        $settings = get_option('neurame_settings', []);
        $parent_mode = !empty($settings['neurame_parent_mode']);
        $user_id = get_current_user_id();

        if ($parent_mode) {
            $child_id = sanitize_text_field($_POST['child_id'] ?? '');
            if (!$child_id) {
                $this->log('❌ ajax_get_progress_report: Missing child ID');
                wp_send_json_error(['message' => __('شناسه کودک نامعتبر است.', 'neurame-ai-assistant')], 400);
            }
        }

        // دریافت گزارش‌ها
        $reports = get_option('neurame_trainer_reports', []);
        if (!is_array($reports)) {
            $reports = [];
        }

        $filtered_reports = array_filter($reports, function ($report) use ($user_id, $child_id, $parent_mode) {
            if ($parent_mode) {
                return isset($report['child_id']) && $report['child_id'] === $child_id;
            }
            return isset($report['user_id']) && $report['user_id'] === $user_id;
        });

        if (empty($filtered_reports)) {
            $this->log('❌ ajax_get_progress_report: No reports found');
            wp_send_json_success([
                'html' => '<p class="text-gray-600">' . esc_html__('هیچ گزارشی ثبت نشده است.', 'neurame-ai-assistant') . '</p>'
            ]);
        }

        $report_contents = array_map(function ($report) {
            return $report['ai_content'] ?? $report['content'];
        }, $filtered_reports);

        $combined_text = implode("\n---\n", $report_contents);

        // پرامپت برای تحلیل مهارت‌ها
        $prompt = <<<EOD
شما یک تحلیل‌گر آموزشی هستید.
با توجه به گزارش‌های زیر:
1. میزان مهارت‌های کاربر را برای این دسته‌ها بین 0 تا 100 بده: تمرکز، منطق، حل مسئله، مکالمه، خلاقیت.
2. یک خلاصه تحلیلی 2 تا 3 جمله‌ای از نقاط قوت و ضعف کاربر بنویس.
خروجی را فقط در قالب JSON به فرمت زیر بده:
{
  "labels": ["تمرکز", "منطق", "حل مسئله", "مکالمه", "خلاقیت"],
  "values": [75, 80, 65, 70, 90],
  "summary": "کاربر در تمرکز و منطق عملکرد خوبی دارد اما در مکالمه نیاز به تمرین بیشتر دارد."
}
متن گزارش‌ها:
$combined_text
EOD;

        $response = $this->call_ai_api($prompt, $settings);
        if (empty($response['success'])) {
            $this->log('❌ ajax_get_progress_report: AI analysis failed');
            wp_send_json_error(['message' => __('خطا در تحلیل مهارت‌ها.', 'neurame-ai-assistant')], 500);
        }

        $cleaned_response = preg_replace('/^```json\s*|\s*```$/', '', $response['data']);
        $cleaned_response = trim($cleaned_response);
        $json_data = json_decode($cleaned_response, true);

        if (!is_array($json_data) || empty($json_data['labels']) || empty($json_data['values'])) {
            $this->log('❌ ajax_get_progress_report: Invalid AI response');
            wp_send_json_error(['message' => __('پاسخ هوش مصنوعی معتبر نیست.', 'neurame-ai-assistant')], 500);
        }

        // تولید HTML برای نمایش
        ob_start();
        ?>
        <div class="space-y-4">
            <canvas id="progress-chart-<?php echo esc_attr($parent_mode ? $child_id : $user_id); ?>"
                    height="250"></canvas>
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
        // بررسی nonce برای امنیت
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'neurame_trainer_report')) {
            $this->log('❌ ajax_save_trainer_report: Invalid nonce');
            wp_send_json_error(['message' => __('خطای امنیتی.', 'neurame-ai-assistant')], 403);
        }

        // دریافت و پاک‌سازی داده‌های ورودی
        $trainer_id = isset($_POST['trainer_id']) ? absint($_POST['trainer_id']) : 0;
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $child_id = isset($_POST['child_id']) ? sanitize_text_field($_POST['child_id']) : '';
        $report_content = isset($_POST['report_content']) ? sanitize_textarea_field($_POST['report_content']) : '';

        // دریافت تنظیمات افزونه
        $settings = get_option('neurame_settings', []);
        $parent_mode = !empty($settings['neurame_parent_mode']);

        // اعتبارسنجی داده‌ها
        if (!$trainer_id || !$course_id || ($parent_mode && (!$user_id || !$child_id)) || !$report_content) {
            $this->log('❌ ajax_save_trainer_report: Missing required fields');
            wp_send_json_error(['message' => __('اطلاعات ناقص است.', 'neurame-ai-assistant')], 400);
        }

        // دریافت اطلاعات دوره
        $course = wc_get_product($course_id);
        $course_name = $course ? $course->get_name() : __('دوره ناشناس', 'neurame-ai-assistant');
        $this->log('📚 Course Name Loaded: ' . $course_name);

        // آماده‌سازی پرامپت برای بازنویسی گزارش با هوش مصنوعی
        $ai_content = $report_content;
        $api_type = $settings['neurame_api_type'] ?? 'none';
        if ($api_type !== 'none') {
            $prompt = "این گزارش مربی را به صورت حرفه‌ای و خلاصه به زبان فارسی بازنویسی کنید:\n\n" .
                "دوره: " . $course_name . "\n" .
                "گزارش: " . $report_content;

            if ($parent_mode && !empty($child_id)) {
                list($child_user_id, $child_index) = explode('_', $child_id);
                $children = get_user_meta($child_user_id, 'neurame_children', true);
                if (is_array($children) && isset($children[$child_index])) {
                    $child = $children[$child_index];
                    $prompt .= "\n\nبرای کودک: " . $child['name'] . "، سن " . $child['age'] . "، علاقه‌مندی‌ها: " . $child['interests'];
                }
            } else {
                $user = get_userdata($user_id);
                $prompt .= "\n\nبرای کاربر: " . ($user ? $user->display_name : __('کاربر ناشناس', 'neurame-ai-assistant'));
            }

            $prompt .= "\n\nپاسخ را فقط به صورت متن ساده و بدون فرمت Markdown ارائه دهید.";
            $this->log('📝 AI Prompt for Report Rewrite: ' . substr($prompt, 0, 200));

            // فراخوانی API هوش مصنوعی
            $response = $this->call_ai_api($prompt, $settings);
            if ($response['success']) {
                $ai_content = trim($response['data']);
                $this->log('✅ AI Rewritten Content: ' . substr($ai_content, 0, 200));
            } else {
                $this->log('❌ AI Rewrite Failed: ' . ($response['data'] ?? 'No data'));
            }
        }

        // ذخیره گزارش
        $reports = get_option('neurame_trainer_reports', []);
        if (!is_array($reports)) {
            $reports = [];
        }

        $report_data = [
            'trainer_id' => $trainer_id,
            'course_id' => $course_id,
            'user_id' => $user_id,
            'content' => $report_content,
            'ai_content' => $ai_content,
            'timestamp' => current_time('mysql')
        ];
        if ($parent_mode && !empty($child_id)) {
            $report_data['child_id'] = $child_id;
        }

        $reports[] = $report_data;
        update_option('neurame_trainer_reports', $reports);

        // به‌روزرسانی پیشرفت
        if ($parent_mode && !empty($child_id)) {
            $this->update_child_progress_on_report($user_id, $child_id, $ai_content);
        } else {
            $this->update_user_progress_on_report($user_id, $ai_content);
        }

        $this->log('✅ Report saved successfully');
        wp_send_json_success([
            'message' => __('گزارش با موفقیت ذخیره شد.', 'neurame-ai-assistant'),
            'ai_content' => $ai_content
        ]);
    }
}