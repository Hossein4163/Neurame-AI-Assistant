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
                console.error('Neurame Error: `neurame_vars` is not defined after max attempts.');
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
    console.log('✅ neurame_vars آماده شد:', vars);

    const ajaxUrl = vars.ajax_url || '';
    const getNonce = vars.nonce_get_children || '';
    const nonceUpdateChild = neurame_vars.nonce_update_child || '';
    const nonceDeleteChild = neurame_vars.nonce_delete_child || '';
    const nonceLoadBuyers = vars.nonce_load_buyers || '';
    const nonceTrainerReport = vars.nonce_trainer_report || '';
    const nonceLoadTrainers = vars.nonce_load_trainers || '';
    const nonceLoadCourses = vars.nonce_load_courses || '';

    function initForm() {
        console.log('🚀 initForm اجرا شد');

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.manage-children-btn');
            if (!btn) return;

            const userId = btn.getAttribute('data-user-id');
            const row = document.getElementById(`children-row-${userId}`);
            if (!row) {
                console.warn('❌ ردیف مربوط به کاربر پیدا نشد:', userId);
                return;
            }

            const container = row.querySelector('.children-list');
            if (!container) {
                console.warn('❌ کانتینر children-list پیدا نشد.');
                return;
            }

            console.log('📌 کلیک روی مدیریت فرزندان', userId);

            if (row.style.display === 'none' || !row.style.display) {
                row.style.display = 'table-row';
                container.innerHTML = '<p>در حال بارگذاری...</p>';

                const fd = new FormData();
                fd.append('action', 'neurame_get_user_children');
                fd.append('nonce', getNonce);
                fd.append('user_id', userId);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: fd
                })
                    .then(res => res.json())
                    .then(json => {
                        console.log('📦 پاسخ دریافت شد:', json);
                        if (json.success) {
                            container.innerHTML = json.data.html;
                        } else {
                            container.innerHTML = '<p class="text-red-600">خطا در دریافت کودکان</p>';
                            showToast(json.data?.message || 'خطا در دریافت کودکان.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('❌ خطا در بارگذاری کودکان:', err);
                        container.innerHTML = '<p class="text-red-600">خطا در ارتباط با سرور</p>';
                    });
            } else {
                row.style.display = 'none';
            }
        });

        document.addEventListener('click', async function (e) {
            // حذف کودک
            if (e.target.classList.contains('delete-child')) {
                const btn = e.target;
                const index = btn.dataset.index;
                const userId = btn.dataset.userId;

                if (!confirm('آیا از حذف این کودک مطمئن هستید؟')) return;

                const fd = new FormData();
                fd.append('action', 'neurame_delete_child');
                fd.append('nonce', nonceDeleteChild);
                fd.append('user_id', userId);
                fd.append('index', index);

                const res = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await res.json();

                if (json.success) {
                    showToast('✅ کودک حذف شد');
                    btn.closest('.child-box').remove();
                } else {
                    showToast(json.message || 'خطا در حذف کودک', 'error');
                }
            }

            // ذخیره کودک
            if (e.target.classList.contains('update-child')) {
                const btn = e.target;
                const box = btn.closest('.child-box');
                const index = btn.dataset.index;
                const userId = btn.dataset.userId;
                const name = box.querySelector('.child-name')?.value.trim();
                const age = box.querySelector('.child-age')?.value.trim();
                const interests = box.querySelector('.child-interests')?.value.trim();

                if (!name || !age) {
                    showToast('نام و سن الزامی هستند.', 'error');
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'neurame_update_child');
                fd.append('nonce', nonceUpdateChild);
                fd.append('user_id', userId);
                fd.append('index', index);
                fd.append('name', name);
                fd.append('age', age);
                fd.append('interests', interests);

                const res = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await res.json();

                if (json.success) {
                    showToast('✅ کودک بروزرسانی شد');
                } else {
                    showToast(json.message || 'خطا در ذخیره کودک', 'error');
                }
            }
        });

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
            console.log('🧠 ارسال درخواست دریافت کودک برای کاربر:', userId);
            const childSelect = document.getElementById('child_id');
            if (!childSelect) return;

            const fd = new FormData();
            fd.append('action', 'neurame_get_children');
            fd.append('nonce', getNonce);
            fd.append('user_id', userId);


            childSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';

            try {
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
                if (!childId) {
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
                fd.append('child_id', childId);
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
    }

    function initChildrenManagementForm() {
        const addBtn = document.getElementById('add-child');
        const saveBtn = document.getElementById('save-children');
        const childrenList = document.getElementById('children-list');

        if (!addBtn || !saveBtn || !childrenList) return;

        // افزودن کودک جدید
        addBtn.addEventListener('click', () => {
            const card = document.createElement('div');
            card.className = 'card';
            card.innerHTML = `
                <label>نام:</label><input type="text" name="name[]" />
                <label>سن:</label><input type="number" name="age[]" />
                <label>علاقه‌مندی:</label><input type="text" name="interests[]" />
                <label>هدف والد:</label><textarea name="goals[]"></textarea>
                <button type="button" class="remove-child btn-save">حذف</button>
            `;
            childrenList.appendChild(card);
        });

        // حذف کودک
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-child')) {
                e.target.closest('.card')?.remove();
            }
        });

        // ذخیره کودکان
        saveBtn.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('action', 'neurame_save_children');
            fd.append('nonce', neurame_vars.nonce_get_children);

            const children = [];

            childrenList.querySelectorAll('.card').forEach(card => {
                const name = card.querySelector('input[name="name[]"]')?.value.trim();
                const age = card.querySelector('input[name="age[]"]')?.value.trim();
                const interests = card.querySelector('input[name="interests[]"]')?.value.trim();
                const goals = card.querySelector('textarea[name="goals[]"]')?.value.trim();

                if (name && age) {
                    children.push({name, age, interests, goals});
                }
            });

            fd.append('children', JSON.stringify(children));

            fetch(neurame_vars.ajax_url, {
                method: 'POST',
                body: fd
            })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        showToast('✅ اطلاعات ذخیره شد.', 'success');
                    } else {
                        showToast(json.data?.message || 'خطا در ذخیره اطلاعات', 'error');
                    }
                })
                .catch(err => {
                    console.error('❌ خطا در ذخیره:', err);
                    showToast('ارتباط با سرور قطع شد.', 'error');
                });
        });
    }


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initForm();                // فرم گزارش‌ها
            initChildrenManagementForm(); // فرم مدیریت کودکان
        });
    } else {
        initForm();
        initChildrenManagementForm();
    }
});

// ✅ Toast Helper Function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `neurame-toast ${type}`;
    toast.textContent = message;
    Object.assign(toast.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        background: type === 'success' ? '#10b981' : '#ef4444',
        color: '#fff',
        padding: '12px 20px',
        borderRadius: '8px',
        boxShadow: '0 2px 10px rgba(0,0,0,0.2)',
        zIndex: 9999,
        transition: 'opacity 0.3s ease'
    });
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
