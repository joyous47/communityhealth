<div class="language-switcher" style="display: flex; align-items: center; gap: 5px; margin-left: 15px;">
    <a href="?lang=en" class="lang-btn <?php echo ($lang->getCurrent() === 'en') ? 'active' : ''; ?>" 
       style="padding: 5px 10px; border-radius: 4px; text-decoration: none; color: #fff; background: <?php echo ($lang->getCurrent() === 'en') ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.3);">
        EN
    </a>
    <a href="?lang=sw" class="lang-btn <?php echo ($lang->getCurrent() === 'sw') ? 'active' : ''; ?>"
       style="padding: 5px 10px; border-radius: 4px; text-decoration: none; color: #fff; background: <?php echo ($lang->getCurrent() === 'sw') ? 'rgba(255,255,255,0.3)' : 'transparent'; ?>; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.3);">
        SW
    </a>
</div>

<?php
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'sw'])) {
    $lang->setLanguage($_GET['lang']);
    $redirectUrl = str_replace(['?lang=en', '?lang=sw', '&lang=en', '&lang=sw'], '', $_SERVER['REQUEST_URI']);
    header('Location: ' . $redirectUrl);
    exit;
}
?>
