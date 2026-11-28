{{-- Banner berhasil untuk layout Admin dan Kaprodi Kelompok 1. --}}
<script>
    (function () {
        document.querySelectorAll('[data-success-banner]').forEach(function (banner) {
            window.setTimeout(function () { banner.hidden = true; }, 3500);
        });

        if (document.querySelector('[data-clear-category-status]')) {
            var url = new URL(window.location.href);
            if (url.searchParams.has('status')) {
                url.searchParams.delete('status');
                window.history.replaceState(window.history.state, '', url);
            }
        }
    })();
</script>
