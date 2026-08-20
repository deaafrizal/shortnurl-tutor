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
