<?php
/**
 * LUKUABIPOOD OÜ — Adminpaneel
 * Avage: http://localhost:8080/admin.php
 * Muutke parool enne serverisse üleslaadimist!
 */

define('ADMIN_PASSWORD', 'roman');
define('SITE_DIR', __DIR__);
define('PRODUCTS_FILE', SITE_DIR . '/products.html');

session_start();

// ── Auth ──────────────────────────────────────────────
if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) $_SESSION['admin'] = true;
    else $login_error = 'Vale parool!';
}
if (!isset($_SESSION['admin'])) { show_login(isset($login_error) ? $login_error : null); exit; }

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'main';

// ── Helpers ──────────────────────────────────────────
function get_html_files() { return glob(SITE_DIR . '/*.html'); }

function replace_in_all_files($search, $replace) {
    $count = 0;
    foreach (get_html_files() as $file) {
        $c = file_get_contents($file);
        if (strpos($c, $search) !== false) { file_put_contents($file, str_replace($c, str_replace($search, $replace, $c), $c)); $count++; }
    }
    return $count;
}

function replace_in_all($search, $replace) {
    $count = 0;
    foreach (get_html_files() as $file) {
        $c = file_get_contents($file);
        if (strpos($c, $search) !== false) {
            file_put_contents($file, str_replace($search, $replace, $c));
            $count++;
        }
    }
    return $count;
}

// ── Read PRODUCTS from products.html ─────────────────
function read_products() {
    $content = file_get_contents(PRODUCTS_FILE);
    if (preg_match('/const PRODUCTS=\[(.*?)\];/s', $content, $m)) {
        $js = '[' . $m[1] . ']';
        // Convert JS object notation to JSON
        // Remove JS comments
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('#//[^\n]*#', '', $js);
        // Quote unquoted keys: id: → "id":
        $js = preg_replace("/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)\s*:/", '$1"$2":', $js);
        // Convert single quotes to double
        $js = preg_replace("/(?<!\\\\)'/", '"', $js);
        // Remove trailing commas
        $js = preg_replace('/,(\s*[}\]])/s', '$1', $js);
        $products = json_decode($js, true);
        return $products ?: [];
    }
    return [];
}

// ── Write PRODUCTS back to products.html ─────────────
function write_products($products) {
    $content = file_get_contents(PRODUCTS_FILE);
    // Build JS array
    $lines = [];
    foreach ($products as $p) {
        $id    = addslashes($p['id'] ?? '');
        $cat   = addslashes($p['cat'] ?? '');
        $brand = addslashes($p['brand'] ?? '');
        $et    = addslashes($p['et'] ?? '');
        $ru    = addslashes($p['ru'] ?? '');
        $en    = addslashes($p['en'] ?? '');
        $imgs  = isset($p['imgs']) && is_array($p['imgs']) ? $p['imgs'] : [];
        $tags  = isset($p['tags']) && is_array($p['tags']) ? $p['tags'] : [];
        $imgs_js = "['" . implode("','", array_map('addslashes', $imgs)) . "']";
        if (empty($imgs)) $imgs_js = '[]';
        $tags_js = "['" . implode("','", array_map('addslashes', $tags)) . "']";
        if (empty($tags)) $tags_js = '[]';
        $lines[] = "  {id:'$id',imgs:$imgs_js,cat:'$cat',brand:'$brand',et:'$et',ru:'$ru',en:'$en',tags:$tags_js}";
    }
    $new_array = "const PRODUCTS=[\n  /* imgs:[] = placeholder; imgs:['a.jpg','b.jpg'] = carousel */\n" . implode(",\n", $lines) . "\n]";
    $content = preg_replace('/const PRODUCTS=\[.*?\];/s', $new_array . ';', $content);
    file_put_contents(PRODUCTS_FILE, $content);
}

