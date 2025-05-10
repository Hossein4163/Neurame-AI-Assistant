function waitForNeurameVars(callback, maxAttempts = 50, interval = 100) {
    let attempts = 0;
    const check = () => {
        if (typeof neurame_vars !== 'undefined') {
            callback(neurame_vars);
        } else {
            attempts++;
            if (attempts < maxAttempts) {
                setTimeout(check, interval);
            } else {
                console.error('Neurame Error: neurame_vars is not defined.');
                showToast('خطا: تنظیمات افزونه بارگذاری نشد.', 'error');
            }
        }
    };
    check();
}

function showToast(message, type = 'success') {
    if (!document.body) {
        console.error('Neurame Error: document.body is not available.');
        return;
    }

    const toast = document.createElement('div');
    toast.className = `neurame-toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

waitForNeurameVars((vars) => {
    if (vars.is_parent_mode) {
        document.body.classList.add('neurame-parent-mode');
    }

    const ajaxUrl = vars.ajax_url || '';
    const getNonce = vars.nonce_get_children || '';
    const nonceLoadBuyers = vars.nonce_load_buyers || '';
    const nonceTrainerReport = vars.nonce_trainer_report || '';
    const nonceLoadTrainers = vars.nonce_load_trainers || '';
    const nonceLoadCourses = vars.nonce_load_courses || '';

    document.addEventListener('DOMContentLoaded', () => {
        // لود مربیان هنگام بارگذاری صفحه
        async function loadTrainers() {
            const trainerSelect = document.getElementById('trainer_id');
            if (!trainerSelect) return;

            try {
                const fd = new FormData();
                fd.append('action', 'neurame_load_trainers');
                fd.append('nonce', nonceLoadTrainers);

                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                if (json.success) {
                    trainerSelect.innerHTML = '<option value="">یک مربی انتخاب کنید</option>';
                    json.data.forEach(trainer => {
                        const opt = document.createElement('option');
                        opt.value = trainer.id;
                        opt.textContent = trainer.name;
                        trainerSelect.append(opt);
                    });
                } else {
                    trainerSelect.innerHTML = '<option value="">خطا در بارگذاری مربیان</option>';
                    showToast(json.data?.message || 'خطا در بارگذاری مربیان.', 'error');
                }
            } catch (err) {
                console.error('Error loading trainers:', err);
                trainerSelect.innerHTML = '<option value="">خطا در بارگذاری مربیان</option>';
                showToast('خطا در ارتباط با سرور هنگام دریافت مربیان.', 'error');
            }
        }

        // لود دوره‌ها هنگام انتخاب مربی
        async function loadCourses(trainerId) {
            const courseSelect = document.getElementById('course_id');
            if (!courseSelect) return;

            courseSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';

            try {
                const fd = new FormData();
                fd.append('action', 'neurame_load_courses');
                fd.append('nonce', nonceLoadCourses);
                fd.append('trainer_id', trainerId);

                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                if (json.success) {
                    courseSelect.innerHTML = '<option value="">یک دوره انتخاب کنید</option>';
                    json.data.forEach(course => {
                        const opt = document.createElement('option');
                        opt.value = course.id;
                        opt.textContent = course.title;
                        courseSelect.append(opt);
                    });
                } else {
                    courseSelect.innerHTML = '<option value="">خطا در بارگذاری دوره‌ها</option>';
                    showToast(json.data?.message || 'خطا در بارگذاری دوره‌ها.', 'error');
                }
            } catch (err) {
                console.error('Error loading courses:', err);
                courseSelect.innerHTML = '<option value="">خطا در بارگذاری دوره‌ها</option>';
                showToast('خطا در ارتباط با سرور هنگام دریافت دوره‌ها.', 'error');
            }
        }

        // لود کاربران خریدار هنگام انتخاب دوره
        async function loadBuyers(courseId) {
            const userSelect = document.getElementById('user_id');
            const childSelect = document.getElementById('child_id');
            if (!userSelect || !childSelect) return;

            userSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            childSelect.innerHTML = '<option value="">ابتدا یک کاربر انتخاب کنید</option>';

            try {
                const fd = new FormData();
                fd.append('action', 'neurame_load_buyers');
                fd.append('nonce', nonceLoadBuyers);
                fd.append('course_id', courseId);

                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                if (json.success) {
                    if (json.data && json.data.length > 0) {
                        userSelect.innerHTML = '<option value="">یک کاربر انتخاب کنید</option>';
                        json.data.forEach(user => {
                            const opt = document.createElement('option');
                            opt.value = user.id;
                            opt.textContent = user.name;
                            userSelect.append(opt);
                        });
                    } else {
                        userSelect.innerHTML = '<option value="">کاربری برای این دوره یافت نشد</option>';
                    }
                } else {
                    userSelect.innerHTML = '<option value="">خطا در بارگذاری کاربران</option>';
                    showToast(json.data?.message || 'خطا در بارگذاری کاربران.', 'error');
                }
            } catch (err) {
                console.error('Error loading buyers:', err);
                userSelect.innerHTML = '<option value="">خطا در بارگذاری کاربران</option>';
                showToast('خطا در ارتباط با سرور هنگام دریافت کاربران.', 'error');
            }
        }

        // لود کودکان هنگام انتخاب کاربر
        async function loadChildren(userId) {
            const childSelect = document.getElementById('child_id');
            if (!childSelect) return;

            childSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';

            try {
                const fd = new FormData();
                fd.append('action', 'neurame_get_children');
                fd.append('nonce', getNonce);
                fd.append('user_id', userId);

                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                if (json.success) {
                    if (json.data && json.data.length > 0) {
                        childSelect.innerHTML = '<option value="">یک کودک انتخاب کنید</option>';
                        json.data.forEach(child => {
                            const opt = document.createElement('option');
                            opt.value = `${child.user_id}_${child.index}`;
                            opt.textContent = `${child.name} (سن: ${child.age})`;
                            childSelect.append(opt);
                        });
                    } else {
                        childSelect.innerHTML = '<option value="">کودکی برای این کاربر یافت نشد</option>';
                    }
                } else {
                    childSelect.innerHTML = '<option value="">خطا در بارگذاری کودکان</option>';
                    showToast(json.data?.message || 'خطا در بارگذاری کودکان.', 'error');
                }
            } catch (err) {
                console.error('Error loading children:', err);
                childSelect.innerHTML = '<option value="">خطا در بارگذاری کودکان</option>';
                showToast('خطا در ارتباط با سرور هنگام دریافت کودکان.', 'error');
            }
        }

        // مدیریت فرم ثبت گزارش مربی
        jQuery(document).ready(function ($) {
            $('#trainer-report-form').on('submit', async function (e) {
                e.preventDefault();

                const trainerId = $('#trainer_id').val();
                const courseId = $('#course_id').val();
                const userId = $('#user_id').val();
                const childId = $('#child_id').val();
                const reportContent = $('#report_content').val();

                // اعتبارسنجی فیلدها
                if (!trainerId) {
                    showToast('لطفاً یک مربی انتخاب کنید.', 'error');
                    return;
                }
                if (!courseId) {
                    showToast('لطفاً یک دوره انتخاب کنید.', 'error');
                    return;
                }
                if (!userId) {
                    showToast('لطفاً یک کاربر انتخاب کنید.', 'error');
                    return;
                }
                if (neurame_vars.is_parent_mode && !childId) {
                    showToast('لطفاً یک کودک انتخاب کنید.', 'error');
                    return;
                }
                if (!reportContent.trim()) {
                    showToast('لطفاً محتوای گزارش را وارد کنید.', 'error');
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'neurame_save_trainer_report');
                fd.append('nonce', nonceTrainerReport);
                fd.append('trainer_id', trainerId);
                fd.append('course_id', courseId);
                fd.append('user_id', userId);
                if (neurame_vars.is_parent_mode) {
                    fd.append('child_id', childId);
                }
                fd.append('report_content', reportContent);

                try {
                    const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                    const json = await resp.json();

                    if (json.success) {
                        showToast('گزارش با موفقیت ذخیره شد!', 'success');
                        setTimeout(() => {
                            window.location.href = window.location.pathname + '?page=neurame-trainer-reports&message=saved';
                        }, 1000);
                    } else {
                        showToast(json.data?.message || 'خطا در ذخیره گزارش.', 'error');
                    }
                } catch (err) {
                    console.error('Error saving report:', err);
                    showToast('خطا در ارتباط با سرور هنگام ذخیره گزارش.', 'error');
                }
            });
            // رویداد انتخاب مربی
            $('#trainer_id').on('change', function () {
                const trainerId = $(this).val();
                const courseSelect = $('#course_id');
                const userSelect = $('#user_id');
                const childSelect = $('#child_id');

                courseSelect.html('<option value="">یک دوره انتخاب کنید</option>');
                userSelect.html('<option value="">ابتدا یک دوره انتخاب کنید</option>');
                childSelect.html('<option value="">ابتدا یک کاربر انتخاب کنید</option>');

                if (trainerId) {
                    loadCourses(trainerId);
                }
            });

            // رویداد انتخاب دوره
            $('#course_id').on('change', function () {
                const courseId = $(this).val();
                const userSelect = $('#user_id');
                const childSelect = $('#child_id');

                userSelect.html('<option value="">یک کاربر انتخاب کنید</option>');
                childSelect.html('<option value="">ابتدا یک کاربر انتخاب کنید</option>');

                if (courseId) {
                    loadBuyers(courseId);
                }
            });

            // رویداد انتخاب کاربر
            $('#user_id').on('change', function () {
                const userId = $(this).val();
                const childSelect = $('#child_id');

                childSelect.html('<option value="">یک کودک انتخاب کنید</option>');

                if (userId) {
                    loadChildren(userId);
                }
            });
        });

        // لود اولیه مربیان
        loadTrainers();
    });
});