<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/**
 * Aksi halaman stok: tambah, perbarui, dan hapus barang.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_stock') {
            $id = (int) ($_POST['inventory_id'] ?? 0);
            $values = [
                'name' => trim((string) ($_POST['stock_name'] ?? '')),
                'sku' => trim((string) ($_POST['sku'] ?? '')) ?: null,
                'quantity' => max(0, (int) ($_POST['quantity'] ?? 0)),
                'unit' => trim((string) ($_POST['unit'] ?? 'pcs')) ?: 'pcs',
                'purchase_price' => max(0, (float) ($_POST['purchase_price'] ?? 0)),
            ];
            if ($values['name'] === '') {
                throw new InvalidArgumentException('Nama barang wajib diisi.');
            }
            if ($id > 0) {
                $values['id'] = $id;
                $statement = $pdo->prepare('UPDATE inventory SET name=:name, sku=:sku, quantity=:quantity, unit=:unit, purchase_price=:purchase_price WHERE id=:id');
            } else {
                $statement = $pdo->prepare('INSERT INTO inventory (name, sku, quantity, unit, purchase_price) VALUES (:name, :sku, :quantity, :unit, :purchase_price)');
            }
            $statement->execute($values);
            flash($id > 0 ? 'Stok berhasil diperbarui.' : 'Barang berhasil ditambahkan ke stok.');
        } elseif ($action === 'delete_stock') {
            $statement = $pdo->prepare('DELETE FROM inventory WHERE id = :id');
            $statement->execute(['id' => (int) ($_POST['inventory_id'] ?? 0)]);
            flash('Barang berhasil dihapus dari stok.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        flash('Data gagal disimpan. SKU mungkin sudah digunakan barang lain.', 'error');
    }
    redirect('stok.php');
}

$inventory = $pdo->query('SELECT id, name, sku, quantity, unit, purchase_price FROM inventory ORDER BY name')->fetchAll();
$totalItems = 0;
$totalValue = 0.0;
foreach ($inventory as $item) {
    $totalItems += (int) $item['quantity'];
    $totalValue += (float) $item['purchase_price'] * (int) $item['quantity'];
}

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($inventory as $item) {
        if ((int) $item['id'] === $editId) {
            $editing = $item;
            break;
        }
    }
}

$flashMessage = take_flash();
$pageTitle = 'Stok Barang';
$adminView = true;
$currentPage = 'stok';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">PERSEDIAAN BENGKEL</span>
            <h1>Stok Barang</h1>
            <p>Kelola suku cadang dan kebutuhan bengkel lainnya.</p>
        </div>
        <span class="date-chip"><?= count($inventory) ?> jenis barang</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="stat-grid">
        <article class="stat-card"><span class="stat-icon icon-navy"><?= icon('package') ?></span><span class="stat-label">JENIS BARANG</span><strong><?= count($inventory) ?></strong><small>Kode SKU terdaftar</small></article>
        <article class="stat-card"><span class="stat-icon icon-orange"><?= icon('boxes') ?></span><span class="stat-label">TOTAL UNIT</span><strong><?= $totalItems ?></strong><small>Sum seluruh stok</small></article>
        <article class="stat-card"><span class="stat-icon icon-green"><?= icon('coins') ?></span><span class="stat-label">NILAI STOK</span><strong class="stat-money"><?= rupiah($totalValue) ?></strong><small>Harga beli &times; jumlah</small></article>
    </section>

    <section class="admin-section" id="form-stok">
        <div class="section-heading">
            <div><span class="eyebrow"><?= $editing ? 'UBAH BARANG' : 'BARANG BARU' ?></span><h2><?= $editing ? 'Edit barang' : 'Tambah barang' ?></h2></div>
            <?php if ($editing): ?><a class="button button-ghost button-sm" href="stok.php">Batal edit</a><?php endif; ?>
        </div>
        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_stock">
            <input type="hidden" name="inventory_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="form-grid">
                <div><label>NAMA BARANG *</label><input class="text-input" name="stock_name" value="<?= e($editing['name'] ?? '') ?>" required></div>
                <div><label>KODE SKU (OPSIONAL)</label><input class="text-input" name="sku" value="<?= e($editing['sku'] ?? '') ?>"></div>
                <div><label>JUMLAH</label><input class="text-input" name="quantity" type="number" min="0" value="<?= (int) ($editing['quantity'] ?? 0) ?>"></div>
                <div><label>SATUAN</label><input class="text-input" name="unit" value="<?= e($editing['unit'] ?? 'pcs') ?>"></div>
                <div><label>HARGA BELI (RP)</label><input class="text-input" name="purchase_price" type="number" min="0" step="1000" value="<?= e((string) ($editing['purchase_price'] ?? '0')) ?>"></div>
            </div>
            <button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan barang' ?> <?= icon('arrow-right') ?></button>
        </form>
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">DAFTAR STOK</span><h2>Barang tersedia</h2></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>NAMA BARANG</th><th>SKU</th><th>STOK</th><th>HARGA BELI</th><th>AKSI</th></tr></thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><strong><?= e($item['name']) ?></strong></td>
                            <td><?= e($item['sku'] ?: '—') ?></td>
                            <td>
                                <form method="post" class="inline-stock-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="save_stock">
                                    <input type="hidden" name="inventory_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="stock_name" value="<?= e($item['name']) ?>">
                                    <input type="hidden" name="sku" value="<?= e($item['sku'] ?? '') ?>">
                                    <input type="hidden" name="unit" value="<?= e($item['unit']) ?>">
                                    <input type="hidden" name="purchase_price" value="<?= e((string) $item['purchase_price']) ?>">
                                    <input type="number" name="quantity" min="0" value="<?= (int) $item['quantity'] ?>" aria-label="Jumlah stok baru">
                                    <span class="text-muted"><?= e($item['unit']) ?></span>
                                    <button class="text-button" type="submit"><?= icon('refresh-cw') ?> Perbarui</button>
                                </form>
                            </td>
                            <td><?= rupiah($item['purchase_price']) ?></td>
                            <td>
                                <div style="display:flex;gap:12px">
                                    <a class="text-button" href="stok.php?edit=<?= (int) $item['id'] ?>#form-stok"><?= icon('pencil') ?> Edit</a>
                                    <form method="post" data-confirm="Hapus barang ini dari stok?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_stock">
                                        <input type="hidden" name="inventory_id" value="<?= (int) $item['id'] ?>">
                                        <button class="text-button" type="submit" style="color:var(--red)"><?= icon('trash-2') ?> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inventory): ?>
                        <tr><td colspan="5" class="table-empty">Stok masih kosong.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
