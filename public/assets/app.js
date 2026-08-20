function showCopied(button) {
    const label = button.textContent;
    button.textContent = 'Tersalin ✓';
    button.classList.add('text-emerald-400');

    window.setTimeout(() => {
        button.textContent = label;
        button.classList.remove('text-emerald-400');
    }, 2000);
}

function copyText(text, button) {
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(() => showCopied(button));
        return;
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    document.execCommand('copy');
    field.remove();
    showCopied(button);
}

document.querySelectorAll('.copy-button').forEach((button) => {
    button.addEventListener('click', () => copyText(button.dataset.copy, button));
});

document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Hapus tautan pendek ini?')) {
            event.preventDefault();
        }
    });
});

const activityList = document.querySelector('#activity-list');
const activityLoader = document.querySelector('#activity-loader');
const activitySentinel = document.querySelector('#activity-sentinel');

if (activityList && activityLoader && activitySentinel && activityLoader.dataset.hasMore === 'true') {
    let loading = false;

    const appendActivityPage = async () => {
        if (loading || activityLoader.dataset.hasMore !== 'true') return;
        loading = true;
        activityLoader.classList.remove('hidden');
        activityLoader.classList.add('flex');

        try {
            const page = activityLoader.dataset.nextPage;
            const response = await fetch(`/?activity_page=${encodeURIComponent(page)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Activity request failed');
            const result = await response.json();
            const start = activityList.querySelectorAll('.activity-row').length + 1;

            result.items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = 'activity-row border-b border-white/5 last:border-0 hover:bg-white/[.03]';
                row.innerHTML = `<td class="activity-number px-5 py-4 text-zinc-600">${start + index}</td><td class="px-5 py-4 font-mono text-xs"></td><td class="px-5 py-4 text-center"><span class="rounded-full bg-white/[.07] px-2.5 py-1 text-xs">${Number(item.url_count)}</span></td><td class="hidden px-5 py-4 text-xs text-zinc-500 sm:table-cell"></td>`;
                row.children[1].textContent = item.ip_address;
                row.children[3].textContent = new Date(item.last_active.replace(' ', 'T')).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
                activityList.appendChild(row);
            });

            activityLoader.dataset.nextPage = String(Number(page) + 1);
            activityLoader.dataset.hasMore = result.has_more ? 'true' : 'false';
        } catch (error) {
            activityLoader.dataset.hasMore = 'true';
        } finally {
            loading = false;
            if (activityLoader.dataset.hasMore !== 'true') activityLoader.classList.add('hidden');
            else activityLoader.classList.remove('flex');
        }
    };

    new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) appendActivityPage();
    }, { rootMargin: '240px 0px' }).observe(activitySentinel);
}
