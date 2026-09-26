$(function () {
  const $authPage = $('[data-auth-page]');
  if (!$authPage.length) return;

  function showAuthToast(message) {
    $('#toast').text(message).removeClass('-translate-y-24');
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(function () {
      $('#toast').addClass('-translate-y-24');
    }, 2200);
  }

  const $loginTabButton = $('#loginTabButton');
  const $registerTabButton = $('#registerTabButton');
  const $loginPanel = $('#loginPanel');
  const $registerPanel = $('#registerPanel');

  function activateTab(target) {
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
    activateTab('login');
  });
  $registerTabButton.on('click', function () {
    activateTab('register');
  });

  activateTab('login');

  $authPage.on('click', '[data-toggle-password]', function () {
    const $button = $(this);
    const $input = $button.closest('span').find('.password-input');
    const isHidden = $input.attr('type') === 'password';

    $input.attr('type', isHidden ? 'text' : 'password');
    $button.find('i').attr('data-lucide', isHidden ? 'eye-off' : 'eye');
    $button.attr('aria-label', isHidden ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');

    if (window.lucide) {
      window.lucide.createIcons();
    }
  });

  function showFormError($form, message) {
    $form.find('[data-form-error]').text(message).removeClass('hidden');
  }

  function clearFormError($form) {
    $form.find('[data-form-error]').addClass('hidden').text('');
  }

  $loginPanel.on('submit', function (event) {
    event.preventDefault();
    clearFormError($loginPanel);

    const identifier = $('input[name="loginIdentifier"]').val().trim();
    const password = $('input[name="loginPassword"]').val();

    if (!identifier || !password) {
      showFormError($loginPanel, 'الرجاء تعبئة البريد أو رقم الجوال وكلمة المرور.');
      return;
    }

    showAuthToast('تم تسجيل الدخول بنجاح');
  });

  $registerPanel.on('submit', function (event) {
    event.preventDefault();
    clearFormError($registerPanel);

    const name = $('input[name="registerName"]').val().trim();
    const identifier = $('input[name="registerIdentifier"]').val().trim();
    const password = $('input[name="registerPassword"]').val();

    if (!name || !identifier || !password) {
      showFormError($registerPanel, 'الرجاء تعبئة الاسم والبريد أو رقم الجوال وكلمة المرور.');
      return;
    }

    if (password.length < 8) {
      showFormError($registerPanel, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.');
      return;
    }

    showAuthToast('تم إنشاء الحساب بنجاح');
  });

  const $forgotPasswordModal = $('#forgotPasswordModal');

  $('[data-open-forgot-password]').on('click', function () {
    $forgotPasswordModal.removeClass('hidden');
    $('body').addClass('overflow-hidden');
    $('#forgotPasswordForm').removeClass('hidden').siblings('[data-forgot-success]').addClass('hidden');
    $('#forgotPasswordForm')[0].reset();
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
  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && !$forgotPasswordModal.hasClass('hidden')) {
      closeForgotPasswordModal();
    }
  });

  $('#forgotPasswordForm').on('submit', function (event) {
    event.preventDefault();
    $(this).addClass('hidden');
    $(this).siblings('[data-forgot-success]').removeClass('hidden').addClass('flex');
  });
});