// ── Process actions ───────────────────────────────────
$message = '';
$msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Products: delete
    if ($action === 'delete_product') {
        $del_id = $_POST['product_id'];
        $products = read_products();
        $products = array_values(array_filter($products, fn($p) => $p['id'] !== $del_id));
        write_products($products);
        $message = "Toode '$del_id' kustutatud.";
        $tab = 'products';
    }

    // Products: add
    if ($action === 'add_product') {
        $products = read_products();
        $id = preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim($_POST['p_id'])));
        // Check duplicate
        $exists = array_filter($products, fn($p) => $p['id'] === $id);
        if ($exists) {
            $message = "ID '$id' on juba olemas!";
            $msg_type = 'err';
        } else {
            $imgs_raw = trim($_POST['p_imgs']);
            $imgs = $imgs_raw ? array_map('trim', explode(',', $imgs_raw)) : [];
            $tags_raw = trim($_POST['p_tags']);
            $tags = $tags_raw ? array_map('trim', explode(',', strtolower($tags_raw))) : [];
            $products[] = [
                'id'    => $id,
                'imgs'  => $imgs,
                'cat'   => trim($_POST['p_cat']),
                'brand' => trim($_POST['p_brand']),
                'et'    => trim($_POST['p_et']),
                'ru'    => trim($_POST['p_ru']),
                'en'    => trim($_POST['p_en']),
                'tags'  => $tags,
            ];
            write_products($products);
            $message = "Toode '$id' lisatud!";
        }
        $tab = 'products';
    }

    // Products: edit
    if ($action === 'edit_product') {
        $products = read_products();
        $edit_id = $_POST['product_id'];
        foreach ($products as &$p) {
            if ($p['id'] === $edit_id) {
                $imgs_raw = trim($_POST['p_imgs']);
                $p['imgs']  = $imgs_raw ? array_map('trim', explode(',', $imgs_raw)) : [];
                $tags_raw = trim($_POST['p_tags']);
                $p['tags']  = $tags_raw ? array_map('trim', explode(',', strtolower($tags_raw))) : [];
                $p['cat']   = trim($_POST['p_cat']);
                $p['brand'] = trim($_POST['p_brand']);
                $p['et']    = trim($_POST['p_et']);
                $p['ru']    = trim($_POST['p_ru']);
                $p['en']    = trim($_POST['p_en']);
                break;
            }
        }
        write_products($products);
        $message = "Toode '$edit_id' uuendatud.";
        $tab = 'products';
    }

    // Main tab actions
    if ($action === 'update_phone') {
        $old = trim($_POST['old_phone']); $new = trim($_POST['new_phone']);
        $old_t = preg_replace('/[^0-9+]/', '', $old);
        $new_t = preg_replace('/[^0-9+]/', '', $new);
        if ($new_t) {
            $c1 = replace_in_all($old, $new);
            $c2 = replace_in_all($old_t, $new_t);
            replace_in_all('href="tel:'.$old_t.'"', 'href="tel:'.$new_t.'"');
            $message = "Telefon uuendatud.";
        } else { $message = 'Vigane number!'; $msg_type = 'err'; }
    }
    if ($action === 'update_email') {
        $old = trim($_POST['old_email']); $new = trim($_POST['new_email']);
        if (filter_var($new, FILTER_VALIDATE_EMAIL)) {
            $c = replace_in_all($old, $new);
            $message = "E-post uuendatud $c failis.";
        } else { $message = 'Vigane e-post!'; $msg_type = 'err'; }
    }
    if ($action === 'update_price') {
        $old = trim($_POST['old_price']); $new = trim($_POST['new_price']);
        if ($old && $new) { $c = replace_in_all($old, $new); $message = "Hind uuendatud $c failis."; }
        else { $message = 'Täitke mõlemad väljad!'; $msg_type = 'err'; }
    }
    if ($action === 'text_replace') {
        $old = $_POST['old_text']; $new = $_POST['new_text']; $target = $_POST['target_file'];
        if ($old) {
            if ($target === 'all') { $c = replace_in_all($old, $new); $message = "Asendatud $c failis."; }
            else {
                $file = SITE_DIR . '/' . basename($target);
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    $cnt = substr_count($content, $old);
                    file_put_contents($file, str_replace($old, $new, $content));
                    $message = "Asendatud $cnt korda failis " . basename($target) . ".";
                } else { $message = 'Faili ei leitud!'; $msg_type = 'err'; }
            }
        }
    }
    if ($action === 'update_meta') {
        $file = SITE_DIR . '/' . basename($_POST['meta_file']);
        $desc = htmlspecialchars(trim($_POST['new_desc']), ENT_QUOTES);
        if (file_exists($file) && $desc) {
            $c = file_get_contents($file);
            $c = preg_replace('/<meta name="description" content="[^"]*">/', '<meta name="description" content="'.$desc.'">', $c);
            file_put_contents($file, $c);
            $message = 'Meta kirjeldus uuendatud.';
        }
    }
    if ($action === 'backup') {
        $dir = SITE_DIR . '/backups';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = $dir . '/backup_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($name, ZipArchive::CREATE) === true) {
            foreach (get_html_files() as $f) $zip->addFile($f, basename($f));
            $zip->addFile(SITE_DIR . '/css/style.css', 'css/style.css');
            $zip->close();
            $message = 'Varukoopia loodud: ' . basename($name);
        } else { $message = 'Varukoopia loomine ebaõnnestus!'; $msg_type = 'err'; }
    }
}

