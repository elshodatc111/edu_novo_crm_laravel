{{-- To'lov/chegirma/qaytarish/kassa so'rovi formalarida yuborishdan oldin ko'rsatiladigan tasdiqlash oynasi.
     Ishlatish: forma <x-input>/<x-select> maydonlariga ega bo'lishi, `x-data="confirmForm('Sarlavha')"` bilan
     o'ralgan bo'lishi va yuborish tugmasi `type="button" @click="review($event)"` bo'lishi kerak. --}}
<div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/50 p-4">
    <div @click.outside="cancel()" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl dark:bg-ink-900">
        <h3 class="text-base font-bold text-ink-900 dark:text-white" x-text="title"></h3>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Ma'lumotlarni tekshiring. Xato bo'lsa "Bekor qilish" bosing.</p>
        <dl class="mt-4 max-h-64 space-y-2 overflow-y-auto text-sm">
            <template x-for="row in rows" :key="row.label">
                <div class="flex justify-between gap-3 border-b border-ink-100 pb-1.5 dark:border-ink-800">
                    <dt class="text-ink-500 dark:text-ink-400" x-text="row.label"></dt>
                    <dd class="text-right font-semibold text-ink-900 dark:text-white" x-text="row.value"></dd>
                </div>
            </template>
        </dl>
        <div class="mt-5 flex gap-2">
            <button type="button" @click="cancel()" class="btn-secondary flex-1">Bekor qilish</button>
            <button type="button" @click="confirm()" class="btn-primary flex-1">Tasdiqlash</button>
        </div>
    </div>
</div>
