<?php
if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NeurameAI_Trainer_Report_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'neurame-trainer-report';
    }

    public function get_title()
    {
        return __('گزارش مربی Neurame', 'neurame-ai-assistant');
    }

    public function get_icon()
    {
        return 'eicon-post';
    }

    public function get_categories()
    {
        return ['neurame'];
    }

    protected function _register_controls()
    {
        $this->start_controls_section('content_section', [
            'label' => __('محتوا', 'neurame-ai-assistant'),
            'tab' => Controls_Manager::TAB_CONTENT
        ]);
        $this->add_control('title', [
            'label' => __('عنوان فرم', 'neurame-ai-assistant'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ارسال گزارش مربی', 'neurame-ai-assistant')
        ]);
        $this->end_controls_section();
    }

    protected function render()
    {
        if (!is_user_logged_in()) {
            echo '<p class="text-gray-600">' . esc_html__('لطفاً وارد شوید تا بتوانید گزارش ثبت کنید.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $current_user = wp_get_current_user();

        $parent_mode = get_option('neurame_settings')['neurame_parent_mode'] ?? 0;

        ?>
        <div class="neurame-trainer-report-form p-6 bg-white rounded-lg shadow-md space-y-4">
            <h2 class="text-2xl font-semibold mb-4"><?php echo esc_html($settings['title']); ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="neurame_trainer_report">
                <?php wp_nonce_field('neurame_trainer_report', 'neurame_nonce'); ?>

                <!-- انتخاب دوره -->
                <div class="space-y-1">
                    <label
                        class="block text-sm font-medium"><?php echo esc_html__('انتخاب دوره', 'neurame-ai-assistant'); ?></label>
                    <select name="course_id" class="w-full p-2 border rounded-lg">
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

                <!-- انتخاب کودک اگر حالت والدین فعال باشد -->
                <?php if ($parent_mode) : ?>
                    <div class="space-y-1 mt-4">
                        <label
                            class="block text-sm font-medium"><?php echo esc_html__('انتخاب کودک', 'neurame-ai-assistant'); ?></label>
                        <select name="child_id" class="w-full p-2 border rounded-lg">
                            <option
                                value=""><?php echo esc_html__('یک کودک انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                            <?php
                            $children = get_user_meta($current_user->ID, 'neurame_children', true);
                            if (is_array($children)) {
                                foreach ($children as $index => $child) {
                                    printf(
                                        '<option value="%d_%d">%s (سن: %d)</option>',
                                        esc_attr($current_user->ID),
                                        esc_attr($index),
                                        esc_html($child['name']),
                                        esc_attr($child['age'])
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- محتوای گزارش -->
                <div class="space-y-1 mt-4">
                    <label
                        class="block text-sm font-medium"><?php echo esc_html__('محتوای گزارش', 'neurame-ai-assistant'); ?></label>
                    <textarea name="report_content" rows="5" class="w-full p-2 border rounded-lg"></textarea>
                </div>

                <!-- دکمه ارسال -->
                <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    <?php echo esc_html__('ثبت گزارش', 'neurame-ai-assistant'); ?>
                </button>
            </form>
        </div>
        <?php
    }
}
