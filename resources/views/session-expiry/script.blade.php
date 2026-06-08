{{--
    Livewire 419 interceptor. On an expired session/CSRF token, Livewire
    normally shows a "This page has expired" modal. We instead store the
    current URL (so a host can bounce back after re-login) and redirect to the
    login screen, calling preventDefault() to suppress the default modal.

    Expects $redirectUrl (resolved server-side via SessionExpiry::redirectUrl()).
--}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status === 419) {
                    sessionStorage.setItem('redirect_after_login', window.location.href);
                    window.location.href = @js($redirectUrl ?? url('/'));
                    preventDefault();
                }
            });
        });
    });
</script>
