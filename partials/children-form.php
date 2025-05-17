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
                <input type="text" name="name[]" value="<?= esc_attr($child['name'] ?? '') ?>"/>
                <label>سن:</label>
                <input type="number" name="age[]" value="<?= esc_attr($child['age'] ?? '') ?>"/>
                <label>علاقه‌مندی:</label>
                <input type="text" name="interests[]" value="<?= esc_attr($child['interests'] ?? '') ?>"/>
                <label>هدف والد:</label>
                <textarea name="goals[]"><?= esc_textarea($child['goals'] ?? '') ?></textarea>
                <button type="button" class="remove-child btn-save">حذف</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="add-child" class="btn-save">➕ افزودن کودک</button>
    <button type="button" id="save-children" class="btn-save">💾 ذخیره اطلاعات</button>
</div>