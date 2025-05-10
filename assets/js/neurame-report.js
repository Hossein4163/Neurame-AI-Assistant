function loadChartJs(callback) {
    if (typeof Chart !== 'undefined') {
        callback();
    } else {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = callback;
        script.onerror = () => console.error('Neurame Error: Failed to load Chart.js');
        document.head.appendChild(script);
    }
}

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
                console.error('Neurame Error: neurame_vars is not defined after max attempts.');
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
    const ajaxUrl = vars.ajax_url || '';
    const aiNonce = vars.ai_nonce || '';
    const getNonce = vars.nonce_get_children || '';
    const nonceGetReports = vars.nonce_get_reports || '';
    const nonceSaveParentInfo = vars.nonce_save_parent_info || '';
    const nonceFetchParentInfo = vars.nonce_fetch_parent_info || '';
    const userId = vars.user_id || '';
    const isAdmin = vars.is_admin || false;

    document.addEventListener('DOMContentLoaded', () => {
        // ذخیره پروفایل والدین بدون ریدایرکت
        const saveProfileBtn = document.getElementById('save-parent-profile');
        if (saveProfileBtn) {
            saveProfileBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                const textarea = document.querySelector('textarea[name="parent_goals"]');
                if (!textarea || !textarea.value.trim()) {
                    showToast('لطفاً اهداف والدین را وارد کنید.', 'error');
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'neurame_save_parent_info');
                fd.append('nonce', nonceSaveParentInfo);
                fd.append('user_id', userId);
                fd.append('parent_goals', textarea.value.trim());

                try {
                    const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                    const json = await resp.json();

                    if (json.success) {
                        showToast('پروفایل با موفقیت ذخیره شد.', 'success');
                    } else {
                        showToast(json.data?.message || 'خطا در ذخیره پروفایل.', 'error');
                    }
                } catch (err) {
                    console.error('Error saving parent profile:', err);
                    showToast('خطا در ارتباط با سرور.', 'error');
                }
            });
        }

        // لود گزارش‌ها
        async function fetchReports(childId) {
            if (!childId) {
                showToast('لطفاً یک کودک انتخاب کنید.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'neurame_get_reports');
            fd.append('nonce', nonceGetReports);
            fd.append('child_id', childId);

            try {
                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                const container = document.getElementById('reports-list');
                if (!container) {
                    console.error('Reports container not found.');
                    return;
                }

                if (json.success) {
                    container.innerHTML = json.data.html;
                } else {
                    container.innerHTML = `<p class="text-red-600">${json.data?.message || 'خطا در دریافت گزارش‌ها.'}</p>`;
                    showToast(json.data?.message || 'خطا در دریافت گزارش‌ها.', 'error');
                }
            } catch (err) {
                console.error('Reports fetch error:', err);
                const container = document.getElementById('reports-list');
                if (container) {
                    container.innerHTML = `<p class="text-red-600">خطا در دریافت گزارش‌ها: ${err.message}</p>`;
                }
                showToast('خطا در ارتباط با سرور.', 'error');
            }
        }

        // لود گزارش هوشمند و چارت
        async function fetchProgressReport(childId) {
            if (!childId) {
                showToast('لطفاً یک کودک انتخاب کنید.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'neurame_get_progress_report');
            fd.append('nonce', nonceGetReports);
            fd.append('child_id', childId);

            try {
                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                const container = document.getElementById('progress-report');
                if (!container) {
                    console.error('Progress report container not found.');
                    return;
                }

                if (json.success) {
                    container.innerHTML = json.data.html;

                    if (json.data.chart_data) {
                        loadChartJs(() => {
                            const ctx = document.getElementById(`progress-chart-${childId}`).getContext('2d');
                            new Chart(ctx, {
                                type: 'radar',
                                data: {
                                    labels: json.data.chart_data.labels,
                                    datasets: [{
                                        label: 'امتیاز مهارت‌ها',
                                        data: json.data.chart_data.values,
                                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1,
                                    }]
                                },
                                options: {
                                    scales: {
                                        r: {
                                            beginAtZero: true,
                                            max: 100,
                                            ticks: {stepSize: 20}
                                        },
                                    },
                                    plugins: {
                                        legend: {position: 'top'},
                                        title: {
                                            display: true,
                                            text: 'روند پیشرفت مهارت‌ها'
                                        }
                                    }
                                }
                            });
                        });
                    }
                } else {
                    container.innerHTML = `<p class="text-gray-600">${json.data?.message || 'داده‌ای برای نمایش وجود ندارد.'}</p>`;
                }
            } catch (err) {
                console.error('Progress report fetch error:', err);
                const container = document.getElementById('progress-report');
                if (container) {
                    container.innerHTML = `<p class="text-red-600">خطا در دریافت گزارش هوشمند: ${err.message}</p>`;
                }
                showToast('خطا در دریافت گزارش هوشمند.', 'error');
            }
        }

        // دریافت پیشنهاد هوش مصنوعی
        async function fetchAIRecommendation(childId, parentGoals) {
            if (!childId || !parentGoals) {
                showToast('لطفاً همه‌ی فیلدها را تکمیل کنید.', 'error');
                return;
            }

            const [userId, childIndex] = childId.split('_');
            if (!userId || childIndex === undefined) {
                showToast('فرمت شناسه کودک نامعتبر است.', 'error');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'neurame_ai_recommendation');
            fd.append('nonce', aiNonce);
            fd.append('user_id', userId);
            fd.append('child_index', childIndex);
            fd.append('parent_goals', parentGoals);

            try {
                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                const responseContainer = document.getElementById('neurame-ai-response');
                if (!responseContainer) {
                    showToast('بخش نمایش پیشنهادات یافت نشد.', 'error');
                    return;
                }

                if (json.success) {
                    responseContainer.innerHTML = json.data.html;
                    showToast('دوره‌های پیشنهادی با موفقیت دریافت شدند.', 'success');
                } else {
                    responseContainer.innerHTML = `<p class="text-red-600">${json.data?.message || 'خطا در دریافت پیشنهاد از هوش مصنوعی.'}</p>`;
                    showToast(json.data?.message || 'خطا در دریافت پیشنهاد از هوش مصنوعی.', 'error');
                }
            } catch (err) {
                console.error('AI Recommendation fetch error:', err);
                const responseContainer = document.getElementById('neurame-ai-response');
                if (responseContainer) {
                    responseContainer.innerHTML = `<p class="text-red-600">خطا در دریافت پیشنهاد دوره از هوش مصنوعی: ${err.message}</p>`;
                }
                showToast('خطا در ارتباط با هوش مصنوعی.', 'error');
            }
        }

        // لود گزارش‌ها و گزارش هوشمند
        async function loadReportsAndProgress(childId) {
            const reportsContainer = document.getElementById('reports-list');
            const progressContainer = document.getElementById('progress-report');

            if (!childId) {
                if (reportsContainer) {
                    reportsContainer.innerHTML = '<p class="text-gray-600">لطفاً یک کودک انتخاب کنید.</p>';
                }
                if (progressContainer) {
                    progressContainer.innerHTML = '<p class="text-gray-600">لطفاً یک کودک انتخاب کنید.</p>';
                }
                return;
            }

            await Promise.all([
                fetchReports(childId),
                fetchProgressReport(childId)
            ]);
        }

        // رویداد کلیک برای دکمه پیشنهاد هوش مصنوعی
        const recommendButton = document.getElementById('neurame-ai-recommend');
        if (recommendButton) {
            recommendButton.addEventListener('click', async () => {
                const form = document.getElementById('neurame-ai-recommendation-form') || document.getElementById('neurame-info-form');
                if (!form) {
                    showToast('فرم پیشنهادات هوش مصنوعی پیدا نشد.', 'error');
                    return;
                }

                const childSelect = form.querySelector('select[name="child_select"]');
                const parentGoalsInput = form.querySelector('textarea[name="parent_goals"]');

                if (!childSelect || !parentGoalsInput) {
                    showToast('فیلدهای مورد نیاز پیدا نشدند.', 'error');
                    return;
                }

                const childId = childSelect.value.trim();
                const parentGoals = parentGoalsInput.value.trim();

                if (!childId || !parentGoals) {
                    showToast('لطفاً همه‌ی فیلدها را تکمیل کنید.', 'error');
                    return;
                }

                await fetchAIRecommendation(childId, parentGoals);
            });
        }

        // دریافت اطلاعات والدین
        async function fetchParentInfo(userId) {
            const fd = new FormData();
            fd.append('action', 'neurame_fetch_parent_info');
            fd.append('nonce', nonceFetchParentInfo);
            fd.append('user_id', userId);

            try {
                const resp = await fetch(ajaxUrl, {method: 'POST', body: fd});
                const json = await resp.json();

                if (!json.success) {
                    showToast(json.data?.message || 'خطا در دریافت اطلاعات والدین.', 'error');
                    return `<p class="text-red-600">خطا در دریافت اطلاعات والدین: ${json.data?.message || 'خطای ناشناخته'}</p>`;
                }

                return json.data.html;
            } catch (err) {
                console.error('Parent info fetch error:', err);
                showToast('خطا در ارتباط با سرور.', 'error');
                return `<p class="text-red-600">خطا در دریافت اطلاعات والدین: ${err.message}</p>`;
            }
        }

        // مدیریت انتخاب نوع گزارش و محتوای پویا
        const reportTypeSelect = document.getElementById('report-type-select');
        const reportChildSelectContainer = document.getElementById('report-child-select-container');
        const reportContent = document.getElementById('report-content-inner');

        if (reportTypeSelect) {
            reportTypeSelect.addEventListener('change', async function () {
                const type = this.value;
                const childSelect = document.getElementById('report-child-select');
                const childId = childSelect ? childSelect.value : '';

                // نمایش یا مخفی کردن بخش انتخاب فرزند
                reportChildSelectContainer.style.display = (type === 'parental' || type === 'settings') ? 'none' : 'block';
                reportContent.innerHTML = '<p>در حال بارگذاری...</p>';

                if (type === 'parental') {
                    reportContent.innerHTML = await fetchParentInfo(userId);
                } else if (type === 'reports') {
                    if (!childId) {
                        reportContent.innerHTML = '<p>لطفاً یک کودک انتخاب کنید.</p>';
                    } else {
                        await fetchReports(childId);
                    }
                } else if (type === 'progress') {
                    if (!childId) {
                        reportContent.innerHTML = '<p>لطفاً یک کودک انتخاب کنید.</p>';
                    } else {
                        await fetchProgressReport(childId);
                    }
                } else if (type === 'activity') {
                    reportContent.innerHTML = '<p class="text-gray-600">فعالیت‌ها در حال توسعه است.</p>';
                } else if (type === 'settings') {
                    reportContent.innerHTML = '<p class="text-gray-600">تنظیمات در حال توسعه است.</p>';
                }
            });
        }

        // رویداد تغییر برای انتخاب کودک
        const reportChildSelect = document.getElementById('report-child-select');
        if (reportChildSelect) {
            reportChildSelect.addEventListener('change', async () => {
                const childId = reportChildSelect.value.trim();
                const reportType = reportTypeSelect ? reportTypeSelect.value : '';
                if (reportType === 'reports' || reportType === 'progress') {
                    await loadReportsAndProgress(childId);
                }
            });
        }
    });
});