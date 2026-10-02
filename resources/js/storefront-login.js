const $ = window.jQuery;

if ($) {
    $(function () {
        // Login behavior binds only when the Storefront auth root is present.
        const $authPage = $('[data-auth-page]');
        if (!$authPage.length) return;
        if ($authPage.data('storefrontLoginInitialized')) return;
        $authPage.data('storefrontLoginInitialized', true);

        const $loginTabButton = $('#loginTabButton');
        const $registerTabButton = $('#registerTabButton');
        const $loginPanel = $('#loginPanel');
        const $registerPanel = $('#registerPanel');

        // Local tab switch only; the login form keeps its native POST and register stays backend-inert.
        function activateAuthTab(target) {
            const isLogin = target === 'login';

            $loginTabButton
                .attr('aria-selected', isLogin ? 'true' : 'false')
                .toggleClass('bg-olive text-surface shadow-sm', isLogin)
                .toggleClass('text-muted', !isLogin);
            $registerTabButton
                .attr('aria-selected', !isLogin ? 'true' : 'false')
                .toggleClass('bg-olive text-surface shadow-sm', !isLogin)
                .toggleClass('text-muted', isLogin);

            $loginPanel.toggleClass('hidden', !isLogin).toggleClass('flex', isLogin);
            $registerPanel.toggleClass('hidden', isLogin).toggleClass('flex', !isLogin);
        }

        $loginTabButton.on('click', function () {
            activateAuthTab('login');
        });
        $registerTabButton.on('click', function () {
            activateAuthTab('register');
        });

        // Lucide replaces the original <i> with an <svg>, so swap in a fresh placeholder and re-render it.
        $authPage.on('click', '[data-toggle-password]', function () {
            const $toggleButton = $(this);
            const $passwordInput = $toggleButton.closest('span').find('.password-input');
            const isHidden = $passwordInput.attr('type') === 'password';
            const $currentIcon = $toggleButton.children('svg, i').first();
            const iconClass = ($currentIcon.attr('class') || '')
                .split(/\s+/)
                .filter((className) => className && className !== 'lucide' && !className.startsWith('lucide-'))
                .join(' ');

            $passwordInput.attr('type', isHidden ? 'text' : 'password');
            $toggleButton.attr('aria-label', isHidden ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
            $currentIcon.replaceWith($('<i>').attr('data-lucide', isHidden ? 'eye-off' : 'eye').addClass(iconClass));
            window.lucide?.createIcons();
        });

        const $forgotPasswordModal = $('#forgotPasswordModal');
        const $forgotPasswordForm = $('#forgotPasswordForm');

        $('[data-open-forgot-password]').on('click', function () {
            $forgotPasswordModal.removeClass('hidden');
            $('body').addClass('overflow-hidden');
            $forgotPasswordForm.removeClass('hidden').siblings('[data-forgot-success]').addClass('hidden');
            $forgotPasswordForm[0].reset();
        });

        function closeForgotPasswordModal() {
            $forgotPasswordModal.addClass('hidden');
            $('body').removeClass('overflow-hidden');
        }

        $('[data-close-forgot-password]').on('click', closeForgotPasswordModal);
        $forgotPasswordModal.on('click', function (event) {
            if ($(event.target).is('#forgotPasswordModal')) {
                closeForgotPasswordModal();
            }
        });
        $(document).on('keydown.storefrontLogin', function (event) {
            if (event.key === 'Escape' && !$forgotPasswordModal.hasClass('hidden')) {
                closeForgotPasswordModal();
            }
        });

        // Forgot-password backend is deferred: block the implicit single-field Enter submit and do nothing else.
        $forgotPasswordForm.on('submit', function (event) {
            event.preventDefault();
        });
    });
}
