<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class NeurameAI_Child_Progress_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'neurame_child_progress';
    }

    public function get_title()
    {
        return __('روند پیشرفت کودک', 'neurame-ai-assistant');
    }

    public function get_icon()
    {
        return 'eicon-user-circle';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('تنظیمات', 'neurame-ai-assistant'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'child_id',
            [
                'label' => __('آیدی کودک', 'neurame-ai-assistant'),
                'type' => Controls_Manager::TEXT,
                'input_type' => 'text',
                'placeholder' => __('مثال: 123', 'neurame-ai-assistant'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $child_id = !empty($settings['child_id']) ? absint($settings['child_id']) : 0;

        if (!$child_id) {
            echo '<p>' . esc_html__('آیدی کودک وارد نشده است.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        $progress_data = get_user_meta($child_id, 'child_progress_analysis', true);

        if (empty($progress_data)) {
            echo '<p>' . esc_html__('هیچ اطلاعاتی از روند پیشرفت برای این کودک موجود نیست.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        // استایل اولیه
        ?>
        <div class="neurame-child-progress-card p-6 bg-white rounded-lg shadow-md space-y-4">
            <h2 class="text-2xl font-bold mb-4"><?php echo esc_html__('روند پیشرفت کودک', 'neurame-ai-assistant'); ?></h2>

            <!-- خلاصه عملکرد از هوش مصنوعی -->
            <?php if (!empty($progress_data['ai_summary'])) : ?>
                <div class="bg-blue-50 p-4 rounded-lg text-blue-700">
                    <p><?php echo esc_html($progress_data['ai_summary']); ?></p>
                </div>
            <?php endif; ?>

            <!-- نمایش مهارت‌ها -->
            <?php if (!empty($progress_data['skills']) && is_array($progress_data['skills'])) : ?>
                <div class="space-y-4">
                    <?php foreach ($progress_data['skills'] as $skill => $value) : ?>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span
                                    class="text-sm font-medium text-gray-700"><?php echo $this->get_skill_label($skill); ?></span>
                                <span class="text-sm font-medium text-gray-700"><?php echo intval($value); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full"
                                     style="width: <?php echo intval($value); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_skill_label($skill_key)
    {
        $labels = [
            'problem_solving' => __('حل مسئله', 'neurame-ai-assistant'),
            'teamwork' => __('کار تیمی', 'neurame-ai-assistant'),
            'creativity' => __('خلاقیت', 'neurame-ai-assistant'),
            'logical_thinking' => __('تفکر منطقی', 'neurame-ai-assistant'),
            'communication' => __('مهارت ارتباطی', 'neurame-ai-assistant'),
        ];

        return $labels[$skill_key] ?? ucfirst(str_replace('_', ' ', $skill_key));
    }
}
