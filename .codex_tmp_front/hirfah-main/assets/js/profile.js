$(function () {
  const $profilePage = $('[data-profile-page]');
  if (!$profilePage.length) return;

  function showProfileToast(message) {
    $('#toast').text(message).removeClass('-translate-y-24');
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(function () {
      $('#toast').addClass('-translate-y-24');
    }, 2200);
  }

  $('#personalInfoForm').on('submit', function (event) {
    event.preventDefault();
    showProfileToast('تم حفظ معلوماتك الشخصية');
  });

  const $addressList = $('#addressList');
  const $addressEmptyState = $('[data-address-empty-state]');

  function syncAddressEmptyState() {
    const hasAddresses = $addressList.find('.address-list-card').length > 0;
    $addressList.toggleClass('hidden', !hasAddresses);
    $addressEmptyState.toggleClass('hidden', hasAddresses).toggleClass('flex', !hasAddresses);
  }

  $addressList.on('click', '[data-remove-address]', function () {
    $(this).closest('.address-list-card').remove();
    syncAddressEmptyState();
    showProfileToast('تم حذف العنوان');
  });

  $addressList.on('click', '[data-edit-address]', function () {
    const $card = $(this).closest('.address-list-card');
    openAddressModal($card.find('strong').first().text());
  });

  const $addressModal = $('#addressModal');
  const $addressForm = $('#addressForm');
  const $addressModalTitle = $('#addressModalTitle');

  function openAddressModal(editingLabel) {
    $addressModalTitle.text(editingLabel ? 'تعديل العنوان' : 'إضافة عنوان جديد');
    $addressModal.removeClass('hidden');
    $('body').addClass('overflow-hidden');
  }

  function closeAddressModal() {
    $addressModal.addClass('hidden');
    $('body').removeClass('overflow-hidden');
    $addressForm[0].reset();
  }

  $('[data-open-address-modal]').on('click', function () {
    openAddressModal(null);
  });
  $('[data-close-address-modal]').on('click', closeAddressModal);
  $addressModal.on('click', function (event) {
    if ($(event.target).is('#addressModal')) {
      closeAddressModal();
    }
  });
  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && !$addressModal.hasClass('hidden')) {
      closeAddressModal();
    }
  });

  $addressForm.on('submit', function (event) {
    event.preventDefault();

    const label = $('input[name="addressLabel"]').val().trim();
    const phone = $('input[name="addressPhone"]').val().trim();
    const city = $('select[name="addressCity"]').val();
    const details = $('textarea[name="addressDetails"]').val().trim();

    if (!label || !phone || !details) {
      return;
    }

    const $newCard = $(`
      <article class="address-list-card flex flex-col gap-3 rounded-[16px] border border-line bg-canvas p-4 text-start">
        <div class="flex items-start justify-between gap-3">
          <span class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-olive/10 text-olive"><i data-lucide="map-pin" class="h-4 w-4"></i></span>
            <strong class="text-sm font-bold leading-6 text-ink"></strong>
          </span>
        </div>
        <p class="address-list-details text-sm leading-6 text-muted"></p>
        <div class="mt-1 flex items-center gap-3 border-t border-line/70 pt-3 text-xs font-bold">
          <button type="button" data-edit-address class="text-olive underline underline-offset-2">تعديل</button>
          <button type="button" data-remove-address class="text-[#A13D2B] underline underline-offset-2">حذف</button>
        </div>
      </article>
    `);

    $newCard.find('strong').text(label);
    $newCard.find('.address-list-details').text(`${city}، ${details} — ${phone}`);

    $addressList.append($newCard);
    syncAddressEmptyState();

    if (window.lucide) {
      window.lucide.createIcons();
    }

    closeAddressModal();
    showProfileToast('تم حفظ العنوان');
  });

  syncAddressEmptyState();

  $('#passwordForm').on('submit', function (event) {
    event.preventDefault();

    const $form = $(this);
    const $error = $form.find('[data-password-error]');
    const currentPassword = $('input[name="currentPassword"]').val();
    const newPassword = $('input[name="newPassword"]').val();
    const confirmPassword = $('input[name="confirmPassword"]').val();

    $error.addClass('hidden').text('');

    if (!currentPassword || !newPassword || !confirmPassword) {
      $error.removeClass('hidden').text('الرجاء تعبئة كل الحقول.');
      return;
    }

    if (newPassword.length < 8) {
      $error.removeClass('hidden').text('كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.');
      return;
    }

    if (newPassword !== confirmPassword) {
      $error.removeClass('hidden').text('كلمة المرور الجديدة وتأكيدها غير متطابقين.');
      return;
    }

    $form[0].reset();
    showProfileToast('تم تحديث كلمة المرور');
  });
});
