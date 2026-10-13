<?php
declare(strict_types=1);

/**
 * Definisi route API: [metode, pola, controller, fungsi, butuh admin]
 * Pola {id} cocok dengan satu segmen angka.
 */
return [
    // Publik
    ['GET',    '/lookup',              'lookup',        'lookup_public',      false],

    // Autentikasi
    ['GET',    '/auth/csrf',           'auth',          'get_csrf',           false],
    ['GET',    '/auth/setup-needed',   'auth',          'setup_needed',       false],
    ['POST',   '/auth/setup',          'auth',          'setup_first_admin',  false],
    ['POST',   '/auth/login',          'auth',          'login',              false],
    ['POST',   '/auth/logout',         'auth',          'logout',             true],
    ['GET',    '/auth/me',             'auth',          'me',                 true],

    // Ringkasan dashboard
    ['GET',    '/dashboard',           'dashboard',     'summary',            true],

    // Pelanggan
    ['GET',    '/customers',           'customers',     'list_items',         true],
    ['POST',   '/customers',           'customers',     'create',             true],
    ['GET',    '/customers/{id}',      'customers',     'read',               true],
    ['PUT',    '/customers/{id}',      'customers',     'update',             true],
    ['DELETE', '/customers/{id}',      'customers',     'delete',             true],

    // Kendaraan
    ['GET',    '/vehicles',            'vehicles',      'list_items',         true],
    ['POST',   '/vehicles',            'vehicles',      'create',             true],
    ['GET',    '/vehicles/{id}',       'vehicles',      'read',               true],
    ['PUT',    '/vehicles/{id}',       'vehicles',      'update',             true],
    ['DELETE', '/vehicles/{id}',       'vehicles',      'delete',             true],

    // Jenis servis
    ['GET',    '/service-types',       'service_types', 'list_items',         true],
    ['POST',   '/service-types',       'service_types', 'create',             true],
    ['GET',    '/service-types/{id}',  'service_types', 'read',               true],
    ['PUT',    '/service-types/{id}',  'service_types', 'update',             true],
    ['DELETE', '/service-types/{id}',  'service_types', 'delete',             true],

    // Catatan servis
    ['GET',    '/services',            'services',      'list_items',         true],
    ['POST',   '/services',            'services',      'create',             true],
    ['GET',    '/services/{id}',       'services',      'read',               true],
    ['PUT',    '/services/{id}',       'services',      'update',             true],
    ['DELETE', '/services/{id}',       'services',      'delete',             true],

    // Stok barang
    ['GET',    '/inventory',           'inventory',     'list_items',         true],
    ['POST',   '/inventory',           'inventory',     'create',             true],
    ['GET',    '/inventory/{id}',      'inventory',     'read',               true],
    ['PUT',    '/inventory/{id}',      'inventory',     'update',             true],
    ['DELETE', '/inventory/{id}',      'inventory',     'delete',             true],

    // Pembayaran
    ['GET',    '/payments',            'payments',      'list_items',         true],
    ['POST',   '/payments',            'payments',      'upsert',             true],
    ['DELETE', '/payments/{id}',       'payments',      'delete',             true],
];
