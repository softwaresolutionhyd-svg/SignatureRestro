<script>
(function () {
    document.querySelectorAll('form').forEach(function (form) {
        const personEl = form.querySelector('[name="person_type"]');
        if (! personEl) {
            return;
        }

        const paWrap = form.querySelector('.guest-field-pa-wrap');
        const rankWrap = form.querySelector('.guest-field-rank-wrap');
        const coWrap = form.querySelector('.guest-field-co-wrap');
        const paInput = form.querySelector('[name="pa_no"]');
        const rankInput = form.querySelector('[name="guest_rank"]');
        const coInput = form.querySelector('[name="care_of"]');

        function isCivilian() {
            return (personEl.value || '').trim().toLowerCase() === 'civilian';
        }

        function toggleGuestFields() {
            const civilian = isCivilian();
            paWrap?.classList.toggle('d-none', civilian);
            rankWrap?.classList.toggle('d-none', civilian);
            coWrap?.classList.toggle('d-none', ! civilian);
            if (civilian) {
                if (paInput) {
                    paInput.value = '';
                }
                if (rankInput) {
                    rankInput.value = '';
                }
            } else if (coInput) {
                coInput.value = '';
            }
        }

        personEl.addEventListener('change', toggleGuestFields);
        toggleGuestFields();
    });
})();
</script>
