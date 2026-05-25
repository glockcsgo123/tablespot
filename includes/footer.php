<?php
declare(strict_types=1);
?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-row">
            <div class="footer-brand">
                <svg width="20" height="23" viewBox="0 0 28 32" fill="none" style="flex-shrink:0;"><circle cx="14" cy="6" r="5" stroke="#D4A017" stroke-width="2"/><circle cx="14" cy="6" r="2" fill="#D4A017"/><path d="M14 11 L9 7 Q14 -1 19 7 Z" fill="#D4A017"/><rect x="2" y="18" width="24" height="4" rx="2" fill="#D4A017"/><rect x="4" y="22" width="4" height="10" rx="2" fill="#D4A017"/><rect x="20" y="22" width="4" height="10" rx="2" fill="#D4A017"/></svg>
                <span class="footer-brand-name">Table<span style="color:#D4A017;">Spot</span></span>
                <span class="footer-copyright">© <?= date('Y') ?></span>
            </div>
            <div class="footer-links">
                <a href="<?= $appBaseUrl ?>/index.php">Главная</a>
                <a href="<?= $appBaseUrl ?>/profile.php">Кабинет</a>
                <a href="<?= $appBaseUrl ?>/placement.php">Разместить ресторан</a>
                <a href="<?= $appBaseUrl ?>/privacy.php">Политика конфиденциальности</a>
                <a href="<?= $appBaseUrl ?>/admin/login.php">Админ</a>
            </div>
        </div>
    </div>
</footer>
<script src="<?= $appBaseUrl ?>/assets/js/modern.js" defer></script>
</body>
</html>

