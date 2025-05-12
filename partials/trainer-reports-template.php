<?php
if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('دسترسی ندارید.', 'neurame-ai-assistant'));
}

$edit_id = isset($_GET['edit_id']) ? sanitize_text_field($_GET['edit_id']) : null;
$editing = false;
$edit_data = null;

if ($edit_id !== null) {
    $all_reports = get_option('neurame_trainer_reports', []);
    foreach ($all_reports as $report) {
        if ($report['id'] === $edit_id) {
            $edit_data = $report;
            $editing = true;
            break;
        }
    }
}
?>

<div class="wrap">
    <h2 class="text-3xl font-bold mb-8">
        <?php echo $editing ? esc_html__('✏️ ویرایش گزارش مربی', 'neurame-ai-assistant') : esc_html__('📝 ثبت گزارش مربی', 'neurame-ai-assistant'); ?>
    </h2>

    <form id="trainer-report-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
          class="space-y-8 max-w-3xl bg-white p-6 shadow-md rounded-lg">
        <input type="hidden" name="action" value="neurame_trainer_report">
        <?php wp_nonce_field('neurame_trainer_report', 'neurame_nonce'); ?>
        <?php if ($editing): ?>
            <input type="hidden" name="report_id" value="<?php echo esc_attr($edit_id); ?>">
        <?php endif; ?>

        <!-- مربی -->
        <div class="form-group">
            <label for="trainer_id" class="block text-lg font-medium text-gray-800 mb-2">انتخاب مربی</label>
            <select name="trainer_id" id="trainer_id"
                    class="block w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" required>
                <option value="">یک مربی انتخاب کنید</option>
                <?php
                $trainers = get_users(['role' => 'trainer']);
                foreach ($trainers as $trainer) :
                    ?>
                    <option
                        value="<?php echo esc_attr($trainer->ID); ?>" <?php selected($edit_data['trainer_id'] ?? '', $trainer->ID); ?>>
                        <?php echo esc_html($trainer->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- دوره -->
        <div class="form-group">
            <label for="course_id" class="block text-lg font-medium text-gray-800 mb-2">انتخاب دوره</label>
            <select name="course_id" id="course_id"
                    class="block w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" required>
                <option value="">یک دوره انتخاب کنید</option>
                <?php
                $courses = wc_get_products(['limit' => -1]);
                foreach ($courses as $course) :
                    ?>
                    <option
                        value="<?php echo esc_attr($course->get_id()); ?>" <?php selected($edit_data['course_id'] ?? '', $course->get_id()); ?>>
                        <?php echo esc_html($course->get_name()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- کاربر -->
        <div class="form-group">
            <label for="user_id" class="block text-lg font-medium text-gray-800 mb-2">کاربر خریدار</label>
            <input type="number" name="user_id" id="user_id"
                   value="<?php echo esc_attr($edit_data['user_id'] ?? ''); ?>"
                   class="block w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" required>
        </div>

        <!-- کودک -->
        <div class="form-group">
            <label for="child_id" class="block text-lg font-medium text-gray-800 mb-2">کودک</label>
            <input type="text" name="child_id" id="child_id"
                   value="<?php echo esc_attr($edit_data['child_id'] ?? ''); ?>"
                   class="block w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <!-- محتوا -->
        <div class="form-group">
            <label for="report_content" class="block text-lg font-medium text-gray-800 mb-2">محتوای گزارش</label>
            <textarea name="report_content" id="report_content" rows="6"
                      class="block w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300"
                      required><?php echo esc_textarea($edit_data['content'] ?? ''); ?></textarea>
        </div>

        <!-- دکمه‌ها -->
        <div class="flex flex-col md:flex-row gap-4">
            <button type="submit"
                    class="bg-blue-600 text-white font-bold px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">
                <?php echo $editing ? 'ذخیره و بروزرسانی گزارش' : 'ذخیره گزارش'; ?>
            </button>

            <?php if ($editing): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      onsubmit="return confirm('آیا از حذف گزارش مطمئن هستید؟');" class="inline-block">
                    <input type="hidden" name="action" value="neurame_delete_report">
                    <?php wp_nonce_field('neurame_delete_report', 'neurame_nonce'); ?>
                    <input type="hidden" name="report_id" value="<?php echo esc_attr($edit_id); ?>">
                    <button type="submit"
                            class="bg-red-600 text-white font-bold px-6 py-3 rounded-lg hover:bg-red-700 transition duration-300">
                        حذف گزارش
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>