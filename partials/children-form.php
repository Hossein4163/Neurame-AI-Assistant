<?php
if (!is_user_logged_in()) {
    echo '<p>برای مشاهده این بخش ابتدا وارد حساب خود شوید.</p>';
    return;
}

$user_id = get_current_user_id();
$children = get_user_meta($user_id, 'neurame_children', true);
$children = is_array($children) ? $children : [];

?>

<div id="neurame-children-form" class="space-y-6">
    <h2>مدیریت فرزندان</h2>

    <div id="children-list">
        <?php foreach ($children as $index => $child): ?>
            <div class="card" data-index="<?= esc_attr($index) ?>">
                <label>نام:</label>
                <input type="text" name="name[]" value="<?= esc_attr($child['name'] ?? '') ?>" />
                <label>سن:</label>
                <input type="number" name="age[]" value="<?= esc_attr($child['age'] ?? '') ?>" />
                <label>علاقه‌مندی:</label>
                <input type="text" name="interests[]" value="<?= esc_attr($child['interests'] ?? '') ?>" />
                <label>هدف والد:</label>
                <textarea name="goals[]"><?= esc_textarea($child['goals'] ?? '') ?></textarea>
                <button type="button" class="remove-child btn-save">حذف</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="add-child" class="btn-save">➕ افزودن کودک</button>
    <button type="button" id="save-children" class="btn-save">💾 ذخیره اطلاعات</button>
</div>

<script>
jQuery(document).ready(function ($) {
    $('#add-child').on('click', function () {
        $('#children-list').append(`
            <div class="card">
                <label>نام:</label><input type="text" name="name[]" />
                <label>سن:</label><input type="number" name="age[]" />
                <label>علاقه‌مندی:</label><input type="text" name="interests[]" />
                <label>هدف والد:</label><textarea name="goals[]"></textarea>
                <button type="button" class="remove-child btn-save">حذف</button>
            </div>
        `);
    });

    $(document).on('click', '.remove-child', function () {
        $(this).closest('.card').remove();
    });

    $('#save-children').on('click', function () {
        const data = {
            action: 'neurame_save_children',
            nonce: neurame_vars.nonce_get_children,
            children: []
        };

        $('#children-list .card').each(function () {
            const name = $(this).find('input[name="name[]"]').val();
            const age = $(this).find('input[name="age[]"]').val();
            const interests = $(this).find('input[name="interests[]"]').val();
            const goals = $(this).find('textarea[name="goals[]"]').val();

            data.children.push({ name, age, interests, goals });
        });

        $.post(neurame_vars.ajax_url, data, function (res) {
            if (res.success) {
                showToast('اطلاعات ذخیره شد.', 'success');
            } else {
                showToast(res.data.message || 'خطا در ذخیره اطلاعات', 'error');
            }
        });
    });
});
</script>
