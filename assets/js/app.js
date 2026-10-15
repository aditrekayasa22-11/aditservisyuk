/* =========================================================================
   ServisYuk - JavaScript murni (tanpa library)
   Bertugas: menu mobile, animasi scroll, auto-dismiss pesan, fokus form,
   proteksi konfirmasi, dan status memuat pada form cek status pelanggan.
   ========================================================================= */
(function () {
    'use strict';

    /* --- 1. Toggle menu mobile --- */
    var toggle = document.querySelector('.nav-toggle');
    var menu = document.querySelector('.nav-menu');
    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        menu.addEventListener('click', function (event) {
            if (event.target.closest('.nav-link')) {
                menu.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* --- 2. Animasi scroll (reveal) --- */
    var revealTargets = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && revealTargets.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealTargets.forEach(function (element) {
            observer.observe(element);
        });
    } else {
        revealTargets.forEach(function (element) {
            element.classList.add('in-view');
        });
    }

    /* --- 3. Pesan flash hilang otomatis setelah 5 detik --- */
    document.querySelectorAll('.alert-auto').forEach(function (flash) {
        window.setTimeout(function () {
            flash.style.transition = 'opacity .4s ease';
            flash.style.opacity = '0';
            window.setTimeout(function () {
                if (flash.parentNode) { flash.remove(); }
            }, 400);
        }, 5000);
    });

    /* --- 4. Input nomor polisi: huruf besar otomatis --- */
    var plate = document.getElementById('plate_number');
    if (plate) {
        plate.addEventListener('input', function () {
            var start = plate.selectionStart;
            plate.value = plate.value.toUpperCase();
            try { plate.setSelectionRange(start, start); } catch (error) { /* type number/khusus */ }
        });
        if (!plate.value) {
            plate.focus();
        }
    }

    /* --- 5. Konfirmasi sebelum form hapus dikirim --- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* --- 6. Form cek status: tampilkan status memuat & fokus ke PIN --- */
    var lookupForm = document.querySelector('[data-lookup-form]');
    if (lookupForm) {
        lookupForm.addEventListener('submit', function (event) {
            var plateInput = lookupForm.querySelector('#plate_number');
            var pinInput = lookupForm.querySelector('#pin');
            var submit = lookupForm.querySelector('button[type="submit"]');

            if (plateInput && !plateInput.value.trim()) {
                event.preventDefault();
                plateInput.focus();
                return;
            }
            if (pinInput && !pinInput.value.trim()) {
                event.preventDefault();
                pinInput.focus();
                return;
            }
            if (submit) {
                submit.disabled = true;
                submit.dataset.label = submit.innerHTML;
                submit.innerHTML = pinInput && pinInput.value.trim()
                    ? 'Memverifikasi PIN...'
                    : 'Mencari data servis...';
            }
        });
    }

    /* --- 7. Nomor PIN: hanya angka + fokus otomatis --- */
    var pin = document.getElementById('pin');
    if (pin) {
        pin.addEventListener('input', function () {
            pin.value = pin.value.replace(/\D/g, '').slice(0, 6);
        });
        pin.focus();
    }

    /* --- 8. Tombol mata: tampilkan / sembunyikan password --- */
    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute('data-password-toggle'));
        if (!input) { return; }
        var use = toggle.querySelector('use');
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            if (use) { use.setAttribute('href', show ? '#ic-eye-off' : '#ic-eye'); }
            toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    /* --- 9. Pemilihan Metode Pembayaran (Cash, QRIS, Bank BNI/BRI/BCA/Mandiri) --- */
    document.querySelectorAll('.payment-form-interactive').forEach(function (form) {
        var radios = form.querySelectorAll('input[type="radio"][name="payment_method"]');
        var bankRadios = form.querySelectorAll('input[type="radio"][name="bank_choice"]');
        var methodBoxes = form.querySelectorAll('[data-method-box]');
        var bankBoxes = form.querySelectorAll('[data-bank-box]');
        var proofSection = form.querySelector('.proof-upload-section');
        var proofInput = form.querySelector('input[type="file"][name="proof"]');
        var submitBtn = form.querySelector('.btn-submit-payment');

        function updateMethod() {
            var selected = form.querySelector('input[type="radio"][name="payment_method"]:checked');
            var val = selected ? selected.value : 'Transfer';

            methodBoxes.forEach(function (box) {
                box.style.display = box.getAttribute('data-method-box') === val ? 'block' : 'none';
            });

            if (val === 'Tunai') {
                if (proofSection) { proofSection.style.display = 'none'; }
                if (proofInput) { proofInput.required = false; }
                if (submitBtn) {
                    submitBtn.innerHTML = '<svg class="icon"><use href="#ic-circle-check"></use></svg> Konfirmasi Pembayaran Tunai';
                }
            } else {
                if (proofSection) { proofSection.style.display = 'block'; }
                if (proofInput) { proofInput.required = true; }
                if (submitBtn) {
                    submitBtn.innerHTML = '<svg class="icon"><use href="#ic-upload"></use></svg> Kirim Bukti Pembayaran';
                }
            }
        }

        function updateBank() {
            var selectedBank = form.querySelector('input[type="radio"][name="bank_choice"]:checked');
            var bankCode = selectedBank ? selectedBank.value : 'bni';
            bankBoxes.forEach(function (bBox) {
                bBox.style.display = bBox.getAttribute('data-bank-box') === bankCode ? 'block' : 'none';
            });
        }

        radios.forEach(function (r) {
            r.addEventListener('change', updateMethod);
        });

        bankRadios.forEach(function (br) {
            br.addEventListener('change', updateBank);
        });

        updateMethod();
        updateBank();
    });

    /* --- 10. Tombol Salin Nomor Rekening --- */
    document.querySelectorAll('.btn-copy-acc').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(function () {
                    var original = btn.textContent;
                    btn.textContent = 'Tersalin!';
                    btn.style.borderColor = 'var(--green-600)';
                    btn.style.color = 'var(--green-600)';
                    window.setTimeout(function () {
                        btn.textContent = original;
                        btn.style.borderColor = '';
                        btn.style.color = '';
                    }, 2000);
                });
            }
        });
    });
})();
