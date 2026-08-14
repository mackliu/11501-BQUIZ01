
<div class="di" style="height:540px; border:#999 1px solid; width:53.2%; margin:2px 0px 0px 0px; float:left; position:relative; left:20px;">
<?php include "include/marquee.php";?>
    <div style="height:32px; display:block;"></div>
    <!--正中央-->
    <?php
    // 登入失敗訊息（由 api/login.php 寫入 session，顯示後即清除）
    $flash = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
    ?>
    <form method="post" action="api/login.php">
    	<p class="t botli">管理員登入區</p>
        <?php if ($flash !== null): ?>
        <p class="cent" style="color:#c00; font-weight:bold;"><?= e($flash) ?></p>
        <?php endif; ?>
    	<p class="cent">帳號 ： <input name="acc" autofocus="" type="text"></p>
        <p class="cent">密碼 ： <input name="pw" type="password"></p>
        <?= csrf_field() ?>
        <p class="cent">
			<input value="送出" type="submit">
			<input type="reset" value="清除">
		</p>
    </form>
</div>
