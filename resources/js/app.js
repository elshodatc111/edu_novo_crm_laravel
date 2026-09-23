import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Qorong'i / yorug' rejim
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try {
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        } catch (e) {}
        window.dispatchEvent(new Event('themechange'));
    },
});

/* Summa maydonlari: 1000 -> 1 000 (serverga bo'shliqsiz ketadi) */
const formatMoney = (value) => value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

/* Telefon maydonlari: faqat +998 90 123 4567 ko'rinishi */
const formatPhone = (value) => {
    let d = value.replace(/\D/g, '');
    if (d.startsWith('998')) d = d.slice(3);
    d = d.slice(0, 9);
    let out = '+998';
    if (d.length > 0) out += ' ' + d.slice(0, 2);
    if (d.length > 2) out += ' ' + d.slice(2, 5);
    if (d.length > 5) out += ' ' + d.slice(5, 9);
    return out;
};

const reformat = (el, fn) => {
    const digitsBefore = el.value.slice(0, el.selectionStart ?? el.value.length).replace(/\D/g, '').length;
    el.value = fn(el.value);
    if (document.activeElement === el) {
        let pos = 0, seen = 0;
        while (pos < el.value.length && seen < digitsBefore) { if (/\d/.test(el.value[pos])) seen++; pos++; }
        el.setSelectionRange(pos, pos);
    }
};

document.addEventListener('input', (e) => {
    if (e.target.matches?.('[data-money]')) reformat(e.target, formatMoney);
    if (e.target.matches?.('[data-phone]')) reformat(e.target, formatPhone);
});
document.addEventListener('focusin', (e) => {
    if (e.target.matches?.('[data-phone]') && e.target.value.trim() === '') e.target.value = '+998 ';
});
document.addEventListener('focusout', (e) => {
    if (e.target.matches?.('[data-phone]') && e.target.value.trim() === '+998') e.target.value = '';
});
document.addEventListener('submit', (e) => {
    e.target.querySelectorAll?.('[data-money]').forEach((el) => { el.value = el.value.replace(/\D/g, ''); });
}, true);
document.querySelectorAll('[data-money]').forEach((el) => { el.value = formatMoney(el.value); });

/* Yuborilgandan keyin tugmani bloklaydi - ikki marta bosib yuborishning oldini oladi (barcha formalar uchun). */
document.addEventListener('submit', (e) => {
    e.target.querySelectorAll?.('button[type="submit"], input[type="submit"]').forEach((el) => {
        el.disabled = true;
        el.classList.add('opacity-60', 'pointer-events-none');
    });
}, true);

/*
 * Pul o'zgartiruvchi formalar uchun yuborishdan oldingi tasdiqlash oynasi (xato kiritishni oldini olish).
 * Forma x-data="confirmForm('Sarlavha')" bilan o'raladi, yuborish tugmasi
 * type="button" @click="review($event)" bo'ladi, <x-money-confirm /> shu scope ichida chiqadi.
 */
Alpine.data('confirmForm', (title = 'Tasdiqlaysizmi?') => ({
    title,
    open: false,
    submitting: false,
    rows: [],
    pendingForm: null,
    review(e) {
        const form = e.target.closest('form');
        if (!form || !form.reportValidity()) return;

        const rows = [];
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
            if (el.type === 'hidden' || el.name === '_token' || el.name === '_once') return;
            const raw = el.tagName === 'SELECT' ? (el.selectedOptions[0]?.textContent ?? '') : el.value;
            const value = raw.toString().trim();
            if (!value) return;
            const label = el.labels?.[0]?.textContent?.replace('*', '').trim() || el.name;
            rows.push({ label, value: el.matches('[data-money]') ? value + " so'm" : value });
        });

        this.rows = rows;
        this.pendingForm = form;
        this.submitting = false;
        this.open = true;
    },
    confirm() {
        this.submitting = true;
        const form = this.pendingForm;
        this.open = false;
        this.pendingForm = null;
        form?.requestSubmit();
    },
    cancel() {
        this.open = false;
        this.pendingForm = null;
    },
}));

Alpine.start();
