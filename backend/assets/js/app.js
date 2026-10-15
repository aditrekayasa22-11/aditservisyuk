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

    /* --- 4a. Total biaya servis mengikuti pilihan jenis servis --- */
    var serviceTypeOptions = document.querySelector('[data-service-type-options]');
    var serviceTotal = document.querySelector('[data-service-total]');
    if (serviceTypeOptions && serviceTotal) {
        var servicePriceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
        var updateServiceTotal = function () {
            var total = 0;
            serviceTypeOptions.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
                total += Number(input.getAttribute('data-service-price')) || 0;
            });
            serviceTotal.value = 'Rp ' + servicePriceFormat.format(total);
        };
        serviceTypeOptions.addEventListener('change', updateServiceTotal);
        updateServiceTotal();
    }

    /* --- 4b. Pemilihan kendaraan berdasarkan nama pelanggan atau nomor polisi --- */
    var serviceCustomer = document.querySelector('[data-service-customer]');
    var serviceVehicle = document.querySelector('[data-service-vehicle]');
    if (serviceCustomer && serviceVehicle) {
        var filterServiceVehicles = function () {
            var customerId = serviceCustomer.value;
            var matchingOptions = [];

            serviceVehicle.querySelectorAll('option[data-customer-id]').forEach(function (option) {
                var matchesCustomer = !customerId || option.getAttribute('data-customer-id') === customerId;
                option.hidden = !matchesCustomer;
                if (matchesCustomer) { matchingOptions.push(option); }
            });

            var selectedOption = serviceVehicle.options[serviceVehicle.selectedIndex];
            if (selectedOption && selectedOption.hasAttribute('data-customer-id')
                && selectedOption.getAttribute('data-customer-id') !== customerId && customerId) {
                serviceVehicle.value = '';
            }
            if (customerId && matchingOptions.length === 1) {
                serviceVehicle.value = matchingOptions[0].value;
            }
        };

        serviceCustomer.addEventListener('change', filterServiceVehicles);
        serviceVehicle.addEventListener('change', function () {
            var selectedOption = serviceVehicle.options[serviceVehicle.selectedIndex];
            if (selectedOption && selectedOption.hasAttribute('data-customer-id')) {
                serviceCustomer.value = selectedOption.getAttribute('data-customer-id');
                filterServiceVehicles();
            }
        });
        filterServiceVehicles();
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
})();