// ── Current values ────────────────────────────────────
$index = file_get_contents(SITE_DIR . '/index.html');
preg_match('/href="tel:([^"]+)"/', $index, $m_tel);
$current_phone = isset($m_tel[1]) ? $m_tel[1] : '+37256817777';
preg_match('/href="mailto:([^"]+)"/', $index, $m_em);
$current_email = isset($m_em[1]) ? $m_em[1] : 'info@lukuabi-24.ee';
$html_count = count(get_html_files());
$backups = is_dir(SITE_DIR . '/backups') ? array_reverse(glob(SITE_DIR . '/backups/*.zip')) : [];

$CATS = ['Lukukomplektid','Lukukorpused','Elektrilukud','Nutilukud','Turvalukud','Uksesulgurid','Fonolukud','Koodlukud'];

// ── Product for editing ───────────────────────────────
$edit_product = null;
if (isset($_GET['edit'])) {
    $products_all = read_products();
    foreach ($products_all as $p) {
        if ($p['id'] === $_GET['edit']) { $edit_product = $p; break; }
    }
}

// ═══════════════════════════════════════════════════════
// LOGIN
// ═══════════════════════════════════════════════════════
function show_login($error = null) { ?>
<!DOCTYPE html><html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#0f2244;min-height:100vh;display:flex;align-items:center;justify-content:center}.box{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,.3)}h1{font-size:1.2rem;margin-bottom:4px;color:#0f2244}.sub{font-size:.84rem;color:#7a8fa8;margin-bottom:24px}label{display:block;font-size:.76rem;font-weight:700;color:#374a66;margin-bottom:6px}input{width:100%;padding:11px 14px;border:1.5px solid #d4dff0;border-radius:8px;font-size:.95rem;outline:none;font-family:inherit}input:focus{border-color:#1a3a6b}button{width:100%;padding:12px;background:#1a3a6b;color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;margin-top:14px;font-family:inherit}.err{background:#fff0f0;border:1px solid #f5c6c6;border-radius:8px;padding:10px 14px;color:#d42b2b;font-size:.84rem;margin-bottom:14px}.icon{font-size:2.5rem;text-align:center;margin-bottom:14px}</style>
</head><body><div class="box"><div class="icon">🔐</div><h1>LUKUABIPOOD Admin</h1><p class="sub">Sisestage parool</p><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><form method="POST"><label>Parool</label><input type="password" name="password" autofocus><button type="submit">Sisene →</button></form></div></body></html>
<?php }

