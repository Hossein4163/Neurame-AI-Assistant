<?php

namespace Neurame\Widgets;

if (!defined('ABSPATH')) exit;

class RecommendedCoursesWidget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'neurame-recommended-courses';
    }

    public function get_title()
    {
        return __('دوره‌های پیشنهادی Neurame AI', 'neurame-ai-assistant');
    }

    public function get_icon()
    {
        return 'eicon-product-related';
    }

    public function get_categories()
    {
        return ['neurame'];
    }

    protected function _register_controls()
    {
        $this->start_controls_section('content_section', [
            'label' => __('محتوا', 'neurame-ai-assistant'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT
        ]);
        $this->add_control('title', [
            'label' => __('عنوان', 'neurame-ai-assistant'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('دوره‌های پیشنهادی', 'neurame-ai-assistant')
        ]);
        $this->add_control('course_count', [
            'label' => __('تعداد دوره‌ها', 'neurame-ai-assistant'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 3,
            'min' => 1,
            'max' => 10
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_section', [
            'label' => __('استایل', 'neurame-ai-assistant'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE
        ]);
        $this->add_control('title_color', [
            'label' => __('رنگ عنوان', 'neurame-ai-assistant'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#000000',
            'selectors' => ['{{WRAPPER}} .neurame-recommended-courses h2' => 'color: {{VALUE}};']
        ]);
        $this->add_control('card_bg_color', [
            'label' => __('رنگ پس‌زمینه کارت', 'neurame-ai-assistant'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .course-card' => 'background-color: {{VALUE}};']
        ]);
        $this->end_controls_section();
    }

    protected function render()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NeurameAI_Recommended_Courses_Widget: Rendering widget');
        }

        $settings = $this->get_settings_for_display();
        if (!is_user_logged_in()) {
            echo '<p class="text-gray-600">' . esc_html__('لطفاً وارد شوید تا دوره‌ها را ببینید.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        $courses = wc_get_products([
            'limit' => $settings['course_count'],
            'meta_query' => [
                [
                    'key' => '_neurame_suitable_for_children',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($courses)) {
            echo '<p class="text-gray-600">' . esc_html__('هیچ دوره‌ای برای نمایش وجود ندارد.', 'neurame-ai-assistant') . '</p>';
            return;
        }

        echo '<div class="neurame-recommended-courses p-6">';
        echo '<h2 class="text-xl font-semibold mb-4">' . esc_html($settings['title']) . '</h2>';

        // 🔥 اضافه شده: پیام توضیحی قبل از لیست دوره‌ها
        echo '<div class="bg-yellow-100 text-yellow-800 p-4 rounded-lg mb-4">';
        echo esc_html__('دوره‌های پیشنهادی بر اساس علاقه‌مندی‌های کودک شما و سوابق ثبت‌نام قبلی انتخاب شده‌اند.', 'neurame-ai-assistant');
        echo '</div>';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
        foreach ($courses as $course) {
            echo '<div class="course-card p-4 border rounded-lg shadow-sm hover:shadow-md transition-shadow">';
            echo '<h3 class="text-lg font-medium">' . esc_html($course->get_name()) . '</h3>';
            echo '<p class="text-gray-600 mt-2">' . wp_trim_words($course->get_description(), 20) . '</p>';
            echo '<a href="' . esc_url($course->get_permalink()) . '" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">' . esc_html__('مشاهده دوره', 'neurame-ai-assistant') . '</a>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}
