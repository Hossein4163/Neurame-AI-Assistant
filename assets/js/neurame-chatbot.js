document.addEventListener('DOMContentLoaded', () => {
    const icon = document.getElementById('neurame-chat-icon');
    const widget = document.getElementById('neurame-chat-widget');
    const closeBtn = document.getElementById('close-chat');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('chat-messages');

    if (!icon || !widget) {
        console.error('❌ آیکن یا ویجت چت‌بات پیدا نشد.');
        return;
    }

    // 👆 نمایش یا پنهان کردن ویجت چت‌بات با کلیک روی آیکن
    icon.addEventListener('click', () => {
        widget.classList.toggle('hidden');
    });

    if (!form || !input || !messages) {
        console.error('❌ فرم یا ورودی یا بخش پیام‌ها پیدا نشد.');
        return;
    }

    if (icon && widget) {
        icon.addEventListener('click', () => {
            widget.classList.remove('hidden');
        });
    }

    if (closeBtn && widget) {
        closeBtn.addEventListener('click', () => {
            widget.classList.add('hidden');
        });
    }

    // 📩 ارسال پیام کاربر
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const userMsg = input.value.trim();
        if (!userMsg) return;

        // 👤 پیام کاربر
        const userDiv = document.createElement('div');
        userDiv.className = 'chat-user';
        userDiv.textContent = userMsg;
        messages.appendChild(userDiv);
        messages.scrollTop = messages.scrollHeight;
        input.value = '';

        // ⏳ پیام لودینگ
        const loading = document.createElement('div');
        loading.className = 'chat-ai';
        loading.textContent = 'در حال فکر کردن...';
        messages.appendChild(loading);
        messages.scrollTop = messages.scrollHeight;

        try {
            const fd = new FormData();
            fd.append('action', 'neurame_chatbot_ask');
            fd.append('nonce', neurame_vars.nonce_chatbot);
            fd.append('message', userMsg);

            const res = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: fd
            });

            if (!res.ok) throw new Error('خطا در دریافت پاسخ از سرور');

            const resJson = await res.json();
            loading.remove();

            const reply = resJson.data?.reply ?? 'پاسخی دریافت نشد.';
            const aiDiv = document.createElement('div');
            aiDiv.className = 'chat-ai';
            aiDiv.innerHTML = reply;
            messages.appendChild(aiDiv);
            messages.scrollTop = messages.scrollHeight;

            if (resJson.data?.debug_prompt) {
                console.log('%c📤 PROMPT SENT TO AI:', 'color: green; font-weight: bold');
                console.log(resJson.data.debug_prompt);
            }

        } catch (err) {
            console.error('❌ خطا در ارتباط با چت‌بات:', err);
            loading.remove();

            const errorDiv = document.createElement('div');
            errorDiv.className = 'chat-ai';
            errorDiv.textContent = 'متأسفم، مشکلی در پاسخ‌گویی پیش آمد.';
            messages.appendChild(errorDiv);
        }
    });
});
