<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'Plataforma SG-SST') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {
            50:'#eff6ff',100:'#dbeafe',500:'#0a84ff',600:'#0071e3',700:'#0060c9',900:'#1e3a8a' } } } } }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ============ macOS design system ============ */
        :root{
            --mac-blue:#0071e3; --mac-green:#34c759; --mac-red:#ff3b30; --mac-orange:#ff9500;
            --mac-ink:#1d1d1f; --mac-sub:#6e6e73; --mac-card:rgba(255,255,255,.78);
            --mac-stroke:rgba(0,0,0,.08); --mac-shadow:0 8px 30px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.06);
            --mac-radius:18px;
        }
        *{ -webkit-font-smoothing:antialiased; }
        html{ scroll-behavior:smooth; }
        body{
            font-family:-apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Inter, "Segoe UI", Arial, sans-serif;
            color:var(--mac-ink); letter-spacing:-.01em; min-height:100vh;
            background:
              radial-gradient(900px 500px at 8% -5%, rgba(10,132,255,.20), transparent 60%),
              radial-gradient(800px 480px at 95% 0%, rgba(191,90,242,.16), transparent 60%),
              radial-gradient(700px 500px at 55% 110%, rgba(52,199,89,.12), transparent 60%),
              linear-gradient(180deg,#f5f7fb 0%,#eef1f7 100%);
            background-attachment:fixed;
        }
        [x-cloak]{ display:none !important; }
        .mac-glass{
            background:rgba(250,250,252,.72); backdrop-filter:blur(22px) saturate(180%);
            -webkit-backdrop-filter:blur(22px) saturate(180%);
            border-bottom:1px solid var(--mac-stroke);
        }
        .mac-sidebar{
            background:rgba(246,246,248,.66); backdrop-filter:blur(22px) saturate(180%);
            -webkit-backdrop-filter:blur(22px) saturate(180%); border-right:1px solid var(--mac-stroke);
        }
        .mac-card{
            background:var(--mac-card); backdrop-filter:blur(14px) saturate(160%);
            -webkit-backdrop-filter:blur(14px) saturate(160%);
            border:1px solid var(--mac-stroke); border-radius:var(--mac-radius); box-shadow:var(--mac-shadow);
        }
        /* Todo panel blanco heredado se eleva al estilo macOS */
        .bg-white.border, section.bg-white, div.bg-white.rounded-xl, div.bg-white.rounded-2xl{
            border-radius:var(--mac-radius) !important; box-shadow:var(--mac-shadow);
            border-color:var(--mac-stroke) !important;
        }
        .mac-item{
            display:flex; align-items:center; gap:.65rem; padding:.5rem .6rem; border-radius:11px;
            font-size:.875rem; font-weight:500; color:#3a3a3c; transition:all .18s ease; border:1px solid transparent;
        }
        .mac-item:hover{ background:rgba(0,0,0,.045); transform:translateX(2px); }
        .mac-item.active{ background:linear-gradient(180deg,#0a84ff,#0071e3); color:#fff; box-shadow:0 4px 14px rgba(0,113,227,.35); }
        .mac-item.active .mac-ico{ background:rgba(255,255,255,.22) !important; color:#fff !important; }
        .mac-ico{
            width:28px; height:28px; min-width:28px; border-radius:8px; display:flex; align-items:center; justify-content:center;
            font-size:.8rem; color:#fff; box-shadow:inset 0 1px 0 rgba(255,255,255,.35), 0 1px 2px rgba(0,0,0,.15);
        }
        .mac-btn{
            display:inline-flex; align-items:center; gap:.45rem; background:linear-gradient(180deg,#0a84ff,#0071e3); color:#fff;
            font-weight:600; font-size:.875rem; padding:.55rem 1rem; border-radius:11px; border:0; cursor:pointer;
            box-shadow:0 4px 14px rgba(0,113,227,.30), inset 0 1px 0 rgba(255,255,255,.35); transition:all .15s ease;
        }
        .mac-btn:hover{ filter:brightness(1.07); transform:translateY(-1px); } .mac-btn:active{ transform:scale(.98); }
        .mac-btn-secondary{
            display:inline-flex; align-items:center; gap:.45rem; background:rgba(255,255,255,.85); color:var(--mac-ink);
            font-weight:600; font-size:.875rem; padding:.55rem 1rem; border-radius:11px; border:1px solid var(--mac-stroke);
            box-shadow:0 1px 3px rgba(0,0,0,.06); transition:all .15s ease; cursor:pointer;
        }
        .mac-btn-secondary:hover{ background:#fff; transform:translateY(-1px); }
        .mac-input, input[type=text],input[type=email],input[type=password],input[type=number],input[type=date],input[type=month],select,textarea{
            border-radius:10px !important;
        }
        input,select,textarea{ transition:box-shadow .15s ease, border-color .15s ease; }
        input:focus,select:focus,textarea:focus{ outline:none !important; border-color:var(--mac-blue) !important; box-shadow:0 0 0 4px rgba(10,132,255,.18) !important; }
        .mac-badge{ display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .6rem; border-radius:999px; font-size:.72rem; font-weight:700; }
        .mac-muted{ color:var(--mac-sub); }
        .mac-alert{ border-radius:14px; padding:.9rem 1.1rem; font-size:.875rem; border:1px solid var(--mac-stroke); box-shadow:var(--mac-shadow); }
        .mac-alert-info{ background:rgba(10,132,255,.10); }
        table thead{ background:rgba(0,0,0,.025); } tbody tr{ transition:background .12s ease; } tbody tr:hover{ background:rgba(10,132,255,.045); }
        ::-webkit-scrollbar{ width:10px; height:10px; } ::-webkit-scrollbar-thumb{ background:rgba(0,0,0,.22); border-radius:8px; border:2px solid transparent; background-clip:content-box; }
        .traffic{ width:12px; height:12px; border-radius:50%; box-shadow:inset 0 0 2px rgba(0,0,0,.25); }
        /* Toasts (mensajes flash) flotantes estilo notificación macOS */
        .mac-toasts{ position:fixed; top:74px; right:18px; z-index:100; display:flex; flex-direction:column; gap:10px; max-width:min(420px, calc(100vw - 36px)); }
        .mac-toast{
            display:flex; gap:.7rem; align-items:flex-start; padding:.85rem 1rem; border-radius:16px;
            background:rgba(255,255,255,.82); backdrop-filter:blur(20px) saturate(180%); -webkit-backdrop-filter:blur(20px) saturate(180%);
            border:1px solid var(--mac-stroke); box-shadow:0 12px 34px rgba(0,0,0,.16);
            animation:mac-in .38s cubic-bezier(.21,1.02,.55,1.15); font-size:.875rem;
        }
        .mac-toast.out{ animation:mac-out .3s ease forwards; }
        @keyframes mac-in{ from{ opacity:0; transform:translateX(40px) scale(.96);} to{ opacity:1; transform:none;} }
        @keyframes mac-out{ to{ opacity:0; transform:translateX(40px) scale(.96);} }
        .mac-toast .t-ico{ width:30px;height:30px;min-width:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem; }
    </style>
</head>
<body>
    <?php $embed = !empty($embed ?? null) || isset($_GET['embed']); ?>
    <?php if (\App\Auth::check() && !$embed): ?>
        <?php require __DIR__ . '/partials/navbar.php'; ?>
        <div class="flex items-stretch">
            <?php require __DIR__ . '/partials/sidebar.php'; ?>
            <main class="flex-1 p-5 md:p-7 w-full max-w-[1400px] mx-auto">
    <?php elseif ($embed): ?>
        <main class="p-4 w-full">
    <?php else: ?>
        <main class="min-h-screen flex items-center justify-center p-4">
    <?php endif; ?>

        <!-- Notificaciones (flash) estilo macOS -->
        <div class="mac-toasts" id="macToasts">
        <?php foreach (get_flash() as $f):
            $cfg = match($f['tipo']) {
                'error'   => ['#ff3b30', 'fa-circle-exclamation'],
                'warning' => ['#ff9500', 'fa-triangle-exclamation'],
                default   => ['#34c759', 'fa-circle-check'],
            }; ?>
            <div class="mac-toast" role="alert">
                <span class="t-ico" style="background:<?= $cfg[0] ?>"><i class="fa-solid <?= $cfg[1] ?>"></i></span>
                <span class="flex-1"><?= e($f['mensaje']) ?></span>
                <button type="button" onclick="this.closest('.mac-toast').remove()" class="text-slate-400 hover:text-slate-700" aria-label="Cerrar">✕</button>
            </div>
        <?php endforeach; ?>
        </div>
        <script>
            setTimeout(() => document.querySelectorAll('.mac-toast').forEach(t => {
                t.classList.add('out'); setTimeout(() => t.remove(), 320);
            }), 6000);
        </script>

        <?php require __DIR__ . '/../views/' . $template . '.php'; ?>

    <?php if (\App\Auth::check() && !$embed): ?>
            </main>
        </div>
    <?php else: ?>
        </main>
    <?php endif; ?>
</body>
</html>