// ═══════════════════════════════════════════════════════
// MAIN UI
// ═══════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – LUKUABIPOOD</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f0f4f8;color:#0f1f3d;min-height:100vh}
a{text-decoration:none;color:inherit}
header{background:#0f2244;color:#fff;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.2)}
.hdr{max-width:1200px;margin:0 auto;padding:0 20px;height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.hdr-logo{font-weight:800;font-size:.95rem}
.nav-tabs{display:flex;gap:2px;flex:1;margin:0 24px}
.nav-tab{padding:8px 16px;border-radius:8px;font-size:.84rem;font-weight:600;color:rgba(255,255,255,.6);cursor:pointer;transition:.2s;white-space:nowrap}
.nav-tab:hover,.nav-tab.active{background:rgba(255,255,255,.15);color:#fff}
.hdr-right{display:flex;align-items:center;gap:12px}
.hdr-right span{font-size:.78rem;opacity:.5}
.logout{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:.8rem;cursor:pointer;font-family:inherit}
.logout:hover{background:rgba(255,255,255,.22)}
.wrap{max-width:1200px;margin:0 auto;padding:24px 20px}
.msg{padding:12px 16px;border-radius:10px;font-size:.88rem;font-weight:600;margin-bottom:20px}
.msg.ok{background:#e8f5ee;border:1px solid #a3d4b5;color:#1a7a40}
.msg.err{background:#fff0f0;border:1px solid #f5c6c6;color:#d42b2b}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.stat{background:#fff;border-radius:12px;padding:16px 18px;border:1px solid #d4dff0}
.stat strong{display:block;font-size:1.6rem;font-weight:800;color:#1a3a6b;line-height:1}
.stat span{font-size:.76rem;color:#7a8fa8;margin-top:3px;display:block}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.card{background:#fff;border-radius:14px;border:1px solid #d4dff0;padding:22px;box-shadow:0 2px 8px rgba(15,34,68,.05)}
.card h2{font-size:.9rem;font-weight:700;margin-bottom:3px}
.card .desc{font-size:.78rem;color:#7a8fa8;margin-bottom:16px}
label{display:block;font-size:.74rem;font-weight:700;color:#374a66;margin-bottom:4px;margin-top:10px;letter-spacing:.04em}
input[type=text],input[type=email],input[type=password],textarea,select{width:100%;padding:9px 11px;border:1.5px solid #d4dff0;border-radius:8px;font-size:.88rem;outline:none;font-family:inherit;background:#f7f9fc;color:#0f1f3d}
input:focus,textarea:focus,select:focus{border-color:#1a3a6b;background:#fff;box-shadow:0 0 0 3px rgba(26,58,107,.08)}
textarea{resize:vertical;min-height:72px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-weight:700;font-size:.84rem;cursor:pointer;border:none;font-family:inherit;transition:.2s}
.btn-navy{background:#1a3a6b;color:#fff}.btn-navy:hover{background:#2b5299}
.btn-red{background:#d42b2b;color:#fff}.btn-red:hover{background:#b02020}
.btn-green{background:#1a7a40;color:#fff}.btn-green:hover{background:#145e31}
.btn-sm{padding:5px 12px;font-size:.78rem}
.btn-ghost{background:transparent;border:1.5px solid #d4dff0;color:#374a66}.btn-ghost:hover{border-color:#1a3a6b;color:#1a3a6b}
.full{grid-column:1/-1}
.mt{margin-top:12px}
/* Products table */
.prod-table{width:100%;border-collapse:collapse;font-size:.84rem}
.prod-table th{background:#1a3a6b;color:#fff;padding:10px 12px;text-align:left;font-weight:600;font-size:.76rem;letter-spacing:.04em}
.prod-table td{padding:10px 12px;border-bottom:1px solid #eef3fb;vertical-align:middle}
.prod-table tr:hover td{background:#f7f9fc}
.prod-table .brand-badge{display:inline-block;background:#eef3fb;color:#1a3a6b;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:700}
.prod-table .cat-badge{display:inline-block;background:#f0f4f8;color:#374a66;padding:2px 8px;border-radius:4px;font-size:.72rem}
.prod-table .actions{display:flex;gap:6px;align-items:center}
.prod-search{display:flex;gap:10px;margin-bottom:16px;align-items:center}
.prod-search input{max-width:300px}
.prod-search select{max-width:200px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.imgs-list{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.img-chip{background:#eef3fb;border-radius:5px;padding:3px 8px;font-size:.72rem;color:#1a3a6b}
.count-badge{background:#d42b2b;color:#fff;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;margin-left:4px}
.edit-form{background:#f0f4f8;border-radius:12px;padding:20px;margin-bottom:20px;border:2px solid #1a3a6b}
.edit-form h3{font-size:.9rem;font-weight:700;margin-bottom:14px;color:#1a3a6b}
.file-list{max-height:180px;overflow-y:auto;margin-top:8px}
.file-item{font-size:.78rem;padding:5px 10px;background:#f0f4f8;border-radius:6px;color:#374a66;margin-bottom:3px;display:flex;justify-content:space-between}
.backup-item{font-size:.8rem;padding:8px 12px;background:#f0f4f8;border-radius:8px;display:flex;justify-content:space-between;align-items:center;margin-bottom:5px}
@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:1}.form-row{grid-template-columns:1fr}.nav-tabs{display:none}}
</style>
</head>
<body>

<header>
<div class="hdr">
  <div class="hdr-logo">🔐 LUKUABIPOOD Admin</div>
  <div class="nav-tabs">
    <a href="admin.php?tab=main" class="nav-tab <?= $tab==='main'?'active':'' ?>">⚙️ Üldseaded</a>
    <a href="admin.php?tab=products" class="nav-tab <?= $tab==='products'?'active':'' ?>">
      📦 Tooted
      <?php $pc = count(read_products()); ?>
      <span class="count-badge"><?= $pc ?></span>
    </a>
    <a href="admin.php?tab=backup" class="nav-tab <?= $tab==='backup'?'active':'' ?>">💾 Varukoopiad</a>
    <a href="index.html" target="_blank" class="nav-tab">🌐 Vaata saiti ↗</a>
  </div>
  <div class="hdr-right">
    <span><?= date('d.m.Y H:i') ?></span>
    <form method="POST" style="margin:0"><button type="submit" name="logout" value="1" class="logout">Logi välja</button></form>
  </div>
</div>
</header>

<div class="wrap">

<?php if ($message): ?>
  <div class="msg <?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($tab === 'main'): ?>
<!-- ═══ TAB: ÜLDSEADED ═══ -->
<div class="stats">
  <div class="stat"><strong><?= $html_count ?></strong><span>HTML lehte</span></div>
  <div class="stat"><strong><?= $pc ?></strong><span>Toodet</span></div>
  <div class="stat"><strong><?= count($backups) ?></strong><span>Varukoopiat</span></div>
  <div class="stat"><strong><?= htmlspecialchars($current_phone) ?></strong><span>Telefon</span></div>
</div>

<div class="grid">
<div class="card">
  <h2>📞 Telefon</h2>
  <p class="desc">Uuendab kõigis <?= $html_count ?> failis korraga</p>
  <form method="POST">
    <input type="hidden" name="action" value="update_phone">
    <label>Praegune</label>
    <input type="text" name="old_phone" value="+372 5681 7777">
    <label>Uus number</label>
    <input type="text" name="new_phone" placeholder="+372 5XXX XXXX" required>
    <div class="mt"><button class="btn btn-navy">Uuenda telefon</button></div>
  </form>
</div>

<div class="card">
  <h2>✉️ E-post</h2>
  <p class="desc">Uuendab kõigis failides</p>
  <form method="POST">
    <input type="hidden" name="action" value="update_email">
    <label>Praegune</label>
    <input type="text" name="old_email" value="<?= htmlspecialchars($current_email) ?>">
    <label>Uus e-post</label>
    <input type="email" name="new_email" placeholder="uus@email.ee" required>
    <div class="mt"><button class="btn btn-navy">Uuenda e-post</button></div>
  </form>
</div>

<div class="card">
  <h2>💳 Hinna muutmine</h2>
  <p class="desc">Leia tekst ja asenda</p>
  <form method="POST">
    <input type="hidden" name="action" value="update_price">
    <label>Praegune tekst</label>
    <input type="text" name="old_price" placeholder='alates 9 EUR'>
    <label>Uus tekst</label>
    <input type="text" name="new_price" placeholder='alates 12 EUR'>
    <div class="mt"><button class="btn btn-navy">Muuda</button></div>
  </form>
</div>

<div class="card">
  <h2>🔍 Meta kirjeldus</h2>
  <p class="desc">SEO kirjeldus konkreetsele lehele</p>
  <form method="POST">
    <input type="hidden" name="action" value="update_meta">
    <label>Lehekülg</label>
    <select name="meta_file" id="meta_file_sel" onchange="loadMeta(this.value)">
      <?php foreach (get_html_files() as $f): ?>
        <option value="<?= basename($f) ?>"><?= basename($f) ?></option>
      <?php endforeach; ?>
    </select>
    <div id="meta_current" style="margin-top:10px;padding:10px 12px;background:#f0f4f8;border-radius:8px;font-size:.78rem;display:none">
      <div style="color:#7a8fa8;font-weight:700;margin-bottom:4px;letter-spacing:.04em">PRAEGUNE TITLE</div>
      <div id="meta_cur_title" style="color:#0f1f3d;font-weight:600;margin-bottom:8px"></div>
      <div style="color:#7a8fa8;font-weight:700;margin-bottom:4px;letter-spacing:.04em">PRAEGUNE DESCRIPTION</div>
      <div id="meta_cur_desc" style="color:#374a66"></div>
    </div>
    <label>Uus kirjeldus</label>
    <textarea name="new_desc" id="meta_new_desc" maxlength="160" placeholder="Professionaalne lukuabi..."></textarea>
    <div style="font-size:.72rem;color:#b0c4de;margin-top:3px" id="meta_char_count">0 / 160</div>
    <div class="mt"><button class="btn btn-navy">Uuenda</button></div>
  </form>
</div>

<script>
// Meta data for all pages
const META_DATA = {
<?php foreach (get_html_files() as $f):
    $fc = file_get_contents($f);
    preg_match('/<title>(.*?)<\/title>/s', $fc, $mt);
    preg_match('/<meta name="description" content="([^"]*)"/s', $fc, $md);
    $title = addslashes(trim(strip_tags($mt[1] ?? '')));
    $desc  = addslashes($md[1] ?? '');
    echo '  "' . basename($f) . '": {"title":"' . $title . '","desc":"' . $desc . '"},' . "\n";
endforeach; ?>
};
function loadMeta(fname) {
  const d = META_DATA[fname];
  if (!d) return;
  document.getElementById('meta_cur_title').textContent = d.title || '—';
  document.getElementById('meta_cur_desc').textContent  = d.desc  || '—';
  document.getElementById('meta_current').style.display = 'block';
  const ta = document.getElementById('meta_new_desc');
  ta.value = d.desc || '';
  updateCount();
}
function updateCount() {
  const ta = document.getElementById('meta_new_desc');
  const cnt = document.getElementById('meta_char_count');
  const len = ta.value.length;
  cnt.textContent = len + ' / 160';
  cnt.style.color = len > 155 ? '#d42b2b' : len > 130 ? '#e0870a' : '#b0c4de';
}
document.getElementById('meta_new_desc').addEventListener('input', updateCount);
// Load first page on init
window.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('meta_file_sel');
  if (sel) loadMeta(sel.value);
});
</script>

<div class="card full">
  <h2>✏️ Teksti asendamine</h2>
  <p class="desc">Leia ja asenda suvaline tekst</p>
  <form method="POST">
    <input type="hidden" name="action" value="text_replace">
    <div class="form-row">
      <div><label>Otsi</label><textarea name="old_text" placeholder="Vana tekst..."></textarea></div>
      <div><label>Asenda (tühi = kustuta)</label><textarea name="new_text" placeholder="Uus tekst..."></textarea></div>
    </div>
    <label>Fail</label>
    <select name="target_file">
      <option value="all">— Kõik failid —</option>
      <?php foreach (get_html_files() as $f): ?>
        <option value="<?= basename($f) ?>"><?= basename($f) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="mt"><button class="btn btn-red">Asenda</button></div>
  </form>
</div>
</div>

<?php elseif ($tab === 'products'): ?>
<!-- ═══ TAB: TOOTED ═══ -->
<?php
$products_all = read_products();
$cats_count = [];
foreach ($products_all as $p) {
    $cat = $p['cat'] ?? 'Muu';
    $cats_count[$cat] = ($cats_count[$cat] ?? 0) + 1;
}
?>

<!-- Edit form (if editing) -->
<?php if ($edit_product): ?>
<div class="edit-form">
  <h3>✏️ Muuda toodet: <?= htmlspecialchars($edit_product['id']) ?></h3>
  <form method="POST">
    <input type="hidden" name="action" value="edit_product">
    <input type="hidden" name="product_id" value="<?= htmlspecialchars($edit_product['id']) ?>">
    <div class="form-row">
      <div>
        <label>Kategooria</label>
        <select name="p_cat">
          <?php foreach ($CATS as $c): ?>
            <option value="<?= $c ?>" <?= ($edit_product['cat']??'')===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
        <label>Bränd</label>
        <input type="text" name="p_brand" value="<?= htmlspecialchars($edit_product['brand']??'') ?>" required>
        <label>Fotod (failinimed komaga: a.jpg, b.jpg)</label>
        <input type="text" name="p_imgs" value="<?= htmlspecialchars(implode(', ', $edit_product['imgs']??[])) ?>">
        <label>Sildid/otsingusõnad</label>
        <input type="text" name="p_tags" value="<?= htmlspecialchars(implode(', ', $edit_product['tags']??[])) ?>">
      </div>
      <div>
        <label>Nimetus eesti keeles</label>
        <input type="text" name="p_et" value="<?= htmlspecialchars($edit_product['et']??'') ?>" required>
        <label>Nimetus vene keeles</label>
        <input type="text" name="p_ru" value="<?= htmlspecialchars($edit_product['ru']??'') ?>">
        <label>Nimetus inglise keeles</label>
        <input type="text" name="p_en" value="<?= htmlspecialchars($edit_product['en']??'') ?>">
      </div>
    </div>
    <div class="mt">
      <button class="btn btn-green">💾 Salvesta muudatused</button>
      &nbsp;
      <a href="admin.php?tab=products" class="btn btn-ghost">Tühista</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Add product form -->
<div class="card full" style="margin-bottom:20px">
  <h2>➕ Lisa uus toode</h2>
  <p class="desc">Täitke väljad ja vajutage Lisa</p>
  <form method="POST">
    <input type="hidden" name="action" value="add_product">
    <div class="form-row">
      <div>
        <label>ID (unikaalne, nt "abloy-new-123")</label>
        <input type="text" name="p_id" placeholder="abloy-new-123" required pattern="[a-zA-Z0-9\-]+" title="Ainult tähed, numbrid ja sidekriips">
        <label>Kategooria</label>
        <select name="p_cat">
          <?php foreach ($CATS as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
        </select>
        <label>Bränd</label>
        <input type="text" name="p_brand" placeholder="Abloy" required>
        <label>Fotod (failinimed komaga, tühi = ikoon)</label>
        <input type="text" name="p_imgs" placeholder="foto1.jpg, foto2.jpg">
        <label>Sildid (komaga eraldatud)</label>
        <input type="text" name="p_tags" placeholder="abloy, turvalukk">
      </div>
      <div>
        <label>Nimetus eesti keeles *</label>
        <input type="text" name="p_et" placeholder="Toote nimetus eesti keeles" required>
        <label>Nimetus vene keeles</label>
        <input type="text" name="p_ru" placeholder="Название на русском">
        <label>Nimetus inglise keeles</label>
        <input type="text" name="p_en" placeholder="Product name in English">
      </div>
    </div>
    <div class="mt"><button class="btn btn-green">➕ Lisa toode</button></div>
  </form>
</div>

<!-- Products table -->
<div class="card full">
  <h2>📦 Kõik tooted (<?= count($products_all) ?>)</h2>
  <p class="desc">
    <?php foreach ($cats_count as $c => $n): ?>
      <span class="cat-badge"><?= $c ?>: <?= $n ?></span>&nbsp;
    <?php endforeach; ?>
  </p>
  <div style="overflow-x:auto;margin-top:16px">
    <table class="prod-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Bränd</th>
          <th>Kategooria</th>
          <th>Nimetus (ET)</th>
          <th>Fotod</th>
          <th>Tegevused</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products_all as $p):
          $pid = htmlspecialchars($p['id']??'');
          $et  = htmlspecialchars($p['et']??'');
          $br  = htmlspecialchars($p['brand']??'');
          $cat = htmlspecialchars($p['cat']??'');
          $imgs = $p['imgs'] ?? [];
        ?>
        <tr>
          <td><code style="font-size:.75rem;color:#7a8fa8"><?= $pid ?></code></td>
          <td><span class="brand-badge"><?= $br ?></span></td>
          <td><span class="cat-badge"><?= $cat ?></span></td>
          <td><?= $et ?></td>
          <td>
            <?php if ($imgs): ?>
              <?php foreach ($imgs as $img): ?>
                <span class="img-chip"><?= htmlspecialchars($img) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span style="color:#b0c4de;font-size:.75rem">— ikoon —</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions">
              <a href="admin.php?tab=products&edit=<?= $pid ?>" class="btn btn-ghost btn-sm">✏️ Muuda</a>
              <form method="POST" onsubmit="return confirm('Kustuta <?= $pid ?>?')">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="<?= $pid ?>">
                <button type="submit" class="btn btn-red btn-sm">🗑 Kustuta</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'backup'): ?>
<!-- ═══ TAB: VARUKOOPIAD ═══ -->
<div class="grid">
<div class="card">
  <h2>💾 Loo varukoopia</h2>
  <p class="desc">Salvestab kõik HTML failid ZIP-i koos kuupäevaga</p>
  <form method="POST">
    <input type="hidden" name="action" value="backup">
    <button class="btn btn-green">💾 Loo varukoopia nüüd</button>
  </form>
</div>

<div class="card">
  <h2>📄 HTML failid</h2>
  <p class="desc"><?= $html_count ?> lehte kokku</p>
  <div class="file-list">
    <?php foreach (get_html_files() as $f): ?>
      <div class="file-item">
        <span><?= basename($f) ?></span>
        <span style="color:#b0c4de"><?= round(filesize($f)/1024) ?> KB</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card full">
  <h2>📦 Olemasolevad varukoopiad (<?= count($backups) ?>)</h2>
  <p class="desc" style="margin-bottom:14px">Asuvad kaustas <code>backups/</code></p>
  <?php if ($backups): ?>
    <?php foreach ($backups as $b): ?>
      <div class="backup-item">
        <span><?= basename($b) ?></span>
        <span style="color:#7a8fa8"><?= round(filesize($b)/1024) ?> KB &nbsp;·&nbsp; <?= date('d.m.Y H:i', filemtime($b)) ?></span>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:#b0c4de;font-size:.84rem">Varukoopiad puuduvad. Loo esimene!</p>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>

</div><!-- /wrap -->
</body>
</html>
