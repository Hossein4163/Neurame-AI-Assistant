<?php
if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
}
?>

<div class="wrap">
    <h2 class="text-2xl font-semibold mb-6"><?php esc_html_e('📝 ثبت گزارش مربی', 'neurame-ai-assistant'); ?></h2>

    <form id="trainer-report-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-6 max-w-2xl">
        <input type="hidden" name="action" value="neurame_trainer_report">
        <?php wp_nonce_field('neurame_trainer_report', 'neurame_nonce'); ?>

        <!-- انتخاب مربی -->
        <div>
            <label for="trainer_id" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('مربی', 'neurame-ai-assistant'); ?>
            </label>
            <select name="trainer_id" id="trainer_id" class="block w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                <option value=""><?php esc_html_e('یک مربی انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                <?php
                $trainers = get_users(['role' => 'trainer']);
                foreach ($trainers as $trainer) :
                ?>
                    <option value="<?php echo esc_attr($trainer->ID); ?>">
                        <?php echo esc_html($trainer->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- انتخاب دوره -->
        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('دوره', 'neurame-ai-assistant'); ?>
            </label>
            <select name="course_id" id="course_id" class="neurame-course-select block w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                <option value=""><?php esc_html_e('یک دوره انتخاب کنید', 'neurame-ai-assistant'); ?></option>
                <?php
                $courses = wc_get_products(['limit' => -1]);
                foreach ($courses as $course) :
                ?>
                    <option value="<?php echo esc_attr($course->get_id()); ?>">
                        <?php echo esc_html($course->get_name()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- انتخاب کاربر خریدار -->
        <div id="buyer-wrapper">
            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('کاربر خریدار', 'neurame-ai-assistant'); ?>
            </label>
            <select name="user_id" id="user_id" class="neurame-user-select block w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                <option value=""><?php esc_html_e('ابتدا یک دوره انتخاب کنید', 'neurame-ai-assistant'); ?></option>
            </select>
        </div>

        <!-- انتخاب کودک -->
        <div id="child-wrapper">
            <label for="child_id" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('کودک', 'neurame-ai-assistant'); ?>
            </label>
            <select name="child_id" id="child_id" class="block w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value=""><?php esc_html_e('ابتدا یک کاربر انتخاب کنید', 'neurame-ai-assistant'); ?></option>
            </select>
        </div>

        <!-- محتوای گزارش -->
        <div>
            <label for="report_content" class="block text-sm font-medium text-gray-700 mb-1">
                <?php esc_html_e('محتوای گزارش', 'neurame-ai-assistant'); ?>
            </label>
            <textarea name="report_content" id="report_content" rows="5" class="block w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required></textarea>
        </div>

        <!-- دکمه ارسال -->
        <div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <?php esc_html_e('ذخیره و بازنویسی گزارش', 'neurame-ai-assistant'); ?>
            </button>
        </div>
    </form>
</div>