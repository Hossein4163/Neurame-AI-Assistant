document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('chat-messages');

    if (!form || !input || !messages) {
        console.error('❌ عناصر چت‌بات در DOM پیدا نشدند.');
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const userMsg = input.value.trim();
        if (!userMsg) return;

        const userDiv = document.createElement('div');
        userDiv.className = 'chat-user';
        userDiv.textContent = userMsg;
        messages.appendChild(userDiv);
        messages.scrollTop = messages.scrollHeight;

        input.value = '';

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

            if (resJson.data?.debug_prompt) {
                console.log('%c📤 PROMPT SENT TO AI:', 'color: green; font-weight: bold');
                console.log(resJson.data.debug_prompt);
            }

            const aiDiv = document.createElement('div');
            aiDiv.className = 'chat-ai';
            const reply = resJson.data?.reply ?? resJson.data ?? 'پاسخی دریافت نشد.';
            console.log('🤖 پاسخ دریافتی:', reply);
            aiDiv.innerHTML = reply;
            messages.appendChild(aiDiv);
            messages.scrollTop = messages.scrollHeight;

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
